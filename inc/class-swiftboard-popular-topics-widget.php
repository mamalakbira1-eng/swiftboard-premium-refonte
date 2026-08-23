<?php
if ( ! defined( 'ABSPATH' ))exit;

/**
 * Widget : Sujets populaires
 *
 * Affiche les sujets les plus votés/répondus du forum dans la sidebar.
 *
 * @package SwiftBoard
 * @since 2.0.0
 */
/**
 * Enregistrer le widget.
 *
 * @return void
 */
function swiftboard_register_popular_topics_widget() {
	register_widget( 'SwiftBoard_Popular_Topics_Widget' );
}
add_action( 'widgets_init', 'swiftboard_register_popular_topics_widget' );

/**
 * Class SwiftBoard_Popular_Topics_Widget
 *
 * @extends WP_Widget<array<string, mixed>>
 */
class SwiftBoard_Popular_Topics_Widget extends WP_Widget {

	/**
	 * Constructeur.
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct(
			'swiftboard_popular_topics',
			__( 'SwiftBoard · Sujets populaires', 'swiftboard' ),
			array(
				'description' => __( 'Affiche les sujets les plus populaires du forum (par réponses ou par votes).', 'swiftboard' ),
				'classname'   => 'swiftboard-popular-topics-widget',
			)
		);
	}

	/**
	 * Affichage du widget côté front.
	 *
	 * @param array<string, mixed> $args     Arguments du thème.
	 * @param array<string, mixed> $instance Valeurs du widget.
	 */
	public function widget( $args, $instance ): void {
		// Ne s'affiche que si bbPress est actif
		if ( ! function_exists( 'bbp_get_topic_post_type' ) ) {
			return;
		}

		$title       = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Sujets populaires', 'swiftboard' );
		$number      = ! empty( $instance['number'] ) ? max( 1, min( 20, (int) $instance['number'] ) ) : 5;
		$sort_by     = ! empty( $instance['sort_by'] ) ? $instance['sort_by'] : 'replies';
		$forum_id    = ! empty( $instance['forum_id'] ) ? (int) $instance['forum_id'] : 0;
		$show_votes  = ! empty( $instance['show_votes'] );
		$show_author = ! empty( $instance['show_author'] );

		// Construction de la requête
		$query_args = array(
			'post_type'      => bbp_get_topic_post_type(),
			'post_status'    => 'publish',
			'posts_per_page' => $number,
			'no_found_rows'  => true,
		);

		if ( $sort_by === 'replies' ) {
			// Trier par nombre de réponses (meta _bbp_reply_count)
			$query_args['meta_key'] = '_bbp_reply_count';
			$query_args['orderby']  = 'meta_value_num';
			$query_args['order']    = 'DESC';
		} elseif ( $sort_by === 'freshness' ) {
			// Sujets les plus récents avec activité
			$query_args['orderby'] = 'modified';
			$query_args['order']   = 'DESC';
		} elseif ( $sort_by === 'votes' ) {
			// Trier par votes SwiftBoard (si le meta existe)
			$query_args['meta_key'] = '_swiftboard_votes';
			$query_args['orderby']  = 'meta_value_num';
			$query_args['order']    = 'DESC';
		} else {
			// Par défaut : trier par date (sujets récents)
			$query_args['orderby'] = 'date';
			$query_args['order']   = 'DESC';
		}

		if ( $forum_id > 0 ) {
			$query_args['meta_query'] = array(
				array(
					'key'   => '_bbp_forum_id',
					'value' => $forum_id,
				),
			);
		}

		// meta_key + orderby=meta_value_num impose un INNER JOIN sur
		// wp_postmeta : un sujet sans la meta de tri DISPARAIT du widget.
		// Or _swiftboard_votes et _bbp_reply_count ne sont ecrits qu'a la
		// premiere activite : le widget « populaires » masquait donc tous les
		// sujets encore sans vote ni reponse. Le helper repasse en LEFT JOIN
		// (branche NOT EXISTS) tout en preservant le filtre par forum.
		if ( isset( $query_args['meta_key'] )
			&& function_exists( 'swiftboard_trier_par_meta_numerique' ) ) {
			swiftboard_trier_par_meta_numerique( $query_args, $query_args['meta_key'] );
		}

		$topics_query = new WP_Query( $query_args );

		if ( ! $topics_query->have_posts() ) {
			return; // Pas de sujets, on n'affiche rien
		}

		echo $args['before_widget']; // phpcs:ignore — WP standard

		if ( ! empty( $title ) ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
		}

		echo '<ul class="swiftboard-popular-topics-list">';

		while ( $topics_query->have_posts() ) :
			$topics_query->the_post();
			$topic_id    = get_the_ID();
			$topic_url   = bbp_get_topic_permalink( $topic_id );
			$topic_title = bbp_get_topic_title( $topic_id );
			$author_id   = bbp_get_topic_author_id( $topic_id );
			$author_name = bbp_get_topic_author_display_name( $topic_id );
			$reply_count = (int) bbp_get_topic_reply_count( $topic_id );
			$vote_count  = swiftboard_get_vote_count( $topic_id );
			$post_date   = get_the_date( 'c', $topic_id );
			?>
			<li class="popular-topic-item" itemscope itemtype="https://schema.org/DiscussionForumPosting">
				<a href="<?php echo esc_url( $topic_url ); ?>" class="popular-topic-link" itemprop="url">
					<span class="popular-topic-title" itemprop="headline"><?php echo esc_html( $topic_title ); ?></span>
					<span class="popular-topic-meta">
						<?php if ( $show_votes ) : ?>
							<span class="popular-topic-votes">▲ <?php echo esc_html( swiftboard_format_count( $vote_count ) ); ?></span>
						<?php endif; ?>
						<span class="popular-topic-replies">💬 <?php echo esc_html( swiftboard_format_count( $reply_count ) ); ?></span>
						<?php if ( $show_author ) : ?>
							<span class="popular-topic-author"><?php echo esc_html( $author_name ); ?></span>
						<?php endif; ?>
						<time datetime="<?php echo esc_attr( $post_date ); ?>" class="popular-topic-time"><?php echo esc_html( swiftboard_time_ago( $post_date ) ); ?></time>
					</span>
				</a>
			</li>
			<?php
		endwhile;

		echo '</ul>';

		wp_reset_postdata();

		echo $args['after_widget']; // phpcs:ignore — WP standard
	}

	/**
	 * Formulaire d'admin du widget.
	 *
	 * @param array<string, mixed> $instance Valeurs actuelles.
	 * @return string
	 */
	public function form( $instance ) {
		$title       = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Sujets populaires', 'swiftboard' );
		$number      = ! empty( $instance['number'] ) ? (int) $instance['number'] : 5;
		$sort_by     = ! empty( $instance['sort_by'] ) ? $instance['sort_by'] : 'replies';
		$forum_id    = ! empty( $instance['forum_id'] ) ? (int) $instance['forum_id'] : 0;
		$show_votes  = ! empty( $instance['show_votes'] ) ? (bool) $instance['show_votes'] : true;
		$show_author = ! empty( $instance['show_author'] ) ? (bool) $instance['show_author'] : false;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Titre :', 'swiftboard' ); ?></label>
			<input type="text" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
					value="<?php echo esc_attr( $title ); ?>" class="widefat">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"><?php esc_html_e( 'Nombre de sujets :', 'swiftboard' ); ?></label>
			<input type="number" id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>"
					value="<?php echo esc_attr( (string) $number ); ?>" min="1" max="20" class="tiny-text">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'sort_by' ) ); ?>"><?php esc_html_e( 'Trier par :', 'swiftboard' ); ?></label>
			<select id="<?php echo esc_attr( $this->get_field_id( 'sort_by' ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( 'sort_by' ) ); ?>" class="widefat">
				<option value="replies" <?php selected( $sort_by, 'replies' ); ?>><?php esc_html_e( 'Plus de réponses', 'swiftboard' ); ?></option>
				<option value="votes" <?php selected( $sort_by, 'votes' ); ?>><?php esc_html_e( 'Plus de votes', 'swiftboard' ); ?></option>
				<option value="freshness" <?php selected( $sort_by, 'freshness' ); ?>><?php esc_html_e( 'Plus récents (activité)', 'swiftboard' ); ?></option>
				<option value="recent" <?php selected( $sort_by, 'recent' ); ?>><?php esc_html_e( 'Plus récents (création)', 'swiftboard' ); ?></option>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'forum_id' ) ); ?>"><?php esc_html_e( 'Forum (ID, 0 = tous) :', 'swiftboard' ); ?></label>
			<input type="number" id="<?php echo esc_attr( $this->get_field_id( 'forum_id' ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( 'forum_id' ) ); ?>"
					value="<?php echo esc_attr( (string) $forum_id ); ?>" min="0" class="small-text">
		</p>
		<p>
			<input type="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'show_votes' ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( 'show_votes' ) ); ?>"
					<?php checked( $show_votes ); ?>>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_votes' ) ); ?>"><?php esc_html_e( 'Afficher les votes', 'swiftboard' ); ?></label>
		</p>
		<p>
			<input type="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'show_author' ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( 'show_author' ) ); ?>"
					<?php checked( $show_author ); ?>>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_author' ) ); ?>"><?php esc_html_e( 'Afficher l\'auteur', 'swiftboard' ); ?></label>
		</p>
		<?php
		// WP_Widget::form() est typee `string` par le coeur : la valeur
		// 'noform' y signale un widget sans reglages. Le formulaire est deja
		// affiche, on rend donc une chaine vide plutot que null.
		return '';
	}

	/**
	 * Sauvegarde du widget.
	 *
	 * @param array<string, mixed> $new_instance Nouvelles valeurs.
	 * @param array<string, mixed> $old_instance Anciennes valeurs.
	 * @return array<string, mixed> Valeurs nettoyées.
	 */
	public function update( $new_instance, $old_instance ): array {
		$instance = array();

		$instance['title']       = sanitize_text_field( $new_instance['title'] );
		$instance['number']      = max( 1, min( 20, (int) $new_instance['number'] ) );
		$instance['sort_by']     = in_array( $new_instance['sort_by'], array( 'replies', 'votes', 'freshness', 'recent' ), true ) ? $new_instance['sort_by'] : 'replies';
		$instance['forum_id']    = max( 0, (int) $new_instance['forum_id'] );
		$instance['show_votes']  = ! empty( $new_instance['show_votes'] ) ? 1 : 0;
		$instance['show_author'] = ! empty( $new_instance['show_author'] ) ? 1 : 0;

		return $instance;
	}
}

