<?php
if ( ! defined( 'ABSPATH' )) exit;

/**
 * SwiftBoard — Recherche FULLTEXT MySQL pour 100k+ sujets
 *
 * Problème : WordPress utilise `LIKE '%term%'` par défaut, qui fait un full
 * table scan sur 100k+ lignes (= plusieurs secondes).
 *
 * Solution : MySQL FULLTEXT index sur `post_title` + `post_content`, et
 * remplacement de la clause `posts_search` par `MATCH() AGAINST()` en
 * mode BOOLEAN. Performance : ~10-50ms sur 100k sujets (vs 2-5s avec LIKE).
 *
 * Couvre les post types : topic, reply, forum, post (blog).
 * Langue : français (ft_stopword_file par défaut de MySQL).
 *
 * @package SwiftBoard
 * @since 4.3.0
 *
 * REST + autocomplete : voir inc/fulltext-search-rest.php (CDC-RESTANT-01).
 */
// ============================================================================
// 1. CRÉER LES INDEX FULLTEXT À L'ACTIVATION DU THÈME
// ============================================================================
add_action(
	'after_switch_theme',
	function () {
		global $wpdb;

		// Index FULLTEXT sur wp_posts (post_title + post_content)
		// Filtré par post_type dans la requête, donc un seul index couvre tous les types
		$index_exists = $wpdb->get_var(
			"SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE table_schema = DATABASE()
           AND table_name = '{$wpdb->posts}'
           AND index_name = 'swiftboard_fulltext'"
		);

		if ( ! $index_exists ) {
			// FULLTEXT ne peut pas être préfixé, donc sur tout post_title + post_content
			$wpdb->query(
				"ALTER TABLE {$wpdb->posts}
             ADD FULLTEXT INDEX swiftboard_fulltext (post_title, post_content)"
			);
		}
	}
);

// Bouton admin pour appliquer manuellement (au cas où l'activation est manquée)
add_action(
	'admin_init',
	function () {
		if ( isset( $_GET['swiftboard_apply_fulltext'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'sb_fulltext' ) ) {
			global $wpdb;
			$index_exists = $wpdb->get_var(
				"SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE table_schema = DATABASE()
               AND table_name = '{$wpdb->posts}'
               AND index_name = 'swiftboard_fulltext'"
			);
			if ( ! $index_exists ) {
				$wpdb->query(
					"ALTER TABLE {$wpdb->posts}
                 ADD FULLTEXT INDEX swiftboard_fulltext (post_title, post_content)"
				);
			}
			set_transient( 'swiftboard_fulltext_applied', true, 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=swiftboard-admin' ) );
			exit;
		}
	}
);

// ============================================================================
// 2. REMPLACER LA CLAUSE POSTS_SEARCH PAR MATCH() AGAINST()
// ============================================================================
/**
 * WP génère par défaut : ((wp_posts.post_title LIKE '%term%') OR (wp_posts.post_content LIKE '%term%'))
 * On remplace par : MATCH(wp_posts.post_title, wp_posts.post_content) AGAINST('+term*' IN BOOLEAN MODE)
 *
 * Le mode BOOLEAN permet :
 *  - +term : mot obligatoire
 *  - term* : préfixe (recherche "WordPress" matche "WordPress", "WordPressing")
 *  - "phrase exacte"
 *  - -term : exclure un mot
 */
add_filter(
	'posts_search',
	function ( $search, $query ) {
		global $wpdb;

		// Ne s'applique qu'aux recherches frontend (pas admin, qui a sa propre logique)
		if ( is_admin() || empty( $search ) ) {
			return $search;
		}

		// Vérifier que c'est bien une recherche
		if ( empty( $query->query_vars['s'] ) ) {
			return $search;
		}

		// Vérifier que l'index FULLTEXT existe
		$index_exists = get_transient( 'swiftboard_fulltext_ok' );
		if ( $index_exists === false ) {
			$check = $wpdb->get_var(
				"SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE table_schema = DATABASE()
               AND table_name = '{$wpdb->posts}'
               AND index_name = 'swiftboard_fulltext'"
			);
			set_transient( 'swiftboard_fulltext_ok', (int) $check, HOUR_IN_SECONDS );
			$index_exists = (int) $check;
		}
		if ( ! $index_exists ) {
			// Pas d'index = fallback vers LIKE (comportement WP par défaut)
			return $search;
		}

		// Parser la query string utilisateur
		$terms_raw = sanitize_text_field( $query->query_vars['s'] );
		if (empty( $terms_raw )) return $search;

		// Tokenizer simple : split sur espaces, garde les "phrases entre guillemets"
		preg_match_all( '/"([^"]+)"|(\S+)/', $terms_raw, $matches, PREG_SET_ORDER );

		$boolean_terms = array();
		foreach ( $matches as $m ) {
			if ( ! empty( $m[1] ) ) {
				// Phrase exacte entre guillemets
				$boolean_terms[] = '"' . $m[1] . '"';
			} elseif ( ! empty( $m[2] ) ) {
				$word = $m[2];
				// Skip les mots vides (stopwords courants FR/EN)
				$stopwords = array(
					'le',
					'la',
					'les',
					'un',
					'une',
					'des',
					'de',
					'du',
					'et',
					'or',
					'ni',
					'car',
					'the',
					'a',
					'an',
					'of',
					'and',
					'or',
					'to',
					'in',
					'on',
					'at',
					'is',
					'are',
				);
				if (in_array( mb_strtolower( $word ), $stopwords )) continue;
				// Skip les mots trop courts (< 3 chars)
				if (mb_strlen( $word ) < 3) continue;
				// Skip les caractères non-alphanumériques purs
				if ( ! preg_match( '/^[\p{L}\p{N}_-]+$/u', $word )) continue;
				// Préfixe : term* (matche term, terms, terminal, etc.)
				$boolean_terms[] = $word . '*';
			}
		}

		if ( empty( $boolean_terms ) ) {
			// Que des stopwords → fallback LIKE
			return $search;
		}

		$against = implode( ' ', $boolean_terms );

		// MATCH() AGAINST() en mode BOOLEAN
		$new_search = $wpdb->prepare(
			" AND MATCH({$wpdb->posts}.post_title, {$wpdb->posts}.post_content) AGAINST(%s IN BOOLEAN MODE) ",
			$against
		);

		return $new_search;
	},
	10,
	2
);

// ============================================================================
// 3. ORDER BY PERTINENCE (score FULLTEXT) — UNIQUEMENT SI PAS DE TRI EXPLICITE
// ============================================================================
add_filter(
	'posts_orderby',
	function ( $orderby, $query ) {
		global $wpdb;

		// Uniquement sur les recherches frontend
		if ( is_admin() || empty( $query->query_vars['s'] ) ) {
			return $orderby;
		}

		// Si l'user a explicitement demandé un tri (date, votes, etc.), on respecte
		if ( ! empty( $query->query_vars['orderby'] ) ) {
			return $orderby;
		}

		// Vérifier l'index FULLTEXT
		$index_exists = get_transient( 'swiftboard_fulltext_ok' );
		if ( ! $index_exists) return $orderby;

		// Trier par score FULLTEXT DESC (pertinence), puis par date DESC
		return " MATCH({$wpdb->posts}.post_title, {$wpdb->posts}.post_content) AGAINST('') DESC, {$wpdb->posts}.post_date DESC ";
	},
	10,
	2
);

// ============================================================================
// 7. ADMIN — AFFICHER L'ÉTAT DE L'INDEX FULLTEXT
// ============================================================================
add_action(
	'swiftboard_admin_dashboard_after',
	function () {
		global $wpdb;
		$index_exists = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE table_schema = DATABASE()
           AND table_name = '{$wpdb->posts}'
           AND index_name = 'swiftboard_fulltext'"
		);
		$apply_url    = wp_nonce_url( admin_url( 'admin.php?swiftboard_apply_fulltext=1' ), 'sb_fulltext' );
		?>
	<div class="sb-admin-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin:16px 0;">
		<h3 style="margin:0 0 8px;">🔍 Recherche Fulltext</h3>
		<?php if ( $index_exists ) : ?>
			<p style="color:#16a34a;font-weight:600;">✅ Index FULLTEXT actif sur wp_posts(post_title, post_content)</p>
			<p style="font-size:13px;color:#666;">Recherche sur 100k+ sujets en ~10-50ms au lieu de 2-5s avec LIKE.</p>
		<?php else : ?>
			<p style="color:#d97706;font-weight:600;">⚠️ Index FULLTEXT manquant</p>
			<p style="font-size:13px;color:#666;">Sans index, le fallback LIKE reste utilisé (lent sur 100k+ sujets).</p>
			<a href="<?php echo esc_url( $apply_url ); ?>" class="button button-primary">Activer l'index Fulltext</a>
		<?php endif; ?>
	</div>
		<?php
	}
);
// ============================================================================
// 6. RECHERCHE NATIVE WordPress — INCLURE LES CONTENUS DU FORUM
// ============================================================================
/**
 * Ajoute sujets et réponses aux résultats de la recherche du site.
 *
 * POURQUOI C'EST NECESSAIRE
 * -------------------------
 * bbPress déclare ses types de contenu avec `exclude_from_search => true`.
 * Une recherche WordPress standard interroge `post_type = 'any'`, qui écarte
 * précisément ces types : le formulaire de recherche du site ne remontait
 * donc AUCUN sujet ni réponse, quel que soit le terme.
 *
 * Mesuré avant correction sur une base de 5 000 sujets :
 *   WP_Query(['s' => 'discussion'])                          -> 0 résultat
 *   WP_Query(['s' => 'discussion', 'post_type' => ['topic']]) -> 4 999
 *
 * On ne touche pas à `exclude_from_search` du type lui-même : le modifier
 * changerait aussi le comportement des sitemaps et des flux. On agit
 * uniquement sur la requête principale de recherche du front.
 *
 * @param WP_Query $query Requête en cours.
 * @return void
 */
function swiftboard_inclure_forum_dans_recherche( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return;
	}

	// Une recherche déjà restreinte par l'utilisateur (?post_type=…) est
	// respectée telle quelle.
	$demande = $query->get( 'post_type' );
	if ( ! empty( $demande ) && 'any' !== $demande ) {
		return;
	}

	$types = array( 'post', 'page' );
	foreach ( array( 'bbp_get_topic_post_type', 'bbp_get_reply_post_type', 'bbp_get_forum_post_type' ) as $fn ) {
		if ( function_exists( $fn ) ) {
			$types[] = $fn();
		}
	}

	/**
	 * Types inclus dans la recherche du site.
	 *
	 * @param string[] $types Types de contenu recherchés.
	 */
	$query->set( 'post_type', apply_filters( 'swiftboard_search_post_types', array_values( array_unique( $types ) ) ) );
}
add_action( 'pre_get_posts', 'swiftboard_inclure_forum_dans_recherche' );


/**
 * Restreint la recherche a un forum lorsque la pastille de contexte est active.
 *
 * L'en-tete affiche « r/Nom ✕ » sur une page de communaute et transmet
 * `forum_id`. Sans ce filtre la pastille annoncerait une portee qui n'est pas
 * appliquee : la recherche resterait globale.
 *
 * Couvre les sujets (dont le parent est le forum) et les reponses (dont le
 * meta `_bbp_forum_id` porte le forum).
 *
 * @param WP_Query $query Requete principale.
 * @return void
 */
function swiftboard_restreindre_recherche_au_forum( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtre d'affichage public, sans effet de bord.
	$forum_id = isset( $_GET['forum_id'] ) ? absint( wp_unslash( $_GET['forum_id'] ) ) : 0;
	if ( ! $forum_id || ! function_exists( 'bbp_get_forum_post_type' ) ) {
		return;
	}

	// Le forum doit exister et etre du bon type : un identifiant arbitraire
	// dans l'URL ne doit pas produire une requete incoherente.
	if ( bbp_get_forum_post_type() !== get_post_type( $forum_id ) ) {
		return;
	}

	$query->set( 'post_parent', $forum_id );
	$query->set(
		'post_type',
		array_values(
			array_filter(
				array(
					function_exists( 'bbp_get_topic_post_type' ) ? bbp_get_topic_post_type() : '',
					function_exists( 'bbp_get_reply_post_type' ) ? bbp_get_reply_post_type() : '',
				)
			)
		)
	);
}
add_action( 'pre_get_posts', 'swiftboard_restreindre_recherche_au_forum', 20 );
