<?php
/**
 * SwiftBoard — Navigation laterale (parite Reddit).
 *
 * Colonne persistante : Accueil, Populaires, Nouveautes, Explorer, puis la
 * liste des communautes. Rendue par la page d'accueil ET par les pages de
 * forum, d'ou la mise en commun ici plutot qu'une duplication du balisage.
 *
 * Sur mobile elle devient un rail horizontal defilable (reddit-refonte.css) :
 * elle n'est jamais masquee, seulement reagencee.
 *
 * @package SwiftBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Affiche la navigation laterale.
 *
 * @param int $forum_actif Identifiant du forum courant, pour marquer l'entree
 *                         active dans la liste des communautes. 0 si aucun.
 * @return void
 */
function swiftboard_render_nav_laterale( $forum_actif = 0 ) {
	// Visiteur anonyme : Reddit remplace la navigation par une carte
	// d'inscription (Google / Apple / e-mail). C'est la principale action de
	// conversion, et elle occupe toute la colonne de gauche.
	if ( ! is_user_logged_in() ) {
		swiftboard_render_carte_inscription();
		return;
	}

	$forum_actif = (int) $forum_actif;
	$forum_url   = function_exists( 'bbp_get_forums_url' )
		? bbp_get_forums_url()
		: home_url( '/?post_type=forum' );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- simple lecture d'un parametre de tri public.
	$tri     = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : '';
	$accueil = is_front_page() || is_home();

	$liens = array(
		array(
			'url'   => home_url( '/' ),
			'icone' => 'home',
			'label' => __( 'Accueil', 'swiftboard' ),
			'actif' => ( $accueil && '' === $tri ),
		),
		array(
			'url'   => add_query_arg( 'sort', 'hot', home_url( '/' ) ),
			'icone' => 'popular',
			'label' => __( 'Populaires', 'swiftboard' ),
			'actif' => ( $accueil && 'hot' === $tri ),
		),
		array(
			'url'   => add_query_arg( 'sort', 'new', home_url( '/' ) ),
			'icone' => 'new',
			'label' => __( 'Nouveautés', 'swiftboard' ),
			'actif' => ( $accueil && 'new' === $tri ),
		),
		array(
			'url'   => $forum_url,
			'icone' => 'explore',
			'label' => __( 'Explorer', 'swiftboard' ),
			'actif' => false,
		),
	);

	echo '<nav class="sb-r-nav" aria-label="' . esc_attr__( 'Navigation secondaire', 'swiftboard' ) . '">';
	echo '<ul class="sb-r-nav-list">';
	foreach ( $liens as $lien ) {
		printf(
			'<li><a class="sb-r-nav-link" href="%1$s"%2$s><span class="sb-r-nav-icon" aria-hidden="true">%3$s</span>%4$s</a></li>',
			esc_url( $lien['url'] ),
			$lien['actif'] ? ' aria-current="page"' : '',
			swiftboard_icon( $lien['icone'], 20 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique.
			esc_html( $lien['label'] )
		);
	}
	echo '</ul>';

	$forums = function_exists( 'bbp_get_forum_post_type' )
		? get_posts(
			array(
				'post_type'        => bbp_get_forum_post_type(),
				'posts_per_page'   => 8,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		)
		: array();

	if ( ! empty( $forums ) ) {
		echo '<hr class="sb-r-nav-sep">';
		echo '<div class="sb-r-nav-heading">' . esc_html__( 'Communautés', 'swiftboard' ) . '</div>';
		echo '<ul class="sb-r-nav-list">';
		foreach ( $forums as $forum ) {
			printf(
				'<li><a class="sb-r-nav-link" href="%1$s"%2$s><span class="sb-r-nav-prefix" aria-hidden="true">r/</span>%3$s</a></li>',
				esc_url( get_permalink( $forum->ID ) ),
				( $forum_actif === (int) $forum->ID ) ? ' aria-current="page"' : '',
				esc_html( $forum->post_title )
			);
		}
		echo '</ul>';
	}

	echo '</nav>';
}

/**
 * Carte d'inscription affichee aux visiteurs anonymes.
 *
 * Reprend la composition de Reddit : accroche, fournisseurs OAuth, adresse
 * e-mail, mention legale. Les fournisseurs ne sont proposes que s'ils sont
 * reellement configures — un bouton « Continuer avec Google » qui mene a une
 * erreur serait pire que son absence.
 *
 * @return void
 */
function swiftboard_render_carte_inscription() {
	$reglages = (array) get_option( 'swiftboard_social_settings', array() );
	$ouverte  = (bool) get_option( 'users_can_register' );
	$url_insc = $ouverte ? wp_registration_url() : wp_login_url();

	$fournisseurs = array();
	if ( ! empty( $reglages['google_client_id'] ) ) {
		$fournisseurs[] = array( 'cle' => 'google', 'label' => __( 'Continuer avec Google', 'swiftboard' ) );
	}
	if ( ! empty( $reglages['github_client_id'] ) ) {
		$fournisseurs[] = array( 'cle' => 'github', 'label' => __( 'Continuer avec GitHub', 'swiftboard' ) );
	}
	if ( ! empty( $reglages['facebook_app_id'] ) ) {
		$fournisseurs[] = array( 'cle' => 'facebook', 'label' => __( 'Continuer avec Facebook', 'swiftboard' ) );
	}
	?>
	<aside class="sb-r-signup-card" aria-labelledby="sb-signup-title">
		<h2 class="sb-r-signup-title" id="sb-signup-title">
			<?php esc_html_e( 'Rejoins la communauté et participe aux discussions', 'swiftboard' ); ?>
		</h2>

		<?php foreach ( $fournisseurs as $f ) : ?>
			<a class="sb-r-signup-btn" href="<?php echo esc_url( $url_insc ); ?>" data-provider="<?php echo esc_attr( $f['cle'] ); ?>">
				<span class="sb-r-signup-mark" aria-hidden="true"><?php echo esc_html( strtoupper( substr( $f['cle'], 0, 1 ) ) ); ?></span>
				<?php echo esc_html( $f['label'] ); ?>
			</a>
		<?php endforeach; ?>

		<a class="sb-r-signup-btn" href="<?php echo esc_url( $url_insc ); ?>" data-open-onboarding="true">
			<span class="sb-r-signup-mark" aria-hidden="true">@</span>
			<?php esc_html_e( 'Utiliser une adresse e-mail', 'swiftboard' ); ?>
		</a>

		<a class="sb-r-signup-login" href="<?php echo esc_url( wp_login_url() ); ?>">
			<?php esc_html_e( 'J’ai déjà un compte', 'swiftboard' ); ?>
		</a>

		<p class="sb-r-signup-legal">
			<?php esc_html_e( 'En continuant, tu acceptes nos conditions d’utilisation et notre politique de confidentialité.', 'swiftboard' ); ?>
		</p>
	</aside>
	<?php
}
