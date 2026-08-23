<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Sitemap XML pour bbPress (topics + replies)
 *
 * WordPress core génère /wp-sitemap.xml mais n'inclut que les post types publics
 * avec 'public' = true. bbPress déclare topic/reply comme 'public' => false (par design)
 * pour éviter le duplicate content avec les forums.
 *
 * Solution : on ajoute un provider custom qui injecte topics + replies dans
 * le sitemap core, avec pagination (500 URLs par page sitemap).
 *
 * @package SwiftBoard
 * @since 4.5.0
 */

// Si Yoast ou RankMath gère déjà le sitemap, ne pas dupliquer.
// Ces plugins incluent automatiquement les CPT bbPress dans leur propre sitemap.
if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) {
	return;
}

// ============================================================================
// 1. AJOUTER TOPICS + REPLIES AU SITEMAP CORE
// ============================================================================
add_filter(
	'wp_sitemaps_post_types',
	function ( $post_types ) {
		// Ajouter topic et reply au sitemap core
		if ( function_exists( 'bbp_get_topic_post_type' ) && isset( $post_types['post'] ) ) {
			$post_types[ bbp_get_topic_post_type() ] = get_post_type_object( bbp_get_topic_post_type() );
		}

		// ------------------------------------------------------------------
		// DEFAUT CORRIGE — le garde-fou documente ne gardait rien.
		//
		// Le commentaire d'origine annoncait : « Replies : uniquement si l'admin
		// veut les indexer (defaut : non, pour eviter duplicate) ». Il ne faisait
		// qu'AJOUTER le type quand le filtre valait true, jamais le RETIRER.
		//
		// Or le fournisseur du coeur construit sa liste depuis
		// `get_post_types(['public' => true])`
		// (wp-includes/sitemaps/providers/class-wp-sitemaps-posts.php:37), et
		// bbPress declare `reply` avec `'public' => true` (bbpress.php:576).
		// Le type etait donc DEJA present avant que ce filtre ne s'execute :
		// ne pas l'ajouter ne l'enlevait pas.
		//
		// Mesure avant correction, filtre a false :
		// wp-sitemap.xml annoncait wp-sitemap-posts-reply-1.xml
		// -> 5 URL de reponses exposees au crawl, soit exactement le contenu
		// duplique que le commentaire pretendait eviter.
		//
		// On agit donc dans les DEUX sens : on ajoute si demande, on retire
		// sinon.
		if ( function_exists( 'bbp_get_reply_post_type' ) ) {
			$sb_reply = bbp_get_reply_post_type();
			if ( apply_filters( 'swiftboard_sitemap_include_replies', false ) ) {
				if ( isset( $post_types['post'] ) ) {
					$post_types[ $sb_reply ] = get_post_type_object( $sb_reply );
				}
			} else {
				unset( $post_types[ $sb_reply ] );
			}
		}

		return $post_types;
	}
);

// ============================================================================
// 1b. NE PAS PUBLIER LA LISTE DES AUTEURS
// ============================================================================
//
// DEFAUT CORRIGE — contradiction directe avec inc/security.php.
//
// Le theme ferme explicitement l'enumeration par `?author=N`, au motif
// documente la-bas : « Le slug de l'URL EST le login. Un attaquant obtient la
// moitie du couple identifiant/mot de passe en une requete. »
//
// Le meme renseignement etait pourtant publie, en clair et sans
// authentification, par `wp-sitemap-users-1.xml` — que rien ne desactivait.
//
// Mesure avant correction : les slugs listes par le sitemap des auteurs
// correspondaient caractere pour caractere a des `user_login` reels de
// `wp_users`. Fermer un vecteur et laisser l'autre ouvert ne protege rien.
//
// Les pages d'auteur elles-memes restent accessibles et indexables (Google
// s'en sert pour l'attribution) : on cesse seulement de PUBLIER l'annuaire
// complet des comptes.
add_filter(
	'wp_sitemaps_add_provider',
	function ( $provider, $nom ) {
		if ( 'users' === $nom && ! apply_filters( 'swiftboard_sitemap_include_users', false ) ) {
			return false;
		}
		return $provider;
	},
	10,
	2
);

// ============================================================================
// 2. FILTRER LES POSTS À INCLURE (uniquement publiés + pas de spam/trash)
// ============================================================================
add_filter(
	'wp_sitemaps_posts_query_args',
	function ( $args, $post_type ) {
		if ( function_exists( 'bbp_get_topic_post_type' ) && $post_type === bbp_get_topic_post_type() ) {
			$args['post_status'] = 'publish';
			// Exclure les topics sans replies (optionnel — défaut : on garde tout)
			$args['posts_per_page'] = min( $args['posts_per_page'] ?? 2000, 2000 );
		}
		return $args;
	},
	10,
	2
);

// ============================================================================
// 3. SITEMAP XML CUSTOM POUR FORUMS (post_type=forum, qui est aussi 'public' => false)
// ============================================================================
// Bloc retire : c'etait un add_filter() sur `init` (qui est une ACTION) dont
// le corps ne faisait rien — le `if` interne ne contenait qu'un commentaire,
// et le callback ne retournait aucune valeur. Les forums sont ajoutes au
// sitemap par le filtre wp_sitemaps_post_types ci-dessous.

// Ajouter forums au sitemap
add_filter(
	'wp_sitemaps_post_types',
	function ( $post_types ) {
		if ( function_exists( 'bbp_get_forum_post_type' ) && isset( $post_types['post'] ) ) {
			$forum_pto = get_post_type_object( bbp_get_forum_post_type() );
			if ( $forum_pto ) {
				$post_types[ bbp_get_forum_post_type() ] = $forum_pto;
			}
		}
		return $post_types;
	},
	20
);

// ============================================================================
// 4. PING SITEMAP — SUPPRIME (endpoints fermes par Google et Bing)
// ============================================================================
//
// Ce hook emettait, A CHAQUE PUBLICATION DE SUJET, deux requetes HTTP vers
// https://www.google.com/ping?sitemap=...
// https://www.bing.com/ping?sitemap=...
//
// Trois raisons de le retirer :
//
// 1. LES ENDPOINTS N'EXISTENT PLUS. Google a annonce leur depreciation en
// juin 2023 et les a fermes fin 2023 : ils repondent 404.
// https://developers.google.com/search/blog/2023/06/sitemaps-lastmod-ping
// Google precise que les conserver ne nuit pas, mais « ne fait rien
// d'utile ». Bing a fait le meme constat.
//
// 2. L'APPEL N'ETAIT PAS REELLEMENT NON BLOQUANT. 'blocking' => false evite
// d'attendre la REPONSE, mais pas l'ouverture de connexion ni la
// resolution DNS. Mesure sur cet environnement : publier un sujet passait
// de 13 ms a 460 ms, soit 35 fois plus lent. Sur un import de 500 lignes,
// cela represente plus de trois minutes d'attente pure.
//
// 3. C'EST UN APPEL SORTANT NON CONSENTI a chaque publication, y compris
// depuis un environnement de developpement ou un site prive.
//
// Ce qui remplace le ping, et qui est deja en place :
// - le sitemap est declare dans robots.txt (section 5 ci-dessous) ;
// - inc/search-console.php soumet les URL via l'API Indexing authentifiee,
// seule methode encore supportee.

// ============================================================================
// 5. ROBOTS.TXT — AUTORISER L'INDEXATION DES FORUMS/TOPICS
// ============================================================================
add_filter(
	'robots_txt',
	function ( $output, $public ) {
		if ( $public ) {
			// S'assurer que /forums/ est autorisé
			$output .= "\n# SwiftBoard bbPress\n";
			$output .= "Allow: /forums/\n";
			$output .= "Allow: /wp-sitemap.xml\n";
			$output .= "Disallow: /wp-admin/\n";
			$output .= "Disallow: /wp-login.php\n";
			$output .= "Disallow: /*?sb_user_view=\n"; // pages privées user
		}
		return $output;
	},
	10,
	2
);
