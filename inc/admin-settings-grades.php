<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped — admin context with intentional HTML
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared — SQL uses internal $wpdb variables (safe)
/**
 * SwiftBoard - Réglages du forum + Système de grades
 *
 * 1. Page réglages : votes, uploads, réponses anonymes, limites
 * 2. Système de grades avec permissions personnalisables :
 *    - Rookie (nouveau, limité)
 *    - Membre (par défaut)
 *    - Pro (limites relevées)
 *    - Modérateur (pas de limites, peut créer sous-forums)
 *    - VIP (pas de limites, badge spécial)
 * 3. Page d'attribution des grades aux membres
 *
 * @package SwiftBoard
 * @since 2.3.0
 */
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared — SQL queries use internal $wpdb->prefix variables (safe)

// ============================================================================
// 1. DÉFINITION DES GRADES
// ============================================================================
// v4.6.1 : swiftboard_get_grades() déplacée vers inc/grades.php (chargé sur le front aussi)

// ============================================================================
// 2. RÉCUPÉRER LE GRADE D'UN UTILISATEUR
// ============================================================================
// v4.6.1 : swiftboard_get_user_grade() déplacée vers inc/grades.php (chargé sur le front aussi)

// ============================================================================
// 3. RÉCUPÉRER LES PERMISSIONS D'UN UTILISATEUR
// ============================================================================


// ============================================================================
// 4. VÉRIFIER UNE PERMISSION
// ============================================================================
// v4.6.1 : swiftboard_user_can() déplacée vers inc/grades.php (chargé sur le front aussi)

// ============================================================================
// 5. MENU ADMIN
// ============================================================================
/**
 * @return void
 */
function swiftboard_settings_grades_menu() {
	add_submenu_page( 'swiftboard-dashboard', __( 'Réglages', 'swiftboard' ), __( '⚙️ Réglages', 'swiftboard' ), 'manage_options', 'swiftboard-settings', 'swiftboard_settings_page' );
	add_submenu_page( 'swiftboard-dashboard', __( 'Grades', 'swiftboard' ), __( '🏅 Grades', 'swiftboard' ), 'manage_options', 'swiftboard-grades', 'swiftboard_grades_page' );
}
add_action( 'admin_menu', 'swiftboard_settings_grades_menu' );

// ============================================================================
// 6. PAGE RÉGLAGES DU FORUM

// ============================================================================
// ECRANS D'ADMINISTRATION — DEPLACES (EXI-ARCH-01)
// ============================================================================
// swiftboard_settings_page(), swiftboard_grades_page() et
// swiftboard_reputation_leaderboard_page() vivent desormais dans
// inc/admin-grades-ui.php : ~500 lignes de rendu HTML qui n'ont rien a faire
// dans un module portant de la logique front (hooks user_register, bbp_*).

// ============================================================================
/**
 * swiftboard_assign_default_grade().
 *
 * @param int $user_id Identifiant de l'utilisateur.
 * @return void
 */
function swiftboard_assign_default_grade( int $user_id ): void {
	$default_grade = get_option( 'swiftboard_default_grade', 'rookie' );
	if ( ! get_user_meta( $user_id, 'swiftboard_grade', true ) ) {
		update_user_meta( $user_id, 'swiftboard_grade', $default_grade );
		swiftboard_invalidate_grade_cache( $user_id ); // EXI-TEST-02
	}
}
add_action( 'user_register', 'swiftboard_assign_default_grade' );

// ============================================================================
// 9. FILTRER LES PERMISSIONS DANS LES FORMULAIRES BPRESS
// ============================================================================

// Empêcher la création de sujet selon le grade
/**
 * swiftboard_filter_topic_creation().
 *
 * @param bool $can      Autorisation calculée par bbPress.
 * @param int  $forum_id Identifiant du forum. Optionnel.
 * @return mixed
 */
function swiftboard_filter_topic_creation( $can, $forum_id = 0 ) {
	if ( ! is_user_logged_in()) return false;
	$user_id = get_current_user_id();
	if ( ! swiftboard_user_can( $user_id, 'can_create_topic' ) ) {
		return false;
	}
	return $can;
}
add_filter( 'bbp_current_user_can_access_create_topic_form', 'swiftboard_filter_topic_creation', 10, 2 );

// Empêcher la création de sous-forum selon le grade
/**
 * swiftboard_filter_subforum_creation().
 *
 * @param bool $can Autorisation calculée par bbPress.
 * @return mixed
 */
function swiftboard_filter_subforum_creation( $can ) {
	if ( ! is_user_logged_in()) return false;
	$user_id = get_current_user_id();
	if ( ! swiftboard_user_can( $user_id, 'can_create_subforum' ) ) {
		return false;
	}
	return $can;
}
add_filter( 'bbp_current_user_can_access_create_forum_form', 'swiftboard_filter_subforum_creation' );

// Empêcher les réponses selon le grade
/**
 * swiftboard_filter_reply_creation().
 *
 * @param bool $can      Autorisation calculée par bbPress.
 * @param int  $topic_id Identifiant du sujet. Optionnel.
 * @return mixed
 */
function swiftboard_filter_reply_creation( $can, $topic_id = 0 ) {
	if ( ! is_user_logged_in() ) {
		// Les anonymes peuvent répondre si activé
		return (int) get_option( 'swiftboard_enable_anon_replies', 1 ) === 1;
	}
	$user_id = get_current_user_id();
	if ( ! swiftboard_user_can( $user_id, 'can_reply' ) ) {
		return false;
	}
	return $can;
}
add_filter( 'bbp_current_user_can_access_create_reply_form', 'swiftboard_filter_reply_creation', 10, 2 );

// ============================================================================
// 10. FILTRER LES LIMITES D'UPLOAD SELON LE GRADE
// ============================================================================
/**
 * swiftboard_get_effective_upload_limits().
 *
 * @param int $user_id Identifiant de l'utilisateur.
 * @return array<string, mixed>
 */
function swiftboard_get_effective_upload_limits( $user_id ) {
	$perms = swiftboard_get_user_permissions( $user_id );

	return array(
		'daily'     => $perms['daily_upload_limit'] ?? get_option( 'swiftboard_upload_daily_limit', 2 ),
		'total'     => $perms['total_upload_limit'] ?? get_option( 'swiftboard_upload_total_limit', 200 ),
		'min_posts' => $perms['min_posts_to_upload'] ?? get_option( 'swiftboard_upload_min_posts', 3 ),
	);
}

// Hook dans le module image-upload pour utiliser les limites du grade
add_filter(
	'swiftboard_upload_daily_limit',
	function ( $limit ) {
		$user_id = get_current_user_id();
		if ( ! swiftboard_user_can( $user_id, 'can_upload' )) return 0;
		$user_id     = get_current_user_id();
		$perms       = swiftboard_get_user_permissions( $user_id );
		$grade_limit = $perms['daily_upload_limit'] ?? 0;
		// 0 = illimité pour les grades supérieurs
		if ($grade_limit === 0) return 999999;
		return $grade_limit;
	}
);

add_filter(
	'swiftboard_upload_total_limit',
	function ( $limit ) {
		$user_id     = get_current_user_id();
		$perms       = swiftboard_get_user_permissions( $user_id );
		$grade_limit = $perms['total_upload_limit'] ?? 0;
		if ($grade_limit === 0) return 999999;
		return $grade_limit;
	}
);

add_filter(
	'swiftboard_upload_min_posts',
	function ( $limit ) {
		$user_id = get_current_user_id();
		$perms   = swiftboard_get_user_permissions( $user_id );
		return $perms['min_posts_to_upload'] ?? $limit;
	}
);

// ============================================================================
// 11. AFFICHER LE BADGE DE GRADE DANS LE PROFIL ET LES RÉPONSES
// ============================================================================


// Afficher le badge après le nom de l'auteur dans les réponses
/**
 * @return void
 */
function swiftboard_add_grade_to_reply_author() {
	$author_id = bbp_get_reply_author_id();
	if ( $author_id ) {
		swiftboard_display_grade_badge( (int) $author_id );
	}
}
add_action( 'bbp_theme_after_reply_author_admin_details', 'swiftboard_add_grade_to_reply_author' );

// Afficher le badge dans le profil utilisateur
/**
 * @return void
 */
function swiftboard_add_grade_to_profile() {
	$user_id = bbp_get_displayed_user_id();
	if ( $user_id ) {
		echo '<div style="margin-top:8px;">';
		swiftboard_display_grade_badge( $user_id );
		echo '</div>';
	}
}
// V2 restauration - D3: supprimé car grade déjà rendu par reddit-profile (sb-profile-grade) - évite double affichage
// add_action('bbp_template_after_user_details', 'swiftboard_add_grade_to_profile');

// ============================================================================
// 12. COLONNE GRADE DANS LA LISTE DES UTILISATEURS WordPress
// ============================================================================
/**
 * swiftboard_grade_column().
 *
 * @param array<string, string> $columns Colonnes du tableau, à retourner modifiées.
 * @return mixed
 */
function swiftboard_grade_column( $columns ) {
	$columns['swiftboard_grade'] = 'Grade';
	return $columns;
}
add_filter( 'manage_users_columns', 'swiftboard_grade_column' );

/**
 * swiftboard_grade_column_content().
 *
 * @param mixed  $value       Valeur en cours, à retourner si non concernée.
 * @param string $column_name Nom de la colonne en cours de rendu.
 * @param int    $user_id     Identifiant de l'utilisateur.
 * @return mixed
 */
function swiftboard_grade_column_content( $value, $column_name, $user_id ) {
	if ( $column_name === 'swiftboard_grade' ) {
		$grade_key = swiftboard_get_user_grade( $user_id );
		$grades    = swiftboard_get_grades();
		$grade     = $grades[ $grade_key ] ?? $grades['member'];
		return '<span style="background:' . $grade['color'] . ';color:#fff;padding:2px 10px;border-radius:9999px;font-size:0.75rem;font-weight:700;">' . $grade['icon'] . ' ' . $grade['name'] . '</span>';
	}
	return $value;
}
// manage_users_custom_column est un FILTRE (class-wp-users-list-table.php :
// `$row .= apply_filters('manage_users_custom_column', '', $column_name, $id)`).
// Il etait accroche avec add_action(), qui n'est qu'un ALIAS de add_filter()
// dans le coeur : le comportement etait donc deja correct — verifie par
// mutation sur /wp-admin/users.php, les deux colonnes s'affichent dans les
// deux cas. On utilise add_filter() pour dire ce que le code fait vraiment.
add_filter( 'manage_users_custom_column', 'swiftboard_grade_column_content', 10, 3 );

// ============================================================================
// 13. NOTIFICATION DANS LE FORMULAIRE DE SUJET SI L'UTILISATEUR NE PEUT PAS
// ============================================================================
/**
 * @return void
 */
function swiftboard_topic_permission_notice() {
	if ( ! is_user_logged_in()) return;
	$user_id = get_current_user_id();
	if ( ! swiftboard_user_can( $user_id, 'can_create_topic' ) ) {
		$grade      = swiftboard_get_user_grade( $user_id );
		$grades     = swiftboard_get_grades();
		$grade_name = $grades[ $grade ]['name'] ?? 'Rookie';
		echo '<div class="bbp-template-notice warning">
            <p>🔒 Votre grade <strong>' . esc_html( $grade_name ) . '</strong> ne vous permet pas encore de créer des sujets.
            Participez aux discussions pour débloquer cette fonctionnalité.</p>
        </div>';
	}
}
add_action( 'bbp_template_before_single_forum', 'swiftboard_topic_permission_notice', 5 );

// ============================================================================
// 14. MONTÉE DE GRADE AUTOMATIQUE — MOTEUR DE RÉPUTATION
// ============================================================================
/**
 * Calcule le score de réputation d'un utilisateur.
 *
 * Score = (upvotes reçus sur mes sujets + réponses) × poids respectifs.
 * Les downvotes ne sont PAS pris en compte (on ne pénalise pas, on récompense
 * uniquement les contributions utiles).
 *
 * Cache : 15 minutes via transient (la valeur change rarement dans l'intervalle).
 *
 * @param int $user_id ID de l'utilisateur.
 * @return array {
 *     @type int $score        Score total.
 *     @type int $upvotes      Nombre d'upvotes reçus.
 *     @type int $replies      Nombre de réponses reçues sur mes sujets.
 *     @type int $weight_up    Poids appliqué aux upvotes.
 *     @type int $weight_reply Poids appliqué aux réponses.
 * }
 */
// v4.6.1 : swiftboard_get_user_reputation_score() déplacée vers inc/grades.php (chargé sur le front aussi)

/**
 * Invalide le cache du score de réputation d'un utilisateur.
 * À appeler après un vote ou une nouvelle réponse.
 */
// v4.6.1 : swiftboard_invalidate_reputation_cache() déplacée vers inc/grades.php (chargé sur le front aussi)


// ============================================================================
// 15-17. PROMOTION AUTOMATIQUE — DEPLACEE (EXI-ARCH-01)
// ============================================================================
// Le moteur de promotion vit desormais dans inc/promotion.php, charge sur le
// FRONT. Il s'accroche a bbp_new_reply, swiftboard_vote_cast et wp : depuis un
// module admin-only, ces hooks n'etaient jamais enregistres pour un visiteur.
//
// Fonctions deplacees : swiftboard_get_expected_grade_from_score(),
// swiftboard_maybe_promote_user(), swiftboard_send_promotion_email(),
// swiftboard_on_new_reply_check_promotion(),
// swiftboard_on_vote_cast_check_promotion(),
// swiftboard_register_autopromote_cron(), swiftboard_autopromote_daily_callback().

// ============================================================================
// 18. WIDGET DE PROGRESSION DANS LE PROFIL bbPress
// ============================================================================
/**
 * Affiche la progression de l'utilisateur vers le prochain grade.
 * S'affiche sur le profil bbPress (page utilisateur).
 *
 * @param int $user_id Identifiant de l'utilisateur. Optionnel.
 * @return void
 */
function swiftboard_render_reputation_progress( $user_id = 0 ) {
	$user_id = $user_id ?: ( function_exists( 'bbp_get_displayed_user_id' ) ? bbp_get_displayed_user_id() : 0 );
	if ( ! $user_id ) {
		return;
	}

	$reputation  = swiftboard_get_user_reputation_score( $user_id );
	$score       = $reputation['score'];
	$current     = swiftboard_get_user_grade( $user_id );
	$current_lvl = swiftboard_grade_level( $current );

	// Modérateur / VIP : pas de progression à afficher
	if ( $current_lvl >= 4 ) {
		echo '<div class="swiftboard-reputation sb-reputation-card">
            <div style="font-weight:700;margin-bottom:4px;">🏆 Score de réputation</div>
            <div style="font-size:1.5rem;font-weight:800;color:#16a34a;">' . esc_html( $score ) . ' pts</div>
            <div style="font-size:0.8rem;color:var(--color-text-muted);margin-top:4px;">' . esc_html__( 'Grade maximal atteint — félicitations !', 'swiftboard' ) . '</div>
        </div>';
		return;
	}

	$threshold_member = (int) get_option( 'swiftboard_autopromote_threshold_member', 5 );
	$threshold_pro    = (int) get_option( 'swiftboard_autopromote_threshold_pro', 500 );

	// Prochain seuil + grade cible
	if ( $current === 'rookie' ) {
		$next_score = $threshold_member;
		$next_grade = 'member';
	} elseif ( $current === 'member' ) {
		$next_score = $threshold_pro;
		$next_grade = 'pro';
	} else {
		$next_score = $threshold_pro;
		$next_grade = 'pro';
	}

	$grades    = swiftboard_get_grades();
	$next_info = $grades[ $next_grade ] ?? array(
		'icon' => '',
		'name' => ucfirst( $next_grade ),
	);

	$pct       = $next_score > 0 ? min( 100, round( ( $score / $next_score ) * 100 ) ) : 100;
	$remaining = max( 0, $next_score - $score );

	?>
	<div class="swiftboard-reputation sb-reputation-card">
		<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
			<strong>🏆 Score de réputation</strong>
			<span style="font-size:0.75rem;color:var(--color-text-muted);">
				▲ <?php echo (int) $reputation['upvotes']; ?> upvotes · 💬 <?php echo (int) $reputation['replies']; ?> réponses reçues
			</span>
		</div>
		<div style="font-size:1.75rem;font-weight:800;color:var(--color-primary-text);margin-bottom:8px;">
			<?php echo esc_html( $score ); ?> <span style="font-size:0.875rem;font-weight:600;color:var(--color-text-muted);">pts</span>
		</div>
		<div style="background:#e5e7eb;border-radius:9999px;height:10px;overflow:hidden;margin-bottom:6px;">
			<div style="background:linear-gradient(90deg,#006cbd,#006cbd);height:100%;width:<?php echo (int) $pct; ?>%;transition:width .3s;"></div>
		</div>
		<div style="font-size:0.8rem;color:var(--color-text-muted);">
			Plus que <strong><?php echo (int) $remaining; ?> pts</strong> pour atteindre
			<span style="font-weight:700;color:var(--color-primary-text);"><?php echo esc_html( $next_info['icon'] . ' ' . $next_info['name'] ); ?></span>
		</div>
	</div>
	<?php
}
add_action( 'bbp_template_after_user_details', 'swiftboard_render_reputation_progress', 15 );

// ============================================================================
// 19. COLONNE "SCORE" DANS LA LISTE DES UTILISATEURS WP
// ============================================================================
/**
 * swiftboard_reputation_column().
 *
 * @param array<string, string> $columns Colonnes du tableau, à retourner modifiées.
 * @return mixed
 */
function swiftboard_reputation_column( $columns ) {
	$columns['swiftboard_reputation'] = 'Score';
	return $columns;
}
add_filter( 'manage_users_columns', 'swiftboard_reputation_column' );

/**
 * swiftboard_reputation_column_content().
 *
 * @param mixed  $value       Valeur en cours, à retourner si non concernée.
 * @param string $column_name Nom de la colonne en cours de rendu.
 * @param int    $user_id     Identifiant de l'utilisateur.
 * @return mixed
 */
function swiftboard_reputation_column_content( $value, $column_name, $user_id ) {
	if ( $column_name === 'swiftboard_reputation' ) {
		$rep = swiftboard_get_user_reputation_score( $user_id );
		return '<strong style="color:var(--color-primary-text);">' . (int) $rep['score'] . '</strong>'
			. '<br><small style="color:#999;">▲ ' . (int) $rep['upvotes'] . ' · 💬 ' . (int) $rep['replies'] . '</small>';
	}
	return $value;
}
add_filter( 'manage_users_custom_column', 'swiftboard_reputation_column_content', 10, 3 );

// ============================================================================
// 20. PAGE ADMIN — CLASSEMENT DES RÉPUTATIONS (sous-page)
// ============================================================================
/**
 * @return void
 */
function swiftboard_reputation_leaderboard_menu() {
	add_submenu_page(
		'swiftboard-dashboard',
		__( 'Classement réputation', 'swiftboard' ),
		__( '📊 Classement', 'swiftboard' ),
		'manage_options',
		'swiftboard-reputation',
		'swiftboard_reputation_leaderboard_page'
	);
}
add_action( 'admin_menu', 'swiftboard_reputation_leaderboard_menu' );

/**
 * @return void
 */
function swiftboard_ajax_recalc_reputation() {
	check_ajax_referer( 'swiftboard_recalc_reputation', '_wpnonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Forbidden', 403 );
	}
	$user_id = (int) ( $_POST['user_id'] ?? 0 );
	if ( ! $user_id ) {
		wp_send_json_error( 'Missing user_id', 400 );
	}
	swiftboard_invalidate_reputation_cache( $user_id );
	$rep = swiftboard_get_user_reputation_score( $user_id );
	// En profiter pour vérifier la promotion
	$promotion = swiftboard_maybe_promote_user( $user_id );
	wp_send_json_success(
		array(
			'score'     => $rep['score'],
			'upvotes'   => $rep['upvotes'],
			'replies'   => $rep['replies'],
			'promotion' => $promotion,
		)
	);
}
add_action( 'wp_ajax_swiftboard_recalc_reputation', 'swiftboard_ajax_recalc_reputation' );

// ============================================================================
// 21. DÉSACTIVER LE CRON À LA DÉSINSTALLATION
// ============================================================================
/**
 * @return void
 */
function swiftboard_clear_autopromote_cron() {
	$ts = wp_next_scheduled( 'swiftboard_autopromote_daily' );
	if ( $ts ) {
		wp_unschedule_event( $ts, 'swiftboard_autopromote_daily' );
	}
}
register_deactivation_hook( __FILE__, 'swiftboard_clear_autopromote_cron' );

