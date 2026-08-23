<?php
if ( ! defined( 'ABSPATH' )) exit;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped — admin context with intentional HTML
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared — SQL uses internal $wpdb variables (safe)
/**
 * SwiftBoard - Badge "Top répondeur de la semaine"
 *
 * Affiche un badge 🏆 à côté du nom des 3 utilisateurs ayant le plus répondu
 * au cours des 7 derniers jours.
 *
 * Architecture Hostinger-safe :
 *  - Calcul hebdo via WP-Cron (1 fois par semaine, lundi 3h du matin)
 *  - Résultat stocké dans une option (pas de DB hit par page load)
 *  - Cache transient 1h pour la lecture (anti-requête sur option à chaque hit)
 *  - Requête SQL LIMIT 3 + index sur post_author + post_type + post_date
 *  - Badge injecté via hook bbp_theme_after_reply_author_admin_details
 *    (déjà utilisé par le système de grades)
 *
 * @package SwiftBoard
 * @since 2.7.0
 */
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared — SQL queries use internal $wpdb->prefix variables (safe)

// ============================================================================
// 1. CALCUL DES TOP RÉPONDEURS
// ============================================================================
/**
 * Calcule les 3 meilleurs répondeurs des 7 derniers jours.
 *
 * @return array<string, mixed> [['user_id'=>..,'count'=>..,'rank'=>1], ...]
 */
function swiftboard_compute_weekly_top_responders() {
	global $wpdb;

	// Période : 7 derniers jours
	$since = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );

	// Une seule requête SQL groupée — index sur post_type + post_date + post_author
	$results = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT post_author, COUNT(*) as reply_count
         FROM {$wpdb->posts}
         WHERE post_type   = 'reply'
           AND post_status = 'publish'
           AND post_date   >= %s
           AND post_author > 0
         GROUP BY post_author
         ORDER BY reply_count DESC
         LIMIT 3",
			$since
		),
		ARRAY_A
	);

	$top = array();
	foreach ( $results as $i => $row ) {
		$top[] = array(
			'user_id' => (int) $row['post_author'],
			'count'   => (int) $row['reply_count'],
			'rank'    => $i + 1,
		);
	}

	return array(
		'week_start'  => gmdate( 'Y-m-d', strtotime( 'monday this week' ) ),
		'week_end'    => gmdate( 'Y-m-d', strtotime( 'sunday this week' ) ),
		'computed_at' => current_time( 'mysql' ),
		'top'         => $top,
	);
}

// ============================================================================
// 2. STOCKAGE + CACHE
// ============================================================================
/**
 * swiftboard_save_weekly_top().
 *
 * @param array<string, mixed> $data Données à traiter.
 * @return void
 */
function swiftboard_save_weekly_top( $data ) {
	update_option( 'swiftboard_weekly_top_responders', $data, false );
	// Cache transient 1 heure (renouvelé à chaque lecture)
	set_transient( 'swiftboard_weekly_top_cache', $data, HOUR_IN_SECONDS );
}

/**
 * @return mixed
 */
function swiftboard_get_weekly_top() {
	$cached = get_transient( 'swiftboard_weekly_top_cache' );
	if ( is_array( $cached ) && isset( $cached['top'] ) ) {
		return $cached;
	}
	$data = get_option( 'swiftboard_weekly_top_responders', array() );
	if ( is_array( $data ) && isset( $data['top'] ) ) {
		set_transient( 'swiftboard_weekly_top_cache', $data, HOUR_IN_SECONDS );
		return $data;
	}
	// Si jamais calculé, calculer maintenant (1ère fois)
	$data = swiftboard_compute_weekly_top_responders();
	swiftboard_save_weekly_top( $data );
	return $data;
}
/**
 * Vérifie si un utilisateur est dans le top 3 et renvoie son rang (1, 2, 3) ou 0.
 *
 * @param int $user_id Identifiant de l'utilisateur.
 * @return int
 */
function swiftboard_get_user_weekly_rank( $user_id ) {
	$data = swiftboard_get_weekly_top();
	foreach ( $data['top'] as $entry ) {
		if ( (int) $entry['user_id'] === (int) $user_id ) {
			return (int) $entry['rank'];
		}
	}
	return 0;
}

// ============================================================================
// 3. WP-CRON — RECALCUL HEBDOMADAIRE
// ============================================================================
/**
 * Planifie le recalcul chaque lundi à 3h du matin (heure locale).
 * Utilise wp_schedule_event avec un timestamp calé sur le prochain lundi 3h.
 *
 * @return void
 */
function swiftboard_schedule_weekly_top_cron() {
	if ( wp_next_scheduled( 'swiftboard_weekly_top_recalc' ) ) {
		return;
	}
	// Prochain lundi 3h du matin
	$next_monday = strtotime( 'next monday 3:00 am' );
	wp_schedule_event( $next_monday, 'weekly', 'swiftboard_weekly_top_recalc' );
}
add_action( 'wp', 'swiftboard_schedule_weekly_top_cron' );

// Enregistrer l'intervalle "weekly" si absent
add_filter(
	'cron_schedules',
	function ( $schedules ) {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Une fois par semaine', 'swiftboard' ),
			);
		}
		return $schedules;
	}
);

// Action du cron
add_action(
	'swiftboard_weekly_top_recalc',
	function () {
		$data = swiftboard_compute_weekly_top_responders();
		swiftboard_save_weekly_top( $data );
	}
);

// ============================================================================
// 4. ENDPOINT REST — DASHBOARD WEEKLY TOP (admin & public)
// ============================================================================
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'swiftboard/v1',
			'/weekly-top',
			array(
				'methods'             => 'GET',
				// EXI-SEC-02 : lecture publique — donnees identiques pour tous,
				// aucune information personnelle exposee. Declare explicitement
				// car WordPress 5.5+ emet un _doing_it_wrong() sans ce parametre.
				'permission_callback' => 'swiftboard_rest_public_permission',
				'callback'            => function () {
					$data = swiftboard_get_weekly_top();
					// Enrichir avec les infos user
					foreach ( $data['top'] as &$entry ) {
						$user                  = get_userdata( $entry['user_id'] );
						$entry['display_name'] = $user ? $user->display_name : __( 'Anonyme', 'swiftboard' );
						$entry['avatar_url']   = function_exists( 'get_avatar_url' )
							? get_avatar_url( $entry['user_id'], array( 'size' => 64 ) )
							: '';
					}
					return new WP_REST_Response( $data, 200 );
				},
			)
		);
	}
);

// ============================================================================
// 5. BADGE VISUEL — À CÔTÉ DU NOM DANS LES RÉPONSES
// ============================================================================
/**
 * Affiche le badge "Top répondeur de la semaine" après le nom de l'auteur.
 * Hook sur bbp_theme_after_reply_author_admin_details (comme le badge de grade).
 *
 * @return void
 */
function swiftboard_render_weekly_top_badge() {
	$author_id = function_exists( 'bbp_get_reply_author_id' ) ? bbp_get_reply_author_id() : 0;
	if ( ! $author_id) return;

	$rank = swiftboard_get_user_weekly_rank( (int) $author_id );
	if ( ! $rank) return;

	$medals = array(
		1 => array(
			'icon'  => '🥇',
			'label' => 'Top 1 — Répondeur de la semaine',
			'color' => '#d4af37',
		),
		2 => array(
			'icon'  => '🥈',
			'label' => 'Top 2 — Répondeur de la semaine',
			'color' => '#a8a8a8',
		),
		3 => array(
			'icon'  => '🥉',
			'label' => 'Top 3 — Répondeur de la semaine',
			'color' => '#cd7f32',
		),
	);
	$m      = $medals[ $rank ] ?? null;
	if ( ! $m) return;

	echo '<span class="swiftboard-weekly-badge" '
		. 'style="display:inline-block;background:' . esc_attr( $m['color'] ) . ';color:#fff;'
		. 'padding:2px 8px;border-radius:9999px;font-size:0.6rem;font-weight:700;'
		. 'text-transform:uppercase;letter-spacing:0.04em;margin-left:4px;" '
		. 'title="' . esc_attr( $m['label'] ) . '">'
		. esc_html( $m['icon'] . ' #' . $rank )
		. '</span>';
}
// Priorité 12 pour s'afficher après le badge de grade (priorité 10 dans admin-settings-grades.php)
add_action( 'bbp_theme_after_reply_author_admin_details', 'swiftboard_render_weekly_top_badge', 12 );

// ============================================================================
// 6. WIDGET DASHBOARD — TOP 3 SUR LA PAGE D'ACCUEIL DU FORUM
// ============================================================================
/**
 * Affiche un panneau "Top répondeurs de la semaine" sur l'index du forum.
 * Hook sur bbp_template_before_forums_index (page d'accueil du forum).
 *
 * @return void
 */
function swiftboard_render_weekly_top_panel() {
	$data = swiftboard_get_weekly_top();
	if (empty( $data['top'] )) return;

	echo '<div class="sb-weekly-panel">';
	echo '<h3 class="sb-weekly-panel-title">🏆 ' . esc_html__( 'Top répondeurs de la semaine', 'swiftboard' ) . '</h3>';
	echo '<p class="sb-weekly-panel-date">' . sprintf( esc_html__( 'Du %s au %s', 'swiftboard' ), esc_html( $data['week_start'] ), esc_html( $data['week_end'] ) ) . '</p>';
	echo '<div class="sb-weekly-cards">';

	$medals = array( '🥇', '🥈', '🥉' );
	foreach ( $data['top'] as $entry ) {
		$user = get_userdata( $entry['user_id'] );
		if ( ! $user) continue;
		$medal = $medals[ $entry['rank'] - 1 ] ?? '';
		echo '<div class="sb-weekly-card">';
		echo '<div class="sb-weekly-card-medal">' . esc_html( $medal ) . '</div>';
		echo '<div class="sb-weekly-card-name">' . esc_html( $user->display_name ) . '</div>';
		echo '<div class="sb-weekly-card-count">' . sprintf( esc_html__( '%s réponses', 'swiftboard' ), (int) $entry['count'] ) . '</div>';
		echo '</div>';
	}

	echo '</div></div>';
}
add_action( 'bbp_template_before_forums_index', 'swiftboard_render_weekly_top_panel', 5 );

// ============================================================================
// 7. COLONNE "TOP SEMAINE" DANS WP USERS (admin)
// ============================================================================
add_filter(
	'manage_users_columns',
	function ( $columns ) {
		$columns['swiftboard_weekly_top'] = 'Top semaine';
		return $columns;
	}
);
add_action(
	'manage_users_custom_column',
	function ( $value, $column_name, $user_id ) {
		if ( $column_name === 'swiftboard_weekly_top' ) {
			$rank = swiftboard_get_user_weekly_rank( $user_id );
			if ( ! $rank) return '—';
			$medals = array( '🥇', '🥈', '🥉' );
			return '<span style="font-size:1.25rem;" title="Top ' . $rank . ' répondeur de la semaine">' . $medals[ $rank - 1 ] . ' #' . $rank . '</span>';
		}
		return $value;
	},
	10,
	3
);

// ============================================================================
// 8. PAGE ADMIN — RECALCUL MANUEL
// ============================================================================
add_action(
	'admin_menu',
	function () {
		add_submenu_page(
			'swiftboard-dashboard',
			__( 'Top répondeurs', 'swiftboard' ),
			__( '🏆 Top semaine', 'swiftboard' ),
			'manage_options',
			'swiftboard-weekly-top',
			'swiftboard_weekly_top_admin_page'
		);
	}
);

/**
 * @return void
 */
function swiftboard_weekly_top_admin_page() {
	// EXI-SEC-BLOQ-07 : la capability du menu ne protege pas l'appel direct
	// de la fonction via admin.php?page=... Controle explicite obligatoire.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'swiftboard' ), 403 );
	}

	if ( isset( $_POST['recalc_weekly_top'] ) && check_admin_referer( 'swiftboard_recalc_weekly_top' ) ) {
		$data = swiftboard_compute_weekly_top_responders();
		swiftboard_save_weekly_top( $data );
		echo '<div class="notice notice-success is-dismissible"><p>✅ Top recalculé.</p></div>';
	}
	$data = swiftboard_get_weekly_top();
	?>
	<div class="wrap">
		<h2>🏆 Top répondeurs de la semaine</h2>
		<p class="description">
			Période : <strong><?php echo esc_html( $data['week_start'] ); ?></strong> →
			<strong><?php echo esc_html( $data['week_end'] ); ?></strong>
			(dernier calcul : <?php echo esc_html( $data['computed_at'] ); ?>)
		</p>
		<form method="post" action="">
			<?php wp_nonce_field( 'swiftboard_recalc_weekly_top' ); ?>
			<button type="submit" name="recalc_weekly_top" value="1" class="button button-primary">
				🔄 Recalculer maintenant
			</button>
		</form>
		<table class="wp-list-table widefat fixed striped" style="margin-top:16px;">
			<thead><tr><th><?php esc_html_e( 'Rang', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Utilisateur', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Réponses (7j)', 'swiftboard' ); ?></th></tr></thead>
			<tbody>
			<?php if ( empty( $data['top'] ) ) : ?>
				<tr><td colspan="3" style="text-align:center;color:#999;padding:40px;"><?php esc_html_e( 'Aucune réponse cette semaine.', 'swiftboard' ); ?></td></tr>
				<?php
			else :
				foreach ( $data['top'] as $entry ) :
					$user   = get_userdata( $entry['user_id'] );
					$medals = array( '🥇', '🥈', '🥉' );
					?>
				<tr>
					<td style="font-size:1.5rem;"><?php echo esc_html( $medals[ $entry['rank'] - 1 ] ?? '' ); ?></td>
					<td><strong><?php echo $user ? esc_html( $user->display_name ) : '—'; ?></strong>
						<br><small style="color:#595959;">ID: <?php echo (int) $entry['user_id']; ?></small></td>
					<td><strong style="font-size:1.1rem;color:var(--color-primary);"><?php echo (int) $entry['count']; ?></strong></td>
				</tr>
							<?php
			endforeach;
endif;
			?>
			</tbody>
		</table>
		<p class="description" style="margin-top:16px;">
			<strong><?php esc_html_e( 'Note Hostinger :', 'swiftboard' ); ?></strong> ce calcul s'exécute automatiquement chaque lundi à 3h du matin via WP-Cron.
			Aucune requête DB n'est effectuée sur les pages publiques (tout est en cache transient 1h + option permanente).
		</p>
	</div>
	<?php
}

// ============================================================================
// 9. NETTOYAGE DU CRON À LA DÉSACTIVATION
// ============================================================================
add_action(
	'switch_theme',
	function () {
		$ts = wp_next_scheduled( 'swiftboard_weekly_top_recalc' );
		if ( $ts ) {
			wp_unschedule_event( $ts, 'swiftboard_weekly_top_recalc' );
		}
	}
);

