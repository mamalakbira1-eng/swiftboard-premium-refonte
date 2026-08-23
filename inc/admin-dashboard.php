<?php
if ( ! defined( 'ABSPATH' )) exit;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped — admin context with intentional HTML
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared — SQL uses internal $wpdb variables (safe)
/**
 * SwiftBoard - Dashboard Admin complet
 *
 * Dashboard dédié à la gestion du forum, SÉPARÉ de l'admin WordPress.
 * Accessible via /wp-admin/admin.php?page=swiftboard-dashboard
 *
 * Sections :
 * 1. Overview — statistiques globales (sujets, réponses, votes, images, utilisateurs)
 * 2. Sujets — liste, modération, suppression, verrouillage
 * 3. Réponses — liste, modération, suppression
 * 4. Votes — upvotes/downvotes par post, top/flop
 * 5. Images — toutes les images uploadées (pending/approved/rejected)
 * 6. Utilisateurs — top contributeurs, bannis, statistiques
 *
 * @package SwiftBoard
 * @since 2.3.0
 */
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared — SQL queries use internal $wpdb->prefix variables (safe)

// ============================================================================
// 1. MENU PRINCIPAL
// ============================================================================
/**
 * @return void
 */
function swiftboard_dashboard_menu() {
	add_menu_page(
		__( 'SwiftBoard Dashboard', 'swiftboard' ),
		__( 'SwiftBoard', 'swiftboard' ),
		'moderate_comments',
		'swiftboard-dashboard',
		'swiftboard_dashboard_page',
		'dashicons-chart-area',
		2
	);

	add_submenu_page( 'swiftboard-dashboard', __( 'Overview', 'swiftboard' ), __( 'Overview', 'swiftboard' ), 'moderate_comments', 'swiftboard-dashboard', 'swiftboard_dashboard_page' );
	add_submenu_page( 'swiftboard-dashboard', __( 'Sujets', 'swiftboard' ), __( 'Sujets', 'swiftboard' ), 'moderate_comments', 'swiftboard-topics', 'swiftboard_admin_topics_page' );
	add_submenu_page( 'swiftboard-dashboard', __( 'Réponses', 'swiftboard' ), __( 'Réponses', 'swiftboard' ), 'moderate_comments', 'swiftboard-replies', 'swiftboard_admin_replies_page' );
	// EXI-SEC : expose IP + hash des votants -> manage_options
	add_submenu_page( 'swiftboard-dashboard', __( 'Votes', 'swiftboard' ), __( 'Votes', 'swiftboard' ), 'manage_options', 'swiftboard-admin-votes', 'swiftboard_admin_votes_page' );
	add_submenu_page( 'swiftboard-dashboard', __( 'Images', 'swiftboard' ), __( 'Images', 'swiftboard' ), 'moderate_comments', 'swiftboard-admin-images', 'swiftboard_admin_images_page' );
	// EXI-SEC : expose user_email -> reserve aux profils habilites (pas les Editeurs)
	add_submenu_page( 'swiftboard-dashboard', __( 'Communautés', 'swiftboard' ), __( '🏛️ Communautés', 'swiftboard' ), 'moderate_comments', 'swiftboard-forums', 'swiftboard_admin_forums_page' );
	add_submenu_page( 'swiftboard-dashboard', __( 'Utilisateurs', 'swiftboard' ), __( 'Utilisateurs', 'swiftboard' ), 'list_users', 'swiftboard-users', 'swiftboard_admin_users_page' );
}
// PRIORITE 9 — le menu PARENT doit exister avant tout add_submenu_page().
//
// Cinq modules accrochent leurs sous-pages a 'swiftboard-dashboard' :
// admin-settings-grades, audit-trail, email-digest, top-weekly-responder et
// security. Tous s'enregistrent sur `admin_menu` en priorite 10, comme ce
// menu parent : l'ordre d'execution depend alors du seul ordre de chargement
// des fichiers dans functions.php.
//
// Or admin-settings-grades.php est un module MIXTE (ligne 81) charge AVANT
// admin-dashboard.php (ligne 88, module admin-only). Sa sous-page
// « Classement » s'enregistrait donc sur un parent inexistant et WordPress
// repondait « Sorry, you are not allowed to access this page » — un HTTP 403
// pour un administrateur, constate en simulation sur une installation neuve.
//
// La priorite 9 rend l'ordre explicite au lieu de dependre du hasard.
add_action( 'admin_menu', 'swiftboard_dashboard_menu', 9 );

// ============================================================================
// 2. CSS DU DASHBOARD
// ============================================================================
/**
 * @return void
 */
function swiftboard_dashboard_css() {
	$screen = get_current_screen();
	if ( $screen && strpos( $screen->id, 'swiftboard' ) !== false ) {
		echo '<style>
        .sb-wrap { padding: 20px; max-width: 1400px; }
        .sb-stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; margin: 20px 0; }
        .sb-stat-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; text-align: center; transition: box-shadow 0.2s; }
        .sb-stat-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .sb-stat-card .icon { font-size: 1.5rem; margin-bottom: 8px; }
        .sb-stat-card .number { font-size: 1.75rem; font-weight: 800; color: #006cbd; }
        .sb-stat-card .label { font-size: 0.8rem; color: #6b7280; margin-top: 4px; }
        .sb-stat-card.up .number { color: #006cbd; }
        .sb-stat-card.down .number { color: #7193ff; }
        .sb-stat-card.warning .number { color: #d97706; }
        .sb-stat-card.success .number { color: #16a34a; }
        .sb-stat-card.danger .number { color: #dc2626; }

        .sb-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
        .sb-table th { background: #f8fafc; padding: 12px 16px; text-align: left; font-weight: 700; font-size: 0.8rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid #e2e8f0; }
        .sb-table td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; font-size: 0.875rem; }
        .sb-table tr:hover td { background: #f8fafc; }
        .sb-badge { display: inline-block; padding: 2px 10px; border-radius: 9999px; font-size: 0.7rem; font-weight: 700; }
        .sb-badge.pending { background: #fef3c7; color: #d97706; }
        .sb-badge.approved { background: #edf9e7; color: #16a34a; }
        .sb-badge.rejected { background: #fee2e2; color: #dc2626; }
        .sb-badge.closed { background: #fee2e2; color: #dc2626; }
        .sb-badge.open { background: #edf9e7; color: #16a34a; }
        .sb-badge.sticky { background: #e8f3fb; color: #006cbd; }

        .sb-action-btn { padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; cursor: pointer; border: 1px solid; text-decoration: none; display: inline-block; margin: 2px; }
        .sb-action-btn.approve { background: #16a34a; color: #fff; border-color: #16a34a; }
        .sb-action-btn.reject { background: #dc2626; color: #fff; border-color: #dc2626; }
        .sb-action-btn.delete { background: #fee2e2; color: #dc2626; border-color: #fee2e2; }
        .sb-action-btn.close { background: #fef3c7; color: #d97706; border-color: #fef3c7; }
        .sb-action-btn.open { background: #edf9e7; color: #16a34a; border-color: #edf9e7; }
        .sb-action-btn.view { background: #e8f3fb; color: #006cbd; border-color: #e8f3fb; }

        .sb-score { font-weight: 800; font-size: 1rem; }
        .sb-score.positive { color: #006cbd; }
        .sb-score.negative { color: #7193ff; }
        .sb-score.neutral { color: #6b7280; }

        .sb-section-title { font-size: 1.25rem; font-weight: 700; margin: 24px 0 12px; display: flex; align-items: center; gap: 8px; }
        .sb-filter-bar { display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; align-items: center; }
        .sb-filter-bar select, .sb-filter-bar input { padding: 6px 12px; border: 1px solid #d4d5d7; border-radius: 6px; font-size: 0.85rem; }
        .sb-filter-bar button { padding: 6px 16px; background: #006cbd; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.85rem; }
        .sb-avatar { width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.75rem; color: #fff; }
        .sb-empty { text-align: center; padding: 40px; color: #999; }
        .sb-notice { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-weight: 600; }
        .sb-notice.success { background: #edf9e7; color: #16a34a; }
        .sb-notice.error { background: #fee2e2; color: #dc2626; }
        .sb-progress { width: 100%; height: 6px; background: #f1f5f9; border-radius: 9999px; overflow: hidden; }
        .sb-progress-bar { height: 100%; border-radius: 9999px; transition: width 0.3s; }
        .sb-progress-bar.up { background: #006cbd; }
        .sb-progress-bar.down { background: #7193ff; }
        </style>';
	}
}
add_action( 'admin_head', 'swiftboard_dashboard_css' );

// ============================================================================
// 3. PAGE OVERVIEW
// ============================================================================
/**
 * @return void
 */
function swiftboard_dashboard_page() {
	// EXI-SEC-BLOQ-07 : la capability du menu ne protege pas l'appel direct
	// de la fonction via admin.php?page=... Controle explicite obligatoire.
	if ( ! current_user_can( 'moderate_comments' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'swiftboard' ), 403 );
	}

	global $wpdb;
	$votes_table   = swiftboard_table( 'votes' );
	$uploads_table = swiftboard_table( 'uploads' );

	// Statistiques
	$topics_count  = wp_count_posts( 'topic' )->publish ?? 0;
	$replies_count = wp_count_posts( 'reply' )->publish ?? 0;
	$forums_count  = wp_count_posts( 'forum' )->publish ?? 0;
	$users_count   = count_users()['total_users'] ?? 0;

	// Le cast s'applique AVANT ?? : « (int) x ?? 0 » vaut « ((int) x) ?? 0 »,
	// donc le repli ne protegeait rien et un type de contenu absent emettait
	// « Undefined property stdClass::$pending ». Parentheses explicites.
	$pending_topics = (int) ( wp_count_posts( 'topic' )->pending ?? 0 );
	$spam_replies   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'reply' AND post_status = 'spam'" );

	$total_votes = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $votes_table" );
	$upvotes     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $votes_table WHERE vote_type = 'up'" );
	$downvotes   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $votes_table WHERE vote_type = 'down'" );

	$pending_images  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $uploads_table WHERE status = 'pending'" );
	$approved_images = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $uploads_table WHERE status = 'approved'" );
	$rejected_images = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $uploads_table WHERE status = 'rejected'" );

	$total_original  = (int) $wpdb->get_var( "SELECT COALESCE(SUM(original_size), 0) FROM $uploads_table WHERE status != 'rejected'" );
	$total_converted = (int) $wpdb->get_var( "SELECT COALESCE(SUM(file_size), 0) FROM $uploads_table WHERE status != 'rejected'" );
	$savings_pct     = $total_original > 0 ? round( ( 1 - $total_converted / $total_original ) * 100 ) : 0;

	// Top 5 sujets par score
	$top_topics = $wpdb->get_results(
		"
        SELECT p.ID, p.post_title,
               COALESCE(pm.meta_value, 0) as score
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_swiftboard_vote_score'
        WHERE p.post_type = 'topic' AND p.post_status = 'publish'
        ORDER BY COALESCE(CAST(pm.meta_value AS SIGNED), 0) DESC
        LIMIT 5
    "
	);

	// Top 5 utilisateurs
	$top_pack  = function_exists( 'swiftboard_admin_query_user_stats' )
		? swiftboard_admin_query_user_stats( 5, 0, false )
		: array( 'rows' => array() );
	$top_users = $top_pack['rows'];

	// Activité récente (7 derniers jours)
	$recent_activity = $wpdb->get_var(
		"
        SELECT COUNT(*) FROM {$wpdb->posts}
        WHERE post_type IN ('topic', 'reply')
        AND post_status = 'publish'
        AND post_date > DATE_SUB(NOW(), INTERVAL 7 DAY)
    "
	);

	?>
	<div class="sb-wrap">
		<h1>📊 SwiftBoard Dashboard</h1>
		<p style="color:#4b5563;margin-bottom:20px;"><?php esc_html_e( 'Vue d\'ensemble de votre forum communautaire', 'swiftboard' ); ?></p>

		<?php
		// One-click demo import banner — visible quand le forum est vide
		if ( $topics_count == 0 && $forums_count == 0 && current_user_can( 'manage_options' ) ) :
		?>
		<div style="background: linear-gradient(135deg, #006cbd, #0090e0); color:#fff; padding:24px; border-radius:12px; margin-bottom:20px;">
			<h2 style="color:#fff; margin:0 0 8px;">🚀 <?php esc_html_e( 'Bienvenue sur SwiftBoard !', 'swiftboard' ); ?></h2>
			<p style="margin:0 0 16px; opacity:0.9;"><?php esc_html_e( 'Votre forum est vide. Importez la démo en 1 clic pour avoir instantanément des forums, des sujets, des réponses, des articles de blog, des avatars et des grades militaires.', 'swiftboard' ); ?></p>
			<div style="display:flex; gap:12px; flex-wrap:wrap;">
				<button type="button" id="sb-demo-import-fr" class="button button-primary button-hero" style="background:#fff; color:#006cbd; border:none; font-weight:700; padding:8px 24px; font-size:14px; border-radius:8px;">
					🇫🇷 Importer la démo française
				</button>
				<button type="button" id="sb-demo-import-ar" class="button button-primary button-hero" style="background:rgba(255,255,255,0.2); color:#fff; border:2px solid #fff; font-weight:700; padding:8px 24px; font-size:14px; border-radius:8px;">
					🇲🇦 استيراد النسخة العربية
				</button>
			</div>
			<div id="sb-demo-import-status" style="margin-top:12px; display:none;"></div>
		</div>
		<script>
		jQuery(function($){
			$('#sb-demo-import-fr, #sb-demo-import-ar').on('click', function(){
				var lang = $(this).attr('id') === 'sb-demo-import-fr' ? 'fr' : 'ar';
				var status = $('#sb-demo-import-status');
				status.show().html('<p style="margin:0;">⏳ Importation en cours... Cela peut prendre 30 secondes.</p>');
				$('#sb-demo-import-fr, #sb-demo-import-ar').prop('disabled', true);
				$.post(ajaxurl, {
					action: 'swiftboard_demo_import',
					lang: lang,
					_ajax_nonce: '<?php echo wp_create_nonce('swiftboard_demo_import'); ?>'
				}, function(resp){
					if (resp.success) {
						status.html('<p style="margin:0; font-weight:700;">✅ ' + resp.data + '</p><p style="margin:4px 0 0; opacity:0.8;">Redirection...</p>');
						setTimeout(function(){ window.location.reload(); }, 2000);
					} else {
						status.html('<p style="margin:0; color:#fee2e2;">❌ ' + resp.data + '</p>');
						$('#sb-demo-import-fr, #sb-demo-import-ar').prop('disabled', false);
					}
				});
			});
		});
		</script>
		<?php endif; ?>

		<?php
		// Badges de modération
		$pending_topics = (int) ( wp_count_posts( 'topic' )->pending ?? 0 );
		$pending_forums = (int) ( wp_count_posts( 'forum' )->pending ?? 0 );
		if ( $pending_topics > 0 || $pending_forums > 0 ) :
		?>
		<div class="sb-notice" style="background:#fef3c7;color:#d97706;padding:12px 16px;border-radius:8px;margin-bottom:16px;">
			⏳ <?php echo intval($pending_topics); ?> sujet(s) + <?php echo intval($pending_forums); ?> communauté(s) en attente de validation.
			<?php if ($pending_topics > 0): ?>
				<a href="<?php echo admin_url('admin.php?page=swiftboard-topics&status=pending'); ?>" style="font-weight:700;margin-left:8px;">Sujets →</a>
			<?php endif; ?>
			<?php if ($pending_forums > 0): ?>
				<a href="<?php echo admin_url('admin.php?page=swiftboard-forums&status=pending'); ?>" style="font-weight:700;margin-left:8px;">Communautés →</a>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<?php if ( $pending_images > 0 ) : ?>
		<div class="sb-notice" style="background:#fef3c7;color:#d97706;padding:12px 16px;border-radius:8px;margin-bottom:16px;">
			⏳ <?php echo intval( $pending_images ); ?> image(s) en attente de modération. <a href="<?php echo admin_url( 'admin.php?page=swiftboard-admin-images' ); ?>"><?php esc_html_e( 'Modérer maintenant →', 'swiftboard' ); ?></a>
		</div>
		<?php endif; ?>

		<!-- Statistiques globales -->
		<div class="sb-stats-grid">
			<div class="sb-stat-card"><div class="icon">💬</div><div class="number"><?php echo intval( $forums_count ); ?></div><div class="label"><?php esc_html_e( 'Forums', 'swiftboard' ); ?></div></div>
			<div class="sb-stat-card"><div class="icon">📝</div><div class="number"><?php echo intval( $topics_count ); ?></div><div class="label"><?php esc_html_e( 'Sujets', 'swiftboard' ); ?></div></div>
			<div class="sb-stat-card"><div class="icon">↩️</div><div class="number"><?php echo intval( $replies_count ); ?></div><div class="label"><?php esc_html_e( 'Réponses', 'swiftboard' ); ?></div></div>
			<div class="sb-stat-card"><div class="icon">👥</div><div class="number"><?php echo intval( $users_count ); ?></div><div class="label"><?php esc_html_e( 'Utilisateurs', 'swiftboard' ); ?></div></div>
			<div class="sb-stat-card up"><div class="icon">▲</div><div class="number"><?php echo intval( $upvotes ); ?></div><div class="label"><?php esc_html_e( 'Upvotes', 'swiftboard' ); ?></div></div>
			<div class="sb-stat-card down"><div class="icon">▼</div><div class="number"><?php echo intval( $downvotes ); ?></div><div class="label"><?php esc_html_e( 'Downvotes', 'swiftboard' ); ?></div></div>
			<div class="sb-stat-card warning"><div class="icon">⏳</div><div class="number"><?php echo intval( $pending_images ); ?></div><div class="label"><?php esc_html_e( 'Images en attente', 'swiftboard' ); ?></div></div>
			<div class="sb-stat-card success"><div class="icon">✅</div><div class="number"><?php echo intval( $approved_images ); ?></div><div class="label"><?php esc_html_e( 'Images approuvées', 'swiftboard' ); ?></div></div>
			<div class="sb-stat-card"><div class="icon">💾</div><div class="number"><?php echo intval( $savings_pct ); ?>%</div><div class="label"><?php esc_html_e( 'Économie AVIF (', 'swiftboard' ); ?><?php echo size_format( $total_original ); ?> → <?php echo size_format( $total_converted ); ?>)</div></div>
			<div class="sb-stat-card"><div class="icon">📅</div><div class="number"><?php echo intval( $recent_activity ); ?></div><div class="label"><?php esc_html_e( 'Activité (7 jours)', 'swiftboard' ); ?></div></div>
			<?php if ( $pending_topics > 0 ) : ?>
			<div class="sb-stat-card warning"><div class="icon">📝</div><div class="number"><?php echo intval( $pending_topics ); ?></div><div class="label"><?php esc_html_e( 'Sujets en attente', 'swiftboard' ); ?></div></div>
			<?php endif; ?>
			<?php if ( $spam_replies > 0 ) : ?>
			<div class="sb-stat-card danger"><div class="icon">🚫</div><div class="number"><?php echo intval( $spam_replies ); ?></div><div class="label"><?php esc_html_e( 'Réponses spam', 'swiftboard' ); ?></div></div>
			<?php endif; ?>
		</div>

		<!-- Top 5 sujets -->
		<div class="sb-section-title">🔥 Top 5 sujets par score</div>
		<table class="sb-table">
			<thead><tr><th>#</th><th><?php esc_html_e( 'Titre', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Score', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Actions', 'swiftboard' ); ?></th></tr></thead>
			<tbody>
				<?php
				foreach ( $top_topics as $i => $t ) :
					$score = (int) $t->score;
					?>
				<tr>
					<td><?php echo intval( $i + 1 ); ?></td>
					<td><a href="<?php echo get_permalink( $t->ID ); ?>" target="_blank"><?php echo esc_html( $t->post_title ); ?></a></td>
					<td><span class="sb-score <?php echo $score > 0 ? 'positive' : ( $score < 0 ? 'negative' : 'neutral' ); // phpcs:ignore"><?php echo intval($score); ?></span></td>
					<td><a href="<?php echo get_permalink( $t->ID ); ?>" class="sb-action-btn view" target="_blank"><?php esc_html_e( 'Voir', 'swiftboard' ); ?></a></td>
				</tr>
				<?php endforeach; ?>
				<?php
				if ( empty( $top_topics ) ) :
					?>
					<tr><td colspan="4" class="sb-empty"><?php esc_html_e( 'Aucun sujet pour le moment.', 'swiftboard' ); ?></td></tr><?php endif; ?>
			</tbody>
		</table>

		<!-- Top 5 utilisateurs -->
		<div class="sb-section-title">🏆 Top 5 contributeurs</div>
		<table class="sb-table">
			<thead><tr><th>#</th><th><?php esc_html_e( 'Utilisateur', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Sujets', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Réponses', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Total', 'swiftboard' ); ?></th></tr></thead>
			<tbody>
				<?php
				foreach ( $top_users as $i => $u ) :
					$total = $u->topics + $u->replies;
					?>
				<tr>
					<td><?php echo intval( $i + 1 ); ?></td>
					<td>
						<span class="sb-avatar" style="background:<?php echo array( '#006cbd', '#006cbd', '#16a34a', '#ec4899', '#d97706' )[ $i ]; ?>"><?php echo mb_strtoupper( mb_substr( $u->display_name, 0, 1 ) ); ?></span>
						<?php echo esc_html( $u->display_name ); ?>
					</td>
					<td><?php echo intval( $u->topics ); ?></td>
					<td><?php echo intval( $u->replies ); ?></td>
					<td><strong><?php echo intval( $total ); ?></strong></td>
				</tr>
				<?php endforeach; ?>
				<?php
				if ( empty( $top_users ) ) :
					?>
					<tr><td colspan="5" class="sb-empty"><?php esc_html_e( 'Aucun utilisateur actif.', 'swiftboard' ); ?></td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}


// ============================================================================
// ONE-CLICK DEMO IMPORT (AJAX)
// ============================================================================
add_action('wp_ajax_swiftboard_demo_import', function() {
    check_ajax_referer('swiftboard_demo_import', '_ajax_nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Accès refusé');
    }

    // S'assurer que les fonctions d'import sont chargées
    if (!function_exists('swiftboard_process_import')) {
        require_once SWIFTBOARD_DIR . '/inc/import-csv.php';
        require_once SWIFTBOARD_DIR . '/inc/import-entities.php';
        require_once SWIFTBOARD_DIR . '/inc/admin-bulk-import.php';
    }

    $lang = sanitize_text_field($_POST['lang'] ?? 'fr');
    $csv_file = ($lang === 'ar')
        ? SWIFTBOARD_DIR . '/demo/demo-sante-arabe.csv'
        : SWIFTBOARD_DIR . '/demo/demo-sante-francais.csv';

    if (!file_exists($csv_file)) {
        wp_send_json_error('Fichier démo introuvable: ' . $csv_file);
    }

    // Use the existing import system
    $file = array(
        'name'     => basename($csv_file),
        'type'     => 'text/csv',
        'tmp_name' => $csv_file,
        'error'    => UPLOAD_ERR_OK,
        'size'     => filesize($csv_file),
    );

    $log = swiftboard_process_import($file);

    // Si import arabe : passer le site en arabe + RTL
    if ($lang === 'ar') {
        update_option('WPLANG', 'ar');
        update_option('blog_charset', 'UTF-8');
        // Forcer le RTL au niveau du thème
        update_option('swiftboard_force_rtl', '1');
    }

    // Count results
    $topics  = wp_count_posts('topic')->publish;
    $replies = wp_count_posts('reply')->publish;
    $posts   = wp_count_posts('post')->publish;
    $forums  = wp_count_posts('forum')->publish;
    $users   = count(get_users(array('fields' => 'ID'))) - 1; // exclude admin

    wp_send_json_success(sprintf(
        'Démo importée : %d forums, %d sujets, %d réponses, %d articles, %d membres',
        $forums, $topics, $replies, $posts - 1, $users // -1 = Hello world
    ));
});
