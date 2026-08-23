<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Scenario de diagnostic de l'auto-promotion.
 *
 * EXI-ARCH-01 : extrait de inc/admin-test-autopromote.php. Outil de
 * diagnostic livre a l'administrateur : il cree des comptes, un sujet, des
 * votes, puis verifie qu'une promotion se declenche.
 *
 * @package SwiftBoard
 * @since 5.1.0
 */
// ============================================================================
// 3. SCÉNARIO PRINCIPAL
// ============================================================================
/**
 * @return array<string, mixed>
 */
function swiftboard_test_run_scenario() {
	$log   = array();
	$log[] = array(
		'time' => current_time( 'mysql' ),
		'msg'  => '🎬 Démarrage du scénario de test',
	);

	// S'assurer que les tables existent (au cas où l'admin n'a jamais visité wp-admin)
	swiftboard_create_votes_table();
	if ( function_exists( 'swiftboard_create_notifications_table' ) ) {
		swiftboard_create_notifications_table();
	}

	// --- Étape 1 : Création des utilisateurs ---
	$author_id = swiftboard_test_create_user(
		'sb_test_author_' . time(),
		'sb_test_author_' . time() . '@example.com',
		'Auteur Test (Rookie)'
	);
	$voter1_id = swiftboard_test_create_user(
		'sb_test_voter1_' . time(),
		'sb_test_voter1_' . time() . '@example.com',
		'Votant 1 (Rookie)'
	);
	$voter2_id = swiftboard_test_create_user(
		'sb_test_voter2_' . time(),
		'sb_test_voter2_' . time() . '@example.com',
		'Votant 2 (Rookie)'
	);
	// QUATRE votants sont necessaires, pas deux.
	// Le scenario doit produire 4 upvotes. En les faisant emettre par deux
	// personnes seulement, les votes #3 et #4 tombaient sous l'anti-flood
	// (5 secondes minimum entre deux votes d'un meme votant,
	// votes-social.php:244) : ils echouaient A TOUS LES COUPS, le score
	// plafonnait sous le seuil, et l'outil de diagnostic concluait donc
	// TOUJOURS que l'auto-promotion ne fonctionnait pas — alors qu'elle
	// fonctionnait.
	$voter3_id = swiftboard_test_create_user(
		'sb_test_voter3_' . time(),
		'sb_test_voter3_' . time() . '@example.com',
		'Votant 3 (Rookie)'
	);
	$voter4_id = swiftboard_test_create_user(
		'sb_test_voter4_' . time(),
		'sb_test_voter4_' . time() . '@example.com',
		'Votant 4 (Rookie)'
	);

	if ( ! $author_id || ! $voter1_id || ! $voter2_id || ! $voter3_id || ! $voter4_id ) {
		$log[] = array(
			'time'  => current_time( 'mysql' ),
			'msg'   => '❌ Échec création utilisateurs',
			'error' => true,
		);
		return array(
			'success' => false,
			'log'     => $log,
		);
	}
	$log[] = array(
		'time' => current_time( 'mysql' ),
		'msg'  => "✅ 5 utilisateurs créés : Auteur (ID:{$author_id}), Votants (ID:{$voter1_id}, {$voter2_id}, {$voter3_id}, {$voter4_id})",
	);

	// Vérifier le grade initial
	$initial_grade = swiftboard_get_user_grade( $author_id );
	$log[]         = array(
		'time' => current_time( 'mysql' ),
		'msg'  => "📊 Grade initial de l'auteur : <strong>{$initial_grade}</strong>",
	);

	// --- Étape 2 : Création d'un topic ---
	$topic_id = swiftboard_test_create_topic(
		$author_id,
		'[TEST] Sujet de test pour auto-promotion',
		"Ceci est un sujet de test généré automatiquement par SwiftBoard pour valider le système d'auto-promotion.\n\nMerci de ne pas répondre à ce sujet."
	);
	if ( ! $topic_id ) {
		$log[] = array(
			'time'  => current_time( 'mysql' ),
			'msg'   => '❌ Échec création topic (bbPress est-il activé ?)',
			'error' => true,
		);
		return array(
			'success' => false,
			'log'     => $log,
		);
	}
	update_post_meta( $topic_id, '_swiftboard_test_data', 1 );
	$log[] = array(
		'time' => current_time( 'mysql' ),
		'msg'  => "✅ Topic créé (ID:{$topic_id}) par l'auteur",
	);

	// --- Étape 3 : Une réponse d'un votant (= 1 pt réputation pour l'auteur) ---
	$reply_id = swiftboard_test_create_reply(
		$voter1_id,
		$topic_id,
		"Réponse de test — ceci devrait donner 1 point de réputation à l'auteur du sujet."
	);
	if ( $reply_id ) {
		update_post_meta( $reply_id, '_swiftboard_test_data', 1 );
		$log[] = array(
			'time' => current_time( 'mysql' ),
			'msg'  => "✅ Réponse créée (ID:{$reply_id}) par Votant1 → +1 pt réputation pour l'auteur",
		);
	} else {
		$log[] = array(
			'time'    => current_time( 'mysql' ),
			'msg'     => '⚠️ Échec création réponse (on continue avec les upvotes)',
			'warning' => true,
		);
	}

	// Vérifier le score intermédiaire
	$rep   = swiftboard_get_user_reputation_score( $author_id );
	$log[] = array(
		'time' => current_time( 'mysql' ),
		'msg'  => "📊 Score intermédiaire : <strong>{$rep['score']}</strong> (upvotes: {$rep['upvotes']}, réponses: {$rep['replies']})",
	);

	// --- Étape 4 : 4 upvotes pour atteindre le seuil de 5 ---
	// Note : on simule les votes en se faisant passer pour les votants via wp_set_current_user
	// DEUX contraintes gouvernent ces votes, et le scenario les violait toutes
	// les deux :
	//
	// 1. Un votant DIFFERENT par vote. L'anti-flood interdit a un meme votant
	// d'emettre deux votes a moins de 5 secondes (votes-social.php:244).
	// Avec seulement deux votants, les votes #3 et #4 echouaient
	// systematiquement.
	//
	// 2. Tous les votes doivent porter sur un contenu DE L'AUTEUR. La
	// reputation ne compte que les upvotes recus sur ses propres posts
	// (grades.php, INNER JOIN sur p.post_author). Les votes #3 et #4
	// ciblaient la reponse ecrite par Votant1 : ils ne creditaient donc
	// personne, et le seuil de 5 points restait hors d'atteinte.
	//
	// On vote donc sur le sujet de l'auteur, avec quatre votants distincts.
	// Cumul attendu : 4 upvotes recus + 1 reponse recue = 5 points = seuil.
	$votes_to_cast = array(
		array(
			'voter'  => $voter1_id,
			'target' => $topic_id,
		),
		array(
			'voter'  => $voter2_id,
			'target' => $topic_id,
		),
		array(
			'voter'  => $voter3_id,
			'target' => $topic_id,
		),
		array(
			'voter'  => $voter4_id,
			'target' => $topic_id,
		),
	);

	$original_user = get_current_user_id();
	foreach ( $votes_to_cast as $i => $vote ) {
		wp_set_current_user( $vote['voter'] );
		$result = swiftboard_cast_vote( $vote['target'], 'up' );
		if ( is_wp_error( $result ) ) {
			$log[] = array(
				'time'  => current_time( 'mysql' ),
				'msg'   => '❌ Vote #' . ( $i + 1 ) . ' échoué : ' . $result->get_error_message(),
				'error' => true,
			);
		} else {
			$log[] = array(
				'time' => current_time( 'mysql' ),
				'msg'  => '✅ Vote #' . ( $i + 1 ) . " — Votant ID:{$vote['voter']} a upvoté le post ID:{$vote['target']} → action: {$result['action']}, score du post: {$result['score']}",
			);
		}
	}
	wp_set_current_user( $original_user );

	// --- Étape 5 : Vérifier le score final ---
	swiftboard_invalidate_reputation_cache( $author_id );
	$rep_final = swiftboard_get_user_reputation_score( $author_id );
	$log[]     = array(
		'time' => current_time( 'mysql' ),
		'msg'  => "📊 Score final de l'auteur : <strong>{$rep_final['score']}</strong> (upvotes: {$rep_final['upvotes']}, réponses: {$rep_final['replies']})",
	);

	// --- Étape 6 : Vérifier le grade final ---
	$final_grade = swiftboard_get_user_grade( $author_id );
	$log[]       = array(
		'time' => current_time( 'mysql' ),
		'msg'  => "🏅 Grade final de l'auteur : <strong>{$final_grade}</strong>",
	);

	// --- Vérifier l'historique de promotion ---
	$history = get_user_meta( $author_id, 'swiftboard_promotion_history', true );
	if ( is_array( $history ) && ! empty( $history ) ) {
		$last  = end( $history );
		$log[] = array(
			'time'    => current_time( 'mysql' ),
			'msg'     => "📜 Historique de promotion : {$last['from']} → <strong>{$last['to']}</strong> à {$last['timestamp']} (score: {$last['score']})",
			'success' => ( $final_grade !== 'rookie' ),
		);
	}

	// --- Conclusion ---
	$success = ( $final_grade !== 'rookie' && $final_grade === 'member' );
	if ( $success ) {
		$log[] = array(
			'time'    => current_time( 'mysql' ),
			'msg'     => '🎉 <strong>SUCCÈS</strong> : L\'auteur est passé de Rookie à Membre automatiquement !',
			'success' => true,
		);
	} elseif ( $final_grade === 'rookie' ) {
		$log[] = array(
			'time'    => current_time( 'mysql' ),
			'msg'     => '⚠️ L\'auteur est encore Rookie. Vérifiez les seuils dans Réglages.',
			'warning' => true,
		);
	} else {
		$log[] = array(
			'time'    => current_time( 'mysql' ),
			'msg'     => "ℹ️ L'auteur est passé à {$final_grade} (attendu : member)",
			'warning' => true,
		);
	}

	return array(
		'success'       => $success,
		'log'           => $log,
		'author_id'     => $author_id,
		'topic_id'      => $topic_id,
		'initial_grade' => $initial_grade,
		'final_grade'   => $final_grade,
		'final_score'   => $rep_final['score'],
		'history'       => $history ?: array(),
	);
}

// ============================================================================
// 4. PAGE ADMIN
// ============================================================================
/**
 * @return void
 */
function swiftboard_test_autopromote_page() {
	// Vérifier capacité
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'Accès refusé.', 'swiftboard' ) );
	}

	$result         = null;
	$cleanup_result = null;

	// Lancer le scénario
	if ( isset( $_POST['run_test'] ) && check_admin_referer( 'swiftboard_run_test' ) ) {
		// Désactiver l'envoi d'emails pendant le test pour éviter le spam
		add_filter(
			'pre_wp_mail',
			function () {
				return false;
			}
		);
		$result = swiftboard_test_run_scenario();
		remove_filter( 'pre_wp_mail', '__return_false' );
	}

	// Nettoyer
	if ( isset( $_POST['cleanup_test'] ) && check_admin_referer( 'swiftboard_cleanup_test' ) ) {
		$cleanup_result = swiftboard_test_cleanup();
	}

	// Récupérer la config actuelle pour l'afficher
	$autopromote_enabled = (int) get_option( 'swiftboard_autopromote_enabled', 1 );
	$threshold_member    = (int) get_option( 'swiftboard_autopromote_threshold_member', 5 );
	$threshold_pro       = (int) get_option( 'swiftboard_autopromote_threshold_pro', 500 );
	$weight_upvote       = (int) get_option( 'swiftboard_autopromote_weight_upvote', 1 );
	$weight_reply        = (int) get_option( 'swiftboard_autopromote_weight_reply', 1 );
	?>
	<div class="wrap">
		<h1>🧪 Test du système d'auto-promotion</h1>

		<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin:16px 0;max-width:800px;">
			<h2 style="margin-top:0;">📋 Configuration actuelle</h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Auto-promotion', 'swiftboard' ); ?></th>
                    <td><?php echo $autopromote_enabled ? '✅ Activée' : '❌ Désactivée'; /* phpcs:ignore */ ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Seuil Rookie → Membre', 'swiftboard' ); ?></th>
					<td><?php echo intval( $threshold_member ); ?> pts</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Seuil Membre → Pro', 'swiftboard' ); ?></th>
					<td><?php echo intval( $threshold_pro ); ?> pts</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Poids d\'un upvote reçu', 'swiftboard' ); ?></th>
					<td><?php echo intval( $weight_upvote ); ?> pt</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Poids d\'une réponse reçue', 'swiftboard' ); ?></th>
					<td><?php echo intval( $weight_reply ); ?> pt</td>
				</tr>
			</table>
			<p class="description">
				Le scénario va créer 1 auteur (Rookie) + 2 votants, 1 topic, 1 réponse et 4 upvotes.
				Score attendu = 4 upvotes × <?php echo intval( $weight_upvote ); ?> + 1 réponse × <?php echo intval( $weight_reply ); ?> = <strong><?php echo intval( ( 4 * $weight_upvote ) + ( 1 * $weight_reply ) ); ?> pts</strong>.
				<?php if ( ( 4 * $weight_upvote ) + ( 1 * $weight_reply ) >= $threshold_member ) : ?>
					<span style="color:#16a34a;">✅ Le score devrait déclencher la montée Rookie → Membre.</span>
				<?php else : ?>
					<span style="color:#d97706;">⚠️ Le score ne sera pas suffisant. Ajustez les seuils ou les poids dans Réglages.</span>
				<?php endif; ?>
			</p>
		</div>

		<div style="display:flex;gap:12px;margin:16px 0;">
			<form method="post" action="" data-confirm="Lancer le scénario de test ?">
				<?php wp_nonce_field( 'swiftboard_run_test' ); ?>
				<button type="submit" name="run_test" value="1" class="button button-primary">▶️ Lancer le scénario</button>
			</form>
			<form method="post" action="" data-confirm="Supprimer toutes les données de test ? (utilisateurs, topics, votes)">
				<?php wp_nonce_field( 'swiftboard_cleanup_test' ); ?>
				<button type="submit" name="cleanup_test" value="1" class="button button-secondary">🗑️ Nettoyer les données de test</button>
			</form>
		</div>

		<?php if ( $cleanup_result ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>🧹 Nettoyage terminé : <?php echo (int) $cleanup_result['users_removed']; ?> utilisateur(s) supprimé(s),
			<?php echo (int) $cleanup_result['posts_removed']; ?> post(s) supprimé(s).</p>
		</div>
		<?php endif; ?>

		<?php if ( $result ) : ?>
        <div style="background:<?php echo $result['success'] ? '#dcfce7' : '#fef3c7'; /* phpcs:ignore */ ?>;border-left:4px solid <?php echo $result['success'] ? '#16a34a' : '#d97706'; /* phpcs:ignore */ ?>;padding:16px;margin:16px 0;border-radius:4px;">
			<h2 style="margin-top:0;">
                <?php echo $result['success'] ? '🎉 Test réussi' : '⚠️ Test incomplet'; /* phpcs:ignore */ ?>

			</h2>
			<p>
				<strong><?php esc_html_e( 'Auteur ID :', 'swiftboard' ); ?></strong> <?php echo (int) $result['author_id']; ?><br>
				<strong><?php esc_html_e( 'Topic ID :', 'swiftboard' ); ?></strong> <?php echo (int) $result['topic_id']; ?><br>
				<strong><?php esc_html_e( 'Grade initial :', 'swiftboard' ); ?></strong> <?php echo esc_html( $result['initial_grade'] ); ?><br>
				<strong><?php esc_html_e( 'Grade final :', 'swiftboard' ); ?></strong> <?php echo esc_html( $result['final_grade'] ); ?><br>
				<strong><?php esc_html_e( 'Score final :', 'swiftboard' ); ?></strong> <?php echo (int) $result['final_score']; ?> pts
			</p>
		</div>

		<h2>📜 Journal détaillé</h2>
		<div style="background:#1e293b;color:#e2e8f0;border-radius:8px;padding:16px;font-family:monospace;font-size:13px;line-height:1.6;max-height:500px;overflow-y:auto;">
			<?php foreach ( $result['log'] as $entry ) : ?>
				<div style="color:
				<?php
					echo isset( $entry['error'] ) && $entry['error'] ? '#fca5a5' : (
						isset( $entry['success'] ) && $entry['success'] ? '#86efac' : (
						isset( $entry['warning'] ) && $entry['warning'] ? '#fcd34d' : '#e2e8f0'
						)
									);
				?>
				;">
					[<?php echo esc_html( $entry['time'] ); ?>] <?php echo wp_kses( (string) $entry['msg'], array( 'strong' => array(), 'em' => array(), 'code' => array(), 'br' => array() ) ); ?>
				</div>
			<?php endforeach; ?>
		</div>

			<?php if ( ! empty( $result['history'] ) ) : ?>
		<h2>📊 Historique de promotion</h2>
		<table class="wp-list-table widefat fixed striped">
			<thead><tr><th>De</th><th><?php esc_html_e( 'Vers', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Score', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Date', 'swiftboard' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $result['history'] as $h ) : ?>
				<tr>
					<td><?php echo esc_html( $h['from'] ); ?></td>
					<td><strong><?php echo esc_html( $h['to'] ); ?></strong></td>
					<td><?php echo (int) $h['score']; ?></td>
					<td><?php echo esc_html( $h['timestamp'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
		<?php endif; ?>

		<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:16px;margin-top:24px;max-width:800px;">
			<h3 style="margin-top:0;">💡 À propos de ce test</h3>
			<p>
				Ce test simule un scénario réel en <strong>3 étapes</strong> :
			</p>
			<ol>
				<li><?php esc_html_e( 'Création de 3 utilisateurs (1 auteur + 2 votants) au grade Rookie', 'swiftboard' ); ?></li>
				<li><?php esc_html_e( 'Création d\'un topic + 1 réponse (pour déclencher le hook', 'swiftboard' ); ?><code>bbp_new_reply</code>)</li>
				<li>4 upvotes via <code>swiftboard_cast_vote()</code> (pour déclencher <code>swiftboard_vote_cast</code>)</li>
			</ol>
			<p>
				Si tout fonctionne, l'auteur doit passer automatiquement de Rookie à Membre sans intervention manuelle,
				et un email de félicitations doit être envoyé (bloqué pendant le test pour éviter le spam).
			</p>
			<p style="margin-bottom:0;">
				<strong><?php esc_html_e( 'Note Hostinger :', 'swiftboard' ); ?></strong> ce test ne génère pas de charge serveur excessive
				(3 users + 2 posts + 4 votes = ~15 requêtes DB, exécutées en moins de 2 secondes).
			</p>
		</div>
	</div>
	<?php
}

