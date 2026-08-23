<?php
if ( ! defined( 'ABSPATH' )) exit;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped — admin context with intentional HTML
/**
 * SwiftBoard - Commentaires imbriqués façon Reddit
 *
 * Override le template loop-single-reply pour afficher les réponses en
 * cascade (threading) avec :
 *  - Indentation par niveau (max 8 niveaux, ensuite flatten)
 *  - Barre verticale à gauche pour collapse/expand
 *  - Vote pill (▲ count ▼) sur chaque commentaire
 *  - Tri : Best / Top / New / Controversial / Old
 *  - Lazy loading "Charger plus de réponses"
 *
 * @package SwiftBoard
 * @since 3.2.0
 */
// ============================================================================
// 1. OVERRIDE TEMPLATE loop-single-reply
// ============================================================================
add_filter(
	'bbp_get_template_part',
	function ( $templates, $slug, $name ) {
		if ( $slug === 'loop' && $name === 'single-reply' ) {
			// ATTENTION : bbp_locate_template() attend des noms RELATIFS, qu'il
			// resout ensuite contre chaque dossier de la pile de templates
			// (bbp_get_template_stack) via :
			// ltrim($nom, '/')  puis  trailingslashit($dossier) . $nom
			// Un chemin ABSOLU devient donc « <theme>/home/user/... », introuvable :
			// bbPress ne charge alors AUCUN template et la reponse n'est pas rendue.
			// Bug constate : 3 iterations de boucle, 0 bloc .sb-comment en sortie.
			if ( file_exists( SWIFTBOARD_DIR . '/bbpress/loop-single-reply-reddit.php' ) ) {
				// On place notre variante en tete, en conservant les valeurs par
				// defaut en repli si le fichier venait a disparaitre.
				array_unshift( $templates, 'loop-single-reply-reddit.php' );
			}
		}
		return $templates;
	},
	10,
	3
);

// ============================================================================
// 2. RENDU D'UN COMMENTAIRE REDDIT-LIKE
// ============================================================================
/**
 * Affiche une réponse façon Reddit avec vote pill + collapse bar.
 *
 * @param int $reply_id Identifiant de la réponse. Optionnel.
 * @return void
 */
function swiftboard_reddit_reply( $reply_id = 0 ) {
	static $menu_instance = 0;
	$menu_instance++;
	$reply_menu_suffix = '-' . (string) $menu_instance;
	$reply_id = $reply_id ?: bbp_get_reply_id();
	if ( ! $reply_id) return;

	$author_id       = bbp_get_reply_author_id( $reply_id );
	$topic_id        = bbp_get_reply_topic_id( $reply_id );
	$topic_author_id = bbp_get_topic_author_id( $topic_id );
	$is_op           = ( $author_id === $topic_author_id );
	$votes           = swiftboard_get_vote_count( $reply_id );
	$grade           = swiftboard_get_user_grade( (int) $author_id );
	$grades          = swiftboard_get_grades();
	$grade_info      = $grades[ $grade ] ?? null;

	// V2 restauration - Best Answer
	$best_answer_id = function_exists( 'swiftboard_get_best_answer_id' ) ? swiftboard_get_best_answer_id( $topic_id ) : 0;
	$is_best_answer = $best_answer_id && (int) $reply_id === (int) $best_answer_id;

	// V2 restauration - Badges custom
	$custom_badges = function_exists( 'swiftboard_get_user_badges' ) ? swiftboard_get_user_badges( (int) $author_id ) : array();

	// Vote courant de l'utilisateur
	$my_vote = null;
	if ( function_exists( 'swiftboard_get_my_vote' ) && is_user_logged_in() ) {
		$my_vote = swiftboard_get_my_vote( $reply_id );
	}

	// Profondeur = reply_to
	$reply_to = (int) get_post_meta( $reply_id, '_bbp_reply_to', true );
	$depth    = $reply_to ? 2 : 1;  // simplifié : niveau 1 ou 2
	// On calcule la vraie profondeur en remontant
	if ( $reply_to ) {
		$current   = $reply_to;
		$depth     = 2;
		$max_depth = 8;
		while ( $current > 0 && $depth < $max_depth ) {
			$parent = (int) get_post_meta( $current, '_bbp_reply_to', true );
			if ( ! $parent) break;
			++$depth;
			$current = $parent;
		}
	}
	$depth       = min( $depth, 8 );  // max 8 niveaux ensuite flatten
	$margin_left = ( $depth - 1 ) * 24;
	?>
	<?php
	// <article> et non <div> : chaque reponse est un contenu autonome. Google
	// recommande le HTML semantique pour les agents navigateurs, qui lisent le
	// DOM et l'arbre d'accessibilite plutot que le JSON-LD.
	// Les microdonnees Schema.org (Comment) doublent le JSON-LD directement
	// dans le markup, ce qui reste lisible meme pour un agent qui n'execute
	// aucun script.
	$sb_vote = function_exists( 'swiftboard_get_vote_breakdown' )
		? swiftboard_get_vote_breakdown( $reply_id )
		: array(
			'up'   => 0,
			'down' => 0,
		);
	?>
	<?php $best_class = $is_best_answer ? ' sb-best-answer' : ''; ?>
	<article class="sb-comment<?php echo esc_attr( $best_class ); ?>" id="reply-<?php echo esc_attr( (string) $reply_id ); ?>" data-reply-id="<?php echo esc_attr( (string) $reply_id ); ?>" data-depth="<?php echo esc_attr( (string) $depth ); ?>" style="margin-left:<?php echo (int) $margin_left; ?>px;<?php echo $is_best_answer ? 'border-left-color:var(--color-success);background:var(--color-success-bg);' : ''; ?>" itemscope itemtype="https://schema.org/Comment" itemprop="comment">
		<meta itemprop="upvoteCount" content="<?php echo (int) $sb_vote['up']; ?>">
		<meta itemprop="downvoteCount" content="<?php echo (int) $sb_vote['down']; ?>">
		<?php
		// EXI-QUAL-06 : onclick= remplace par data-sb-action, ecoute
		// deleguee dans assets/js/main.js. Un gestionnaire inline exige
		// 'unsafe-inline' dans la CSP script-src.
		?>
		<div class="sb-comment-collapse-bar" data-sb-action="collapse" role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Replier le fil', 'swiftboard' ); ?>"></div>
		<div class="sb-comment-head">
			<?php
			// Avatar du forum (Reddit-style) au lieu de Gravatar.
			// alt="" VOLONTAIRE : le nom de l'auteur suit immediatement en
			// texte (lien .sb-comment-author). Un alt renseigne ferait annoncer
			// deux fois le meme nom par un lecteur d'ecran. Image decorative.
			echo swiftboard_get_user_avatar_html( (int) $author_id, 22, 'sb-comment-avatar' );
			?>
			<a href="<?php echo esc_url( bbp_get_user_profile_url( (int) $author_id ) ); ?>" class="sb-comment-author <?php echo $is_op ? 'op' : ''; ?>" itemprop="author" itemscope itemtype="https://schema.org/Person"><span itemprop="name"><?php echo esc_html( bbp_get_reply_author_display_name( $reply_id ) ); ?></span></a>
			<?php if ( $grade_info ) : ?>
			<span class="sb-grade-badge" style="background:<?php echo esc_attr( $grade_info['color'] ); ?>;"><?php echo swiftboard_grade_insignia_svg( swiftboard_get_user_grade( (int) $author_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique genere par le theme. ?> <?php echo esc_html( $grade_info['name'] ); ?></span>
			<?php endif; ?>
			<?php
			// V2 restauration - Badges custom
			if ( ! empty( $custom_badges ) ) :
				foreach ( $custom_badges as $badge ) :
					$badge_icon = is_array( $badge ) ? ( $badge['icon'] ?? '🏆' ) : $badge;
					$badge_name = is_array( $badge ) ? ( $badge['name'] ?? $badge_icon ) : $badge;
					?>
				<span class="sb-custom-badge" title="<?php echo esc_attr( $badge_name ); ?>"><?php echo esc_html( $badge_icon ); ?></span>
					<?php
				endforeach;
			endif;
			?>
			<?php if ( $is_best_answer ) : ?>
				<span class="sb-best-badge" style="background:#047857;color:#fff;padding:2px 8px;border-radius:9999px;font-size:10px;font-weight:700;"><?php echo swiftboard_icon('check',14); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?> <?php esc_html_e( 'Résolu', 'swiftboard' ); ?></span>
			<?php endif; ?>
			<span class="sb-meta-sep">·</span>
			<time class="sb-comment-time" itemprop="dateCreated" datetime="<?php echo esc_attr( get_the_date( 'c', $reply_id ) ); ?>"><?php echo esc_html( swiftboard_time_ago( get_the_date( 'c', $reply_id ) ) ); ?></time>
		</div>
		<div class="sb-comment-content" itemprop="text">
            <?php echo bbp_get_reply_content($reply_id); // phpcs:ignore ?>
		</div>
		<div class="sb-comment-actions">
			<div class="sb-comment-votes">
				<button class="sb-comment-vote-btn up <?php echo $my_vote === 'up' ? 'active' : ''; ?>" data-post-id="<?php echo esc_attr( (string) $reply_id ); ?>" data-vote="up" aria-label="Upvoter">
					<svg class="sb-icon" width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2.75 17.5 10.25H14V17.5H6V10.25H2.5L10 2.75z"/></svg>
				</button>
				<span class="sb-comment-vote-count <?php echo $my_vote === 'up' ? 'up' : ( $my_vote === 'down' ? 'down' : '' ); ?>"><?php echo esc_html( swiftboard_format_count( $votes ) ); ?></span>
				<button class="sb-comment-vote-btn down <?php echo $my_vote === 'down' ? 'active' : ''; ?>" data-post-id="<?php echo esc_attr( (string) $reply_id ); ?>" data-vote="down" aria-label="Downvoter">
					<svg class="sb-icon" width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 17.25 2.5 9.75H6V2.5h8v7.25h3.5L10 17.25z"/></svg>
				</button>
			</div>
			<button class="sb-comment-action" data-sb-action="reply-open" aria-label="<?php esc_attr_e( 'Répondre', 'swiftboard' ); ?>" title="<?php esc_attr_e( 'Répondre', 'swiftboard' ); ?>">
				<svg class="sb-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
			</button>
			<button class="sb-comment-action sb-action-share" data-url="<?php echo esc_attr( bbp_get_reply_url( $reply_id ) ); ?>" aria-label="<?php esc_attr_e( 'Partager', 'swiftboard' ); ?>" title="<?php esc_attr_e( 'Partager', 'swiftboard' ); ?>">
				<svg class="sb-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
			</button>
			<?php
			// EXI-MENU-01 (v5.3.3) : « Sauvegarder » rejoint le menu
			// overflow « ⋯ » <= 640px ; il reste visible en ligne sur desktop.
			// Meme element dans les deux cas : l'etat serveur (etalon a11y) et
			// les gestionnaires JS (data-post-id) restent intacts.
			?>
			<span class="sb-actions-overflow">
				<button type="button" id="sb-more-toggle-reply-<?php echo esc_attr( (string) $reply_id . $reply_menu_suffix ); ?>" class="sb-comment-action sb-action-more sb-more-toggle"
					aria-haspopup="menu" aria-expanded="false"
					
					aria-label="<?php esc_attr_e( 'Plus d’actions', 'swiftboard' ); ?>" title="<?php esc_attr_e( 'Plus d’actions', 'swiftboard' ); ?>">
					<svg class="sb-icon" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
				</button>
				<span class="sb-more-menu" id="sb-more-menu-reply-<?php echo esc_attr( (string) $reply_id . $reply_menu_suffix ); ?>" role="menu" aria-labelledby="sb-more-toggle-reply-<?php echo esc_attr( (string) $reply_id . $reply_menu_suffix ); ?>">
					<button role="menuitemcheckbox" class="sb-comment-action sb-action-save" data-post-id="<?php echo esc_attr( (string) $reply_id ); ?>" aria-checked="false" aria-label="<?php esc_attr_e( 'Sauvegarder', 'swiftboard' ); ?>" title="<?php esc_attr_e( 'Sauvegarder', 'swiftboard' ); ?>">
						<svg class="sb-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
					</button>
				</span>
			</span>
			<?php
			/*
			 * EXI-BBP-02 — liens de moderation bbPress.
			 *
			 * bbPress rend ces liens dans loop-single-reply.php et
			 * content-single-topic-lead.php. Le rendu Reddit du theme remplace
			 * ces gabarits : sans cet appel, un moderateur ou un keymaster
			 * n'avait AUCUN moyen de fermer, editer, marquer en spam ou
			 * supprimer un contenu depuis le front. Verifie sur WordPress
			 * reel : 0 lien rendu pour un compte keymaster.
			 *
			 * bbp_reply_admin_links() n'affiche rien pour un utilisateur sans
			 * droit de moderation : aucune fuite pour les visiteurs.
			 */
			// V2 restauration - Bouton ✔ Résolu pour auteur/admin
			if ( is_user_logged_in() && function_exists( 'swiftboard_can_solve_topic_callback' ) ) {
				$can_solve = false;
				$topic     = get_post( $topic_id );
				if ( $topic ) {
					$uid       = get_current_user_id();
					$can_solve = ( $uid === (int) $topic->post_author ) || current_user_can( 'moderate_comments' );
				}
				if ( $can_solve ) {
					$solve_nonce = wp_create_nonce( 'wp_rest' );
					$is_solved   = $is_best_answer;
					?>
					<button type="button" class="sb-comment-action sb-action-solve<?php echo $is_solved ? ' active' : ''; ?>" data-topic-id="<?php echo esc_attr( (string) $topic_id ); ?>" data-reply-id="<?php echo esc_attr( (string) $reply_id ); ?>" data-nonce="<?php echo esc_attr( $solve_nonce ); ?>" aria-label="<?php esc_attr_e( 'Marquer comme résolu', 'swiftboard' ); ?>" title="<?php echo $is_solved ? esc_attr__( 'Retirer résolu', 'swiftboard' ) : esc_attr__( 'Marquer comme résolu', 'swiftboard' ); ?>">
						<svg class="sb-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
					</button>
					<?php
				}
			}
			if ( function_exists( 'bbp_reply_admin_links' ) ) {
				bbp_reply_admin_links(
					array(
						'before' => '<span class="sb-comment-action sb-mod-links">',
						'after'  => '</span>',
						'sep'    => ' · ',
						'id'     => $reply_id,
					)
				);
			}
			?>
		</div>
		<div class="sb-comment-reply-form" style="display:none;">
			<textarea placeholder="Votre réponse…" class="sb-comment-textarea" aria-label="<?php esc_attr_e('Votre réponse', 'swiftboard'); ?>"></textarea>
			<div style="text-align:right;margin-top:6px;">
				<button class="sb-comment-action" data-sb-action="reply-cancel"><?php esc_html_e( 'Annuler', 'swiftboard' ); ?></button>
				<button class="sb-comment-action sb-comment-submit"><?php esc_html_e( 'Commenter', 'swiftboard' ); ?></button>
			</div>
		</div>
	</article>
	<?php
}

// ============================================================================
// 3. TEMPLATE loop-single-reply-reddit.php — VERSIONNE
// ============================================================================
// Plus de generation a l'execution : cf. bbpress/loop-single-reply-reddit.php.
// L'ecriture disque via `admin_init` echouait en lecture seule et n'avait pas
// encore eu lieu apres un deploiement, faisant retomber le forum sur le rendu
// bbPress par defaut.


// ============================================================================
// 4. HOOK — HEAD DE LA SECTION RÉPONSES
// ============================================================================
/**
 * Injecte la barre de tri des commentaires + le formulaire de compose
 * avant la loop des réponses.
 */
add_action(
	'bbp_template_before_replies_loop',
	function () {
		$sort        = isset( $_GET['csort'] ) ? sanitize_text_field( wp_unslash( $_GET['csort'] ) ) : 'best';
		$allowed     = array( 'best', 'top', 'new', 'controversial', 'old' );
		$sort        = in_array( $sort, $allowed, true ) ? $sort : 'best';
		// bbp_get_topic_reply_count() sans argument s'appuie sur le sujet
		// courant de la boucle. Appelee depuis ce hook, la boucle des reponses
		// est deja engagee et l'ID se perd : le compteur renvoyait 0 alors que
		// les reponses existaient bien en base. On passe l'ID explicitement.
		$sb_topic_id = function_exists( 'bbp_get_topic_id' ) ? bbp_get_topic_id() : get_the_ID();
		if ( ! $sb_topic_id ) {
			$sb_topic_id = get_queried_object_id();
		}
		$reply_count = function_exists( 'bbp_get_topic_reply_count' ) ? (int) bbp_get_topic_reply_count( $sb_topic_id, true ) : 0;
		?>
	<div class="sb-comments-header">
		<span class="sb-comments-count"><?php echo (int) $reply_count; ?> commentaires</span>
		<div class="sb-comment-sort">
			<span style="font-size:11px;color:var(--color-text-muted);margin-right:4px;"><?php esc_html_e( 'Trier par :', 'swiftboard' ); ?></span>
            <a href="?csort=best" class="sb-comment-sort-btn<?php echo $sort === 'best' ? ' active' : ''; /* phpcs:ignore */ ?>"><?php echo swiftboard_icon('popular', 14); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?> Best</a>
            <a href="?csort=top" class="sb-comment-sort-btn<?php echo $sort === 'top' ? ' active' : ''; /* phpcs:ignore */ ?>"><?php echo swiftboard_icon('top', 14); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?> Top</a>
            <a href="?csort=new" class="sb-comment-sort-btn<?php echo $sort === 'new' ? ' active' : ''; /* phpcs:ignore */ ?>"><?php echo swiftboard_icon('new', 14); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?> New</a>
            <a href="?csort=controversial" class="sb-comment-sort-btn<?php echo $sort === 'controversial' ? ' active' : ''; /* phpcs:ignore */ ?>"><?php echo swiftboard_icon('flame', 14); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?> Controversial</a>
            <a href="?csort=old" class="sb-comment-sort-btn<?php echo $sort === 'old' ? ' active' : ''; /* phpcs:ignore */ ?>"><?php echo swiftboard_icon('recent', 14); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?> Old</a>
		</div>
	</div>
		<?php if ( is_user_logged_in() ) : ?>
	<div class="sb-comment-compose">
			<?php
			$current_user = wp_get_current_user();
			$sb_compose_avatar = function_exists( 'swiftboard_get_avatar' ) ? swiftboard_get_avatar( $current_user->ID, 32 ) : '';
			?>
			<?php if ( $sb_compose_avatar ) : ?>
		<?php echo $sb_compose_avatar; // phpcs:ignore ?>
		<?php else : ?>
		<div class="sb-comment-compose-avatar-mock"><?php echo esc_html( strtoupper( substr( $current_user->display_name, 0, 1 ) ) ); ?></div>
		<?php endif; ?>
		<div style="flex:1;">
			<textarea class="sb-comment-compose-input" placeholder="Participer à la discussion…"></textarea>
			<div class="sb-comment-compose-actions">
				<button class="sb-comment-action"><?php esc_html_e( 'Annuler', 'swiftboard' ); ?></button>
				<button class="sb-comment-action sb-comment-submit-primary"><?php esc_html_e( 'Commenter', 'swiftboard' ); ?></button>
			</div>
		</div>
	</div>
	<?php endif; ?>
		<?php
	}
);

// ============================================================================
// 5. TRI DES COMMENTAIRES
// ============================================================================
/**
 * Modifie la requête bbp_has_replies pour appliquer le tri demandé.
 */
add_filter(
	'bbp_has_replies_query',
	function ( $args ) {
		$sort = isset( $_GET['csort'] ) ? sanitize_text_field( wp_unslash( $_GET['csort'] ) ) : 'best';

		// DOUBLON CORRIGE : bbPress ajoute le SUJET lui-meme en tete de la boucle
		// des reponses quand _bbp_include_root vaut 1 (valeur par defaut).
		// Or bbpress/content-single-topic.php affiche deja le message original
		// au-dessus de la liste (« Topic original (premier message) », l.59).
		// Resultat : le premier message s'affichait DEUX FOIS, une fois en tete et
		// une fois comme commentaire #1. Constate en revue visuelle apres
		// activation du rendu Reddit.
		// bbPress (replies/template.php:134) choisit le post_type ainsi :
		// bbp_show_lead_topic() ? [reply] : [topic, reply]
		// Avec la valeur par defaut (false), le SUJET entre donc dans la boucle
		// des reponses. Or content-single-topic.php l'affiche deja en tete
		// (« Topic original (premier message) », l.59) : le premier message
		// apparaissait DEUX FOIS. On restreint la boucle aux seules reponses.
		$args['post_type'] = bbp_get_reply_post_type();

		switch ( $sort ) {
			case 'new':
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
				break;
			case 'old':
				$args['orderby'] = 'date';
				$args['order']   = 'ASC';
				break;
			case 'top':
			case 'best':
				// Best = top score + ratio up/down, simplifie a top.
				swiftboard_trier_par_meta_numerique( $args, '_swiftboard_vote_score' );
				break;
			case 'controversial':
				// Controversial = beaucoup de votes + score proche de 0
				swiftboard_trier_par_meta_numerique( $args, '_swiftboard_vote_up' );
				break;
		}
		return $args;
	}
);


/**
 * Trie une requete par une meta numerique SANS exclure les posts qui ne
 * possedent pas cette meta.
 *
 * DEUX DEFAUTS CORRIGES ICI
 * -------------------------
 * 1. DISPARITION (critique).
 *    `$args['meta_key'] = 'x'` fait generer a WP_Query un INNER JOIN sur
 *    wp_postmeta. Tout contenu depourvu de cette meta est alors ABSENT du
 *    resultat — il n'est pas mal trie, il disparait.
 *    Or `_swiftboard_vote_score` n'est ecrit qu'au PREMIER vote
 *    (votes-social.php:140), et `_bbp_reply_count` a la premiere reponse.
 *    Mesure avant correctif : 2 reponses publiees, 0 avec la meta, soit
 *    100 % d'invisibles ; endpoint LLM du forum : 0 sujet sur 3.
 *
 * 2. TRI SUR UNE META ARBITRAIRE (critique aussi).
 *    Le premier correctif utilisait `orderby => ['score' => 'DESC']` avec une
 *    meta_query a deux branches. La branche `NOT EXISTS` produit un LEFT JOIN
 *    NON CONTRAINT sur wp_postmeta : pour un post sans score, MySQL trie sur
 *    la meta_value de n'importe quelle ligne jointe. Reproduit de facon
 *    deterministe : forum #44, un sujet jamais vote portant
 *    `_bbp_forum_id = 44` etait classe DEVANT un sujet a 7 votes, parce que
 *    44 > 7. Le tri « meilleurs sujets » etait donc arbitraire des qu'un
 *    contenu n'avait pas encore ete vote.
 *
 * SOLUTION RETENUE
 * ----------------
 * L'ordre est calcule par une sous-requete correlee explicitement bornee a la
 * cle voulue, avec COALESCE(..., 0) pour les contenus sans meta :
 *
 *     COALESCE((SELECT CAST(meta_value AS SIGNED) FROM wp_postmeta
 *               WHERE post_id = wp_posts.ID AND meta_key = '<cle>' LIMIT 1), 0) DESC
 *
 * Aucun JOIN n'est necessaire pour le tri, donc aucune ambiguite possible sur
 * la cle lue, et aucun contenu n'est exclu. La colonne (post_id, meta_key) est
 * l'index natif de wp_postmeta : la sous-requete est une recherche par index.
 *
 * @param array<string, mixed> $args     Arguments de requete, modifies par reference.
 * @param string               $meta_key Cle de meta servant au tri.
 * @return void
 */
function swiftboard_trier_par_meta_numerique( array &$args, $meta_key ) {
	// meta_key + orderby=meta_value_num produirait l'INNER JOIN excluant.
	unset( $args['meta_key'], $args['meta_value'], $args['order'] );

	// Le tri est pose par le filtre ci-dessous, qui a besoin de lire la cle
	// sur la requete. suppress_filters doit rester a false : get_posts() le
	// met a true par defaut, ce qui desactiverait posts_clauses.
	$args['swiftboard_tri_meta'] = $meta_key;
	$args['suppress_filters']    = false;
	$args['orderby']             = 'none';
}

/**
 * Applique l'ORDER BY des requetes marquees par
 * swiftboard_trier_par_meta_numerique().
 *
 * @param array<string, string> $clauses Clauses SQL de la requete.
 * @param WP_Query              $query   Requete courante.
 * @return array<string, mixed>
 */
function swiftboard_appliquer_tri_meta( $clauses, $query ) {
	$meta_key = $query->get( 'swiftboard_tri_meta' );
	if ( ! $meta_key || ! is_string( $meta_key ) ) {
		return $clauses;
	}

	global $wpdb;
	$cle = esc_sql( $meta_key );

	// Sous-requete correlee bornee a la cle : pas de JOIN, donc pas de risque
	// de trier sur une autre meta du meme post.
	$clauses['orderby'] = "COALESCE((
            SELECT CAST(sb_tri.meta_value AS SIGNED)
            FROM {$wpdb->postmeta} sb_tri
            WHERE sb_tri.post_id = {$wpdb->posts}.ID
              AND sb_tri.meta_key = '{$cle}'
            LIMIT 1
        ), 0) DESC, {$wpdb->posts}.post_date ASC";

	return $clauses;
}
add_filter( 'posts_clauses', 'swiftboard_appliquer_tri_meta', 999, 2 );

/**
 * Declare la variable de requete du tri : sans cela, $query->get() la renvoie
 * vide sur les WP_Query construites a partir d'arguments publics.
 *
 * @param string[] $vars Variables de requete autorisees.
 * @return string[]
 */
function swiftboard_enregistrer_var_tri( $vars ) {
	$vars[] = 'swiftboard_tri_meta';
	return $vars;
}
add_filter( 'query_vars', 'swiftboard_enregistrer_var_tri' );

// ============================================================================
// 6. JS + CSS
// ============================================================================
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_script(
			'swiftboard-nested-comments',
			SWIFTBOARD_URI . '/assets/js/nested-comments.js',
			array( 'swiftboard-main' ),
			defined( 'SWIFTBOARD_VERSION' ) ? SWIFTBOARD_VERSION : null,
			true
		);
	},
	30
);

