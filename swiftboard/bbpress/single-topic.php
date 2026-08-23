<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Template override : sujet unique (sujet + reponses).
 *
 * Mise en page trois colonnes, alignee sur la page de communaute et la page
 * d'accueil : navigation (ou carte d'inscription) a gauche, fil au centre,
 * informations de la communaute a droite. Le template etait auparavant rendu
 * en pleine largeur, ce qui en faisait la seule page du site sans reperes
 * lateraux.
 *
 * @package SwiftBoard
 */

get_header();

$sb_topic_id = bbp_get_topic_id();
$sb_forum_id = (int) bbp_get_topic_forum_id( $sb_topic_id );
?>

<div class="sb-home sb-topic-page">
	<div class="sb-home-container">

		<?php
		// Colonne gauche : navigation pour un membre, appel a l'inscription
		// pour un visiteur (voir inc/nav-lateral.php).
		swiftboard_render_nav_laterale( $sb_forum_id );
		?>

		<main id="primary" class="sb-home-main site-main" role="main" aria-label="<?php esc_attr_e( 'Sujet', 'swiftboard' ); ?>">

			<div class="sb-topic-back">
				<a class="sb-back-btn" href="<?php echo esc_url( get_permalink( $sb_forum_id ) ); ?>"
				   aria-label="<?php esc_attr_e( 'Retour à la communauté', 'swiftboard' ); ?>">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
					     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M15 18l-6-6 6-6"/>
					</svg>
				</a>
				<a class="sb-topic-back-label" href="<?php echo esc_url( get_permalink( $sb_forum_id ) ); ?>">
					<span class="sb-u-prefix">r/</span><?php echo esc_html( get_the_title( $sb_forum_id ) ); ?>
				</a>
			</div>

			<div id="bbpress-forum" class="bbpress-topic-single">
				<?php bbp_get_template_part( 'content', 'single-topic' ); ?>
			</div>
		</main>

		<aside class="sb-home-sidebar">
			<?php
			// Meme carte « A propos » que la page de communaute : un lecteur
			// arrive souvent sur un sujet sans etre passe par le forum.
			$sb_desc    = get_post_field( 'post_content', $sb_forum_id );
			$sb_topics  = function_exists( 'bbp_get_forum_topic_count' ) ? bbp_get_forum_topic_count( $sb_forum_id ) : 0;
			$sb_replies = function_exists( 'bbp_get_forum_reply_count' ) ? bbp_get_forum_reply_count( $sb_forum_id ) : 0;
			?>
			<div class="sb-sidebar-card sb-about-card">
				<div class="sb-sidebar-card-body">
					<h2 class="sb-about-title">
						<span class="sb-u-prefix">r/</span><?php echo esc_html( get_the_title( $sb_forum_id ) ); ?>
					</h2>
					<?php if ( $sb_desc ) : ?>
						<div class="sb-about-desc"><?php echo wp_kses_post( wp_trim_words( $sb_desc, 30, '…' ) ); ?></div>
					<?php endif; ?>

					<div class="sb-about-stats">
						<div class="sb-about-stat">
							<strong><?php echo esc_html( swiftboard_format_count( $sb_topics ) ); ?></strong>
							<span><?php esc_html_e( 'Sujets', 'swiftboard' ); ?></span>
						</div>
						<div class="sb-about-stat">
							<strong><?php echo esc_html( swiftboard_format_count( $sb_replies ) ); ?></strong>
							<span><?php esc_html_e( 'Réponses', 'swiftboard' ); ?></span>
						</div>
					</div>

					<a class="sb-r-chip" href="<?php echo esc_url( get_permalink( $sb_forum_id ) ); ?>">
						<?php esc_html_e( 'Voir la communauté', 'swiftboard' ); ?>
					</a>
				</div>
			</div>

			<?php
			// Regles du forum, saisies via la metabox (voir inc/forum-rules.php).
			$sb_regles_brut = (string) get_post_meta( $sb_forum_id, '_swiftboard_forum_rules', true );
			$sb_regles      = array_values( array_filter( array_map( 'trim', explode( "\n", $sb_regles_brut ) ) ) );
			if ( ! empty( $sb_regles ) ) :
				?>
				<div class="sb-sidebar-card">
					<div class="sb-sidebar-card-header"><?php esc_html_e( 'Règles de la communauté', 'swiftboard' ); ?></div>
					<div class="sb-sidebar-card-body">
						<ol class="sb-about-rules">
							<?php foreach ( $sb_regles as $sb_i => $sb_regle ) : ?>
								<li><span class="sb-rule-num"><?php echo (int) ( $sb_i + 1 ); ?></span><span><?php echo esc_html( $sb_regle ); ?></span></li>
							<?php endforeach; ?>
						</ol>
					</div>
				</div>
			<?php endif; ?>

			<?php
			// Publications en lien : autres sujets de la meme communaute.
			$sb_lies = get_posts(
				array(
					'post_type'        => bbp_get_topic_post_type(),
					'post_parent'      => $sb_forum_id,
					'posts_per_page'   => 5,
					'post__not_in'     => array( $sb_topic_id ),
					'suppress_filters' => false,
				)
			);
			if ( ! empty( $sb_lies ) ) :
				?>
				<div class="sb-sidebar-card">
					<div class="sb-sidebar-card-header"><?php esc_html_e( 'Publications en lien', 'swiftboard' ); ?></div>
					<div class="sb-sidebar-card-body">
						<?php foreach ( $sb_lies as $sb_lie ) : ?>
							<a class="sb-sidebar-hot-item" href="<?php echo esc_url( get_permalink( $sb_lie->ID ) ); ?>">
								<span>
									<span class="sb-sidebar-hot-title"><?php echo esc_html( $sb_lie->post_title ); ?></span>
									<span class="sb-sidebar-hot-meta">
										<?php echo (int) bbp_get_topic_reply_count( $sb_lie->ID, true ); ?>
										<?php esc_html_e( 'commentaires', 'swiftboard' ); ?>
									</span>
								</span>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</aside>

	</div>
</div>

<?php
get_footer();
