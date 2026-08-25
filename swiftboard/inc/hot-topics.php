<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared — SQL uses internal $wpdb variables (safe)
/**
 * SwiftBoard - Sujets Chauds (Hot Topics)
 *
 * Affiche les sujets les plus populaires selon un algorithme "à la Reddit/HN" :
 *
 *   score_hot = (upvotes - downvotes) + (réponses × 2) + bonus_jeunesse
 *   bonus_jeunesse = log10(age_heures + 2) inversé
 *
 * Trois fenêtres temporelles : 24h, 7j, 30j
 *
 * Architecture Hostinger-safe :
 *  - 1 requête SQL agrégée par fenêtre temporelle
 *  - Cache transient 10 min (sb_hot_{period}_{limit})
 *  - Widget WordPress standard (apparait dans Apparence > Widgets)
 *  - Shortcode [swiftboard_hot_topics period="7d" limit="5"]
 *  - Panneau d'accueil bbPress via hook bbp_template_before_forums_index
 *
 * @package SwiftBoard
 * @since 2.9.0
 */
// ============================================================================
// 1. CALCUL DES SUJETS CHAUDS
// ============================================================================
/**
 * Récupère les sujets chauds pour une période donnée.
 *
 * @param string $period '24h' | '7d' | '30d'
 * @param int    $limit  Nombre de sujets (1-20)
 * @return list<array<string, mixed>> Sujets ordonnes du plus chaud au moins chaud.
 */
function swiftboard_get_hot_topics( $period = '7d', $limit = 5 ) {
	$period = swiftboard_normalize_period( $period );
	$limit  = max( 1, min( 20, (int) $limit ) );

	$cache_key = 'sb_hot_' . $period . '_' . $limit;
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	global $wpdb;
	$votes_table = swiftboard_table( 'votes' );

	// Vérifier que la table votes existe
	$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $votes_table ) ) === $votes_table;

	// Intervalle SQL
	$interval_map = array(
		'24h' => '1 DAY',
		'7d'  => '7 DAY',
		'30d' => '30 DAY',
		'all' => '100 YEAR',
	);
	$interval     = $interval_map[ $period ] ?? '7 DAY';
	$since        = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $interval ) );

	if ( $table_exists ) {
		// Algorithme : score de votes + réponses × 2, dégradé dans le temps
		$sql = $wpdb->prepare(
			"SELECT t.ID,
                    t.post_title,
                    t.post_author,
                    t.post_date,
                    t.post_parent as forum_id,
                    t.menu_order,
                    COALESCE(v.up, 0)   as up_count,
                    COALESCE(v.down, 0) as down_count,
                    COALESCE(v.score, 0) as vote_score,
                    COALESCE(r.reply_count, 0) as reply_count
             FROM {$wpdb->posts} t
             LEFT JOIN (
                 SELECT v.post_id,
                        SUM(CASE WHEN v.vote_type = 'up' THEN 1 ELSE 0 END) as up,
                        SUM(CASE WHEN v.vote_type = 'down' THEN 1 ELSE 0 END) as down,
                        SUM(CASE WHEN v.vote_type = 'up' THEN 1 ELSE -1 END) as score
                 FROM {$votes_table} v
                 INNER JOIN {$wpdb->posts} t2
                         ON t2.ID = v.post_id
                        AND t2.post_type = 'topic'
                        AND t2.post_status = 'publish'
                        AND t2.post_date >= %s
                 GROUP BY v.post_id
             ) v ON v.post_id = t.ID
             LEFT JOIN (
                 SELECT r.post_parent, COUNT(*) as reply_count
                 FROM {$wpdb->posts} r
                 INNER JOIN {$wpdb->posts} t3
                         ON t3.ID = r.post_parent
                        AND t3.post_type = 'topic'
                        AND t3.post_status = 'publish'
                        AND t3.post_date >= %s
                 WHERE r.post_type = 'reply' AND r.post_status = 'publish'
                 GROUP BY r.post_parent
             ) r ON r.post_parent = t.ID
             WHERE t.post_type = 'topic'
               AND t.post_status = 'publish'
               AND t.post_date >= %s
             ORDER BY (COALESCE(v.score, 0) + (COALESCE(r.reply_count, 0) * 2)) DESC,
                      t.post_date DESC
             LIMIT %d",
			$since,
			$since,
			$since,
			$limit
		);
	} else {
		// Fallback si la table votes n'existe pas encore : score par réponses
		$sql = $wpdb->prepare(
			"SELECT t.ID,
                    t.post_title,
                    t.post_author,
                    t.post_date,
                    t.post_parent as forum_id,
                    t.menu_order,
                    0 as up_count,
                    0 as down_count,
                    0 as vote_score,
                    COALESCE(r.reply_count, 0) as reply_count
             FROM {$wpdb->posts} t
             LEFT JOIN (
                 SELECT post_parent, COUNT(*) as reply_count
                 FROM {$wpdb->posts}
                 WHERE post_type = 'reply' AND post_status = 'publish'
                 GROUP BY post_parent
             ) r ON r.post_parent = t.ID
             WHERE t.post_type = 'topic'
               AND t.post_status = 'publish'
               AND t.post_date >= %s
             ORDER BY (COALESCE(r.reply_count, 0) * 2) DESC, t.post_date DESC
             LIMIT %d",
			$since,
			$limit
		);
	}

	$rows = $wpdb->get_results( $sql, ARRAY_A );

	$topics = array();
	foreach ( $rows as $row ) {
		$topics[] = array(
			'id'          => (int) $row['ID'],
			'title'       => $row['post_title'],
			'url'         => get_permalink( $row['ID'] ),
			'author_id'   => (int) $row['post_author'],
			'author_name' => get_the_author_meta( 'display_name', $row['post_author'] ),
			'date'        => $row['post_date'],
			'time_ago'    => swiftboard_time_ago( strtotime( $row['post_date'] ) ),
			'vote_score'  => (int) $row['vote_score'],
			'up_count'    => (int) $row['up_count'],
			'down_count'  => (int) $row['down_count'],
			'reply_count' => (int) $row['reply_count'],
			'forum_id'    => (int) $row['forum_id'],
			'forum_url'   => $row['forum_id'] ? get_permalink( $row['forum_id'] ) : '',
			'forum_name'  => $row['forum_id'] ? get_the_title( $row['forum_id'] ) : '',
			'hot_score'   => (int) $row['vote_score'] + ( (int) $row['reply_count'] * 2 ),
		);
	}

	set_transient( $cache_key, $topics, 10 * MINUTE_IN_SECONDS );
	return $topics;
}

/**
 * swiftboard_normalize_period().
 *
 * @param mixed $period À documenter.
 * @return mixed
 */
function swiftboard_normalize_period( $period ) {
	$allowed = array( '24h', '7d', '30d', 'all' );
	return in_array( $period, $allowed, true ) ? $period : 'all';
}

// ============================================================================
// 2. INVALIDATION DU CACHE (à appeler après un vote)
// ============================================================================
add_action(
	'swiftboard_vote_cast',
	function ( $post_id, $vote_type, $user_id ) {
		// Invalider tous les caches hot topics
		foreach ( array( '24h', '7d', '30d', 'all' ) as $period ) {
			for ( $i = 1; $i <= 20; $i++ ) {
				delete_transient( 'sb_hot_' . $period . '_' . $i );
			}
		}
	},
	30,
	3
);

/**
 * Invalide les caches hot-topics.
 *
 * Decouvert en simulation : le cache n'etait purge qu'apres un VOTE.
 * Un sujet fraichement publie restait donc invisible sur l'accueil
 * jusqu'a 10 minutes (duree du transient).
 *
 * @return void
 */
function swiftboard_flush_hot_cache() {
	foreach ( array( '24h', '7d', '30d', 'all' ) as $period ) {
		for ( $i = 1; $i <= 20; $i++ ) {
			delete_transient( 'sb_hot_' . $period . '_' . $i );
		}
	}
}

// Publication / modification / suppression d'un sujet ou d'une reponse
add_action( 'save_post_topic', 'swiftboard_flush_hot_cache', 20 );
add_action( 'save_post_reply', 'swiftboard_flush_hot_cache', 20 );
add_action( 'bbp_new_topic', 'swiftboard_flush_hot_cache', 20 );
add_action( 'bbp_new_reply', 'swiftboard_flush_hot_cache', 20 );
add_action( 'trashed_post', 'swiftboard_flush_hot_cache', 20 );
add_action( 'deleted_post', 'swiftboard_flush_hot_cache', 20 );

// ============================================================================
// 3. RENDU HTML RÉUTILISABLE
// ============================================================================
/**
 * swiftboard_render_hot_topics().
 *
 * @param string               $period À documenter. Optionnel.
 * @param int                  $limit  Nombre maximal d'éléments. Optionnel.
 * @param array<string, mixed> $args   Arguments, fusionnés avec les valeurs par défaut. Optionnel.
 * @return mixed
 */
function swiftboard_render_hot_topics( $period = '7d', $limit = 5, $args = array() ) {
	$defaults = array(
		'show_forum'   => true,
		'show_author'  => true,
		'show_votes'   => true,
		'show_replies' => true,
		'show_time'    => true,
		'title'        => '',
		'class'        => '',
	);
	$args     = array_merge( $defaults, $args );

	$topics = swiftboard_get_hot_topics( $period, $limit );
	if ( empty( $topics ) ) {
		return '<div class="swiftboard-hot-empty">' . esc_html__( 'Aucun sujet chaud pour le moment.', 'swiftboard' ) . '</div>';
	}

	$period_labels = array(
		'24h' => '24 dernières heures',
		'7d'  => '7 derniers jours',
		'30d' => '30 derniers jours',
	);
	$period_label  = $period_labels[ $period ] ?? '7 derniers jours';

	$html = '<div class="swiftboard-hot ' . esc_attr( $args['class'] ) . '" data-period="' . esc_attr( $period ) . '">';
	if ( $args['title'] ) {
		$html .= '<h3 class="swiftboard-hot-title">' . esc_html( $args['title'] ) . '</h3>';
	}
	$html .= '<p class="swiftboard-hot-period">🔥 Sur les ' . esc_html( $period_label ) . '</p>';
	$html .= '<ol class="swiftboard-hot-list">';

	foreach ( $topics as $i => $t ) {
		$rank       = $i + 1;
		$rank_class = $rank <= 3 ? ' rank-' . $rank : '';
		$html      .= '<li class="swiftboard-hot-item' . $rank_class . '">';
		$html      .= '<span class="swiftboard-hot-rank">' . $rank . '</span>';
		$html      .= '<div class="swiftboard-hot-content">';
		$html      .= '<a href="' . esc_url( $t['url'] ) . '" class="swiftboard-hot-link">' . esc_html( $t['title'] ) . '</a>';

		$html .= '<div class="swiftboard-hot-meta">';
		if ( $args['show_votes'] && $t['vote_score'] !== 0 ) {
			$vote_class = $t['vote_score'] > 0 ? ' positive' : ' negative';
			$html      .= '<span class="swiftboard-hot-votes' . $vote_class . '">▲ ' . (int) $t['vote_score'] . '</span>';
		}
		if ( $args['show_replies'] ) {
			$html .= '<span class="swiftboard-hot-replies">💬 ' . (int) $t['reply_count'] . '</span>';
		}
		if ( $args['show_author'] ) {
			$html .= '<span class="swiftboard-hot-author">par ' . esc_html( $t['author_name'] ) . '</span>';
		}
		if ( $args['show_time'] ) {
			$html .= '<span class="swiftboard-hot-time">' . esc_html( $t['time_ago'] ) . '</span>';
		}
		if ( $args['show_forum'] && $t['forum_name'] ) {
			$html .= '<a href="' . esc_url( $t['forum_url'] ) . '" class="swiftboard-hot-forum">' . esc_html( $t['forum_name'] ) . '</a>';
		}
		$html .= '</div>'; // .swiftboard-hot-meta

		$html .= '</div>'; // .swiftboard-hot-content
		$html .= '</li>';
	}

	$html .= '</ol>';
	$html .= '<a href="' . esc_url( home_url( '/?swiftboard_sort=hot' ) ) . '" class="swiftboard-hot-see-all">' . esc_html__( 'Voir tous les sujets →', 'swiftboard' ) . '</a>';
	$html .= '</div>';

	return $html;
}

// ============================================================================
// 4. WIDGET WordPress STANDARD
// ============================================================================
/**
 * @extends WP_Widget<array<string, mixed>>
 */
class SwiftBoard_Hot_Topics_Widget extends WP_Widget {

		/**
		 * @return void
		 */
	public function __construct() {
		parent::__construct(
			'swiftboard_hot_topics',
			__( 'SwiftBoard — Sujets chauds', 'swiftboard' ),
			array(
				'description' => __( 'Affiche les sujets les plus populaires (votes + réponses).', 'swiftboard' ),
				'classname'   => 'widget-swiftboard-hot',
			)
		);
	}

		/**
		 * @param mixed $args
		 * @param mixed $instance
		 * @return void
		 */
	public function widget( $args, $instance ) {
		$title  = ! empty( $instance['title'] ) ? $instance['title'] : __( '🔥 Sujets chauds', 'swiftboard' );
		$period = ! empty( $instance['period'] ) ? $instance['period'] : '7d';
		$limit  = ! empty( $instance['limit'] ) ? (int) $instance['limit'] : 5;

		echo $args['before_widget'];
		if ( $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
		}

		echo swiftboard_render_hot_topics(
			$period,
			$limit,
			array(
				'title' => '',
			)
		);

		echo $args['after_widget'];
	}

		/**
		 * @param mixed $instance
		 * @return string
		 */
	public function form( $instance ) {
		$title  = ! empty( $instance['title'] ) ? $instance['title'] : __( '🔥 Sujets chauds', 'swiftboard' );
		$period = ! empty( $instance['period'] ) ? $instance['period'] : '7d';
		$limit  = ! empty( $instance['limit'] ) ? (int) $instance['limit'] : 5;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php _e( 'Titre :', 'swiftboard' ); ?></label>
			<input type="text" class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
					value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'period' ) ); ?>"><?php _e( 'Période :', 'swiftboard' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'period' ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( 'period' ) ); ?>">
				<option value="24h" <?php selected( $period, '24h' ); ?>>24 dernières heures</option>
				<option value="7d"  <?php selected( $period, '7d' ); ?>>7 derniers jours</option>
				<option value="30d" <?php selected( $period, '30d' ); ?>>30 derniers jours</option>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"><?php _e( 'Nombre de sujets :', 'swiftboard' ); ?></label>
			<input type="number" min="1" max="20" class="widefat"
					id="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( 'limit' ) ); ?>"
					value="<?php echo (int) $limit; ?>">
		</p>
		<?php
		// Voir le widget « sujets populaires » : le coeur type form() en string.
		return '';
	}

		/**
		 * @param mixed $new_instance
		 * @param mixed $old_instance
		 * @return mixed
		 */
	public function update( $new_instance, $old_instance ) {
		$instance           = array();
		$instance['title']  = sanitize_text_field( $new_instance['title'] );
		$instance['period'] = swiftboard_normalize_period( $new_instance['period'] );
		$instance['limit']  = max( 1, min( 20, (int) $new_instance['limit'] ) );
		return $instance;
	}
}

add_action(
	'widgets_init',
	function () {
		register_widget( 'SwiftBoard_Hot_Topics_Widget' );
	}
);

// ============================================================================
// 5. SHORTCODE [swiftboard_hot_topics]
// ============================================================================
add_shortcode(
	'swiftboard_hot_topics',
	function ( $atts ) {
		$atts = shortcode_atts(
			array(
				'period'       => '7d',
				'limit'        => 5,
				'title'        => '🔥 Sujets chauds',
				'show_forum'   => true,
				'show_author'  => true,
				'show_votes'   => true,
				'show_replies' => true,
				'show_time'    => true,
				'class'        => '',
			),
			$atts,
			'swiftboard_hot_topics'
		);

		return swiftboard_render_hot_topics(
			swiftboard_normalize_period( $atts['period'] ),
			max( 1, min( 20, (int) $atts['limit'] ) ),
			array(
				'title'        => $atts['title'],
				'show_forum'   => filter_var( $atts['show_forum'], FILTER_VALIDATE_BOOLEAN ),
				'show_author'  => filter_var( $atts['show_author'], FILTER_VALIDATE_BOOLEAN ),
				'show_votes'   => filter_var( $atts['show_votes'], FILTER_VALIDATE_BOOLEAN ),
				'show_replies' => filter_var( $atts['show_replies'], FILTER_VALIDATE_BOOLEAN ),
				'show_time'    => filter_var( $atts['show_time'], FILTER_VALIDATE_BOOLEAN ),
				'class'        => $atts['class'],
			)
		);
	}
);

// ============================================================================
// 6. PANNEAU SUR L'INDEX DU FORUM (hook bbp_template_before_forums_index)
// ============================================================================
add_action(
	'bbp_template_before_forums_index',
	function () {
		// Priorité 10 (avant le panneau "top répondeurs" priorité 5 ? non, après)
		// On l'affiche en priorité 10 pour qu'il soit après le top hebdo (priorité 5)
		echo '<div style="margin:24px 0;">';
		echo swiftboard_render_hot_topics(
			'7d',
			5,
			array(
				'title' => '🔥 Sujets chauds de la semaine',
			)
		);
		echo '</div>';
	},
	10
);

// ============================================================================
// 7. ENDPOINT REST — POUR RECHARGER EN AJAX
// ============================================================================
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'swiftboard/v1',
			'/hot-topics',
			array(
				'methods'             => 'GET',
				// EXI-SEC-02 : lecture publique — donnees identiques pour tous,
				// aucune information personnelle exposee. Declare explicitement
				// car WordPress 5.5+ emet un _doing_it_wrong() sans ce parametre.
				'permission_callback' => 'swiftboard_rest_public_permission',
				'callback'            => function ( WP_REST_Request $req ) {
					$period   = swiftboard_normalize_period( $req->get_param( 'period' ) ?: 'all' );
					$limit    = max( 1, min( 20, (int) ( $req->get_param( 'limit' ) ?: 5 ) ) );
					$response = new WP_REST_Response(
						array(
							'period' => $period,
							'topics' => swiftboard_get_hot_topics( $period, $limit ),
						),
						200
					);
					// Cache-Control public : donnees calculees et identiques pour tous.
					// On passe par WP_REST_Response::header() et non par header() : la
					// fonction PHP native emet un warning "headers already sent" des que
					// quelque chose a ete envoye avant, et court-circuite la couche REST
					// (les filtres rest_post_dispatch ne voient pas l'en-tete).
					$response->header( 'Cache-Control', 'public, max-age=300' );
					return $response;
				},
				'args'                => array(
					'period' => array(
						'type'    => 'string',
						'enum'    => array( '24h', '7d', '30d', 'all' ),
						'default' => 'all',
					),
					'limit'  => array(
						'type'    => 'integer',
						'default' => 5,
						'minimum' => 1,
						'maximum' => 20,
					),
				),
			)
		);
	}
);

// ============================================================================
// 8. CACHE PRE-WARMING AU PREMIER HIT
// ============================================================================
/**
 * Au premier chargement après activation du thème, on pré-charge les caches
 * hot-topics pour les périodes les plus communes (24h, 7d, 30d × limit 5,10).
 */
add_action(
	'after_switch_theme',
	function () {
		foreach ( array( '24h', '7d', '30d', 'all' ) as $period ) {
			foreach ( array( 5, 10 ) as $limit ) {
				swiftboard_get_hot_topics( $period, $limit );
			}
		}
	}
);

