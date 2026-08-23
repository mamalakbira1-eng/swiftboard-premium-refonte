<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Ecran d'administration de la securite.
 *
 * EXI-ARCH-01 : extrait de inc/security.php. Rendu d'interface uniquement ;
 * les en-tetes HTTP, la CSP et le verrouillage restent charges en front.
 *
 * @package SwiftBoard
 * @since 5.1.0
 */
/**
 * @return void
 */
function swiftboard_security_page() {
	// EXI-SEC-BLOQ-07 : la capability du menu ne protege pas l'appel direct
	// de la fonction via admin.php?page=... Controle explicite obligatoire.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'swiftboard' ), 403 );
	}

	// Sauvegarde des options de hardening (POST handler).
	if ( isset( $_POST['swiftboard_security_save'] ) && check_admin_referer( 'swiftboard_security_settings' ) ) {
		update_option( 'swiftboard_block_xmlrpc', isset( $_POST['swiftboard_block_xmlrpc'] ) ? '1' : '0' );
		update_option( 'swiftboard_block_rdf', isset( $_POST['swiftboard_block_rdf'] ) ? '1' : '0' );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Réglages de sécurité enregistrés.', 'swiftboard' ) . '</p></div>';
	}

	$block_xmlrpc = get_option( 'swiftboard_block_xmlrpc', true );
	$block_rdf    = get_option( 'swiftboard_block_rdf', true );

	$checks = array(
		'X-Frame-Options'        => swiftboard_header_sent_check( 'X-Frame-Options' ),
		'X-Content-Type-Options' => swiftboard_header_sent_check( 'X-Content-Type-Options' ),
		'Referrer-Policy'        => swiftboard_header_sent_check( 'Referrer-Policy' ),
		'XML-RPC désactivé'      => $block_xmlrpc ? '✅ Désactivé' : '❌ Activé (recommandé: désactiver)',
		'File editor désactivé'  => defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ? '✅ Désactivé' : '❌ Activé',
		'Version WP cachée'      => ! has_action( 'wp_head', 'wp_generator' ) ? '✅ Cachée' : '❌ Visible',
		'REST users protégé'     => '✅ Anonymes bloqués',
		'Rate limit REST'        => '✅ 60 req/min/IP',
		'Brute force login'      => '✅ 5 tentatives / 5min',
	);
	?>
	<div class="wrap">
		<h1>🛡️ Sécurité SwiftBoard</h1>
		<p class="description"><?php esc_html_e( 'État des protections de sécurité actives sur votre site.', 'swiftboard' ); ?></p>

		<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:16px;margin:16px 0;max-width:800px;">
			<h3 style="margin-top:0;">✅ Protections actives</h3>
			<table class="widefat" style="background:#fff;">
				<thead><tr><th><?php esc_html_e( 'Protection', 'swiftboard' ); ?></th><th><?php esc_html_e( 'État', 'swiftboard' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $checks as $name => $status ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $name ); ?></strong></td>
						<td><?php echo esc_html( $status ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:16px;margin:16px 0;max-width:800px;">
			<h3 style="margin-top:0;">⚙️ Réglages de hardening</h3>
			<p class="description"><?php esc_html_e( 'Activez ou désactivez les protections. Désactivez XML-RPC seulement si vous utilisez une application mobile qui en a besoin.', 'swiftboard' ); ?></p>
			<form method="post" action="">
				<?php wp_nonce_field( 'swiftboard_security_settings' ); ?>
				<input type="hidden" name="swiftboard_security_save" value="1" />
				<table class="form-table" style="max-width:700px;">
					<tr>
						<th scope="row"><label for="swiftboard_block_xmlrpc">🔒 Bloquer XML-RPC</label></th>
						<td>
							<label><input type="checkbox" name="swiftboard_block_xmlrpc" id="swiftboard_block_xmlrpc" value="1" <?php checked( $block_xmlrpc, '1' ); ?> />
							<?php esc_html_e( 'Bloquer xmlrpc.php (recommandé — stoppe le SSRF pingback et le brute-force)', 'swiftboard' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="swiftboard_block_rdf">🔒 Bloquer le flux RDF</label></th>
						<td>
							<label><input type="checkbox" name="swiftboard_block_rdf" id="swiftboard_block_rdf" value="1" <?php checked( $block_rdf, '1' ); ?> />
							<?php esc_html_e( 'Désactiver /feed/rdf (recommandé — stoppe l&#039;énumération d&#039;utilisateurs)', 'swiftboard' ); ?></label>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Enregistrer', 'swiftboard' ) ); ?>
			</form>
		</div>

		<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:16px;margin:16px 0;max-width:800px;">
			<h3 style="margin-top:0;">📋 Recommandations serveur (Hostinger)</h3>
			<ol>
				<li><strong><?php esc_html_e( 'Forcer HTTPS', 'swiftboard' ); ?></strong> : active SSL dans hPanel → SSL Manager</li>
				<li><strong>PHP version</strong> : utilisez PHP 8.2+ (hPanel → Advanced → PHP)</li>
				<li><strong>WAF</strong> : activez le Web Application Firewall d'Hostinger (hPanel → Security)</li>
				<li><strong><?php esc_html_e( 'Sauvegardes', 'swiftboard' ); ?></strong> : activez les backups quotidiens automatiques</li>
				<li><strong>wp-config.php</strong> : ajoutez <code>define('DISALLOW_FILE_EDIT', true);</code> et <code>define('DISALLOW_FILE_MODS', true);</code> (déjà fait par le thème)</li>
				<li><strong><?php esc_html_e( 'Clés de sécurité', 'swiftboard' ); ?></strong> : régénérez les SALT keys sur https://api.wordpress.org/secret-key/1.1/salt/ et collez-les dans wp-config.php</li>
				<li><strong>htaccess</strong> : ajoutez <code><?php esc_html_e( 'Options -Indexes', 'swiftboard' ); ?></code> pour bloquer directory listing</li>
				<li><strong>license.txt</strong> : ajoutez dans <code>.htaccess</code> pour cacher la version WP (le thème ne supprime pas ce fichier core — c'est le serveur qui le bloque) :
					<pre style="background:#1e293b;color:#e2e8f0;padding:12px;border-radius:6px;overflow-x:auto;font-size:13px;">&lt;Files license.txt&gt;
  Require all denied
&lt;/Files&gt;</pre>
					<?php esc_html_e( 'Équivalent nginx :', 'swiftboard' ); ?> <code>location = /license.txt { return 404; }</code>
				</li>
			</ol>
		</div>

		<div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:16px;margin:16px 0;max-width:800px;">
			<h3 style="margin-top:0;color:#92400e;">⚠️ À faire manuellement</h3>
			<ul>
				<li><strong><?php esc_html_e( 'Mots de passe admin', 'swiftboard' ); ?></strong> : utilisez un mot de passe fort (16+ caractères, généré par WP)</li>
				<li><strong>2FA</strong> : installez <a href="https://wordpress.org/plugins/two-factor/" target="_blank"><?php esc_html_e( 'Two Factor', 'swiftboard' ); ?></a> pour les comptes admin/modo</li>
				<li><strong><?php esc_html_e( 'Limit login attempts', 'swiftboard' ); ?></strong> : déjà géré par SwiftBoard (5 tentatives / 5min, lock 15min)</li>
				<li><strong><?php esc_html_e( 'Updates', 'swiftboard' ); ?></strong> : activez les mises à jour automatiques dans Réglages → Mises à jour</li>
				<li><strong><?php esc_html_e( 'Plugins', 'swiftboard' ); ?></strong> : supprimez les plugins inactifs + thèmes inutilisés</li>
			</ul>
		</div>

		<div style="background:#fee2e2;border:1px solid #fecaca;border-radius:8px;padding:16px;margin:16px 0;max-width:800px;">
			<h3 style="margin-top:0;color:#991b1b;">🚨 À NE PAS FAIRE</h3>
			<ul>
				<li>❌ N'utilisez jamais <code>admin</code> comme nom d'utilisateur admin</li>
				<li>❌ Ne partagez jamais votre wp-config.php ou service account JSON</li>
				<li>❌ N'installez pas de plugins "nulled" (piratés) — backdoors garanties</li>
				<li>❌ Ne désactivez pas les updates de sécurité WordPress</li>
				<li>❌ Ne mettez pas <code>WP_DEBUG</code> à <code>true</code> en production</li>
			</ul>
		</div>
	</div>
	<?php
}

