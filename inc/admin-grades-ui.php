<?php
if ( ! defined( 'ABSPATH' )) exit;

/**
 * SwiftBoard — Ecrans d'administration des grades et de la reputation.
 *
 * EXI-ARCH-01 — separation UI / logique metier
 * --------------------------------------------
 * inc/admin-settings-grades.php melangeait 1102 lignes d'ecrans HTML et de
 * logique appelee depuis le FRONT. Ce fichier ne contient plus que du rendu
 * d'interface, charge uniquement en administration :
 *
 *     swiftboard_settings_page()               reglages generaux
 *     swiftboard_grades_page()                 gestion des grades
 *     swiftboard_reputation_leaderboard_page() classement
 *
 * La logique metier vit desormais dans inc/grades.php (permissions, badge) et
 * inc/promotion.php (moteur de promotion), tous deux charges en front.
 *
 * Chacune de ces pages refait son propre controle de capacite : la capability
 * declaree a add_submenu_page() ne protege que l'entree de menu, pas l'appel
 * direct via admin.php?page=... (EXI-SEC-BLOQ-07).
 *
 * @package SwiftBoard
 * @since 5.1.0
 */
// ============================================================================
/**
 * @return void
 */
function swiftboard_settings_page() {
	// EXI-SEC-BLOQ-07 : la capability du menu ne protege pas l'appel direct
	// de la fonction via admin.php?page=... Controle explicite obligatoire.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'swiftboard' ), 403 );
	}

        // phpcs:ignore WordPress.Security.NonceVerification.Missing — checked below
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized — all values are (int) cast
	if ( isset( $_POST['swiftboard_save_settings'] ) && check_admin_referer( 'swiftboard_forum_settings' ) ) {
		update_option( 'swiftboard_enable_anon_replies', isset( $_POST['enable_anon_replies'] ) ? 1 : 0 );
		update_option( 'swiftboard_enable_votes', isset( $_POST['enable_votes'] ) ? 1 : 0 );
		update_option( 'swiftboard_enable_uploads', isset( $_POST['enable_uploads'] ) ? 1 : 0 );
		update_option( 'swiftboard_upload_daily_limit', (int) $_POST['daily_upload_limit'] );
		update_option( 'swiftboard_upload_total_limit', (int) $_POST['total_upload_limit'] );
		update_option( 'swiftboard_upload_min_posts', (int) $_POST['min_posts'] );
		update_option( 'swiftboard_upload_max_rejected', (int) $_POST['max_rejected'] );
		update_option( 'swiftboard_upload_avif_quality', (int) $_POST['avif_quality'] );
		update_option( 'swiftboard_vote_rate_limit', (int) $_POST['vote_rate_limit'] );
		update_option( 'swiftboard_default_grade', sanitize_text_field( wp_unslash( $_POST['default_grade'] ) ) );
		// === Montée de grade automatique ===
		update_option( 'swiftboard_autopromote_enabled', isset( $_POST['autopromote_enabled'] ) ? 1 : 0 );
		update_option( 'swiftboard_autopromote_threshold_member', (int) $_POST['autopromote_threshold_member'] );
		update_option( 'swiftboard_autopromote_threshold_pro', (int) $_POST['autopromote_threshold_pro'] );
		update_option( 'swiftboard_autopromote_weight_upvote', (int) $_POST['autopromote_weight_upvote'] );
		update_option( 'swiftboard_autopromote_weight_reply', (int) $_POST['autopromote_weight_reply'] );
		update_option( 'swiftboard_autopromote_notify_email', isset( $_POST['autopromote_notify_email'] ) ? 1 : 0 );
		echo '<div class="notice notice-success is-dismissible"><p>✅ Réglages enregistrés.</p></div>';
	}

	$enable_anon    = (int) get_option( 'swiftboard_enable_anon_replies', 1 );
	$enable_votes   = (int) get_option( 'swiftboard_enable_votes', 1 );
	$enable_uploads = (int) get_option( 'swiftboard_enable_uploads', 1 );
	$daily_upload   = (int) get_option( 'swiftboard_upload_daily_limit', 2 );
	$total_upload   = (int) get_option( 'swiftboard_upload_total_limit', 200 );
	$min_posts      = (int) get_option( 'swiftboard_upload_min_posts', 3 );
	$max_rejected   = (int) get_option( 'swiftboard_upload_max_rejected', 5 );
	$avif_quality   = (int) get_option( 'swiftboard_upload_avif_quality', 60 );
	$vote_rate      = (int) get_option( 'swiftboard_vote_rate_limit', 60 ); // secondes
	$default_grade  = get_option( 'swiftboard_default_grade', 'rookie' );
	$grades         = swiftboard_get_grades();

	// === Montée de grade automatique : valeurs par défaut ===
	$autopromote_enabled = (int) get_option( 'swiftboard_autopromote_enabled', 1 );
	$threshold_member    = (int) get_option( 'swiftboard_autopromote_threshold_member', 5 );
	$threshold_pro       = (int) get_option( 'swiftboard_autopromote_threshold_pro', 500 );
	$weight_upvote       = (int) get_option( 'swiftboard_autopromote_weight_upvote', 1 );
	$weight_reply        = (int) get_option( 'swiftboard_autopromote_weight_reply', 1 );
	$autopromote_email   = (int) get_option( 'swiftboard_autopromote_notify_email', 1 );
	?>
	<div class="wrap">
		<h1>⚙️ Réglages du forum SwiftBoard</h1>
		<form method="post" action="">
			<?php wp_nonce_field( 'swiftboard_forum_settings' ); ?>

			<h2 class="title">🎛️ Fonctionnalités</h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Réponses anonymes', 'swiftboard' ); ?></th>
					<td>
						<label><input type="checkbox" name="enable_anon_replies" value="1" <?php checked( $enable_anon, 1 ); ?>> Autoriser les visiteurs à répondre (nom + email/téléphone)</label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'System de votes', 'swiftboard' ); ?></th>
					<td>
						<label><input type="checkbox" name="enable_votes" value="1" <?php checked( $enable_votes, 1 ); ?>> Activer les upvotes/downvotes</label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Upload d\'images', 'swiftboard' ); ?></th>
					<td>
						<label><input type="checkbox" name="enable_uploads" value="1" <?php checked( $enable_uploads, 1 ); ?>> Activer l'upload d'images avec conversion AVIF</label>
					</td>
				</tr>
			</table>

			<h2 class="title">📊 Limites (grade Membre par défaut)</h2>
			<table class="form-table">
				<tr>
					<th><label for="daily_upload_limit"><?php esc_html_e( 'Limite uploads / jour', 'swiftboard' ); ?></label></th>
					<td>
						<input type="number" name="daily_upload_limit" id="daily_upload_limit" value="<?php echo esc_attr( (string) $daily_upload ); ?>" min="0" max="1000" class="small-text">
						<p class="description">0 = illimité. Les grades Pro/Modo/VIP ont leurs propres limites.</p>
					</td>
				</tr>
				<tr>
					<th><label for="total_upload_limit"><?php esc_html_e( 'Limite uploads totale', 'swiftboard' ); ?></label></th>
					<td>
						<input type="number" name="total_upload_limit" id="total_upload_limit" value="<?php echo esc_attr( (string) $total_upload ); ?>" min="0" max="10000" class="small-text">
						<p class="description">0 = illimité.</p>
					</td>
				</tr>
				<tr>
					<th><label for="min_posts"><?php esc_html_e( 'Messages minimum pour uploader', 'swiftboard' ); ?></label></th>
					<td>
						<input type="number" name="min_posts" id="min_posts" value="<?php echo esc_attr( (string) $min_posts ); ?>" min="0" max="100" class="small-text">
						<p class="description">0 = pas de minimum.</p>
					</td>
				</tr>
				<tr>
					<th><label for="max_rejected"><?php esc_html_e( 'Seuil de rejets (anti-spam)', 'swiftboard' ); ?></label></th>
					<td>
						<input type="number" name="max_rejected" id="max_rejected" value="<?php echo esc_attr( (string) $max_rejected ); ?>" min="1" max="100" class="small-text">
						<p class="description"><?php esc_html_e( 'Blocage après X images rejetées en 24h.', 'swiftboard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="avif_quality"><?php esc_html_e( 'Qualité AVIF', 'swiftboard' ); ?></label></th>
					<td>
						<input type="range" name="avif_quality" id="avif_quality" value="<?php echo esc_attr( (string) $avif_quality ); ?>" min="10" max="100" step="5" oninput="document.getElementById('avif_val').textContent=this.value">
						<span id="avif_val" style="font-weight:bold;"><?php echo esc_html( (string) $avif_quality ); ?></span>
						<p class="description"><?php esc_html_e( 'Recommandé : 50-70.', 'swiftboard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="vote_rate_limit"><?php esc_html_e( 'Intervalle entre votes (secondes)', 'swiftboard' ); ?></label></th>
					<td>
						<input type="number" name="vote_rate_limit" id="vote_rate_limit" value="<?php echo esc_attr( (string) $vote_rate ); ?>" min="5" max="3600" class="small-text">
						<p class="description">60 = 1 vote par minute. Les grades Pro/Modo/VIP ne sont pas limités.</p>
					</td>
				</tr>
			</table>

			<h2 class="title">🏅 Grade par défaut des nouveaux inscrits</h2>
			<table class="form-table">
				<tr>
					<th><label for="default_grade"><?php esc_html_e( 'Grade', 'swiftboard' ); ?></label></th>
					<td>
						<select name="default_grade" id="default_grade">
							<?php foreach ( $grades as $key => $g ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $default_grade, $key ); ?>>
									<?php echo esc_html( $g['icon'] . ' ' . $g['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Les nouveaux utilisateurs reçoivent ce grade automatiquement.', 'swiftboard' ); ?></p>
					</td>
				</tr>
			</table>

			<h2 class="title">🚀 Montée de grade automatique</h2>
			<p class="description" style="margin-bottom:12px;">
				Les utilisateurs sont promus automatiquement selon leur <strong>score de réputation</strong> =
				(upvotes reçus × poids upvote) + (réponses reçues sur mes sujets × poids réponse).
				Les grades Modérateur et VIP ne sont <strong>jamais rétrogradés</strong> ni promus automatiquement.
			</p>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Activer la montée automatique', 'swiftboard' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="autopromote_enabled" value="1" <?php checked( $autopromote_enabled, 1 ); ?>>
							Cocher pour activer la promotion automatique Rookie → Membre → Pro
						</label>
					</td>
				</tr>
				<tr>
					<th><label for="autopromote_threshold_member"><?php esc_html_e( 'Seuil Rookie → Membre', 'swiftboard' ); ?></label></th>
					<td>
						<input type="number" name="autopromote_threshold_member" id="autopromote_threshold_member"
								value="<?php echo esc_attr( (string) $threshold_member ); ?>" min="1" max="10000" class="small-text">
						<p class="description"><?php esc_html_e( 'Score minimum pour passer de Rookie à Membre. Défaut : 5.', 'swiftboard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="autopromote_threshold_pro"><?php esc_html_e( 'Seuil Membre → Pro', 'swiftboard' ); ?></label></th>
					<td>
						<input type="number" name="autopromote_threshold_pro" id="autopromote_threshold_pro"
								value="<?php echo esc_attr( (string) $threshold_pro ); ?>" min="1" max="100000" class="small-text">
						<p class="description"><?php esc_html_e( 'Score minimum pour passer de Membre à Pro. Défaut : 500 (échelle annoncée v5.3.8).', 'swiftboard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="autopromote_weight_upvote"><?php esc_html_e( 'Poids d\'un upvote reçu', 'swiftboard' ); ?></label></th>
					<td>
						<input type="number" name="autopromote_weight_upvote" id="autopromote_weight_upvote"
								value="<?php echo esc_attr( (string) $weight_upvote ); ?>" min="0" max="100" class="small-text">
						<p class="description"><?php esc_html_e( 'Combien de points vaut 1 upvote reçu. Défaut : 1.', 'swiftboard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="autopromote_weight_reply"><?php esc_html_e( 'Poids d\'une réponse reçue', 'swiftboard' ); ?></label></th>
					<td>
						<input type="number" name="autopromote_weight_reply" id="autopromote_weight_reply"
								value="<?php echo esc_attr( (string) $weight_reply ); ?>" min="0" max="100" class="small-text">
						<p class="description"><?php esc_html_e( 'Combien de points vaut 1 réponse reçue sur un de mes sujets. Défaut : 1.', 'swiftboard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Notification email', 'swiftboard' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="autopromote_notify_email" value="1" <?php checked( $autopromote_email, 1 ); ?>>
							Envoyer un email à l'utilisateur lors d'une promotion
						</label>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" name="swiftboard_save_settings" class="button button-primary">💾 Enregistrer</button>
			</p>
		</form>
	</div>
}

// ============================================================================
// 7. PAGE GRADES — ATTRIBUTION AUX MEMBRES
// ============================================================================
/**
 * @return void
 */
function swiftboard_grades_page(): void {
	// EXI-SEC-BLOQ-07 : la capability du menu ne protege pas l'appel direct
	// de la fonction via admin.php?page=... Controle explicite obligatoire.
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('Accès refusé.', 'swiftboard'), 403);
	}

	global $wpdb;

	// Sauvegarder un bonus karma (v5.3.6 — EXI-KARMA-01)
	// « est-ce que j'ai la possibilite depuis le dashboard admin d'ajouter
	//  de la karma pour des profils » → OUI, ici, membre par membre.
	if (isset($_POST['sb_save_karma_bonus']) && check_admin_referer('swiftboard_karma_bonus')) {
		$user_id = (int) $_POST['user_id'];
		$bonus   = (int) $_POST['karma_bonus'];
		// Plafond raisonnable : evite les fautes de frappe a 6 chiffres qui
		// casseraient la credibilite du classement.
		$bonus = max(0, min(99999, $bonus));
		update_user_meta($user_id, 'swiftboard_karma_bonus', $bonus);
		if (function_exists('swiftboard_invalidate_reputation_cache')) {
			swiftboard_invalidate_reputation_cache($user_id);
		}
		echo '<div class="notice notice-success is-dismissible"><p>✅ Bonus karma enregistré : +' . (int) $bonus . ' pour ce profil.</p></div>';
	}

	// Sauvegarder un grade
	if (isset($_POST['assign_grade']) && check_admin_referer('swiftboard_assign_grade')) {
		$user_id = (int) $_POST['user_id'];
		$grade = sanitize_text_field(wp_unslash($_POST['grade']));
		$grades = swiftboard_get_grades();

		if (isset($grades[$grade])) {
			update_user_meta($user_id, 'swiftboard_grade', $grade);
			swiftboard_invalidate_grade_cache($user_id); // EXI-TEST-02
			echo '<div class="notice notice-success is-dismissible"><p>✅ Grade attribué : ' . $grades[$grade]['icon'] . ' ' . $grades[$grade]['name'] . '</p></div>';
		}
	}

	// Sauvegarder une permission personnalisée
	if (isset($_POST['save_custom_perms']) && check_admin_referer('swiftboard_custom_perms')) {
		$user_id = (int) $_POST['user_id'];
		$custom = [
			'can_create_topic'      => isset($_POST['can_create_topic']),
			'can_create_subforum'   => isset($_POST['can_create_subforum']),
			'can_reply'             => isset($_POST['can_reply']),
			'can_upload'            => isset($_POST['can_upload']),
			'can_vote'              => isset($_POST['can_vote']),
			'daily_upload_limit'    => (int) $_POST['custom_daily_upload'],
			'total_upload_limit'    => (int) $_POST['custom_total_upload'],
			'daily_vote_limit'      => (int) $_POST['custom_daily_vote'],
		];
		update_user_meta($user_id, 'swiftboard_custom_permissions', $custom);
		echo '<div class="notice notice-success is-dismissible"><p>✅ Permissions personnalisées enregistrées.</p></div>';
	}

	// Récupérer tous les utilisateurs actifs du forum
	$pag = function_exists('swiftboard_admin_pagination_args')
		? swiftboard_admin_pagination_args(50, 100)
		: array('page' => 1, 'per' => 50, 'offset' => 0);
	$grade_where = "EXISTS (SELECT 1 FROM {$wpdb->posts} WHERE post_author = u.ID AND post_type IN ('topic','reply'))
		OR EXISTS (SELECT 1 FROM {$wpdb->usermeta} WHERE user_id = u.ID AND meta_key = 'swiftboard_grade')";
	$total_grade_users = (int) $wpdb->get_var(
		"SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u WHERE {$grade_where}"
	);
	$users = $wpdb->get_results($wpdb->prepare(
		"SELECT u.ID, u.display_name, u.user_email, u.user_registered
		FROM {$wpdb->users} u
		WHERE {$grade_where}
		ORDER BY u.display_name
		LIMIT %d OFFSET %d",
		$pag['per'],
		$pag['offset']
	));
	if (function_exists('swiftboard_admin_render_pagination')) {
		swiftboard_admin_render_pagination($total_grade_users, $pag['per'], $pag['page']);
	}


	$grades = swiftboard_get_grades();
	$default_grade = get_option('swiftboard_default_grade', 'rookie');
	// v5.3.8 — EXI-KARMA-03 : planchers d'import (non ronds) affiches sous les seuils annonces.
	$sb_planchers = function_exists('swiftboard_import_karma_planchers') ? swiftboard_import_karma_planchers() : [];
	?>
	<div class="wrap">
		<h1>🏅 Grades des membres</h1>

		<!-- Légende des grades -->
		<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin:20px 0;">
			<?php foreach ( $grades as $key => $g ) : ?>
			<div style="background:#fff;border:2px solid <?php echo esc_attr( $g['color'] ); ?>;border-radius:8px;padding:16px;text-align:center;">
				<div style="font-size:2rem;"><?php echo esc_html( $g['icon'] ); ?></div>
				<div style="font-weight:700;color:<?php echo esc_attr( $g['color'] ); ?>;margin:4px 0;"><?php echo esc_html( $g['name'] ); ?></div>
				<div style="font-size:0.72rem;color:#64748b;margin-bottom:4px;">
					<?php $sb_plancher = isset( $sb_planchers[ $key ] ) ? (int) $sb_planchers[ $key ] : 0; ?>
					<?php if ( in_array( $key, array( 'moderator', 'vip' ), true ) ) : ?>
						<?php printf( esc_html__( 'Manuel — annoncé %1$s — plancher import %2$s', 'swiftboard' ), intval( $g['min_score'] ), $sb_plancher ); ?>
					<?php elseif ( $g['min_score'] > 0 ) : ?>
						<?php printf( esc_html__( 'Auto dès %1$s — plancher import %2$s', 'swiftboard' ), intval( $g['min_score'] ), $sb_plancher ); ?>
					<?php else : ?>
						<?php esc_html_e( 'Grade de départ (0 karma)', 'swiftboard' ); ?>
					<?php endif; ?>
				</div>
				<div style="font-size:0.75rem;color:#666;">
					<?php
					if ( $g['daily_upload_limit'] === 0 ) :
						?>
						<?php esc_html_e( 'Uploads illimités', 'swiftboard' ); ?><br>
						<?php
else :
	?>
						<?php esc_html_e( 'Uploads :', 'swiftboard' ); ?><?php echo intval( $g['daily_upload_limit'] ); ?>/jour<br><?php endif; ?>
					<?php
					if ( $g['can_create_subforum'] ) :
						?>
						<?php esc_html_e( 'Sous-forums : ✅', 'swiftboard' ); ?><br>
						<?php
else :
	?>
						<?php esc_html_e( 'Sous-forums : ❌', 'swiftboard' ); ?><br><?php endif; ?>
					<?php
					if ( $g['can_create_topic'] ) :
						?>
						<?php esc_html_e( 'Sujets : ✅', 'swiftboard' ); ?><br>
						<?php
else :
	?>
						<?php esc_html_e( 'Sujets : ❌', 'swiftboard' ); ?><br><?php endif; ?>
					<?php
					if ( $g['daily_vote_limit'] === 0 ) :
						?>
						<?php esc_html_e( 'Votes illimités', 'swiftboard' ); ?>
						<?php
else :
	?>
						<?php esc_html_e( 'Votes :', 'swiftboard' ); ?><?php echo intval( $g['daily_vote_limit'] ); ?>/jour<?php endif; ?>
				</div>
			</div>
			<?php endforeach; ?>
		</div>

		<h2><?php esc_html_e( 'Attribuer des grades', 'swiftboard' ); ?></h2>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Utilisateur', 'swiftboard' ); ?></th>
					<th><?php esc_html_e( 'Email', 'swiftboard' ); ?></th>
					<th><?php esc_html_e( 'Grade actuel', 'swiftboard' ); ?></th>
					<th><?php esc_html_e( 'Karma (bonus)', 'swiftboard' ); ?></th>
					<th><?php esc_html_e( 'Changer le grade', 'swiftboard' ); ?></th>
					<th><?php esc_html_e( 'Permissions personnalisées', 'swiftboard' ); ?></th>
					<th><?php esc_html_e( 'Inscrit le', 'swiftboard' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $users as $u ) :
					$current_grade      = swiftboard_get_user_grade( $u->ID );
					$current_grade_info = $grades[ $current_grade ] ?? $grades['member'];
					?>
				<tr>
					<td>
						<strong><?php echo esc_html( $u->display_name ); ?></strong>
						<br><small style="color:#595959;">ID: <?php echo (int) $u->ID; ?></small>
					</td>
					<td><?php echo esc_html( $u->user_email ); ?></td>
					<td>
						<span style="background:<?php echo esc_attr( $current_grade_info['color'] ); ?>;color:#fff;padding:4px 12px;border-radius:9999px;font-size:0.8rem;font-weight:700;">
							<?php echo esc_html( $current_grade_info['icon'] . ' ' . $current_grade_info['name'] ); ?>
						</span>
					</td>
					<td>
						<?php
						// v5.3.6 — EXI-KARMA-01 : bonus karma par profil.
						// Affiche le score calcule (upvotes+reponses+bonus) et
						// permet d'ajuster le bonus depuis ce tableau.
						$rep   = function_exists( 'swiftboard_get_user_reputation_score' ) ? swiftboard_get_user_reputation_score( $u->ID ) : array(
							'score'   => 0,
							'upvotes' => 0,
							'replies' => 0,
							'bonus'   => 0,
						);
						$bonus = (int) ( $rep['bonus'] ?? 0 );
						?>
						<form method="post" action="" style="display:flex;align-items:center;gap:6px;">
							<?php wp_nonce_field( 'swiftboard_karma_bonus' ); ?>
							<input type="hidden" name="user_id" value="<?php echo (int) $u->ID; ?>">
							<input type="hidden" name="sb_save_karma_bonus" value="1">
							<input type="number" name="karma_bonus" value="<?php echo (int) $bonus; ?>" min="0" max="99999" step="1"
									style="width:80px;padding:3px 6px;" aria-label="<?php echo esc_attr( sprintf( __( 'Bonus karma de %s', 'swiftboard' ), $u->display_name ) ); ?>">
							<button type="submit" class="button button-small" title="<?php esc_attr_e( 'Enregistrer le bonus', 'swiftboard' ); ?>">💾</button>
						</form>
						<small style="color:#595959;display:block;margin-top:4px;">
							<?php
							printf(
								/* translators: 1: score total, 2: upvotes recus, 3: reponses recues */
								esc_html__( 'Total : %1$s (▲%2$s reçus + 💬%3$s reçues)', 'swiftboard' ),
								'<strong>' . (int) $rep['score'] . '</strong>',
								(int) ( $rep['upvotes'] ?? 0 ),
								(int) ( $rep['replies'] ?? 0 )
							);
							?>
						</small>
					</td>
					<td>
						<form method="post" action="" style="display:flex;gap:4px;">
							<?php wp_nonce_field( 'swiftboard_assign_grade' ); ?>
							<input type="hidden" name="user_id" value="<?php echo esc_attr( (int) $u->ID ); ?>">
							<select name="grade" onchange="this.form.submit()" style="padding:4px;border-radius:4px;"
									aria-label="<?php echo esc_attr( sprintf( __( 'Grade de %s', 'swiftboard' ), $u->display_name ) ); ?>">
								<?php foreach ( $grades as $key => $g ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_grade, $key ); ?>>
										<?php echo esc_html( $g['icon'] . ' ' . $g['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<input type="hidden" name="assign_grade" value="1">
						</form>
					</td>
					<td>
						<a href="#" data-sb-toggle="custom-<?php echo esc_attr( (int) $u->ID ); ?>" style="font-size:0.8rem;">⚙️ Personnaliser</a>
						<div id="custom-<?php echo esc_attr( (int) $u->ID ); ?>" style="display:none;background:#f8fafc;padding:12px;border-radius:8px;margin-top:8px;">
							<form method="post" action="">
								<?php wp_nonce_field( 'swiftboard_custom_perms' ); ?>
								<input type="hidden" name="user_id" value="<?php echo esc_attr( (int) $u->ID ); ?>">
								<input type="hidden" name="save_custom_perms" value="1">
								<table style="width:100%;font-size:0.85rem;">
									<tr>
										<td><label><input type="checkbox" name="can_create_topic" <?php checked( $current_grade_info['can_create_topic'], true ); ?>> Créer sujets</label></td>
										<td><label><input type="checkbox" name="can_create_subforum" <?php checked( $current_grade_info['can_create_subforum'], true ); ?>> Créer sous-forums</label></td>
									</tr>
									<tr>
										<td><label><input type="checkbox" name="can_reply" <?php checked( $current_grade_info['can_reply'], true ); ?>> Répondre</label></td>
										<td><label><input type="checkbox" name="can_upload" <?php checked( $current_grade_info['can_upload'], true ); ?>> Uploader images</label></td>
									</tr>
									<tr>
										<td><label><input type="checkbox" name="can_vote" <?php checked( $current_grade_info['can_vote'], true ); ?>> Voter</label></td>
										<td><?php esc_html_e( 'Uploads/jour :', 'swiftboard' ); ?><input type="number" name="custom_daily_upload" value="<?php echo esc_attr( $current_grade_info['daily_upload_limit'] ); ?>" min="0" style="width:60px;" placeholder="0=∞"></td>
									</tr>
									<tr>
										<?php
										// total_upload_limit n'existe dans AUCUN grade par defaut
										// (swiftboard_get_grades()) : la cle n'apparait que
										// sur les permissions personnalisees enregistrees
										// depuis cet ecran. Sans valeur par defaut, le champ
										// « Uploads total » emettait un Warning PHP pour
										// chaque utilisateur affiche. Repli sur le reglage
										// global, comme le fait deja
										// swiftboard_get_effective_upload_limits().
										?>
										<td><?php esc_html_e( 'Uploads total :', 'swiftboard' ); ?><input type="number" name="custom_total_upload" value="<?php echo esc_attr( $current_grade_info['total_upload_limit'] ?? get_option( 'swiftboard_upload_total_limit', 200 ) ); ?>" min="0" style="width:60px;" placeholder="0=∞"></td>
										<td><?php esc_html_e( 'Votes/jour :', 'swiftboard' ); ?><input type="number" name="custom_daily_vote" value="<?php echo esc_attr( $current_grade_info['daily_vote_limit'] ); ?>" min="0" style="width:60px;" placeholder="0=∞"></td>
									</tr>
								</table>
								<button type="submit" class="button button-small button-primary" style="margin-top:8px;">💾 Enregistrer</button>
							</form>
						</div>
					</td>
					<td><?php echo esc_html( date( 'Y-m-d', strtotime( $u->user_registered ) ) ); ?></td>
				</tr>
				<?php endforeach; ?>
				<?php if ( empty( $users ) ) : ?>
				<tr><td colspan="6" style="text-align:center;color:#999;padding:40px;"><?php esc_html_e( 'Aucun utilisateur.', 'swiftboard' ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}

// ============================================================================
// CLASSEMENT — DEPLACE (EXI-ARCH-01)
// ============================================================================
// swiftboard_reputation_leaderboard_page() vit dans
// inc/admin-reputation-ui.php : ce fichier depassait sinon les 500 lignes.

