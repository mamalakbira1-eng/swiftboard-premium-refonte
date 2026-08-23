<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Rendu du digest hebdomadaire (HTML et texte brut).
 *
 * EXI-ARCH-01 : extrait de inc/email-digest.php, qui depassait 1000 lignes.
 * Isoler le gabarit de l'e-mail a un interet pratique : c'est la partie qu'on
 * modifie le plus souvent, et la seule ou une erreur ne casse que l'apparence
 * du message, pas la mecanique d'envoi.
 *
 * Les deux versions sont produites systematiquement : un client de messagerie
 * qui refuse le HTML doit recevoir un texte lisible, pas un message vide.
 *
 * @package SwiftBoard
 * @since 5.1.0
 */
// ============================================================================
/**
 * swiftboard_digest_render_html().
 *
 * @param int                  $user_id Identifiant de l'utilisateur.
 * @param array<string, mixed> $data    Données à traiter.
 * @return mixed
 */
function swiftboard_digest_render_html( $user_id, $data ) {
	$settings   = swiftboard_digest_get_settings();
	$user       = get_userdata( $user_id );
	$site_name  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$grades     = swiftboard_get_grades();
	$grade_info = $grades[ $data['my_stats']['grade'] ] ?? array(
		'icon' => '',
		'name' => 'Membre',
	);

	// Exception tracee a la regle R01 de tools/audit-senior.php :
	// cette fonction ne s'execute qu'en contexte e-mail (cron, previsualisation
	// admin), jamais pendant le rendu d'une page publique bufferisee par le
	// page-cache. La garde reste posee par prudence : si un appel remonte un
	// jour dans un template, on renvoie une chaine vide plutot que de laisser
	// PHP lever une erreur fatale.
	foreach ( (array) ob_get_status( true ) as $sb_niveau ) {
		if ( ! empty( $sb_niveau['name'] ) && 'default output handler' !== $sb_niveau['name'] ) {
			return '';
		}
	}

	ob_start();
	?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc_html( $site_name ); ?> — Digest hebdo</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:24px 0;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.06);">

<!-- Header -->
<tr><td style="background:linear-gradient(135deg,#006cbd,#006cbd);padding:32px 40px;text-align:center;">
<h1 style="margin:0;color:#fff;font-size:24px;font-weight:700;">📰 Votre digest hebdo</h1>
<p style="margin:8px 0 0;color:rgba(255,255,255,0.9);font-size:14px;"><?php echo esc_html( $site_name ); ?></p>
</td></tr>

<!-- Salutation -->
<tr><td style="padding:32px 40px 0;">
<p style="margin:0;font-size:16px;"><?php esc_html_e( 'Bonjour', 'swiftboard' ); ?><strong><?php echo esc_html( $user->display_name ); ?></strong> 👋</p>
<p style="margin:8px 0 0;font-size:14px;color:#6b7280;"><?php esc_html_e( 'Voici le résumé de votre activité sur le forum cette semaine.', 'swiftboard' ); ?></p>
</td></tr>

<!-- Stats perso -->
<tr><td style="padding:24px 40px 0;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:8px;">
<tr>
<td style="padding:20px;text-align:center;">
<div style="font-size:28px;font-weight:800;color:#006cbd;"><?php echo (int) $data['my_stats']['score']; ?></div>
<div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;margin-top:4px;"><?php esc_html_e( 'Score réputation', 'swiftboard' ); ?></div>
</td>
<td style="padding:20px;text-align:center;border-left:1px solid #e5e7eb;">
<div style="font-size:28px;font-weight:800;color:#16a34a;">▲ <?php echo (int) $data['my_stats']['upvotes']; ?></div>
<div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;margin-top:4px;"><?php esc_html_e( 'Upvotes reçus', 'swiftboard' ); ?></div>
</td>
<td style="padding:20px;text-align:center;border-left:1px solid #e5e7eb;">
<div style="font-size:28px;font-weight:800;color:#006cbd;">💬 <?php echo (int) $data['my_stats']['replies']; ?></div>
<div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;margin-top:4px;"><?php esc_html_e( 'Réponses reçues', 'swiftboard' ); ?></div>
</td>
</tr>
</table>
</td></tr>

<!-- Promotion éventuelle -->
	<?php
	if ( ! empty( $data['promotion'] ) ) :
		$to_info = $grades[ $data['promotion']['to'] ] ?? array(
			'icon' => '',
			'name' => $data['promotion']['to'],
		);
		?>
<tr><td style="padding:24px 40px 0;">
<div style="background:linear-gradient(135deg,#fef3c7,#fde68a);border-radius:8px;padding:20px;text-align:center;border:1px solid #fcd34d;">
<div style="font-size:32px;">🎉</div>
<div style="font-size:16px;font-weight:700;color:#92400e;margin-top:8px;"><?php esc_html_e( 'Félicitations, vous avez été promu !', 'swiftboard' ); ?></div>
<div style="font-size:14px;color:#78350f;margin-top:4px;"><?php esc_html_e( 'Vous êtes maintenant', 'swiftboard' ); ?><?php echo esc_html( $to_info['icon'] . ' ' . $to_info['name'] ); ?> (score : <?php echo (int) $data['promotion']['score']; ?> pts)</div>
</div>
</td></tr>
<?php endif; ?>

<!-- Sujets chauds -->
	<?php if ( ! empty( $data['hot_topics'] ) ) : ?>
<tr><td style="padding:24px 40px 0;">
<h2 style="margin:0 0 12px;font-size:16px;color:#1f2937;">🔥 Sujets chauds cette semaine</h2>
		<?php foreach ( $data['hot_topics'] as $i => $t ) : ?>
<a href="<?php echo esc_url( $t['url'] ); ?>" style="display:block;padding:12px 16px;background:#f9fafb;border-radius:6px;margin-bottom:6px;text-decoration:none;color:#1f2937;border-left:3px solid #ef4444;">
<div style="font-size:14px;font-weight:600;"><?php echo esc_html( ( $i + 1 ) . '. ' . $t['title'] ); ?></div>
<div style="font-size:12px;color:#6b7280;margin-top:2px;">▲ <?php echo (int) $t['score']; ?> · <?php echo (int) $t['votes']; ?> votes · par <?php echo esc_html( $t['author'] ); ?></div>
</a>
<?php endforeach; ?>
</td></tr>
<?php endif; ?>

<!-- Top répondeurs -->
	<?php if ( ! empty( $data['top_responders'] ) ) : ?>
<tr><td style="padding:24px 40px 0;">
<h2 style="margin:0 0 12px;font-size:16px;color:#1f2937;">🏆 Top répondeurs de la semaine</h2>
		<?php
		foreach ( $data['top_responders'] as $r ) :
			$medals = array( '🥇', '🥈', '🥉' );
			?>
<div style="display:flex;align-items:center;padding:10px 16px;background:#f9fafb;border-radius:6px;margin-bottom:6px;">
<span style="font-size:20px;margin-right:12px;"><?php echo esc_html( $medals[ $r['rank'] - 1 ] ?? '' ); ?></span>
<div>
<div style="font-size:14px;font-weight:600;color:#1f2937;"><?php echo esc_html( $r['name'] ); ?></div>
<div style="font-size:12px;color:#6b7280;"><?php echo (int) $r['count']; ?> réponses cette semaine</div>
</div>
</div>
<?php endforeach; ?>
</td></tr>
<?php endif; ?>

<!-- Footer -->
<tr><td style="padding:32px 40px;border-top:1px solid #e5e7eb;margin-top:24px;">
<p style="margin:0;font-size:13px;color:#6b7280;line-height:1.6;">
	<?php
	$footer = str_replace( '{site_name}', $site_name, $settings['footer_text'] );
	echo esc_html( $footer );
	?>
<br><br>
Vous recevez cet email car vous êtes inscrit sur <?php echo esc_html( $site_name ); ?>.
<br><?php esc_html_e( 'Pour vous désabonner,', 'swiftboard' ); ?><a href="<?php echo esc_url( home_url( '/profil/?digest_unsubscribe=1&uid=' . $user_id . '&token=' . swiftboard_digest_unsubscribe_token( $user_id ) ) ); ?>" style="color:#006cbd;">cliquez ici</a>.
</p>
</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
	<?php
	return ob_get_clean();
}

/**
 * swiftboard_digest_render_plain().
 *
 * @param int                  $user_id Identifiant de l'utilisateur.
 * @param array<string, mixed> $data    Données à traiter.
 * @return mixed
 */
function swiftboard_digest_render_plain( $user_id, $data ) {
	$settings   = swiftboard_digest_get_settings();
	$user       = get_userdata( $user_id );
	$site_name  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$grades     = swiftboard_get_grades();
	$grade_info = $grades[ $data['my_stats']['grade'] ] ?? array(
		'icon' => '',
		'name' => 'Membre',
	);

	$txt  = "=== Votre digest hebdo ===\n";
	$txt .= $site_name . "\n\n";
	$txt .= 'Bonjour ' . $user->display_name . ",\n\n";
	$txt .= "Voici le résumé de votre activité cette semaine.\n\n";
	$txt .= "-- Vos statistiques --\n";
	$txt .= 'Score de réputation : ' . $data['my_stats']['score'] . " pts\n";
	$txt .= 'Upvotes reçus       : ' . $data['my_stats']['upvotes'] . "\n";
	$txt .= 'Réponses reçues     : ' . $data['my_stats']['replies'] . "\n";
	$txt .= 'Grade actuel        : ' . $grade_info['icon'] . ' ' . $grade_info['name'] . "\n\n";

	if ( ! empty( $data['promotion'] ) ) {
		$to_info = $grades[ $data['promotion']['to'] ] ?? array( 'name' => $data['promotion']['to'] );
		$txt    .= '🎉 FÉLICITATIONS ! Vous avez été promu à ' . $to_info['name'] . "\n\n";
	}

	if ( ! empty( $data['hot_topics'] ) ) {
		$txt .= "-- Sujets chauds --\n";
		foreach ( $data['hot_topics'] as $i => $t ) {
			$txt .= ( $i + 1 ) . '. ' . $t['title'] . "\n";
			$txt .= '   Score : ' . $t['score'] . ' | ' . $t['votes'] . ' votes | par ' . $t['author'] . "\n";
			$txt .= '   ' . $t['url'] . "\n\n";
		}
	}

	if ( ! empty( $data['top_responders'] ) ) {
		$medals = array( '1er', '2e', '3e' );
		$txt   .= "-- Top répondeurs --\n";
		foreach ( $data['top_responders'] as $r ) {
			$txt .= $medals[ $r['rank'] - 1 ] . ' : ' . $r['name'] . ' (' . $r['count'] . " réponses)\n";
		}
		$txt .= "\n";
	}

	$txt .= str_replace( '{site_name}', $site_name, $settings['footer_text'] ) . "\n\n";
	// Le lien texte doit porter uid ET token, sinon l'endpoint sort en
	// silence (il exige les trois parametres) : l'utilisateur croit s'etre
	// desabonne, continue de recevoir, et finit par signaler en spam.
	$txt .= "--\nPour vous désabonner, visitez : " . home_url(
		'/profil/?digest_unsubscribe=1&uid=' . $user_id
		. '&token=' . swiftboard_digest_unsubscribe_token( $user_id )
	) . "\n";

	return $txt;
}

// ============================================================================
// 7. ENVOI DU DIGEST À UN UTILISATEUR

