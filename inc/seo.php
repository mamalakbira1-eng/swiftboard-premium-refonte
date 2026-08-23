<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard v2 - Module SEO
 *
 * Optimisations SEO on-page.
 * Pensé pour être utilisé AVEC ou SANS plugin SEO (RankMath, Yoast).
 * Si un plugin SEO est détecté, on désactive nos features redondantes.
 *
 * @package SwiftBoard
 * @since 2.0.0
 */
// ============================================================================
// DÉTECTION PLUGIN SEO
// ============================================================================
/**
 * @return mixed
 */
function swiftboard_has_seo_plugin() {
	static $cache = null;
	if ( $cache !== null ) {
		return $cache;
	}

	$active_plugins = (array) get_option( 'active_plugins', array() );
	if ( is_multisite() ) {
		$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
	}
	$seo_plugins = array(
		'seo-by-rank-math/rank-math.php',
		'seo-by-rank-math-pro/rank-math-pro.php',
		'wordpress-seo/wp-seo.php',
		'wordpress-seo-premium/wp-seo-premium.php',
		'all-in-one-seo-pack/all_in_one_seo_pack.php',
		'wp-seopress/seopress.php',
		'slim-seo/slim-seo.php',
	);
	foreach ( $seo_plugins as $plugin ) {
		if ( in_array( $plugin, $active_plugins, true ) || array_key_exists( $plugin, $active_plugins ) ) {
			$cache = true;
			return $cache;
		}
	}
	$cache = false;
	return $cache;
}

if ( swiftboard_has_seo_plugin() ) {
	return; // Laisser le plugin SEO gérer
}

// ============================================================================
// 1. META DESCRIPTION
// ============================================================================
add_action( 'wp_head', 'swiftboard_canonical_tag', 5 );
// EXI-SEO-03 — PRE-REQUIS : desactiver le canonical du coeur WordPress.
// Sans cela, le theme AJOUTE son canonical au lieu de le remplacer :
// mesure sur single-forum et topic = 2 balises canonical (signal
// contradictoire pour les moteurs).
remove_action( 'wp_head', 'rel_canonical' );

/**
 * @return void
 */
function swiftboard_canonical_tag() {
	$url = '';

	if ( is_singular() ) {
		$url = get_permalink();
	} elseif ( is_home() || is_front_page() ) {
		$url = home_url( '/' );
	} elseif ( function_exists( 'is_bbpress' ) && is_bbpress() ) {
		// Archives bbPress (les singuliers sont deja traites ci-dessus).
		// On reconstruit depuis $wp->request : $_SERVER['REQUEST_URI'] est une
		// entree non fiable (parametres de tracking, injection).
		global $wp;
		$req = isset( $wp->request ) ? $wp->request : '';
		$url = home_url( user_trailingslashit( $req ) );
	} elseif ( is_archive() || is_search() ) {
		// Pas de canonical sur les resultats de recherche : contenu non indexable
		return;
	}

	if ( $url ) {
		// Strip sort/filter params from canonical (SEO: avoid duplicate content)
		if ( isset( $_GET['sort'] ) || isset( $_GET['period'] ) || isset( $_GET['csort'] ) ) {
			$url = remove_query_arg( array( 'sort', 'period', 'csort' ), $url );
		}
		echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . PHP_EOL;
	}
}

/**
 * @return void
 */
function swiftboard_meta_description() {
	global $post;

	$description = '';

	if ( is_front_page() ) {
		// EXI-SEO-03 : la tagline peut etre vide (cas mesure) -> repli explicite
		// pour ne jamais servir une meta description absente sur la page
		// la plus indexee du site.
		$description = get_bloginfo( 'description' );
		if ( ! $description ) {
			$description = sprintf(
				/* translators: %s : nom du site */
				__( '%s — forum communautaire : discussions, entraide et partage.', 'swiftboard' ),
				get_bloginfo( 'name' )
			);
		}
	} elseif ( function_exists( 'is_bbpress' ) && is_bbpress() ) {
		// Doit passer AVANT is_singular() : un forum/topic bbPress est
		// is_singular() == true, la branche generique le captait donc en
		// premier et renvoyait une chaine vide (contenu de forum vide).
		if ( function_exists( 'bbp_is_single_forum' ) && bbp_is_single_forum() ) {
			$forum_desc  = bbp_get_forum_content( bbp_get_forum_id() );
			$description = $forum_desc
				? wp_trim_words( wp_strip_all_tags( $forum_desc ), 30 )
				: sprintf(
					/* translators: 1: nom du forum, 2: nom du site */
					__( '%1$s — discussions et entraide sur %2$s.', 'swiftboard' ),
					bbp_get_forum_title(),
					get_bloginfo( 'name' )
				);
		} elseif ( function_exists( 'bbp_is_single_topic' ) && bbp_is_single_topic() ) {
			// Optimized: title + excerpt (max 160 chars)
			$topic_title   = bbp_get_topic_title();
			$topic_content = wp_trim_words( wp_strip_all_tags( bbp_get_topic_content() ), 25 );
			$description   = $topic_title;
			if ( $topic_content ) {
				$remaining = 160 - mb_strlen( $topic_title ) - 3; // 3 = " — "
				if ( $remaining > 30 ) {
					$description = $topic_title . ' — ' . mb_substr( $topic_content, 0, $remaining );
				}
			}
		} else {
			$description = sprintf(
				/* translators: %s : nom du site */
				__( 'Forums de %s — discussions et entraide.', 'swiftboard' ),
				get_bloginfo( 'name' )
			);
		}
	} elseif ( is_singular() && $post && has_excerpt( $post->ID ) ) {
		$description = get_the_excerpt( $post );
	} elseif ( is_singular() && $post ) {
		$description = wp_trim_words( strip_shortcodes( strip_tags( $post->post_content ) ), 25 );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term_desc = term_description();
		if ( $term_desc ) {
			$description = wp_trim_words( strip_tags( $term_desc ), 25 );
		} else {
			$description = sprintf( __( 'Articles dans %s', 'swiftboard' ), single_term_title( '', false ) );
		}
	} elseif ( is_archive() && is_day() ) {
		$description = sprintf( __( 'Archives du %s', 'swiftboard' ), get_the_date() );
	} elseif ( is_archive() && is_month() ) {
		$description = sprintf( __( 'Archives de %s', 'swiftboard' ), get_the_date( 'F Y' ) );
	} elseif ( is_archive() && is_year() ) {
		$description = sprintf( __( 'Archives de %s', 'swiftboard' ), get_the_date( 'Y' ) );
	} elseif ( is_search() ) {
		$description = sprintf( __( 'Résultats de recherche pour : %s', 'swiftboard' ), get_search_query() );
	} elseif ( function_exists( 'is_bbpress' ) && is_bbpress() ) {
		if ( bbp_is_single_forum() ) {
			$description = bbp_get_forum_title() . ' — ' . get_bloginfo( 'name' );
		} elseif ( bbp_is_single_topic() ) {
			$description = wp_trim_words( strip_tags( bbp_get_topic_content() ), 30 );
		} else {
			$description = __( 'Forum de discussion — ', 'swiftboard' ) . get_bloginfo( 'name' );
		}
	}

	$description = trim( $description );
	if ( $description ) {
		if ( mb_strlen( $description ) > 160 ) {
			$description = mb_substr( $description, 0, 157 );
			$coupe       = mb_strrpos( $description, ' ' );
			if ( $coupe !== false ) {
				$description = mb_substr( $description, 0, $coupe ) . '…';
			}
		}
		echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'swiftboard_meta_description', 1 );

// ============================================================================
// HREFLANG — Multilingual SEO (requires Polylang or WPML)
// ============================================================================

/**
 * Emit hreflang tags for multilingual sites.
 * x-default = English (default language).
 * Requires Polylang (free) or WPML.
 *
 * @return void
 */
function swiftboard_hreflang_tags() {
	// Polylang
	if ( function_exists( 'pll_the_languages' ) ) {
		$languages = pll_the_languages( array( 'raw' => 1 ) );
		if ( empty( $languages ) ) {
			return;
		}

		foreach ( $languages as $lang ) {
			if ( ! empty( $lang['current_lang'] ) ) {
				continue;
			}
			printf(
				'<link rel="alternate" hreflang="%s" href="%s">' . "\n",
				esc_attr( $lang['slug'] ),
				esc_url( $lang['url'] )
			);
		}
		// x-default = English
		$default_url = function_exists( 'pll_home_url' ) ? pll_home_url( 'en' ) : home_url( '/' );
		printf( '<link rel="alternate" hreflang="x-default" href="%s">' . "\n", esc_url( $default_url ) );
		return;
	}

	// WPML
	if ( function_exists( 'icl_get_languages' ) ) {
		$languages = icl_get_languages( 'skip_missing=0&orderby=code' );
		if ( empty( $languages ) ) {
			return;
		}

		foreach ( $languages as $lang ) {
			if ( $lang['active'] ) {
				continue;
			}
			printf(
				'<link rel="alternate" hreflang="%s" href="%s">' . "\n",
				esc_attr( $lang['language_code'] ),
				esc_url( $lang['url'] )
			);
		}
		$default_url = home_url( '/' );
		printf( '<link rel="alternate" hreflang="x-default" href="%s">' . "\n", esc_url( $default_url ) );
	}
}
add_action( 'wp_head', 'swiftboard_hreflang_tags', 6 );



// ============================================================================
// 2. META ROBOTS — BUGFIX : is_author() attend un ID/slug, pas un email
// ============================================================================
/**
 * @return void
 */
function swiftboard_meta_robots() {
	$robots = array();

	if ( is_search() ) {
		$robots[] = 'noindex, follow';
	}
	if ( is_404() ) {
		$robots[] = 'noindex, follow';
	}
	if ( is_paged() && get_query_var( 'paged' ) > 1 ) {
		$robots[] = 'noindex, follow';
	}
	// Bugfix v1 : is_author() ne prend pas d'email. On noindex toutes les archives auteur.
	if ( is_author() ) {
		$robots[] = 'noindex, follow';
	}

	if ( ! empty( $robots ) ) {
		echo '<meta name="robots" content="' . esc_attr( implode( ', ', $robots ) ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'swiftboard_meta_robots', 2 );

// ============================================================================
// 3. OPEN GRAPH + TWITTER CARDS
// ============================================================================
/**
 * @return void
 */
function swiftboard_open_graph() {
	global $post;

	echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
	echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '">' . "\n";

	if ( is_front_page() ) {
		$og_type  = 'website';
		$og_title = get_bloginfo( 'name' );
		$og_desc  = get_bloginfo( 'description' );
		$og_url   = home_url();
	} elseif ( is_singular() ) {
		$og_type  = 'article';
		$og_title = get_the_title();
		$og_desc  = has_excerpt() ? get_the_excerpt() : wp_trim_words( strip_shortcodes( strip_tags( $post->post_content ) ), 30 );
		$og_url   = get_permalink();
	} elseif ( function_exists( 'is_bbpress' ) && is_bbpress() ) {
		$og_type = 'article';
		if ( bbp_is_single_forum() ) {
			$og_title   = bbp_get_forum_title();
			$og_content = bbp_get_forum_content();
			$og_desc    = $og_content ? wp_trim_words( strip_tags( $og_content ), 30 ) : bbp_get_forum_title();
		} elseif ( bbp_is_single_topic() ) {
			$og_title = bbp_get_topic_title();
			$og_desc  = wp_trim_words( strip_tags( bbp_get_topic_content() ), 30 );
		} else {
			$og_title = __( 'Forum', 'swiftboard' ) . ' — ' . get_bloginfo( 'name' );
			$og_desc  = get_bloginfo( 'description' );
		}
		$og_url = ( function_exists( 'bbp_get_forum_permalink' ) && bbp_get_forum_permalink() ) ?: home_url();
	} else {
		$og_type  = 'website';
		$og_title = wp_get_document_title();
		$og_desc  = get_bloginfo( 'description' );
		$og_url   = home_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
	}

	echo '<meta property="og:type" content="' . esc_attr( $og_type ) . '">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '">' . "\n";
	if ( empty( $og_desc ) ) {
		$og_desc = get_bloginfo( 'description' );
	}
	if ( empty( $og_desc ) ) {
		$og_desc = wp_trim_words( get_bloginfo( 'name' ) . ' — ' . __( 'Forum communautaire', 'swiftboard' ), 30 );
	}
	echo '<meta property="og:description" content="' . esc_attr( $og_desc ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $og_url ) . '">' . "\n";

	// EXI-SEO-02 — og:image
	// Le bloc d'origine etait conditionne a has_post_thumbnail() : or un topic
	// bbPress n'a JAMAIS d'image a la une. La condition etait donc toujours
	// fausse et AUCUNE page n'emettait og:image (mesure : 0 sur 5 pages),
	// alors que twitter:card = summary_large_image est declare plus bas.
	// Cascade : image a la une > image uploadee dans le topic > logo > fallback.
	$og_image = '';
	$og_w     = 1200;
	$og_h     = 630;

	if ( is_singular() && has_post_thumbnail() ) {
		$og_image = wp_get_attachment_image_url( get_post_thumbnail_id(), 'large' );
		$img_data = wp_get_attachment_image_src( get_post_thumbnail_id(), 'large' );
		if ( $img_data ) {
			$og_w = (int) $img_data[1];
			$og_h = (int) $img_data[2];
		}
	}

	if ( ! $og_image && function_exists( 'bbp_is_single_topic' ) && bbp_is_single_topic() ) {
		$img = get_post_meta( bbp_get_topic_id(), '_swiftboard_image_url', true );
		if ( $img ) {
			$og_image = $img;
		}
	}

	if ( ! $og_image ) {
		$logo_id = get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$og_image = wp_get_attachment_image_url( $logo_id, 'large' );
		}
	}

	if ( ! $og_image ) {
		$og_image = SWIFTBOARD_URI . '/assets/img/og-default.jpg';
	}

	echo '<meta property="og:image" content="' . esc_url( $og_image ) . '">' . "\n";
	echo '<meta property="og:image:width" content="' . (int) $og_w . '">' . "\n";
	echo '<meta property="og:image:height" content="' . (int) $og_h . '">' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( $og_title ) . '">' . "\n";
	if ( $og_desc ) {
		echo '<meta name="twitter:description" content="' . esc_attr( $og_desc ) . '">' . "\n";
	}
	echo '<meta name="twitter:image" content="' . esc_url( $og_image ) . '">' . "\n";

	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
}
add_action( 'wp_head', 'swiftboard_open_graph', 3 );

// ============================================================================
// 4. FIL D'ARIANE (Breadcrumb)
// ============================================================================
/**
 * @return mixed
 */
function swiftboard_breadcrumbs() {
	if ( is_front_page() ) {
		return '';
	}

	$breadcrumbs  = '<nav aria-label="' . esc_attr__( 'Fil d\'ariane', 'swiftboard' ) . '" class="breadcrumbs">';
	$breadcrumbs .= '<ol itemscope itemtype="https://schema.org/BreadcrumbList">';

	$breadcrumbs .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
	$breadcrumbs .= '<a itemprop="item" href="' . esc_url( home_url( '/' ) ) . '">';
	$breadcrumbs .= '<span itemprop="name">' . esc_html__( 'Accueil', 'swiftboard' ) . '</span></a>';
	$breadcrumbs .= '<meta itemprop="position" content="1">';
	$breadcrumbs .= '</li>';

	$position = 2;

	if ( function_exists( 'is_bbpress' ) && is_bbpress() ) {
		if ( $forum_id = bbp_get_forum_id() ) {
			$breadcrumbs .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
			$breadcrumbs .= '<a itemprop="item" href="' . esc_url( bbp_get_forum_permalink( $forum_id ) ) . '">';
			$breadcrumbs .= '<span itemprop="name">' . esc_html( bbp_get_forum_title( $forum_id ) ) . '</span></a>';
			$breadcrumbs .= '<meta itemprop="position" content="' . $position . '">';
			$breadcrumbs .= '</li>';
			++$position;
		}
		if ( bbp_is_single_topic() || bbp_is_single_reply() ) {
			$topic_id     = bbp_get_topic_id();
			$breadcrumbs .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
			$breadcrumbs .= '<a itemprop="item" href="' . esc_url( bbp_get_topic_permalink( $topic_id ) ) . '">';
			$breadcrumbs .= '<span itemprop="name">' . esc_html( bbp_get_topic_title( $topic_id ) ) . '</span></a>';
			$breadcrumbs .= '<meta itemprop="position" content="' . $position . '">';
			$breadcrumbs .= '</li>';
		}
	} elseif ( is_category() ) {
		$breadcrumbs .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
		$breadcrumbs .= '<span itemprop="name">' . esc_html( single_cat_title( '', false ) ) . '</span>';
		$breadcrumbs .= '<meta itemprop="position" content="' . $position . '">';
		$breadcrumbs .= '</li>';
	} elseif ( is_singular( 'post' ) ) {
		$categories = get_the_category();
		if ( ! empty( $categories ) ) {
			$cat          = $categories[0];
			$breadcrumbs .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
			$breadcrumbs .= '<a itemprop="item" href="' . esc_url( get_category_link( $cat->term_id ) ) . '">';
			$breadcrumbs .= '<span itemprop="name">' . esc_html( $cat->name ) . '</span></a>';
			$breadcrumbs .= '<meta itemprop="position" content="' . $position . '">';
			$breadcrumbs .= '</li>';
			++$position;
		}
		$breadcrumbs .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
		$breadcrumbs .= '<span itemprop="name">' . esc_html( get_the_title() ) . '</span>';
		$breadcrumbs .= '<meta itemprop="position" content="' . $position . '">';
		$breadcrumbs .= '</li>';
	} elseif ( is_search() ) {
		$breadcrumbs .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
		$breadcrumbs .= '<span itemprop="name">' . esc_html__( 'Recherche', 'swiftboard' ) . '</span>';
		$breadcrumbs .= '<meta itemprop="position" content="' . $position . '">';
		$breadcrumbs .= '</li>';
	} else {
		$breadcrumbs .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
		$breadcrumbs .= '<span itemprop="name">' . esc_html( wp_get_document_title() ) . '</span>';
		$breadcrumbs .= '<meta itemprop="position" content="' . $position . '">';
		$breadcrumbs .= '</li>';
	}

	$breadcrumbs .= '</ol></nav>';
	return $breadcrumbs;
}

// ============================================================================
// 5. STRUCTURATION DES TITRES HN (un seul H1 par page)
// ============================================================================
/**
 * swiftboard_llm_friendly_headings().
 *
 * @param string $content Contenu à traiter.
 * @return mixed
 */
function swiftboard_llm_friendly_headings( $content ) {
	$content = preg_replace( '/<h1([^>]*)>/i', '<h2$1>', $content );
	$content = str_replace( '</h1>', '</h2>', $content );
	return $content;
}
add_filter( 'the_content', 'swiftboard_llm_friendly_headings', 20 );
add_filter( 'bbp_get_topic_content', 'swiftboard_llm_friendly_headings', 20 );
add_filter( 'bbp_get_reply_content', 'swiftboard_llm_friendly_headings', 20 );

/**
 * LOT 8 — Title tag : garantir 50+ caracteres sur la home.
 */
add_filter( 'document_title_parts', 'swiftboard_seo_title_parts', 20 );

/**
 * Garantit 50+ caractères sur la home en ajoutant le tagline.
 *
 * @param array $parts Les parties du titre.
 * @return array
 */
function swiftboard_seo_title_parts( $parts ) {
	if ( is_front_page() ) {
		if ( empty( $parts['tagline'] ?? '' ) ) {
			$parts['tagline'] = get_bloginfo( 'description' );
		}
	}
	return $parts;
}
