<?php
if ( ! defined( 'ABSPATH' )) exit;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped — admin context with intentional HTML
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared — SQL uses internal $wpdb variables (safe)
/**
 * SwiftBoard - Profil utilisateur façon Reddit
 *
 * Override la page profil bbPress par défaut avec un layout moderne :
 *  - Hero header avec gradient + avatar + nom + grade + stats
 *  - Onglets : Aperçu / Sujets / Réponses / Sauvegardés / Suivis / Notifications / Trophées
 *  - Karma total (somme des upvotes reçus)
 *  - Date d'inscription + badges (top répondeur, etc.)
 *
 * URL : /forums/users/{username}/  → intercepté par template_redirect
 *
 * @package SwiftBoard
 * @since 3.4.0
 */
// ============================================================================
// 1. INTERCEPTER LE PROFIL bbPress
// ============================================================================
/**
 * Détecte les URLs de profil bbPress et redirige vers notre template custom.
 */
add_action(
	'template_redirect',
	function () {
		if ( ! function_exists( 'bbp_is_single_user' ) || ! bbp_is_single_user() ) {
			return;
		}
		$user_id = bbp_get_displayed_user_id();
		if ( ! $user_id) return;

		// Récupère l'onglet demandé (overview par défaut)
		$tab     = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'overview';
		$allowed = array( 'overview', 'posts', 'comments', 'saved', 'following', 'trophies', 'notifications', 'compte', 'edit' );
		$tab     = in_array( $tab, $allowed, true ) ? $tab : 'overview';

		// EXI-MBR-01 : les notifications sont privees — repli si ce n'est pas son profil
		if ( $tab === 'notifications' && get_current_user_id() !== (int) $user_id ) {
			$tab = 'overview';
		}

		// Meme regle pour l'onglet « Compte » : il porte la suppression du compte.
		// L'acces direct par URL au profil d'un tiers ne doit rien exposer.
		if ( $tab === 'compte' && get_current_user_id() !== (int) $user_id ) {
			$tab = 'overview';
		}

		get_header();
		swiftboard_render_reddit_profile( $user_id, $tab );
		get_footer();
		exit;
	}
);

// ============================================================================
// 2. RENDU DU PROFIL
// ============================================================================
/**
 * swiftboard_render_reddit_profile().
 *
 * @param int   $user_id Identifiant de l'utilisateur.
 * @param mixed $tab     À documenter.
 * @return void
 */
function swiftboard_render_reddit_profile( $user_id, $tab ) {
	$user = get_userdata( $user_id );
	if ( ! $user) return;

	$grade      = swiftboard_get_user_grade( $user_id );
	$grades     = swiftboard_get_grades();
	$grade_info = $grades[ $grade ] ?? null;

	$rep   = swiftboard_get_user_reputation_score( $user_id );
	if ( ! is_array( $rep ) || ! isset( $rep['score'] ) ) {
		$rep = array(
			'score'        => 0,
			'upvotes'      => 0,
			'replies'      => 0,
			'weight_up'    => 0,
			'weight_reply' => 0,
		);
	}
	$karma = (int) $rep['score'];

	// Stats : nb topics + nb replies
	global $wpdb;
	$topics_count  = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d AND post_type = 'topic' AND post_status = 'publish'",
			$user_id
		)
	);
	$replies_count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d AND post_type = 'reply' AND post_status = 'publish'",
			$user_id
		)
	);

	// Top répondeur ?
	$weekly_rank = function_exists( 'swiftboard_get_user_weekly_rank' ) ? swiftboard_get_user_weekly_rank( $user_id ) : 0;

	// Avatar : avatar du forum (Reddit-style) au lieu de Gravatar.
	// Les comptes importés n'ont pas de vrai Gravatar ; on utilise le jeu
	// d'avatars locaux (choisi par le membre ou attribué selon l'ID).
	$avatar_html = swiftboard_get_user_avatar_html( (int) $user_id, 96, 'sb-profile-avatar' );

	// V2 restauration - D3: compat extensions, hooks bbp_template_before/after_user_details
	// Le profil reddit intercepte avec exit, donc les hooks natifs ne sont jamais tirés -> on les restaure ici
	do_action( 'bbp_template_before_user_details' );

	?>
	<div class="sb-profile">

		<!-- Hero -->
		<div class="sb-profile-hero">
			<div class="sb-profile-hero-overlay"></div>
			<div class="sb-profile-hero-content">
                <?php echo $avatar_html; // phpcs:ignore -- HTML construit et échappé dans la fonction ?>
				<div class="sb-profile-name">
					<h1><?php echo esc_html( $user->display_name ); ?></h1>
					<?php if ( $grade_info ) : ?>
					<span class="sb-profile-grade" style="background:<?php echo esc_attr( $grade_info['color'] ); ?>">
						<?php echo swiftboard_grade_insignia_svg( swiftboard_get_user_grade( $user_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique genere par le theme. ?> <?php echo esc_html( $grade_info['name'] ); ?>
					</span>
					<?php endif; ?>
				</div>
				<div class="sb-profile-joined">
					<?php
					printf(
						esc_html__( 'Inscrit le %s', 'swiftboard' ),
						esc_html( date_i18n( get_option( 'date_format' ), strtotime( $user->user_registered ) ) )
					);
					?>
				</div>

			</div>
		</div>

		<?php
		// Sélecteur d'avatar : visible uniquement pour le membre sur SON profil.
		if ( get_current_user_id() === (int) $user_id ) {
			swiftboard_avatar_picker();
		}
		?>
		<!-- Stats row -->
		<div class="sb-profile-stats">
			<div class="sb-profile-stat">
				<div class="sb-profile-stat-value"><?php echo esc_html( swiftboard_format_count( $karma ) ); ?></div>
				<div class="sb-profile-stat-label"><?php esc_html_e( 'Karma', 'swiftboard' ); ?></div>
			</div>
			<div class="sb-profile-stat">
				<div class="sb-profile-stat-value"><?php echo esc_html( swiftboard_format_count( $rep['upvotes'] ) ); ?></div>
				<div class="sb-profile-stat-label"><?php esc_html_e( 'Upvotes reçus', 'swiftboard' ); ?></div>
			</div>
			<div class="sb-profile-stat">
				<div class="sb-profile-stat-value"><?php echo (int) $topics_count; ?></div>
				<div class="sb-profile-stat-label"><?php esc_html_e( 'Sujets', 'swiftboard' ); ?></div>
			</div>
			<div class="sb-profile-stat">
				<div class="sb-profile-stat-value"><?php echo (int) $replies_count; ?></div>
				<div class="sb-profile-stat-label"><?php esc_html_e( 'Réponses', 'swiftboard' ); ?></div>
			</div>
			<?php
			if ( $weekly_rank > 0 ) :
				$medals = array( '🥇', '🥈', '🥉' );
				?>
			<div class="sb-profile-stat">
				<div class="sb-profile-stat-value"><?php echo $medals[ $weekly_rank - 1 ] ?? '#' . $weekly_rank; ?></div>
				<div class="sb-profile-stat-label"><?php esc_html_e( 'Top semaine', 'swiftboard' ); ?></div>
			</div>
			<?php endif; ?>
		</div>

		<?php
		// EXI-MBR-02 : progression vers le grade suivant
		$sb_grades  = swiftboard_get_grades();
		$sb_current = swiftboard_get_user_grade( $user_id );
		$sb_score   = (int) $rep['score'];
		$sb_next    = null;
		foreach ( $sb_grades as $sb_key => $sb_g ) {
			if ( (int) $sb_g['min_score'] > $sb_score
				&& ( $sb_next === null || (int) $sb_g['min_score'] < (int) $sb_next['min_score'] ) ) {
				$sb_next = $sb_g + array( 'key' => $sb_key );
			}
		}
		?>
		<div class="sb-grade-progress-wrap">
			<?php
			if ( $sb_next ) :
				$sb_min = (int) ( $sb_grades[ $sb_current ]['min_score'] ?? 0 );
				$sb_max = (int) $sb_next['min_score'];
				$sb_pct = $sb_max > $sb_min
					? max( 0, min( 100, (int) round( ( $sb_score - $sb_min ) / ( $sb_max - $sb_min ) * 100 ) ) )
					: 100;
				?>
				<div class="sb-grade-progress"
					role="progressbar"
					aria-valuenow="<?php echo (int) $sb_pct; ?>"
					aria-valuemin="0"
					aria-valuemax="100"
					aria-label="<?php esc_attr_e( 'Progression vers le grade suivant', 'swiftboard' ); ?>">
					<div class="sb-grade-progress-bar" style="width:<?php echo (int) $sb_pct; ?>%"></div>
				</div>
				<p class="sb-grade-next">
					<?php
					printf(
						esc_html__( 'Encore %1$d points pour atteindre %2$s', 'swiftboard' ),
						max( 0, $sb_max - $sb_score ),
						esc_html( $sb_next['name'] )
					);
					?>
				</p>
			<?php else : ?>
				<p class="sb-grade-next"><?php esc_html_e( 'Grade maximum atteint 🎉', 'swiftboard' ); ?></p>
			<?php endif; ?>
			<?php
			// v5.3.8 — EXI-KARMA-03 : echelle des grades + calcul du karma
			// affiches sur le profil de chaque membre.
			echo function_exists( 'swiftboard_get_karma_ladder_html' ) ? swiftboard_get_karma_ladder_html() : ''; /* phpcs:ignore — HTML echappe dans le helper */
			?>
		</div>

		<!-- Tabs -->
		<nav class="sb-profile-tabs">
            <a href="?tab=overview" class="sb-profile-tab <?php echo $tab === 'overview' ? 'active' : ''; /* phpcs:ignore */ ?>">📋 <?php esc_html_e('Aperçu', 'swiftboard'); ?></a>
            <a href="?tab=posts" class="sb-profile-tab <?php echo $tab === 'posts' ? 'active' : ''; /* phpcs:ignore */ ?>">📝 Sujets</a>
            <a href="?tab=comments" class="sb-profile-tab <?php echo $tab === 'comments' ? 'active' : ''; /* phpcs:ignore */ ?>">💬 Réponses</a>
			<?php if ( get_current_user_id() === $user_id ) : ?>
            <a href="?tab=saved" class="sb-profile-tab <?php echo $tab === 'saved' ? 'active' : ''; /* phpcs:ignore */ ?>">🔖 Sauvegardés</a>
            <a href="?tab=following" class="sb-profile-tab <?php echo $tab === 'following' ? 'active' : ''; /* phpcs:ignore */ ?>">⭐ Suivis</a>
                        <a href="?tab=notifications" class="sb-profile-tab <?php echo $tab === 'notifications' ? 'active' : ''; /* phpcs:ignore */ ?>">🔔 <?php esc_html_e('Notifications', 'swiftboard'); ?><?php $sb_unread = swiftboard_get_unread_count($user_id); if ($sb_unread > 0): ?><span class="sb-tab-badge"><?php echo (int) $sb_unread; ?></span><?php endif; ?></a>
			<?php endif; ?>
            <a href="?tab=trophies" class="sb-profile-tab <?php echo $tab === 'trophies' ? 'active' : ''; /* phpcs:ignore */ ?>">🏆 Trophées</a>
			<?php if ( get_current_user_id() === (int) $user_id ) : ?>
            <a href="?tab=compte" class="sb-profile-tab <?php echo $tab === 'compte' ? 'active' : ''; /* phpcs:ignore */ ?>">⚙️ <?php esc_html_e('Compte', 'swiftboard'); ?></a>
            <a href="?tab=edit" class="sb-profile-tab <?php echo $tab === 'edit' ? 'active' : ''; /* phpcs:ignore */ ?>">✏️ <?php esc_html_e('Modifier', 'swiftboard'); ?></a>
			<?php endif; ?>
		</nav>

		<?php
		// V2 restauration - Barre progression réputation (était dans admin-settings-grades mais jamais affichée car reddit-profile fait exit)
		if ( function_exists( 'swiftboard_render_reputation_progress' ) ) {
			swiftboard_render_reputation_progress( $user_id );
		}
		?>

		<?php do_action( 'bbp_template_after_user_details' ); ?>

		<!-- Content -->
		<div class="sb-profile-content">
			<?php
			switch ( $tab ) {
				case 'posts':
					swiftboard_profile_render_posts( $user_id );
					break;
				case 'comments':
					swiftboard_profile_render_comments( $user_id );
					break;
				case 'saved':
					if ( get_current_user_id() === $user_id ) {
						swiftboard_profile_render_saved( $user_id );
					} else {
						echo '<p style="text-align:center;color:var(--color-text-muted);padding:40px;">' . esc_html__( 'Section privée.', 'swiftboard' ) . '</p>';
					}
					break;
				case 'following':
					if ( get_current_user_id() === $user_id ) {
						swiftboard_profile_render_following( $user_id );
					} else {
						echo '<p style="text-align:center;color:var(--color-text-muted);padding:40px;">' . esc_html__( 'Section privée.', 'swiftboard' ) . '</p>';
					}
					break;
				case 'notifications':
					if ( get_current_user_id() === (int) $user_id ) {
						swiftboard_profile_render_notifications( $user_id );
					}
					break;
				case 'compte':
					// Reglages du compte, dont la suppression RGPD.
					// Le theme remplace INTEGRALEMENT le rendu de profil de
					// bbPress (template_redirect + exit) : les hooks du
					// gabarit `form-user-edit.php` ne sont jamais atteints.
					// C'est ici, et nulle part ailleurs, que le formulaire de
					// suppression peut etre rendu.
					if ( get_current_user_id() === (int) $user_id
						&& function_exists( 'swiftboard_formulaire_suppression_compte' ) ) {
						swiftboard_formulaire_suppression_compte();
					}
					break;
				case 'trophies':
					swiftboard_profile_render_trophies( $user_id, $rep, $topics_count, $replies_count, $weekly_rank );
					break;
				case 'edit':
					if ( get_current_user_id() === (int) $user_id || current_user_can( 'edit_user', $user_id ) ) {
						bbp_get_template_part( 'form', 'user-edit' );
					}
					break;
				default:
					swiftboard_profile_render_overview( $user_id );
			}
			?>
		</div>
	</div>
	<?php
}








// ============================================================================
// 4. CSS
// ============================================================================
add_action(
	'wp_enqueue_scripts',
	function () {
	},
	30
);

