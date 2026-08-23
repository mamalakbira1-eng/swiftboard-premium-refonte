<?php
if ( ! defined( 'ABSPATH' )) exit;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped — admin context with intentional HTML
/**
 * SwiftBoard - Layout Reddit-like
 *
 * Transforme l'affichage par défaut de bbPress en cards façon Reddit :
 *  - Colonne de vote verticale à gauche (▲ score ▼)
 *  - Métas en haut (forum, auteur, grade, temps, flair)
 *  - Titre + contenu + image + actions (commenter, partager, sauvegarder, cacher)
 *  - Card / Compact view toggle
 *
 * Hooks utilisés :
 *  - bbp_template_before_topics_loop : injecte la sort-bar
 *  - bbp_theme_template_prefix      : override loop-single-topic
 *  - wp_enqueue_scripts             : injecte le JS + CSS spécifique
 *
 * @package SwiftBoard
 * @since 3.0.0
 */

// ============================================================================
// 1. OVERRIDE TEMPLATE loop-single-topic
// ============================================================================
/**
 * Force SwiftBoard à utiliser son propre template pour les topics dans la loop.
 * On garde les templates originaux de /bbpress/ mais on override loop-single-topic.
 */
add_filter(
	'bbp_get_template_part',
	function ( $templates, $slug, $name ) {
		if ( $slug === 'loop' && $name === 'single-topic' ) {
			// bbp_locate_template() attend des noms RELATIFS : il fait
			// ltrim($nom, '/')  puis  trailingslashit($dossier) . $nom
			// sur chaque dossier de la pile de templates. Un chemin ABSOLU donne
			// « <theme>/home/user/... », introuvable -> aucun template charge.
			if ( file_exists( SWIFTBOARD_DIR . '/bbpress/loop-single-topic-reddit.php' ) ) {
				array_unshift( $templates, 'loop-single-topic-reddit.php' );
			}
		}
		return $templates;
	},
	10,
	3
);

// ============================================================================
// 2. INJECTER LA SORT-BAR AVANT LA LOOP
// ============================================================================
// NOTE: This hook adds the sort bar HTML. feed-sort.php also hooks this for cache metas.
// Both are needed — they do different things.
add_action(
	'bbp_template_before_topics_loop',
	function () {
		$current_sort   = isset( $_GET['sort'] ) ? sanitize_text_field( wp_unslash( $_GET['sort'] ) ) : 'hot';
		$current_period = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : 'all';
		$view           = isset( $_COOKIE['sb_view'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['sb_view'] ) ) : 'card';
		?>
		<?php
		// v5.3 (EXI-SORT-02) : libelles FR, aria-current sur l'onglet actif, et
		// filtre periode reserve a Hot/Top (pas de « 24h » sur Nouveau, qui est
		// un simple tri chronologique — comme Reddit).
		$sb_sort_labels = array(
			'hot'    => '🔥 ' . __( 'Hot', 'swiftboard' ),
			'new'    => '🆕 ' . __( 'Nouveau', 'swiftboard' ),
			'top'    => '📈 ' . __( 'Top', 'swiftboard' ),
			'rising' => '🚀 ' . __( 'Rising', 'swiftboard' ),
		);
		?>
	<div class="sb-sort-bar" data-view="<?php echo esc_attr( $view ); ?>">
		<div class="sb-sort-tabs" role="navigation" aria-label="<?php esc_attr_e( 'Trier les sujets', 'swiftboard' ); ?>">
			<?php foreach ( $sb_sort_labels as $sb_s => $sb_label ) : ?>
			<a href="?sort=<?php echo esc_attr( $sb_s ); ?>&period=<?php echo esc_attr( $current_period ); ?>"
				class="sb-sort-tab <?php echo $current_sort === $sb_s ? 'active' : ''; ?>"
				<?php echo $current_sort === $sb_s ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $sb_label ); ?></a>
			<?php endforeach; ?>
			<?php
			// v5.3.1 : filtre periode (« 7 jours ») retire a la demande produit —
			// la barre reste identique partout : Hot / Nouveau / Top / Rising.
			// Le tri applique la periode par defaut (7d) en arriere-plan.
			?>
		</div>
		<div class="sb-view-toggle">
            <button type="button" class="<?php echo $view === 'card' ? 'active' : ''; /* phpcs:ignore */ ?>" data-view="card">⛶ Card</button>
            <button type="button" class="<?php echo $view === 'compact' ? 'active' : ''; /* phpcs:ignore */ ?>" data-view="compact">≡ Compact</button>
		</div>
	</div>
		<?php
	}
);

// ============================================================================
// 3. RENDU D'UNE CARD TOPIC — Helper réutilisable
// ============================================================================
/**
 * Affiche une card topic façon Reddit.
 * À appeler dans loop-single-topic-reddit.php.
 *
 * @return void
 */
function swiftboard_reddit_topic_card() {
	$topic_id    = bbp_get_topic_id();
	$author_id   = bbp_get_topic_author_id( $topic_id );
	$forum_id    = bbp_get_topic_forum_id( $topic_id );
	$votes       = swiftboard_get_vote_count( $topic_id );
	$reply_count = bbp_get_topic_reply_count( $topic_id, true );
	$voice_count = bbp_get_topic_voice_count( $topic_id );
	$forum_name  = bbp_get_forum_title( $forum_id );
	$forum_url   = bbp_get_forum_permalink( $forum_id );
	$grade       = swiftboard_get_user_grade( (int) $author_id );
	$grades      = swiftboard_get_grades();
	$grade_info  = $grades[ $grade ] ?? null;

	// Flair du topic (terme de taxonomie bbp_topic_tag, ou meta)
	$flair       = get_post_meta( $topic_id, '_swiftboard_flair', true );
	$flair_class = $flair ? 'sb-flair-' . sanitize_title( $flair ) : '';

	// Image attachée (première image du topic)
	$has_image = get_post_meta( $topic_id, '_swiftboard_has_image', true );
	$image_url = get_post_meta( $topic_id, '_swiftboard_image_url', true );

	// Vote de l'utilisateur courant
	$my_vote = null;
	if ( function_exists( 'swiftboard_get_my_vote' ) && is_user_logged_in() ) {
		$my_vote = swiftboard_get_my_vote( $topic_id );
	}
	?>
	<article class="sb-post-card <?php echo $flair_class; ?>" id="topic-<?php echo esc_attr( (string) $topic_id ); ?>" data-post-id="<?php echo esc_attr( (string) $topic_id ); ?>">
		<div class="sb-post-votes">
			<button class="sb-vote-btn up <?php echo $my_vote === 'up' ? 'active' : ''; ?>" data-post-id="<?php echo esc_attr( (string) $topic_id ); ?>" data-vote="up" aria-label="Upvoter">
				<svg class="sb-icon" width="18" height="18" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2.75 17.5 10.25H14V17.5H6V10.25H2.5L10 2.75z"/></svg>
			</button>
			<span class="sb-vote-count <?php echo $my_vote === 'up' ? 'up' : ( $my_vote === 'down' ? 'down' : '' ); ?>"><?php echo esc_html( swiftboard_format_count( $votes ) ); ?></span>
			<button class="sb-vote-btn down <?php echo $my_vote === 'down' ? 'active' : ''; ?>" data-post-id="<?php echo esc_attr( (string) $topic_id ); ?>" data-vote="down" aria-label="Downvoter">
				<svg class="sb-icon" width="18" height="18" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 17.25 2.5 9.75H6V2.5h8v7.25h3.5L10 17.25z"/></svg>
			</button>
		</div>
		<div class="sb-post-body">
			<div class="sb-post-meta-top">
				<a href="<?php echo esc_url( $forum_url ); ?>" class="sb-forum-pill">
                                    r/<?php echo esc_html( $forum_name ); ?>
				</a>
				<span class="sb-meta-sep">·</span>
				<?php
				swiftboard_render_author_line( (int) $author_id, bbp_get_topic_author_display_name( $topic_id ) );
				if ( $grade_info ) {
					printf(
						'<span class="sb-grade-badge" style="background:%s;">%s %s</span>',
						esc_attr( $grade_info['color'] ),
						swiftboard_grade_insignia_svg( swiftboard_get_user_grade( (int) $author_id ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique.
						esc_html( $grade_info['name'] )
					);
				}
				?>
				<span class="sb-meta-sep">·</span>
				<span class="sb-post-time"><?php echo esc_html( swiftboard_time_ago( strtotime( bbp_get_topic_post_date( $topic_id ) ) ) ); ?></span>
				<?php if ( $flair ) : ?>
				<span class="sb-post-flair sb-flair-<?php echo esc_attr( sanitize_title( $flair ) ); ?>"><?php echo esc_html( $flair ); ?></span>
				<?php endif; ?>
				<?php
				// Bouton « Rejoindre » : voir inc/join-button.php.
				swiftboard_render_join_button( bbp_get_topic_forum_id( $topic_id ) );
				?>
			</div>
			<h2 class="sb-post-title">
				<a href="<?php echo esc_url( bbp_get_topic_permalink( $topic_id ) ); ?>"><?php echo bbp_get_topic_title( $topic_id ); ?></a>
			</h2>
			<div class="sb-post-content">
				<?php
				$content = wp_strip_all_tags( bbp_get_topic_content( $topic_id ) );
				echo esc_html( wp_trim_words( $content, 35, '…' ) );
				?>
			</div>
			<?php if ( $has_image && $image_url ) : ?>
			<a href="<?php echo esc_url( bbp_get_topic_permalink( $topic_id ) ); ?>" class="sb-post-image-link">
				<img class="sb-post-image" src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( bbp_get_topic_title( $topic_id ) ); ?>" loading="lazy">
			</a>
			<?php endif; ?>
			<?php
			// Zone d'actions produite par UNE seule fonction
			// (inc/ui-corrections.php), partagee avec front-page.php.
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML construit et echappe dans la fonction.
			echo swiftboard_actions_carte_html(
				(int) $topic_id,
				bbp_get_topic_permalink( $topic_id ),
				(int) $reply_count
			);
			?>
		</div>
	</article>
	<?php
}

// ============================================================================
// 4. TEMPLATE loop-single-topic-reddit.php — VERSIONNE
// ============================================================================
// Le template n'est plus genere a l'execution. Il etait ecrit sur le disque
// par swiftboard_ensure_reddit_template() sur `admin_init` : absent apres un
// deploiement tant que personne n'ouvrait l'admin, impossible a creer sur un
// hebergement en lecture seule ou avec DISALLOW_FILE_MODS, et jamais present
// pour un visiteur anonyme sur une instance fraiche. Le forum retombait alors
// silencieusement sur le rendu bbPress par defaut.
// Le fichier vit desormais dans bbpress/loop-single-topic-reddit.php.


// ============================================================================
// 5. JAVASCRIPT — Toggle view + actions
// ============================================================================
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_script(
			'swiftboard-reddit-actions',
			SWIFTBOARD_URI . '/assets/js/reddit-actions.js',
			array( 'swiftboard-main' ),
			defined( 'SWIFTBOARD_VERSION' ) ? SWIFTBOARD_VERSION : null,
			true
		);
	},
	30
);

// ============================================================================
// 6. CSS — Styles des cards Reddit
// ============================================================================
add_action(
	'wp_enqueue_scripts',
	function () {
	},
	30
);

