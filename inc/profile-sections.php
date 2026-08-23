<?php
if ( ! defined( 'ABSPATH' )) exit;

/**
 * SwiftBoard — Onglets du profil membre.
 *
 * EXI-ARCH-01 : extrait de inc/reddit-profile.php (603 lignes). Chaque onglet
 * est une fonction de rendu independante ; les regrouper ici laisse dans
 * reddit-profile.php la seule mecanique de navigation.
 *
 * @package SwiftBoard
 * @since 5.1.0
 */
// ============================================================================
// 3. RENDU DES ONGLETS
// ============================================================================
/**
 * swiftboard_profile_render_overview().
 *
 * @param int $user_id Identifiant de l'utilisateur.
 * @return void
 */
function swiftboard_profile_render_overview( $user_id ) {
	// Mix : 5 derniers sujets + 5 dernières réponses
	echo '<div class="sb-profile-section">';
	echo '<h3 class="sb-profile-section-title">' . esc_html__( 'Sujets récents', 'swiftboard' ) . '</h3>';
	swiftboard_profile_render_posts( $user_id, 5 );
	echo '</div>';
	echo '<div class="sb-profile-section">';
	echo '<h3 class="sb-profile-section-title">' . esc_html__( 'Réponses récentes', 'swiftboard' ) . '</h3>';
	swiftboard_profile_render_comments( $user_id, 5 );
	echo '</div>';

	// EXI-MBR-03 : reponses RECUES (ce que les autres ont ecrit sur mes sujets)
	echo '<div class="sb-profile-section">';
	echo '<h3 class="sb-profile-section-title">'
		. esc_html__( '💬 Dernières réponses à mes sujets', 'swiftboard' ) . '</h3>';
	swiftboard_profile_render_received_replies( $user_id, 10 );
	echo '</div>';
}

// ============================================================================
// EXI-MBR-03 — REPONSES RECUES SUR MES SUJETS
// ============================================================================
/**
 * Liste les reponses ecrites par d'AUTRES sur les sujets de l'utilisateur.
 *
 * L'onglet « Reponses » liste ce que l'utilisateur a ecrit ; cette vue montre
 * ce qu'il a recu — c'est la premiere chose qu'un membre veut voir en revenant.
 *
 * @param int $user_id Auteur des sujets.
 * @param int $limit   Nombre de reponses a lister.
 * @return void
 */
function swiftboard_profile_render_received_replies( $user_id, $limit = 10 ) {
	global $wpdb;
	$user_id = (int) $user_id;
	$limit   = max( 1, min( 50, (int) $limit ) );

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT r.ID, r.post_date, r.post_author, r.post_parent
           FROM {$wpdb->posts} r
           INNER JOIN {$wpdb->posts} t ON t.ID = r.post_parent
          WHERE r.post_type    = 'reply'
            AND r.post_status  = 'publish'
            AND t.post_author  = %d
            AND r.post_author <> %d
       ORDER BY r.post_date DESC
          LIMIT %d",
			$user_id,
			$user_id,
			$limit
		)
	);

	if ( empty( $rows ) ) {
		echo '<p class="sb-empty">'
			. esc_html__( 'Aucune réponse reçue pour le moment.', 'swiftboard' )
			. '</p>';
		return;
	}

	// Anti-N+1 : precharger auteurs et sujets en batch
	$author_ids = array_unique( array_filter( wp_list_pluck( $rows, 'post_author' ) ) );
	if ( ! empty( $author_ids ) ) {
		cache_users( $author_ids );
	}
	$topic_ids = array_unique( array_filter( wp_list_pluck( $rows, 'post_parent' ) ) );
	if ( ! empty( $topic_ids ) ) {
		_prime_post_caches( $topic_ids, false, false );
	}

	echo '<ul class="sb-received-replies">';
	foreach ( $rows as $r ) {
		$author = get_userdata( (int) $r->post_author );
		printf(
			'<li class="sb-received-reply">'
			. '<a href="%1$s"><strong>%2$s</strong> %3$s <em>%4$s</em></a>'
			. '<time datetime="%5$s">%6$s</time>'
			. '</li>',
			esc_url( get_permalink( (int) $r->ID ) ),
			esc_html( $author ? $author->display_name : __( 'Anonyme', 'swiftboard' ) ),
			esc_html__( 'a répondu à', 'swiftboard' ),
			esc_html( get_the_title( (int) $r->post_parent ) ),
			esc_attr( $r->post_date ),
			esc_html( swiftboard_time_ago( $r->post_date ) )
		);
	}
	echo '</ul>';
}

/**
 * swiftboard_profile_render_posts().
 *
 * @param int $user_id Identifiant de l'utilisateur.
 * @param int $limit   Nombre maximal d'éléments. Optionnel.
 * @return void
 */
function swiftboard_profile_render_posts( $user_id, $limit = 20 ) {
	$q = new WP_Query(
		array(
			'post_type'      => 'topic',
			'post_author'    => $user_id,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
	if ( ! $q->have_posts() ) {
		echo '<p style="text-align:center;color:var(--color-text-muted);padding:40px;">' . esc_html__( 'Aucun sujet.', 'swiftboard' ) . '</p>';
		return;
	}
	echo '<div class="sb-profile-list">';
	while ( $q->have_posts() ) {
		$q->the_post();
		$topic_id = get_the_ID();
		$votes    = swiftboard_get_vote_count( $topic_id );
		$replies  = function_exists( 'bbp_get_topic_reply_count' ) ? bbp_get_topic_reply_count( $topic_id, true ) : 0;
		$forum_id = wp_get_post_parent_id( $topic_id );
		?>
		<article class="sb-profile-list-item">
			<div class="sb-profile-list-votes">
				▲ <?php echo esc_html( swiftboard_format_count( $votes ) ); ?>
			</div>
			<div class="sb-profile-list-content">
				<h4><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>
				<div class="sb-profile-list-meta">
					<?php if ( $forum_id ) : ?>
					<a href="<?php echo esc_url( get_permalink( $forum_id ) ); ?>">r/<?php echo esc_html( get_the_title( $forum_id ) ); ?></a>
					<span>·</span>
					<?php endif; ?>
					<span>💬 <?php echo (int) $replies; ?> commentaires</span>
					<span>·</span>
					<span><?php echo esc_html( swiftboard_time_ago( strtotime( get_post_field( 'post_date' ) ) ) ); ?></span>
				</div>
			</div>
		</article>
	<?php
	}
	wp_reset_postdata();
	echo '</div>';
}

/**
 * swiftboard_profile_render_comments().
 *
 * @param int $user_id Identifiant de l'utilisateur.
 * @param int $limit   Nombre maximal d'éléments. Optionnel.
 * @return void
 */
function swiftboard_profile_render_comments($user_id, $limit = 20) {
	global $wpdb;
	$replies = $wpdb->get_results($wpdb->prepare(
		"SELECT ID, post_parent, post_content, post_date
		FROM {$wpdb->posts}
		WHERE post_author = %d AND post_type = 'reply' AND post_status = 'publish'
		ORDER BY post_date DESC
		LIMIT %d",
		$user_id,
		$limit
	));
	if (empty($replies)) {
		echo '<p style="text-align:center;color:var(--color-text-muted);padding:40px;">' . esc_html__('Aucune réponse.', 'swiftboard') . '</p>';
		return;
	}
	echo '<div class="sb-profile-list">';
	foreach ($replies as $r) {
		$topic_id = wp_get_post_parent_id($r->ID);
		$votes = swiftboard_get_vote_count($r->ID);
		$topic_title = $topic_id ? get_the_title($topic_id) : '';
		$topic_url = $topic_id ? get_permalink($topic_id) : '';
		?>
		<article class="sb-profile-list-item">
			<div class="sb-profile-list-votes">
				▲ <?php echo esc_html( swiftboard_format_count( $votes ) ); ?>
			</div>
			<div class="sb-profile-list-content">
				<div class="sb-profile-list-meta">
					<?php if ( $topic_url ) : ?>
					Réponse à <a href="<?php echo esc_url( $topic_url ); ?>"><?php echo esc_html( $topic_title ); ?></a>
					<?php else : ?>
					Réponse
					<?php endif; ?>
					· <span><?php echo esc_html( swiftboard_time_ago( strtotime( $r->post_date ) ) ); ?></span>
				</div>
				<div class="sb-profile-list-excerpt">
					<?php echo esc_html( wp_trim_words( wp_strip_all_tags( $r->post_content ), 30, '…' ) ); ?>
				</div>
			</div>
		</article>
		<?php
	}
	echo '</div>';
}

/**
 * swiftboard_profile_render_saved().
 *
 * @param int $user_id Identifiant de l'utilisateur.
 * @return void
 */
function swiftboard_profile_render_saved( $user_id ) {
	$topics = swiftboard_get_user_topics_list( $user_id, 'saved' );
	if ( empty( $topics ) ) {
		echo '<p style="text-align:center;color:var(--color-text-muted);padding:40px;">' . esc_html__( 'Aucun sujet sauvegardé.', 'swiftboard' ) . '</p>';
		return;
	}
	echo '<div class="sb-profile-list">';
	foreach ( $topics as $t ) {
		?>
		<article class="sb-profile-list-item">
			<div class="sb-profile-list-votes">▲ <?php echo esc_html( swiftboard_format_count( $t['votes'] ) ); ?></div>
			<div class="sb-profile-list-content">
				<h4><a href="<?php echo esc_url( $t['url'] ); ?>"><?php echo esc_html( $t['title'] ); ?></a></h4>
				<div class="sb-profile-list-meta">
					par <?php echo esc_html( $t['author_name'] ); ?> · 💬 <?php echo (int) $t['replies']; ?> commentaires
				</div>
			</div>
		</article>
		<?php
	}
	echo '</div>';
}

/**
 * swiftboard_profile_render_following().
 *
 * @param int $user_id Identifiant de l'utilisateur.
 * @return void
 */
function swiftboard_profile_render_following( $user_id ) {
	$topics = swiftboard_get_user_topics_list( $user_id, 'followed' );
	if ( empty( $topics ) ) {
		echo '<p style="text-align:center;color:var(--color-text-muted);padding:40px;">' . esc_html__( 'Vous ne suivez aucun sujet.', 'swiftboard' ) . '</p>';
		return;
	}
	echo '<div class="sb-profile-list">';
	foreach ( $topics as $t ) {
		?>
		<article class="sb-profile-list-item">
			<div class="sb-profile-list-votes">▲ <?php echo esc_html( swiftboard_format_count( $t['votes'] ) ); ?></div>
			<div class="sb-profile-list-content">
				<h4><a href="<?php echo esc_url( $t['url'] ); ?>"><?php echo esc_html( $t['title'] ); ?></a></h4>
				<div class="sb-profile-list-meta">
					par <?php echo esc_html( $t['author_name'] ); ?> · 💬 <?php echo (int) $t['replies']; ?> commentaires
				</div>
			</div>
		</article>
		<?php
	}
	echo '</div>';
}

/**
 * swiftboard_profile_render_trophies().
 *
 * @param int   $user_id       Identifiant de l'utilisateur.
 * @param mixed $rep           À documenter.
 * @param mixed $topics_count  À documenter.
 * @param mixed $replies_count À documenter.
 * @param mixed $weekly_rank   À documenter.
 * @return void
 */
function swiftboard_profile_render_trophies( $user_id, $rep, $topics_count, $replies_count, $weekly_rank ) {
	$trophies = array();

	// Trophy : Premier sujet
	if ($topics_count >= 1) $trophies[] = array(
		'icon' => '📝',
		'name' => 'Premier sujet',
		'desc' => 'Vous avez publié votre premier sujet',
	);
	// 10 sujets
	if ($topics_count >= 10) $trophies[] = array(
		'icon' => '📚',
		'name' => 'Prolifique',
		'desc' => '10 sujets publiés',
	);
	// 100 sujets
	if ($topics_count >= 100) $trophies[] = array(
		'icon' => '🎖️',
		'name' => 'Légende',
		'desc' => '100 sujets publiés',
	);

	// First reply
	if ($replies_count >= 1) $trophies[]   = array(
		'icon' => '💬',
		'name' => 'Bavard',
		'desc' => 'Première réponse publiée',
	);
	if ($replies_count >= 50) $trophies[]  = array(
		'icon' => '🗣️',
		'name' => 'Contributeur',
		'desc' => '50 réponses',
	);
	if ($replies_count >= 500) $trophies[] = array(
		'icon' => '🎤',
		'name' => 'Orateur',
		'desc' => '500 réponses',
	);

	// Karma
	if ($rep['score'] >= 5) $trophies[]    = array(
		'icon' => '⭐',
		'name' => 'Promu Membre',
		'desc' => '5 points de réputation',
	);
	if ($rep['score'] >= 50) $trophies[]   = array(
		'icon' => '🌟',
		'name' => 'Promu Pro',
		'desc' => '50 points de réputation',
	);
	if ($rep['score'] >= 500) $trophies[]  = array(
		'icon' => '💎',
		'name' => 'Expert',
		'desc' => '500 points de réputation',
	);
	if ($rep['score'] >= 5000) $trophies[] = array(
		'icon' => '👑',
		'name' => 'Légende du forum',
		'desc' => '5000 points de réputation',
	);

	// Top répondeur
	if ($weekly_rank === 1) $trophies[]   = array(
		'icon' => '🥇',
		'name' => 'Champion de la semaine',
		'desc' => 'Top 1 répondeur cette semaine',
	);
	elseif ($weekly_rank > 0) $trophies[] = array(
		'icon' => $weekly_rank === 2 ? '🥈' : '🥉',
		'name' => 'Top ' . $weekly_rank . ' semaine',
		'desc' => 'Top ' . $weekly_rank . ' répondeur cette semaine',
	);

	if ( empty( $trophies ) ) {
		echo '<p style="text-align:center;color:var(--color-text-muted);padding:40px;">' . esc_html__( 'Aucun trophée pour le moment. Participez pour en débloquer ! 🏆', 'swiftboard' ) . '</p>';
		return;
	}
	echo '<div class="sb-trophies-grid">';
	foreach ( $trophies as $t ) {
		?>
		<div class="sb-trophy">
			<div class="sb-trophy-icon"><?php echo esc_html( $t['icon'] ); ?></div>
			<div class="sb-trophy-name"><?php echo esc_html( $t['name'] ); ?></div>
			<div class="sb-trophy-desc"><?php echo esc_html( $t['desc'] ); ?></div>
		</div>
		<?php
	}
	echo '</div>';
}

// ============================================================================
// EXI-MBR-01 — ONGLET NOTIFICATIONS (prive : proprietaire du profil uniquement)
// ============================================================================
/**
 * Affiche le flux de notifications de l'utilisateur.
 *
 * Reserve au proprietaire du profil : double garde (ici + dans le routeur
 * d'onglets) pour qu'un acces direct par URL ne fuite rien.
 *
 * @param int $user_id Utilisateur affiche.
 * @param int $limit   Nombre de notifications a lister.
 * @return void
 */
function swiftboard_profile_render_notifications( $user_id, $limit = 30 ) {
	$user_id = (int) $user_id;
	if ( get_current_user_id() !== $user_id ) {
		return;
	}

	$notifs = swiftboard_get_notifications( $user_id, $limit );
	$unread = swiftboard_get_unread_count( $user_id );

	echo '<div class="sb-profile-notifs">';

	echo '<div class="sb-profile-notifs-head">';
	printf(
		'<h3>%s</h3>',
		esc_html(
			$unread > 0
				? sprintf(
					/* translators: %d : nombre de notifications non lues */
					_n( '%d notification non lue', '%d notifications non lues', $unread, 'swiftboard' ),
					$unread
				)
				: __( 'Notifications', 'swiftboard' )
		)
	);
	if ( $unread > 0 ) {
		printf(
			'<button type="button" class="sb-notif-markall-profile" data-user="%d">%s</button>',
			$user_id,
			esc_html__( 'Tout marquer comme lu', 'swiftboard' )
		);
	}
	echo '</div>';

	if ( empty( $notifs ) ) {
		echo '<p class="sb-empty">' . esc_html__( 'Aucune notification pour le moment.', 'swiftboard' ) . '</p>';
		echo '</div>';
		return;
	}

	// Anti-N+1 : precharger acteurs et permaliens en batch
	$actor_ids = array_unique( array_filter( array_column( $notifs, 'actor_id' ) ) );
	if ( ! empty( $actor_ids ) ) {
		cache_users( $actor_ids );
	}
	$post_ids = array_unique( array_filter( array_column( $notifs, 'post_id' ) ) );
	if ( ! empty( $post_ids ) ) {
		_prime_post_caches( $post_ids, false, false );
	}

	echo '<ul class="sb-notif-feed">';
	foreach ( $notifs as $n ) {
		$url   = ! empty( $n['post_id'] ) ? get_permalink( (int) $n['post_id'] ) : '';
		$actor = ! empty( $n['actor_id'] ) ? get_userdata( (int) $n['actor_id'] ) : null;

		printf(
			'<li class="sb-notif-item%1$s">'
			. '<span class="sb-notif-ico" aria-hidden="true">%2$s</span>'
			. '<a class="sb-notif-body" href="%3$s">'
			. '<strong>%4$s</strong><span>%5$s</span></a>'
			. '<time datetime="%6$s">%7$s</time>'
			. '</li>',
			empty( $n['is_read'] ) ? ' is-unread' : '',
			esc_html( swiftboard_notif_icon( $n['type'] ) ),
			esc_url( $url ?: home_url( '/' ) ),
			esc_html( $actor ? $actor->display_name . ' — ' . $n['title'] : $n['title'] ),
			esc_html( $n['excerpt'] ),
			esc_attr( $n['created_at'] ),
			esc_html( swiftboard_time_ago( $n['created_at'] ) )
		);
	}
	echo '</ul>';
	echo '</div>';
}

