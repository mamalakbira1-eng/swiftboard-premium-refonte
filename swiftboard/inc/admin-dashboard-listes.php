<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Ecrans de liste de l'administration.
 *
 * EXI-ARCH-01 : extrait de inc/admin-dashboard.php (673 lignes). Cinq tableaux
 * de donnees (sujets, reponses, votes, images, membres), rendu pur.
 *
 * La page Membres expose des adresses e-mail : elle est declaree en list_users
 * et non en moderate_comments — un editeur ne doit pas y acceder.
 *
 * @package SwiftBoard
 * @since 5.1.0
 */
// ============================================================================
// 4. PAGE SUJETS
// ============================================================================
/**
 * @return void
 */
function swiftboard_admin_topics_page() {
	// EXI-SEC-BLOQ-07 : la capability du menu ne protege pas l'appel direct
	// de la fonction via admin.php?page=... Controle explicite obligatoire.
	if ( ! current_user_can( 'moderate_comments' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'swiftboard' ), 403 );
	}

	global $wpdb;
	$status_filter = sanitize_text_field( wp_unslash( $_GET['status'] ?? 'all' ) );
	$search        = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );

	// Actions
	if ( isset( $_GET['action'] ) && isset( $_GET['id'] ) && check_admin_referer( 'sb_topic_action' ) ) {
		$id     = (int) intval( $_GET['id'] );
		$action = sanitize_text_field( wp_unslash( $_GET['action'] ) );
		switch ( $action ) {
			case 'approve':
				wp_update_post(array('ID' => $id, 'post_status' => 'publish'));
				break;
			case 'reject':
				wp_trash_post($id);
				break;
			case 'close':
				update_post_meta( $id, '_bbp_status', 'closed' );
				break;
			case 'open':
				update_post_meta( $id, '_bbp_status', 'open' );
				break;
			case 'sticky':
				update_post_meta( $id, '_bbp_sticky', '1' );
				break;
			case 'unsticky':
				delete_post_meta( $id, '_bbp_sticky' );
				break;
			case 'spam':
				wp_update_post(
					array(
						'ID'          => $id,
						'post_status' => 'spam',
					)
				);
				break;
			case 'trash':
				wp_trash_post( $id );
				break;
			case 'delete':
				wp_delete_post( $id, true );
				break;
		}
		echo '<div class="sb-notice success">' . esc_html__( 'Action effectuée.', 'swiftboard' ) . '</div>';
	}

	$args = array(
		'post_type'      => 'topic',
		'posts_per_page' => 50,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);
	if ($status_filter !== 'all') $args['post_status'] = $status_filter;
	if ($search) $args['s']                            = $search;
	$topics = get_posts( $args );

	?>
	<div class="sb-wrap">
		<h1>📝 Sujets du forum</h1>

		<div class="sb-filter-bar">
			<form method="GET" action="">
				<input type="hidden" name="page" value="swiftboard-topics">
				<select name="status">
					<option value="all" <?php selected( $status_filter, 'all' ); ?>><?php esc_html_e( 'Tous les statuts', 'swiftboard' ); ?></option>
					<option value="publish" <?php selected( $status_filter, 'publish' ); ?>><?php esc_html_e( 'Publiés', 'swiftboard' ); ?></option>
					<option value="pending" <?php selected( $status_filter, 'pending' ); ?>>En attente</option>
					<option value="spam" <?php selected( $status_filter, 'spam' ); ?>><?php esc_html_e( 'Spam', 'swiftboard' ); ?></option>
					<option value="trash" <?php selected( $status_filter, 'trash' ); ?>><?php esc_html_e( 'Corbeille', 'swiftboard' ); ?></option>
				</select>
				<input type="text" name="s" placeholder="Rechercher..." value="<?php echo esc_attr( $search ); ?>">
				<button type="submit"><?php esc_html_e( 'Filtrer', 'swiftboard' ); ?></button>
			</form>
		</div>

		<table class="sb-table">
			<thead>
				<tr>
					<th>ID</th><th><?php esc_html_e( 'Titre', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Auteur', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Réponses', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Score', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Statut', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Date', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Actions', 'swiftboard' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $topics as $t ) :
					$author      = get_userdata( (int) $t->post_author );
					$reply_count = (int) get_post_meta( $t->ID, '_bbp_reply_count', true );
					$score       = (int) get_post_meta( $t->ID, '_swiftboard_vote_score', true );
					$is_closed   = get_post_meta( $t->ID, '_bbp_status', true ) === 'closed';
					$is_sticky   = get_post_meta( $t->ID, '_bbp_sticky', true ) === '1';
					$nonce       = wp_create_nonce( 'sb_topic_action' );
					$base_url    = admin_url( 'admin.php?page=swiftboard-topics' );
					?>
				<tr>
					<td>#<?php echo intval( $t->ID ); ?></td>
					<td>
						<a href="<?php echo esc_url( get_permalink( $t->ID ) ); ?>" target="_blank"><?php echo esc_html( mb_substr( $t->post_title, 0, 60 ) ); ?></a>
						<?php
						if ( $is_sticky ) :
							?>
							<span class="sb-badge sticky">📌 Épinglé</span><?php endif; ?>
					</td>
					<td><?php echo $author ? esc_html( $author->display_name ) : 'Inconnu'; ?></td>
					<td><?php echo intval( $reply_count ); ?></td>
					<td><span class="sb-score <?php echo $score > 0 ? 'positive' : ( $score < 0 ? 'negative' : 'neutral' ); // phpcs:ignore"><?php echo intval($score); ?></span></td>
					<td>
						<?php
						if ( $is_closed ) :
							?>
							<span class="sb-badge closed"><?php esc_html_e( 'Fermé', 'swiftboard' ); ?></span>
							<?php
						else :
							?>
							<span class="sb-badge open"><?php esc_html_e( 'Ouvert', 'swiftboard' ); ?></span><?php endif; ?>
					</td>
					<td><?php echo esc_html( date( 'Y-m-d H:i', strtotime( $t->post_date ) ) ); ?></td>
				<td>
					<a href="<?php echo esc_url( get_permalink( $t->ID ) ); ?>" class="sb-action-btn view" target="_blank"><?php esc_html_e( 'Voir', 'swiftboard' ); ?></a>
					<?php if ($t->post_status === 'pending'): ?>
						<a href="<?php echo wp_nonce_url( $base_url . '&action=approve&id=' . $t->ID, 'sb_topic_action' ); ?>" class="sb-action-btn approve">✅ <?php esc_html_e('Valider', 'swiftboard'); ?></a>
						<a href="<?php echo wp_nonce_url( $base_url . '&action=reject&id=' . $t->ID, 'sb_topic_action' ); ?>" class="sb-action-btn reject" data-confirm="Rejeter ce sujet ?">❌ <?php esc_html_e('Rejeter', 'swiftboard'); ?></a>
					<?php else: ?>
						<?php if ( $is_closed ) : ?>
							<a href="<?php echo wp_nonce_url( $base_url . '&action=open&id=' . $t->ID, 'sb_topic_action' ); ?>" class="sb-action-btn open"><?php esc_html_e( 'Ouvrir', 'swiftboard' ); ?></a>
						<?php else : ?>
							<a href="<?php echo wp_nonce_url( $base_url . '&action=close&id=' . $t->ID, 'sb_topic_action' ); ?>" class="sb-action-btn close"><?php esc_html_e( 'Fermer', 'swiftboard' ); ?></a>
						<?php endif; ?>
						<?php if ( $is_sticky ) : ?>
							<a href="<?php echo wp_nonce_url( $base_url . '&action=unsticky&id=' . $t->ID, 'sb_topic_action' ); ?>" class="sb-action-btn"><?php esc_html_e( 'Désépingler', 'swiftboard' ); ?></a>
						<?php else : ?>
							<a href="<?php echo wp_nonce_url( $base_url . '&action=sticky&id=' . $t->ID, 'sb_topic_action' ); ?>" class="sb-action-btn"><?php esc_html_e( 'Épingler', 'swiftboard' ); ?></a>
						<?php endif; ?>
						<a href="<?php echo wp_nonce_url( $base_url . '&action=delete&id=' . $t->ID, 'sb_topic_action' ); ?>" class="sb-action-btn delete" data-confirm="Supprimer définitivement ?"><?php esc_html_e( 'Supprimer', 'swiftboard' ); ?></a>
					<?php endif; ?>
				</td>
				</tr>
				<?php endforeach; ?>
				<?php
				if ( empty( $topics ) ) :
					?>
					<tr><td colspan="8" class="sb-empty"><?php esc_html_e( 'Aucun sujet trouvé.', 'swiftboard' ); ?></td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}

// ============================================================================
// 5. PAGE RÉPONSES
// ============================================================================
/**
 * @return void
 */
function swiftboard_admin_replies_page() {
	// EXI-SEC-BLOQ-07 : la capability du menu ne protege pas l'appel direct
	// de la fonction via admin.php?page=... Controle explicite obligatoire.
	if ( ! current_user_can( 'moderate_comments' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'swiftboard' ), 403 );
	}

	$status_filter = sanitize_text_field( wp_unslash( $_GET['status'] ?? 'all' ) );
	$search        = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );

	// Actions de modération
	if ( isset( $_GET['action'] ) && isset( $_GET['id'] ) && check_admin_referer( 'sb_reply_action' ) ) {
		$id     = (int) intval( $_GET['id'] );
		$action = sanitize_text_field( wp_unslash( $_GET['action'] ) );
		switch ( $action ) {
			case 'approve':
				wp_update_post(array('ID' => $id, 'post_status' => 'publish'));
				break;
			case 'reject':
				wp_trash_post($id);
				break;
			case 'spam':
				wp_update_post(array('ID' => $id, 'post_status' => 'spam'));
				break;
			case 'delete':
				wp_delete_post($id, true);
				break;
		}
		echo '<div class="sb-notice success">Action effectuée.</div>';
	}

	$args = array(
		'post_type'      => 'reply',
		'posts_per_page' => 50,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);
	if ($status_filter !== 'all') $args['post_status'] = $status_filter;
	if ($search) $args['s']                            = $search;
	$replies = get_posts( $args );

	?>
	<div class="sb-wrap">
		<h1>↩️ Réponses du forum</h1>

		<div class="sb-filter-bar">
			<form method="GET" action="">
				<input type="hidden" name="page" value="swiftboard-replies">
			<select name="status">
				<option value="all" <?php selected( $status_filter, 'all' ); ?>><?php esc_html_e( 'Tous', 'swiftboard' ); ?></option>
				<option value="publish" <?php selected( $status_filter, 'publish' ); ?>><?php esc_html_e( 'Publiées', 'swiftboard' ); ?></option>
				<option value="pending" <?php selected( $status_filter, 'pending' ); ?>><?php esc_html_e( 'En attente', 'swiftboard' ); ?></option>
				<option value="spam" <?php selected( $status_filter, 'spam' ); ?>><?php esc_html_e( 'Spam', 'swiftboard' ); ?></option>
				<option value="trash" <?php selected( $status_filter, 'trash' ); ?>><?php esc_html_e( 'Corbeille', 'swiftboard' ); ?></option>
			</select>
				<input type="text" name="s" placeholder="Rechercher..." value="<?php echo esc_attr( $search ); ?>">
				<button type="submit"><?php esc_html_e( 'Filtrer', 'swiftboard' ); ?></button>
			</form>
		</div>

		<table class="sb-table">
			<thead><tr><th>ID</th><th><?php esc_html_e( 'Contenu', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Auteur', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Sujet parent', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Score', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Date', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Actions', 'swiftboard' ); ?></th></tr></thead>
			<tbody>
				<?php
				foreach ( $replies as $r ) :
					$author = get_userdata( (int) $r->post_author );
					$score  = (int) get_post_meta( $r->ID, '_swiftboard_vote_score', true );
					$parent = get_post( $r->post_parent );
					?>
				<tr>
					<td>#<?php echo intval( $r->ID ); ?></td>
					<td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;"><?php echo esc_html( mb_substr( wp_strip_all_tags( $r->post_content ), 0, 100 ) ); ?></td>
					<td><?php echo $author ? esc_html( $author->display_name ) : 'Inconnu'; ?></td>
					<td><?php echo $parent ? '<a href="' . esc_url( get_permalink( $parent->ID ) ) . '" target="_blank">' . esc_html( mb_substr( $parent->post_title, 0, 40 ) ) . '</a>' : '—'; ?></td>
					<td><span class="sb-score <?php echo $score > 0 ? 'positive' : ( $score < 0 ? 'negative' : 'neutral' ); // phpcs:ignore"><?php echo intval($score); ?></span></td>
					<td><?php echo esc_html( date( 'Y-m-d H:i', strtotime( $r->post_date ) ) ); ?></td>
				<td>
					<a href="<?php echo esc_url( get_permalink( $r->ID ) ); ?>" class="sb-action-btn view" target="_blank"><?php esc_html_e( 'Voir', 'swiftboard' ); ?></a>
					<?php if ($r->post_status === 'pending'): ?>
						<a href="<?php echo wp_nonce_url( admin_url('admin.php?page=swiftboard-replies&action=approve&id=' . $r->ID), 'sb_reply_action' ); ?>" class="sb-action-btn approve">✅ <?php esc_html_e('Valider', 'swiftboard'); ?></a>
						<a href="<?php echo wp_nonce_url( admin_url('admin.php?page=swiftboard-replies&action=reject&id=' . $r->ID), 'sb_reply_action' ); ?>" class="sb-action-btn reject" data-confirm="Rejeter ?">❌ <?php esc_html_e('Rejeter', 'swiftboard'); ?></a>
					<?php else: ?>
						<a href="<?php echo wp_nonce_url( admin_url('admin.php?page=swiftboard-replies&action=spam&id=' . $r->ID), 'sb_reply_action' ); ?>" class="sb-action-btn" data-confirm="Marquer comme spam ?">🚫 Spam</a>
						<a href="<?php echo wp_nonce_url( admin_url('admin.php?page=swiftboard-replies&action=delete&id=' . $r->ID), 'sb_reply_action' ); ?>" class="sb-action-btn delete" data-confirm="Supprimer définitivement ?"><?php esc_html_e( 'Supprimer', 'swiftboard' ); ?></a>
					<?php endif; ?>
				</td>
				</tr>
				<?php endforeach; ?>
				<?php
				if ( empty( $replies ) ) :
					?>
					<tr><td colspan="7" class="sb-empty"><?php esc_html_e( 'Aucune réponse trouvée.', 'swiftboard' ); ?></td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}

// ============================================================================
// 6. PAGE VOTES
// ============================================================================
/**
 * @return void
 */
function swiftboard_admin_votes_page() {
	// EXI-SEC-BLOQ-07 : la capability du menu ne protege pas l'appel direct
	// de la fonction via admin.php?page=... Controle explicite obligatoire.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'swiftboard' ), 403 );
	}

	global $wpdb;
	$votes_table = swiftboard_table( 'votes' );

	$total        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$votes_table}" );
	$up           = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$votes_table} WHERE vote_type = %s", 'up' ) );
	$down         = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$votes_table} WHERE vote_type = %s", 'down' ) );
	$unique_ips   = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT voter_ip) FROM {$votes_table}" );
	$unique_users = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT user_id) FROM {$votes_table} WHERE user_id > %d", 0 ) );

	// Top posts
	$top = $wpdb->get_results(
		"
        SELECT post_id,
               SUM(CASE WHEN vote_type = 'up' THEN 1 ELSE 0 END) as up_count,
               SUM(CASE WHEN vote_type = 'down' THEN 1 ELSE 0 END) as down_count,
               SUM(CASE WHEN vote_type = 'up' THEN 1 ELSE -1 END) as score
        FROM $votes_table GROUP BY post_id ORDER BY score DESC LIMIT 20
    "
	);

	// Flop posts
	$flop = $wpdb->get_results(
		"
        SELECT post_id,
               SUM(CASE WHEN vote_type = 'up' THEN 1 ELSE 0 END) as up_count,
               SUM(CASE WHEN vote_type = 'down' THEN 1 ELSE 0 END) as down_count,
               SUM(CASE WHEN vote_type = 'up' THEN 1 ELSE -1 END) as score
        FROM $votes_table GROUP BY post_id ORDER BY score ASC LIMIT 10
    "
	);

	?>
	<div class="sb-wrap">
		<h1>🗳️ Votes du forum</h1>

		<div class="sb-stats-grid">
			<div class="sb-stat-card"><div class="icon">🗳️</div><div class="number"><?php echo intval( $total ); ?></div><div class="label"><?php esc_html_e( 'Total votes', 'swiftboard' ); ?></div></div>
			<div class="sb-stat-card up"><div class="icon">▲</div><div class="number"><?php echo intval( $up ); ?></div><div class="label"><?php esc_html_e( 'Upvotes', 'swiftboard' ); ?></div></div>
			<div class="sb-stat-card down"><div class="icon">▼</div><div class="number"><?php echo intval( $down ); ?></div><div class="label"><?php esc_html_e( 'Downvotes', 'swiftboard' ); ?></div></div>
			<div class="sb-stat-card"><div class="icon">🌐</div><div class="number"><?php echo intval( $unique_ips ); ?></div><div class="label">IP uniques</div></div>
			<div class="sb-stat-card"><div class="icon">👥</div><div class="number"><?php echo intval( $unique_users ); ?></div><div class="label"><?php esc_html_e( 'Membres votants', 'swiftboard' ); ?></div></div>
		</div>

		<div class="sb-section-title">🔥 Top 20 posts (meilleur score)</div>
		<table class="sb-table">
			<thead><tr><th>#</th><th><?php esc_html_e( 'Post', 'swiftboard' ); ?></th><th>▲ Up</th><th>▼ Down</th><th><?php esc_html_e( 'Score', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Répartition', 'swiftboard' ); ?></th></tr></thead>
			<tbody>
				<?php
				foreach ( $top as $i => $p ) :
					$post        = get_post( $p->post_id );
					$total_votes = (int) $p->up_count + (int) $p->down_count;
					$up_pct      = $total_votes > 0 ? round( $p->up_count / $total_votes * 100 ) : 0;
					?>
				<tr>
					<td><?php echo intval( $i + 1 ); ?></td>
					<td><?php echo $post ? '<a href="' . esc_url( get_permalink( $p->post_id ) ) . '" target="_blank">' . esc_html( mb_substr( $post->post_title ?: wp_strip_all_tags( $post->post_content ), 0, 50 ) ) . '</a>' : '<em>' . esc_html__( 'Supprimé', 'swiftboard' ) . '</em>'; ?></td>
					<td style="color:#006cbd;font-weight:700;">▲ <?php echo intval( $p->up_count ); ?></td>
					<td style="color:#7193ff;font-weight:700;">▼ <?php echo intval( $p->down_count ); ?></td>
					<td><span class="sb-score <?php echo $p->score >= 0 ? 'positive' : 'negative'; ?>"><?php echo ( $p->score >= 0 ? '+' : '' ) . intval( $p->score ); ?></span></td>
					<td style="min-width:120px;">
						<div class="sb-progress" style="display:flex;">
							<div class="sb-progress-bar up" style="width:<?php echo intval( $up_pct ); ?>%;"></div>
							<div class="sb-progress-bar down" style="width:<?php echo 100 - $up_pct; ?>%;"></div>
						</div>
					</td>
				</tr>
				<?php endforeach; ?>
				<?php
				if ( empty( $top ) ) :
					?>
					<tr><td colspan="6" class="sb-empty"><?php esc_html_e( 'Aucun vote.', 'swiftboard' ); ?></td></tr><?php endif; ?>
			</tbody>
		</table>

		<div class="sb-section-title">💀 Flop 10 posts (pire score)</div>
		<table class="sb-table">
			<thead><tr><th>#</th><th><?php esc_html_e( 'Post', 'swiftboard' ); ?></th><th>▲ Up</th><th>▼ Down</th><th><?php esc_html_e( 'Score', 'swiftboard' ); ?></th></tr></thead>
			<tbody>
				<?php
				foreach ( $flop as $i => $p ) :
					$post = get_post( $p->post_id );
					?>
				<tr>
					<td><?php echo intval( $i + 1 ); ?></td>
					<td><?php echo $post ? '<a href="' . esc_url( get_permalink( $p->post_id ) ) . '" target="_blank">' . esc_html( mb_substr( $post->post_title ?: wp_strip_all_tags( $post->post_content ), 0, 50 ) ) . '</a>' : '<em>' . esc_html__( 'Supprimé', 'swiftboard' ) . '</em>'; ?></td>
					<td style="color:#006cbd;">▲ <?php echo intval( $p->up_count ); ?></td>
					<td style="color:#7193ff;">▼ <?php echo intval( $p->down_count ); ?></td>
					<td><span class="sb-score <?php echo $p->score >= 0 ? 'positive' : 'negative'; ?>"><?php echo ( $p->score >= 0 ? '+' : '' ) . intval( $p->score ); ?></span></td>
				</tr>
				<?php endforeach; ?>
				<?php
				if ( empty( $flop ) ) :
					?>
					<tr><td colspan="5" class="sb-empty"><?php esc_html_e( 'Aucun vote négatif.', 'swiftboard' ); ?></td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}

// ============================================================================
// 7. PAGE IMAGES
// ============================================================================
/**
 * @return void
 */
function swiftboard_admin_images_page() {
	// EXI-SEC-BLOQ-07 : la capability du menu ne protege pas l'appel direct
	// de la fonction via admin.php?page=... Controle explicite obligatoire.
	if ( ! current_user_can( 'moderate_comments' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'swiftboard' ), 403 );
	}

	global $wpdb;
	$uploads_table = swiftboard_table( 'uploads' );
	$status_filter = sanitize_text_field( wp_unslash( $_GET['status'] ?? 'all' ) );

	// Actions
	if ( isset( $_GET['action'] ) && isset( $_GET['id'] ) && check_admin_referer( 'sb_image_action' ) ) {
		$id     = (int) intval( $_GET['id'] );
		$action = sanitize_text_field( wp_unslash( $_GET['action'] ) );
		if ( $action === 'approve' ) {
			$wpdb->update(
				$uploads_table,
				array(
					'status'       => 'approved',
					'moderated_by' => get_current_user_id(),
					'moderated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $id )
			);
		} elseif ( $action === 'reject' ) {
			$img = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $uploads_table WHERE id = %d", $id ) );
			if ($img && file_exists( $img->filepath )) unlink( $img->filepath );
			$wpdb->update(
				$uploads_table,
				array(
					'status'       => 'rejected',
					'moderated_by' => get_current_user_id(),
					'moderated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $id )
			);
		}
		echo '<div class="sb-notice success">Image ' . ( $action === 'approve' ? 'approuvée' : 'rejetée' ) . '.</div>';
	}

	$where   = $status_filter !== 'all' ? $wpdb->prepare( 'WHERE status = %s', $status_filter ) : '';
	$pag_img = function_exists( 'swiftboard_admin_pagination_args' )
		? swiftboard_admin_pagination_args( 50, 100 )
		: array(
			'page'   => 1,
			'per'    => 50,
			'offset' => 0,
		);
	// $where is built from a status whitelist above (never raw user SQL).
	$total_images = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$uploads_table} {$where}" );
	$images       = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$uploads_table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
			$pag_img['per'],
			$pag_img['offset']
		)
	);

	$pending  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$uploads_table} WHERE status = %s", 'pending' ) );
	$approved = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$uploads_table} WHERE status = %s", 'approved' ) );
	$rejected = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$uploads_table} WHERE status = %s", 'rejected' ) );

	?>
	<div class="sb-wrap">
		<h1>🖼️ Images du forum</h1>

		<div class="sb-stats-grid">
			<div class="sb-stat-card warning"><div class="icon">⏳</div><div class="number"><?php echo intval( $pending ); ?></div><div class="label">En attente</div></div>
			<div class="sb-stat-card success"><div class="icon">✅</div><div class="number"><?php echo intval( $approved ); ?></div><div class="label"><?php esc_html_e( 'Approuvées', 'swiftboard' ); ?></div></div>
			<div class="sb-stat-card danger"><div class="icon">❌</div><div class="number"><?php echo intval( $rejected ); ?></div><div class="label"><?php esc_html_e( 'Rejetées', 'swiftboard' ); ?></div></div>
		</div>

		<div class="sb-filter-bar">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=swiftboard-admin-images' ) ); ?>" class="sb-action-btn <?php echo $status_filter === 'all' ? '' : 'view'; ?>"><?php esc_html_e( 'Toutes', 'swiftboard' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=swiftboard-admin-images&status=pending' ) ); ?>" class="sb-action-btn <?php echo $status_filter === 'pending' ? '' : 'view'; ?>">⏳ En attente</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=swiftboard-admin-images&status=approved' ) ); ?>" class="sb-action-btn <?php echo $status_filter === 'approved' ? '' : 'view'; ?>">✅ Approuvées</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=swiftboard-admin-images&status=rejected' ) ); ?>" class="sb-action-btn <?php echo $status_filter === 'rejected' ? '' : 'view'; ?>">❌ Rejetées</a>
		</div>

		<table class="sb-table">
			<thead><tr><th><?php esc_html_e( 'Image', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Utilisateur', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Taille originale', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Taille AVIF', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Économie', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Statut', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Date', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Actions', 'swiftboard' ); ?></th></tr></thead>
			<tbody>
				<?php
				foreach ( $images as $img ) :
					// La table declare une colonne « id » MINUSCULE
					// (inc/image-upload-schema.php). Le rendu lisait « ->ID » :
					// propriete inexistante, donc null, donc des liens
					// « &id= » vides — la moderation etait inoperante et PHP
					// emettait « Undefined property: stdClass::$ID ».
					$user    = get_userdata( $img->user_id );
					$savings = $img->original_size > 0 ? round( ( 1 - $img->file_size / $img->original_size ) * 100 ) : 0;
					$nonce   = wp_nonce_url( admin_url( 'admin.php?page=swiftboard-admin-images&action=' . ( $img->status === 'pending' ? 'approve' : 'reject' ) . '&id=' . $img->id ), 'sb_image_action' );
					?>
				<tr>
					<td><img src="<?php echo esc_url( $img->image_url ); ?>" alt="<?php echo esc_attr( $img->status ); ?>" style="max-width:80px;max-height:80px;border-radius:6px;object-fit:cover;"></td>
					<td><?php echo $user ? esc_html( $user->display_name ) : 'Inconnu'; ?></td>
					<td><?php echo size_format( $img->original_size ); ?></td>
					<td><?php echo size_format( $img->file_size ); ?></td>
					<td style="color:#16a34a;font-weight:700;">-<?php echo intval( $savings ); ?>%</td>
					<td><span class="sb-badge <?php echo esc_attr( $img->status ); ?>"><?php echo esc_attr( $img->status ); ?></span></td>
					<td><?php echo esc_html( $img->created_at ); ?></td>
					<td>
						<?php if ( $img->status === 'pending' ) : ?>
							<a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=swiftboard-admin-images&action=approve&id=' . $img->id ), 'sb_image_action' ); ?>" class="sb-action-btn approve">✅ Approuver</a>
							<a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=swiftboard-admin-images&action=reject&id=' . $img->id ), 'sb_image_action' ); ?>" class="sb-action-btn reject" data-confirm="Supprimer ?">❌ Rejeter</a>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
				<?php
				if ( empty( $images ) ) :
					?>
					<tr><td colspan="8" class="sb-empty"><?php esc_html_e( 'Aucune image.', 'swiftboard' ); ?></td></tr><?php endif; ?>
			</tbody>
		</table>
		<?php
		if ( function_exists( 'swiftboard_admin_render_pagination' ) ) {
			swiftboard_admin_render_pagination( (int) $total_images, (int) $pag_img['per'], (int) $pag_img['page'] );
		}
		?>
	</div>
	<?php
}

// ============================================================================
// 8. PAGE UTILISATEURS
// ============================================================================
/**
 * @return void
 */
function swiftboard_admin_users_page() {
	// EXI-SEC-BLOQ-07 : la capability du menu ne protege pas l'appel direct
	// de la fonction via admin.php?page=... Controle explicite obligatoire.
	if ( ! current_user_can( 'list_users' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'swiftboard' ), 403 );
	}

	global $wpdb;
	$votes_table   = swiftboard_table( 'votes' );
	$uploads_table = swiftboard_table( 'uploads' );

	$force = isset( $_GET['sb_refresh_stats'] ); // capability already enforced above
	if ( $force ) {
		check_admin_referer( 'sb_refresh_user_stats' );
		if ( function_exists( 'swiftboard_admin_flush_user_stats_cache' ) ) {
			swiftboard_admin_flush_user_stats_cache();
		}
	}
	$pag         = function_exists( 'swiftboard_admin_pagination_args' )
		? swiftboard_admin_pagination_args( 50, 100 )
		: array(
			'page'   => 1,
			'per'    => 50,
			'offset' => 0,
		);
	$stats       = function_exists( 'swiftboard_admin_query_user_stats' )
		? swiftboard_admin_query_user_stats( $pag['per'], $pag['offset'], (bool) $force )
		: array(
			'rows'  => array(),
			'total' => 0,
		);
	$users       = $stats['rows'];
	$total_users = (int) $stats['total'];

	?>
	<div class="sb-wrap">
		<h1>👥 Utilisateurs du forum</h1>
		<?php
		$refresh_url = wp_nonce_url( add_query_arg( 'sb_refresh_stats', '1' ), 'sb_refresh_user_stats' );
		echo '<p><a class="button" href="' . esc_url( $refresh_url ) . '">' . esc_html__( 'Rafraîchir les stats', 'swiftboard' ) . '</a> ';
		echo esc_html( sprintf( __( '(%d utilisateurs actifs)', 'swiftboard' ), (int) $total_users ) ) . '</p>';
		if ( function_exists( 'swiftboard_admin_render_pagination' ) ) {
			swiftboard_admin_render_pagination( (int) $total_users, (int) $pag['per'], (int) $pag['page'] );
		}
		?>

		<table class="sb-table">
			<thead><tr><th><?php esc_html_e( 'Utilisateur', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Email', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Sujets', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Réponses', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Images', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Votes', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Total activité', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Inscrit le', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Actions', 'swiftboard' ); ?></th></tr></thead>
			<tbody>
				<?php
				foreach ( $users as $u ) :
					$total = $u->topics + $u->replies;
					?>
				<tr>
					<td>
						<span class="sb-avatar" style="background:#006cbd;"><?php echo mb_strtoupper( mb_substr( $u->display_name, 0, 1 ) ); ?></span>
						<?php echo esc_html( $u->display_name ); ?>
					</td>
					<td><?php echo esc_html( $u->user_email ); ?></td>
					<td><?php echo intval( $u->topics ); ?></td>
					<td><?php echo intval( $u->replies ); ?></td>
					<td><?php echo intval( $u->images ); ?></td>
					<td><?php echo intval( $u->votes ); ?></td>
					<td><strong><?php echo intval( $total ); ?></strong></td>
					<td><?php echo esc_html( date( 'Y-m-d', strtotime( $u->user_registered ) ) ); ?></td>
					<td>
						<a href="<?php echo function_exists( 'bbp_get_user_profile_url' ) ? esc_url( bbp_get_user_profile_url( $u->ID ) ) : '#'; ?>" class="sb-action-btn view" target="_blank"><?php esc_html_e( 'Profil', 'swiftboard' ); ?></a>
						<a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . (int) $u->ID ) ); ?>" class="sb-action-btn view"><?php esc_html_e( 'Éditer', 'swiftboard' ); ?></a> | <a href="#" style="color:#dc2626;" data-sb-action="ban">🚫 Bannir</a>
					</td>
				</tr>
				<?php endforeach; ?>
				<?php
				if ( empty( $users ) ) :
					?>
					<tr><td colspan="9" class="sb-empty"><?php esc_html_e( 'Aucun utilisateur actif.', 'swiftboard' ); ?></td></tr><?php endif; ?>
			</tbody>
		</table>
		<?php
		if ( function_exists( 'swiftboard_admin_render_pagination' ) ) {
			swiftboard_admin_render_pagination( (int) $total_users, (int) $pag['per'], (int) $pag['page'] );
		}
		?>
	</div>
	<?php
}


// ============================================================================
// 9. PAGE COMMUNAUTÉS (Subreddits / Forums) — Modération
// ============================================================================
function swiftboard_admin_forums_page() {
	if ( ! current_user_can( 'moderate_comments' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'swiftboard' ), 403 );
	}

	$status_filter = sanitize_text_field( wp_unslash( $_GET['status'] ?? 'all' ) );

	// Actions
	if ( isset( $_GET['action'] ) && isset( $_GET['id'] ) && check_admin_referer( 'sb_forum_action' ) ) {
		$id     = (int) intval( $_GET['id'] );
		$action = sanitize_text_field( wp_unslash( $_GET['action'] ) );
		switch ( $action ) {
			case 'approve':
				wp_update_post(array('ID' => $id, 'post_status' => 'publish'));
				break;
			case 'reject':
				wp_trash_post($id);
				break;
			case 'delete':
				wp_delete_post($id, true);
				break;
		}
		echo '<div class="sb-notice success">Action effectuée.</div>';
	}

	$args = array(
		'post_type'      => 'forum',
		'posts_per_page' => 50,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);
	if ($status_filter !== 'all') $args['post_status'] = $status_filter;
	$forums = get_posts( $args );

	$pending_count = count(get_posts(['post_type'=>'forum','post_status'=>'pending','numberposts'=>-1]));

	?>
	<div class="sb-wrap">
		<h1>🏛️ <?php esc_html_e('Communautés (Subreddits)', 'swiftboard'); ?></h1>

		<?php if ($pending_count > 0): ?>
		<div class="sb-notice" style="background:#fef3c7;color:#d97706;padding:12px 16px;border-radius:8px;margin-bottom:16px;">
			⏳ <?php echo intval($pending_count); ?> <?php esc_html_e('communauté(s) en attente de validation.', 'swiftboard'); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=swiftboard-forums&status=pending' ) ); ?>" style="font-weight:700;margin-left:8px;"><?php esc_html_e('Voir →', 'swiftboard'); ?></a>
		</div>
		<?php endif; ?>

		<div class="sb-filter-bar">
			<form method="GET" action="">
				<input type="hidden" name="page" value="swiftboard-forums">
				<select name="status">
					<option value="all" <?php selected( $status_filter, 'all' ); ?>><?php esc_html_e( 'Tous', 'swiftboard' ); ?></option>
					<option value="publish" <?php selected( $status_filter, 'publish' ); ?>><?php esc_html_e( 'Publiés', 'swiftboard' ); ?></option>
					<option value="pending" <?php selected( $status_filter, 'pending' ); ?>><?php esc_html_e( 'En attente', 'swiftboard' ); ?></option>
					<option value="trash" <?php selected( $status_filter, 'trash' ); ?>><?php esc_html_e( 'Corbeille', 'swiftboard' ); ?></option>
				</select>
				<button type="submit"><?php esc_html_e( 'Filtrer', 'swiftboard' ); ?></button>
			</form>
		</div>

		<table class="sb-table">
			<thead><tr>
				<th>ID</th>
				<th><?php esc_html_e('Nom', 'swiftboard'); ?></th>
				<th><?php esc_html_e('Auteur', 'swiftboard'); ?></th>
				<th><?php esc_html_e('Sujets', 'swiftboard'); ?></th>
				<th><?php esc_html_e('Membres', 'swiftboard'); ?></th>
				<th><?php esc_html_e('Statut', 'swiftboard'); ?></th>
				<th><?php esc_html_e('Date', 'swiftboard'); ?></th>
				<th><?php esc_html_e('Actions', 'swiftboard'); ?></th>
			</tr></thead>
			<tbody>
				<?php foreach ( $forums as $f ) :
					$author = get_userdata( (int) $f->post_author );
					$topics = function_exists('bbp_get_forum_topic_count') ? bbp_get_forum_topic_count($f->ID) : 0;
					$members = function_exists('swiftboard_subreddit_member_count') ? swiftboard_subreddit_member_count($f->ID) : '—';
					$base_url = admin_url('admin.php?page=swiftboard-forums');
				?>
				<tr>
					<td>#<?php echo intval($f->ID); ?></td>
					<td><a href="<?php echo esc_url( get_permalink( $f->ID ) ); ?>" target="_blank">r/<?php echo esc_html($f->post_title); ?></a></td>
					<td><?php echo $author ? esc_html($author->display_name) : 'Inconnu'; ?></td>
					<td><?php echo intval($topics); ?></td>
					<td><?php echo esc_html($members); ?></td>
					<td>
						<?php if ($f->post_status === 'pending'): ?>
							<span class="sb-badge pending">⏳ <?php esc_html_e('En attente', 'swiftboard'); ?></span>
						<?php elseif ($f->post_status === 'publish'): ?>
							<span class="sb-badge open"><?php esc_html_e('Publié', 'swiftboard'); ?></span>
						<?php else: ?>
							<span class="sb-badge"><?php echo esc_html($f->post_status); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html(date('Y-m-d H:i', strtotime($f->post_date))); ?></td>
					<td>
						<a href="<?php echo esc_url( get_permalink( $f->ID ) ); ?>" class="sb-action-btn view" target="_blank"><?php esc_html_e('Voir', 'swiftboard'); ?></a>
						<?php if ($f->post_status === 'pending'): ?>
							<a href="<?php echo wp_nonce_url($base_url . '&action=approve&id=' . $f->ID, 'sb_forum_action'); ?>" class="sb-action-btn approve">✅ <?php esc_html_e('Valider', 'swiftboard'); ?></a>
							<a href="<?php echo wp_nonce_url($base_url . '&action=reject&id=' . $f->ID, 'sb_forum_action'); ?>" class="sb-action-btn reject" data-confirm="Rejeter ?">❌ <?php esc_html_e('Rejeter', 'swiftboard'); ?></a>
						<?php else: ?>
							<a href="<?php echo wp_nonce_url($base_url . '&action=delete&id=' . $f->ID, 'sb_forum_action'); ?>" class="sb-action-btn delete" data-confirm="Supprimer définitivement ?"><?php esc_html_e('Supprimer', 'swiftboard'); ?></a>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
				<?php if (empty($forums)): ?>
				<tr><td colspan="8" class="sb-empty"><?php esc_html_e('Aucune communauté.', 'swiftboard'); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}
