<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Cursor-based pagination pour bbPress
 *
 * Anti-slow OFFSET : pour 100k topics, OFFSET 10000 = filesort lent.
 * Solution : cursor-based pagination (WHERE ID < $last_id ORDER BY ID DESC LIMIT N).
 *
 * Stratégie :
 * 1. Hook sur `bbp_has_topics_query` pour détecter le paramètre `?cursor=123`
 * 2. Ajouter un filtre `posts_where` qui injecte `AND ID < $cursor`
 * 3. Désactiver l'OFFSET natif bbPress quand un cursor est fourni
 * 4. Ajouter un bouton "Plus de sujets" qui recharge en AJAX avec le dernier ID vu
 *
 * @package SwiftBoard
 * @since 4.3.0
 */
// ============================================================================
// 1. DÉTECTER LE CURSOR ET L'INJECTER DANS LA REQUÊTE bbPress
// ============================================================================
add_filter(
	'bbp_has_topics_query',
	function ( $args ) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture d'un paramètre de pagination public.
		$cursor = isset( $_GET['cursor'] ) ? (int) $_GET['cursor'] : 0;
		if ( $cursor > 0 ) {
			// REPLI SANS JAVASCRIPT — le curseur est un OFFSET, pas un identifiant.
			//
			// Ce chemin est celui du `href` du bouton : robot d'indexation,
			// navigateur sans JS, extension qui bloque le script, clic milieu.
			// Il utilisait `AND ID < $cursor`, exactement le mécanisme corrigé
			// côté REST — et il en portait donc le même défaut.
			//
			// Mesuré avant correction, tri « hot », 30 sujets à activité inverse
			// de l'ID : page 1 = 29 sujets, page 2 = 19 sujets dont **19
			// doublons**, et 1 sujet jamais rendu. Googlebot n'aurait jamais vu ce
			// contenu.
			//
			// Corriger la seule route REST laissait le bug entier pour quiconque
			// n'exécute pas le JS.
			$args['offset'] = $cursor;

			// L'ORDRE DOIT ETRE IDENTIQUE ENTRE LES DEUX PAGES.
			//
			// Piège mesuré : la page 1 (sans `?cursor=`) est triée par
			// `feed-sort.php` (score de votes + réponses×2), la page 2 l'était par
			// un ORDER BY différent. Deux tris distincts sur la même liste
			// produisent des recouvrements — 13 doublons constatés, et des sujets
			// jamais rendus.
			//
			// On NE désactive donc PAS feed-sort ici : c'est lui qui définit
			// l'ordre du forum. On se contente de paginer dedans par offset. Son
			// ORDER BY se termine désormais par `ID DESC` (unique), ce qui rend
			// l'ordre total et la pagination fiable.
		}
		return $args;
	}
);

// ============================================================================
// 2. (SUPPRIMÉ) FILTRE posts_where « AND ID < $cursor »
// ============================================================================
// La pagination du repli sans JS passe désormais par `offset` (section 1).
//
// Ce filtre ajoutait `AND ID < $cursor` à toute requête portant la variable
// `swiftboard_cursor`. Il ne fonctionnait que si l'ordre d'affichage était
// exactement `ID DESC` : sur un tri « hot », il sautait les sujets d'ID
// supérieur absents de la page précédente et en répétait d'autres.

// ============================================================================
// 3. BOUTON "PLUS DE SUJETS" (LOAD MORE) — INJECTÉ APRÈS LA LOOP
// ============================================================================
add_action(
	'bbp_template_after_topics_loop',
	function () {
		global $wp_query;

		// La boucle de sujets appartient a bbPress, PAS a la requete principale.
		// Sur la page d'un forum, $wp_query decrit le FORUM (post_type 'forum',
		// voire aucun post_type) tandis que les sujets affiches vivent dans
		// bbpress()->topic_query. Lire $wp_query faisait echouer la garde
		// « post_type === topic » a tous les coups : le bouton « Charger plus »
		// n'apparaissait jamais, meme avec 5 000 sujets dans le forum.
		// Mesure avant correction : wp_query->posts = 0, topic_query->posts = 15.
		$boucle = $wp_query;
		if ( function_exists( 'bbpress' ) ) {
			$bbp = bbpress();
			if ( isset( $bbp->topic_query ) && ! empty( $bbp->topic_query->posts ) ) {
				$boucle = $bbp->topic_query;
			}
		}

		// Ne s'affiche que sur les loops topics (pas sur single topic)
		if ( empty( $boucle->posts ) || ! isset( $boucle->query_vars['post_type'] ) ) {
			return;
		}
		if ( $boucle->query_vars['post_type'] !== 'topic' ) {
			return;
		}

		// Le curseur émis est une POSITION, pas un identifiant.
		//
		// L'ancien code prenait MIN(ID) des sujets affichés. Le commentaire
		// affirmait « l'ID reste un cursor valide » pour les tris hot/top : c'est
		// faux, et c'est la cause du défaut. Sur un tri par dernière activité,
		// l'ID n'a aucun rapport avec le rang.
		//
		// La position suivante est simplement : position courante + nombre de
		// sujets rendus.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- paramètre de pagination public.
		$depart  = isset( $_GET['cursor'] ) ? max( 0, (int) $_GET['cursor'] ) : 0;
		$last_id = $depart + count( $boucle->posts );
		if ( ! $last_id ) {
			return;
		}

		// Si on a moins de posts que per_page, on est à la fin
		$per_page = (int) ( $boucle->query_vars['posts_per_page'] ?? 15 );
		if ( count( $boucle->posts ) < $per_page ) {
			return; // pas besoin de bouton
		}

		// URL de la page suivante (cursor = dernier ID)
		$current_url = strtok( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '', '?' );
		$sort        = isset( $_GET['sort'] ) ? sanitize_text_field( wp_unslash( $_GET['sort'] ) ) : 'hot';
		$period      = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : 'all';
		$next_url    = add_query_arg(
			array(
				'cursor' => $last_id,
				'sort'   => $sort,
				'period' => $period,
			),
			$current_url
		);

		// Le forum courant est resolu ICI, cote serveur, et transmis en data-*.
		// Le script client le deduisait auparavant de l'URL sans jamais l'ajouter a
		// la requete : « Charger plus » renvoyait les sujets de TOUS les forums.
		// Trois sources, de la plus fiable a la plus contextuelle :
		// 1. le post_parent de la boucle affichee — c'est litteralement le forum
		// dont les sujets sont a l'ecran, y compris quand bbPress n'a pas
		// encore etabli son contexte ;
		// 2. l'objet interroge, sur la page d'un forum ;
		// 3. bbp_get_forum_id(), en dernier recours.
		$forum_courant = (int) ( $boucle->query_vars['post_parent'] ?? 0 );

		if ( ! $forum_courant && is_singular( 'forum' ) ) {
			$forum_courant = (int) get_queried_object_id();
		}
		if ( ! $forum_courant && function_exists( 'bbp_get_forum_id' ) ) {
			$forum_courant = (int) bbp_get_forum_id();
		}

		echo '<div class="sb-load-more-wrapper" style="text-align:center;padding:24px 0;">';
		echo '<a href="' . esc_url( $next_url ) . '" class="sb-load-more-btn btn-secondary"'
		. ' data-cursor="' . esc_attr( (string) $last_id ) . '"'
		. ' data-forum-id="' . esc_attr( (string) $forum_courant ) . '"'
		. ' data-sort="' . esc_attr( $sort ) . '"'
		. ' data-period="' . esc_attr( $period ) . '"'
		. ' data-rest-url="' . esc_attr( esc_url_raw( rest_url( 'swiftboard/v1/topics/load-more' ) ) ) . '">';
		esc_html_e( 'Plus de sujets', 'swiftboard' );
		echo '</a>';
		echo '</div>';
	}
);

// ============================================================================
// 4. REST ENDPOINT POUR LOAD MORE EN AJAX (optionnel)
// ============================================================================
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'swiftboard/v1',
			'/topics/load-more',
			array(
				'methods'             => 'GET',
				'permission_callback' => 'swiftboard_rest_public_permission',
				'callback'            => 'swiftboard_rest_load_more_topics',
				'args'                => array(
					'cursor'   => array(
						'type'     => 'integer',
						'required' => true,
					),
					'forum_id' => array( 'type' => 'integer' ),
					'sort'     => array(
						'type'    => 'string',
						'default' => 'hot',
						'enum'    => array( 'hot', 'new', 'top', 'rising' ),
					),
					'period'   => array(
						'type'    => 'string',
						'default' => 'all',
						'enum'    => array( '24h', '7d', '30d', 'all' ),
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 15,
						'maximum' => 50,
					),
				),
			)
		);
	}
);

/**
 * swiftboard_rest_load_more_topics().
 *
 * @param WP_REST_Request<array<string, mixed>> $req Requête REST entrante.
 * @return mixed
 */
function swiftboard_rest_load_more_topics( WP_REST_Request $req ) {
	$cursor   = (int) $req->get_param( 'cursor' );
	$forum_id = (int) $req->get_param( 'forum_id' );
	$sort     = sanitize_text_field( $req->get_param( 'sort' ) );
	$period   = sanitize_text_field( $req->get_param( 'period' ) );
	$per_page = max( 1, min( 50, (int) $req->get_param( 'per_page' ) ) );

	if ( $cursor <= 0 ) {
		return new WP_REST_Response( array( 'error' => 'cursor manquant' ), 400 );
	}

	$args = array(
		'post_type'             => 'topic',
		'post_status'           => 'publish',
		'posts_per_page'        => $per_page,
		'no_found_rows'         => true,
		'meta_query'            => array(),
		// Marqueur : indique a inc/feed-sort.php de NE PAS reecrire l'ORDER BY.
		// Son filtre `posts_orderby` s'applique a toutes les requetes « topic »
		// et se termine par `post_date DESC`, qui n'est pas unique : l'ordre
		// devient instable et la pagination par offset perd des lignes.
		'swiftboard_pagination' => true,
	);
	if ( $forum_id ) {
		// Inclure les sous-forums (comme single-forum.php avec post_parent__in).
		$sub_forums = get_posts( array(
			'post_type'      => 'forum',
			'post_parent'    => $forum_id,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );
		$args['post_parent__in'] = array_merge( array( $forum_id ), $sub_forums );
	}

	// Période
	$interval = swiftboard_period_to_interval( $period );
	if ( $interval && $sort !== 'new' ) {
		$args['date_query'] = array(
			array(
				'column' => 'post_date',
				'after'  => '-' . $interval,
			),
		);
	}

	// TRI — un ordre TOTAL et DÉTERMINISTE est obligatoire.
	//
	// Sans `orderby` explicite, MySQL ne garantit aucun ordre stable entre deux
	// requêtes. Mesuré : avec un simple OFFSET, la page 1 renvoyait
	// `39, 22, 21, 20…` et la page 2 `21, 6, 7…` — le sujet 21 apparaissait
	// DEUX fois, et d'autres nulle part. Une pagination par offset sur un ordre
	// instable perd des lignes, exactement comme le curseur ID qu'on remplace.
	//
	// On ajoute donc systématiquement `ID` comme dernier critère : il est
	// unique, donc il départage tous les ex æquo et rend l'ordre total.
	switch ( $sort ) {
		case 'new':
			$args['orderby'] = array( 'ID' => 'DESC' );
			break;

		case 'top':
			// Score de votes, puis ID pour départager.
			$args['meta_key'] = '_swiftboard_vote_score';
			$args['orderby']  = array(
				'meta_value_num' => 'DESC',
				'ID'             => 'DESC',
			);
			break;

		default:
			// « hot » / « rising » : dernière activité, puis ID.
			//
			// `meta_key` seul EXCLUT les sujets dépourvus de la méta : WP_Query
			// pose alors un INNER JOIN sur postmeta. Mesuré : 2 sujets sans
			// `_bbp_last_active_time` (import, création programmatique,
			// migration) devenaient définitivement inaccessibles — le défaut
			// même qu'on corrige, sous une autre forme.
			//
			// La clause OR avec `NOT EXISTS` rend le JOIN externe : les sujets
			// sans méta restent inclus et se rangent en fin de tri, ce qui est
			// le comportement attendu pour un sujet sans activité connue.
			$args['meta_query'] = array(
				'relation'    => 'OR',
				'sb_activite' => array(
					'key'     => '_bbp_last_active_time',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => '_bbp_last_active_time',
					'compare' => 'NOT EXISTS',
				),
			);
			// `meta_value` fonctionne ici car `_bbp_last_active_time` est une
			// date au format `Y-m-d H:i:s`, dont l'ordre lexicographique est
			// aussi l'ordre chronologique.
			$args['orderby'] = array(
				'sb_activite' => 'DESC',
				'ID'          => 'DESC',
			);
			break;
	}

	// CURSEUR ADAPTÉ À LA CLÉ DE TRI
	//
	// DÉFAUT CORRIGÉ — le curseur portait TOUJOURS sur l'ID (`WHERE ID < $cursor`),
	// quel que soit le tri demandé. Cela ne fonctionne que si l'ordre d'affichage
	// coïncide avec `ID DESC`.
	//
	// Dès que le tri réel diffère — « hot » classe par dernière activité, « top »
	// par score — la condition `ID < MIN(ID_affichés)` saute tous les sujets dont
	// l'ID est supérieur mais qui n'étaient pas sur la page 1.
	//
	// Mesure sur 35 sujets dont l'activité est inverse de l'ID (cas normal d'un
	// forum où une vieille discussion est relancée) :
	// page 1 (hot) : 18 sujets, curseur émis = 5
	// page 2       : 0 sujet  →  17 sujets DÉFINITIVEMENT inaccessibles
	//
	// Correction : on pagine par OFFSET sur la clé de tri réelle, pour TOUS les
	// tris. C'est la seule façon d'atteindre l'intégralité des sujets.
	//
	// Pourquoi pas un curseur ID conservé pour « new » ? Parce que l'ordre
	// effectif de ce tri n'est pas garanti être `ID DESC` : d'autres modules
	// (sujets épinglés, tri bbPress par dernière activité) réécrivent le
	// `orderby`. Mesuré : en tri « new », la route renvoyait `9, 5, 65…` — non
	// décroissant. Un curseur ID appliqué à un ordre qui n'est pas trié par ID
	// reproduit exactement le défaut qu'on corrige. Faire dépendre la logique
	// d'une hypothèse fragile sur l'ordre, c'est réintroduire le bug plus tard.
	//
	// L'OFFSET a un coût connu et assumé : si du contenu s'insère pendant la
	// pagination, une entrée peut se répéter ou glisser d'une page. C'est le
	// compromis retenu — un doublon occasionnel est infiniment préférable à des
	// sujets définitivement inaccessibles.
	//
	// Le client ne calcule plus rien : il renvoie tel quel le `next_cursor`
	// fourni par la réponse. Lui laisser deviner MIN(ID) le condamnait à se
	// tromper, puisque seule la route connaît l'ordre réellement appliqué.
	// PHP_INT_MAX signifie « première page » : offset 0.
	$args['offset'] = ( $cursor >= PHP_INT_MAX ) ? 0 : max( 0, $cursor );

	$query = new WP_Query( $args );

	// Anti-N+1
	$post_ids = wp_list_pluck( $query->posts, 'ID' );
	if ( ! empty( $post_ids ) ) {
		update_meta_cache( 'post', $post_ids );
		_prime_post_caches( $post_ids, false, false );
		$author_ids = array_unique( array_filter( wp_list_pluck( $query->posts, 'post_author' ) ) );
		cache_users( $author_ids );
	}

	$topics = array();
	while ( $query->have_posts() ) {
		$query->the_post();
		$topic_id    = get_the_ID();
		$votes       = function_exists( 'swiftboard_get_vote_count' ) ? swiftboard_get_vote_count( $topic_id ) : 0;
		$reply_count = function_exists( 'bbp_get_topic_reply_count' ) ? bbp_get_topic_reply_count( $topic_id, true ) : 0;
		$author_id   = get_post_field( 'post_author', $topic_id );
		$parent_id   = wp_get_post_parent_id( $topic_id );

		$topics[] = array(
			'id'          => $topic_id,
			'title'       => get_the_title(),
			'url'         => get_permalink(),
			'author_id'   => (int) $author_id,
			'author_name' => get_the_author_meta( 'display_name', (int) $author_id ),
			'forum_id'    => (int) $parent_id,
			'forum_name'  => $parent_id ? get_the_title( $parent_id ) : '',
			'forum_url'   => $parent_id ? get_permalink( $parent_id ) : '',
			'votes'       => (int) $votes,
			'reply_count' => (int) $reply_count,
			'date'        => get_the_date( 'c' ),
			'excerpt'     => wp_trim_words( wp_strip_all_tags( get_the_content() ), 35, '…' ),
		);
	}
	wp_reset_postdata();

	// Prochain cursor = dernier ID de la page courante
	// Le curseur suivant est la POSITION atteinte dans l'ordre de tri, pas un
	// identifiant. Renvoyer MIN(ID) était précisément le défaut : sur un tri
	// « hot », l'ID n'a aucun rapport avec le rang.
	$next_cursor = 0;
	if ( ! empty( $topics ) ) {
		$depart      = ( $cursor >= PHP_INT_MAX ) ? 0 : max( 0, $cursor );
		$next_cursor = $depart + count( $topics );
	}
	// `has_more` doit refléter le RESTE RÉEL, pas une approximation.
	//
	// `count($topics) >= $per_page` est un raccourci trompeur : dès qu'une page
	// n'est pas pleine — parce qu'un sujet a été filtré après la requête, ou
	// parce que le lot tombe juste — le client cesse de paginer alors qu'il
	// reste du contenu. Mesuré : la pagination s'arrêtait à 18 sujets sur 35.
	//
	// On compare la position atteinte au total réellement disponible.
	$sb_total    = new WP_Query(
		array_merge(
			$args,
			array(
				'posts_per_page' => 1,
				'offset'         => 0,
				'fields'         => 'ids',
				'no_found_rows'  => false,
			)
		)
	);
	$total_dispo = (int) $sb_total->found_posts;
	$has_more    = ( $next_cursor > 0 ) && ( $next_cursor < $total_dispo );

	$sb_response = new WP_REST_Response(
		array(
			'topics'      => $topics,
			'next_cursor' => $next_cursor,
			'has_more'    => $has_more,
		),
		200
	);
	// EXI-TEST-03 : en-tete pose sur l'objet reponse, pas via header().
	// header() emet un warning des que la sortie a commence et
	// echappe aux filtres rest_post_dispatch.
	$sb_response->header( 'Cache-Control', 'public, max-age=60' );
	return $sb_response;
}

// ============================================================================
// 5. JS — LOAD MORE AU CLIC (sans rechargement de page)
// ============================================================================
// Le comportement « Charger plus » vit dans assets/js/load-more.js.
//
// Il etait auparavant imprime en <script> inline dans wp_footer. Le theme sert
// une CSP `script-src 'self'` en ENFORCE : ce bloc, absent des empreintes
// SHA-256, etait purement et simplement bloque par le navigateur — sans erreur
// visible, le bouton restait inerte. Un fichier externe est servi par 'self',
// donc autorise sans empreinte a maintenir.
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! function_exists( 'is_bbpress' ) || ! is_bbpress() ) {
			return;
		}
		if ( ! is_post_type_archive( 'topic' ) && ! is_tax( 'forum' ) && ! is_singular( 'forum' ) ) {
			return;
		}

		wp_enqueue_script(
			'swiftboard-load-more',
			SWIFTBOARD_ASSETS . '/js/load-more.js',
			array(),
			SWIFTBOARD_VERSION,
			true
		);
	}
);
