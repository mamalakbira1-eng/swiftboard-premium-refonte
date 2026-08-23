<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Ecran « Classement de reputation ».
 *
 * EXI-ARCH-01 : extrait de inc/admin-grades-ui.php pour ramener chaque module
 * sous le seuil de 500 lignes fixe par le cahier. Une page d'ecran par
 * fichier reste par ailleurs plus simple a relire qu'un module fourre-tout.
 *
 * L'ecran expose les adresses e-mail des membres : il est reserve a
 * manage_options, capability qui implique deja list_users. Le controle est
 * refait dans la fonction, la capability du menu ne protegeant pas l'appel
 * direct via admin.php?page=... (EXI-SEC-BLOQ-07).
 *
 * @package SwiftBoard
 * @since 5.1.0
 */
// ============================================================================
// 8. APPLIQUER LES GRADES AUTOMATIQUEMENT AUX NOUVEAUX UTILISATEURS

/**
 * @return void
 */
function swiftboard_reputation_leaderboard_page() {
	// EXI-SEC-BLOQ-07 : la capability du menu ne protege pas l'appel direct
	// de la fonction via admin.php?page=... Controle explicite obligatoire.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'swiftboard' ), 403 );
	}

	global $wpdb;

	// CDC-PROD-FERME-06 : pagination
	$pag             = function_exists( 'swiftboard_admin_pagination_args' )
		? swiftboard_admin_pagination_args( 50, 100 )
		: array(
			'page'   => 1,
			'per'    => 50,
			'offset' => 0,
		);
	$total_rep_users = (int) $wpdb->get_var(
		"SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
         WHERE EXISTS (
             SELECT 1 FROM {$wpdb->posts}
             WHERE post_author = u.ID AND post_type IN ('topic','reply')
         )"
	);
	$users           = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT DISTINCT u.ID, u.display_name, u.user_email
             FROM {$wpdb->users} u
             WHERE EXISTS (
                 SELECT 1 FROM {$wpdb->posts}
                 WHERE post_author = u.ID AND post_type IN ('topic','reply')
             )
             ORDER BY u.display_name
             LIMIT %d OFFSET %d",
			$pag['per'],
			$pag['offset']
		)
	);

	if ( function_exists( 'swiftboard_admin_render_pagination' ) ) {
		swiftboard_admin_render_pagination( $total_rep_users, $pag['per'], $pag['page'] );
	}
	$ranked = array();
	foreach ( $users as $u ) {
		$rep      = swiftboard_get_user_reputation_score( $u->ID );
		$ranked[] = array(
			'ID'      => $u->ID,
			'name'    => $u->display_name,
			'email'   => $u->user_email,
			'score'   => $rep['score'],
			'upvotes' => $rep['upvotes'],
			'replies' => $rep['replies'],
			'grade'   => swiftboard_get_user_grade( $u->ID ),
		);
	}
	// Tri par score décroissant
	usort(
		$ranked,
		function ( $a, $b ) {
			return $b['score'] <=> $a['score'];
		}
	);

	$grades = swiftboard_get_grades();
	?>
	<div class="wrap">
		<h1>📊 Classement de réputation</h1>
		<p class="description">
			Score = (upvotes reçus × <?php echo (int) get_option( 'swiftboard_autopromote_weight_upvote', 1 ); ?>)
					+ (réponses reçues × <?php echo (int) get_option( 'swiftboard_autopromote_weight_reply', 1 ); ?>).
			Seuils : Rookie → Membre = <?php echo (int) get_option( 'swiftboard_autopromote_threshold_member', 5 ); ?> pts,
			Membre → Pro = <?php echo (int) get_option( 'swiftboard_autopromote_threshold_pro', 500 ); ?> pts.
		</p>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width:60px;"><?php esc_html_e( 'Rang', 'swiftboard' ); ?></th>
					<th><?php esc_html_e( 'Utilisateur', 'swiftboard' ); ?></th>
					<th><?php esc_html_e( 'Score', 'swiftboard' ); ?></th>
					<th><?php esc_html_e( 'Upvotes reçus', 'swiftboard' ); ?></th>
					<th><?php esc_html_e( 'Réponses reçues', 'swiftboard' ); ?></th>
					<th><?php esc_html_e( 'Grade', 'swiftboard' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'swiftboard' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php
			foreach ( $ranked as $i => $r ) :
				$g      = $grades[ $r['grade'] ] ?? $grades['member'];
				$medals = array( '🥇', '🥈', '🥉' );
				$rank   = $i < 3 ? $medals[ $i ] : '#' . ( $i + 1 );
				?>
				<tr>
					<?php
					// $rank vaut une medaille (« 🥇 ») ou « #4 » : intval()
					// renvoyait 0 dans les DEUX cas, donc toute la colonne
					// « Rang » affichait 0. esc_html() est l'echappement
					// correct pour une chaine destinee au corps du document.
					?>
					<td style="font-size:1.25rem;font-weight:700;text-align:center;"><?php echo esc_html( $rank ); ?></td>
					<td>
						<strong><?php echo esc_html( $r['name'] ); ?></strong>
						<br><small style="color:#999;"><?php echo esc_html( $r['email'] ); ?></small>
					</td>
					<td><strong style="color:#006cbd;font-size:1.1rem;"><?php echo (int) $r['score']; ?></strong></td>
					<td>▲ <?php echo (int) $r['upvotes']; ?></td>
					<td>💬 <?php echo (int) $r['replies']; ?></td>
					<td>
						<span style="background:<?php echo esc_attr( $g['color'] ); ?>;color:#fff;padding:2px 10px;border-radius:9999px;font-size:0.75rem;font-weight:700;">
							<?php echo esc_html( $g['icon'] . ' ' . $g['name'] ); ?>
						</span>
					</td>
					<td>
						<button type="button" class="button button-small swiftboard-recalc"
								data-user-id="<?php echo (int) $r['ID']; ?>">
							🔄 Recalculer
						</button>
					</td>
				</tr>
			<?php endforeach; ?>
			<?php if ( empty( $ranked ) ) : ?>
				<tr><td colspan="7" style="text-align:center;color:#999;padding:40px;"><?php esc_html_e( 'Aucun utilisateur actif.', 'swiftboard' ); ?></td></tr>
			<?php endif; ?>
			</tbody>
		</table>
		<script>
		document.querySelectorAll('.swiftboard-recalc').forEach(function(btn) {
			btn.addEventListener('click', function() {
				var uid = this.getAttribute('data-user-id');
				btn.disabled = true;
				btn.textContent = '⏳ Recalcul...';
				fetch(ajaxurl, {
					method: 'POST',
					headers: {'Content-Type': 'application/x-www-form-urlencoded'},
					body: 'action=swiftboard_recalc_reputation&user_id=' + uid + '&_wpnonce=<?php echo wp_create_nonce( 'swiftboard_recalc_reputation' ); ?>'
				}).then(function(r) { return r.json(); })
					.then(function(data) {
					if (data.success) {
						btn.textContent = '✅ Recalculé';
						setTimeout(function(){ location.reload(); }, 800);
					} else {
						btn.textContent = '❌ Erreur';
						btn.disabled = false;
					}
					});
			});
		});
		</script>
	</div>
	<?php
}

/**
 * Handler AJAX pour recalculer le score d'un utilisateur à la demande.
 */

