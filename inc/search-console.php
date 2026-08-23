<?php
if ( ! defined( 'ABSPATH' )) exit;

/**
 * SwiftBoard - Google Search Console Integration
 *
 * Soumet automatiquement les nouvelles URLs à Google Search Console via
 * l'Indexing API (https://developers.google.com/search/apis/indexing-api/v3/quickstart).
 *
 * 1. Page admin "🔍 Search Console" — config API key + service account JSON
 * 2. Hook sur publish_topic / publish_reply → soumission URL
 * 3. Bouton "Soumettre tout le forum" — batch de toutes les URLs
 * 4. Status tracking (dernière soumission, status HTTP, réponse Google)
 * 5. Cron quotidien pour soumettre les URLs modifiées
 *
 * Prérequis :
 *  - Créer un projet Google Cloud
 *  - Activer Indexing API
 *  - Créer un Service Account (JSON key)
 *  - Ajouter le service account comme propriétaire dans Search Console
 *
 * @package SwiftBoard
 * @since 3.5.0
 */
// ============================================================================
// 1. MENU ADMIN
// ============================================================================
add_action(
	'admin_menu',
	function () {
		add_submenu_page(
			'swiftboard-dashboard',
			__( 'Search Console', 'swiftboard' ),
			__( '🔍 Search Console', 'swiftboard' ),
			'manage_options',
			'swiftboard-search-console',
			'swiftboard_search_console_page'
		);
	}
);

// ============================================================================
// 2. HELPERS
// ============================================================================
/**
 * @return array<string, mixed>
 */
function swiftboard_sc_get_settings() {
	return array(
		'service_account_json' => swiftboard_sc_decrypt( get_option( 'swiftboard_sc_service_account', '' ) ),
		'auto_submit'          => (int) get_option( 'swiftboard_sc_auto_submit', 1 ),
		'last_submit'          => get_option( 'swiftboard_sc_last_submit', '' ),
		'stats'                => array(
			'submitted' => (int) get_option( 'swiftboard_sc_submitted_count', 0 ),
			'failed'    => (int) get_option( 'swiftboard_sc_failed_count', 0 ),
		),
	);
}

// ============================================================================
// E-4 FIX: CHIFFREMENT DU SERVICE ACCOUNT JSON
// ============================================================================
/**
 * Chiffre le JSON du compte de service avant stockage en base (AES-256-CBC).
 *
 * @param string $data JSON du compte de service, en clair.
 * @return string Chaine chiffree encodee en base64, ou '' si l'entree est vide.
 */
function swiftboard_sc_encrypt( $data ) {
	if (empty( $data )) return '';
	$key       = wp_salt( 'auth' );
	$iv        = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-256-cbc' ) );
	$encrypted = openssl_encrypt( $data, 'aes-256-cbc', $key, 0, $iv );
	if ($encrypted === false) return '';
	return base64_encode( $iv . '::' . $encrypted );
}

/**
 * Dechiffre la valeur produite par swiftboard_sc_encrypt().
 *
 * @param string $data Chaine chiffree encodee en base64.
 * @return string JSON en clair, ou '' si l'entree est vide ou illisible.
 */
function swiftboard_sc_decrypt( $data ) {
	if (empty( $data )) return '';
	$key     = wp_salt( 'auth' );
	$decoded = base64_decode( $data );
	if ( $decoded === false || strpos( $decoded, '::' ) === false ) {
		// Not encrypted (legacy data) — return as-is for backward compatibility
		return $data;
	}
	list($iv, $encrypted) = explode( '::', $decoded, 2 );
	$decrypted            = openssl_decrypt( $encrypted, 'aes-256-cbc', $key, 0, $iv );
	return $decrypted !== false ? $decrypted : '';
}

/**
 * @return array<string, mixed>
 */
function swiftboard_sc_get_log() {
	$log = get_option( 'swiftboard_sc_log', array() );
	return is_array( $log ) ? array_slice( $log, -50 ) : array();
}

/**
 * swiftboard_sc_add_log().
 *
 * @param string $url     URL.
 * @param mixed  $status  À documenter.
 * @param mixed  $message À documenter.
 * @return void
 */
function swiftboard_sc_add_log( $url, $status, $message ) {
	$log   = swiftboard_sc_get_log();
	$log[] = array(
		'time'    => current_time( 'mysql' ),
		'url'     => $url,
		'status'  => $status,
		'message' => $message,
	);
	update_option( 'swiftboard_sc_log', array_slice( $log, -100 ), false );
}

// ============================================================================
// 3. SOUMETTRE UNE URL À GOOGLE
// ============================================================================

/**
 * swiftboard_sc_validate_url().
 *
 * @param string $url URL.
 * @return bool
 */
function swiftboard_sc_validate_url( $url ) {
	$parsed = wp_parse_url( $url );
	if ( ! isset( $parsed['scheme'] ) || ! in_array( $parsed['scheme'], array( 'http', 'https' ), true ) ) {
		return false;
	}
	$host      = $parsed['host'] ?? '';
	$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
	if ( $host !== $site_host ) {
		return false;
	}
	return true;
}

/**
 * swiftboard_sc_submit_url().
 *
 * @param string $url  URL.
 * @param string $type Type de contenu. Optionnel.
 * @return array<string, mixed>
 */
function swiftboard_sc_submit_url( $url, $type = 'URL_UPDATED' ) {
	$settings = swiftboard_sc_get_settings();
	if ( ! swiftboard_sc_validate_url( $url ) ) {
		return array(
			'success' => false,
			'error'   => 'URL must be on this domain',
		); }
	if ( empty( $settings['service_account_json'] ) ) {
		return array(
			'success' => false,
			'error'   => 'Service account JSON not configured',
		);
	}

	$service_account = json_decode( $settings['service_account_json'], true );
	if ( ! $service_account || ! isset( $service_account['private_key'] ) ) {
		return array(
			'success' => false,
			'error'   => 'Invalid service account JSON',
		);
	}

	// Générer un JWT (simplifié — en production, utiliser firebase/php-jwt)
	$token = swiftboard_sc_generate_jwt( $service_account );

	if ( is_wp_error( $token ) ) {
		return array(
			'success' => false,
			'error'   => $token->get_error_message(),
		);
	}

	// Requête à l'Indexing API
	$response = wp_remote_post(
		'https://indexing.googleapis.com/v3/urlNotifications:publish',
		array(
			'timeout' => 30,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $token,
			),
			'body'    => wp_json_encode(
				array(
					'url'  => $url,
					'type' => $type,
				)
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		swiftboard_sc_add_log( $url, 'error', $response->get_error_message() );
		return array(
			'success' => false,
			'error'   => $response->get_error_message(),
		);
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );

	if ( $code >= 200 && $code < 300 ) {
		update_option( 'swiftboard_sc_submitted_count', (int) get_option( 'swiftboard_sc_submitted_count', 0 ) + 1 );
		update_option( 'swiftboard_sc_last_submit', current_time( 'mysql' ) );
		swiftboard_sc_add_log( $url, 'success', 'Submitted (' . $code . ')' );
		return array(
			'success' => true,
			'code'    => $code,
			'body'    => $body,
		);
	} else {
		update_option( 'swiftboard_sc_failed_count', (int) get_option( 'swiftboard_sc_failed_count', 0 ) + 1 );
		swiftboard_sc_add_log( $url, 'error', 'HTTP ' . $code . ': ' . substr( $body, 0, 200 ) );
		return array(
			'success' => false,
			'error'   => 'HTTP ' . $code,
			'body'    => $body,
		);
	}
}

// ============================================================================
// 4. GÉNÉRATION JWT (simplifié)
// ============================================================================
/**
 * swiftboard_sc_generate_jwt().
 *
 * @param mixed $service_account À documenter.
 * @return mixed
 */
function swiftboard_sc_generate_jwt( $service_account ) {
	$now     = time();
	$header  = array(
		'alg' => 'RS256',
		'typ' => 'JWT',
	);
	$payload = array(
		'iss'   => $service_account['client_email'],
		'scope' => 'https://www.googleapis.com/auth/indexing',
		'aud'   => 'https://oauth2.googleapis.com/token',
		'exp'   => $now + 3600,
		'iat'   => $now,
	);

	$base64_header  = rtrim( strtr( base64_encode( wp_json_encode( $header ) ), '+/', '-_' ), '=' );
	$base64_payload = rtrim( strtr( base64_encode( wp_json_encode( $payload ) ), '+/', '-_' ), '=' );

	$signature_input = $base64_header . '.' . $base64_payload;

	// Signer avec la clé privée
	$private_key = openssl_pkey_get_private( $service_account['private_key'] );
	if ( ! $private_key ) {
		return new WP_Error( 'jwt_error', 'Cannot load private key' );
	}

	// openssl_pkey_free() est DEPRECIEE depuis PHP 8.0 : openssl_pkey_get_private()
	// renvoie desormais un OpenSSLAsymmetricKey, libere automatiquement par le
	// ramasse-miettes. L'appel emettait un Deprecated a chaque soumission
	// d'URL a Google, donc a chaque publication de sujet.
	$signature = '';
	if ( ! openssl_sign( $signature_input, $signature, $private_key, 'sha256WithRSAEncryption' ) ) {
		return new WP_Error( 'jwt_error', 'Cannot sign JWT' );
	}

	$base64_signature = rtrim( strtr( base64_encode( $signature ), '+/', '-_' ), '=' );
	$jwt              = $signature_input . '.' . $base64_signature;

	// Échanger le JWT contre un access token
	$response = wp_remote_post(
		'https://oauth2.googleapis.com/token',
		array(
			'timeout' => 30,
			'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
			'body'    => http_build_query(
				array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				)
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! isset( $body['access_token'] ) ) {
		return new WP_Error( 'token_error', 'No access token in response: ' . substr( wp_remote_retrieve_body( $response ), 0, 200 ) );
	}

	return $body['access_token'];
}

// ============================================================================
// 5. HOOK AUTO-SUBMIT SUR PUBLISH TOPIC/REPLY
// ============================================================================
add_action(
	'transition_post_status',
	function ( $new_status, $old_status, $post ) {
		if ( ! in_array( $post->post_type, array( 'topic', 'reply' ), true )) return;
		if ($new_status !== 'publish') return;

		$settings = swiftboard_sc_get_settings();
		if ( ! (int) $settings['auto_submit']) return;

		$url = get_permalink( $post->ID );
		if ( ! $url) return;

		// Pour les replies, soumettre l'URL du topic parent
		if ( $post->post_type === 'reply' ) {
			$topic_id = wp_get_post_parent_id( $post->ID );
			if ( $topic_id ) {
				$url = get_permalink( $topic_id );
			}
		}

		swiftboard_sc_submit_url( $url );
	},
	10,
	3
);

// ============================================================================
// 6. CRON QUOTIDIEN — SOUMETTRE LES URLs MODIFIÉES
// ============================================================================
/*
 * EXI-ARCH-01 — planification sur `admin_init`, pas sur `wp`.
 *
 * Ce module est admin-only : le hook `wp` ne s'y declenche donc jamais, sauf
 * a ce qu'un administrateur charge une page d'administration au moment precis
 * ou le cron doit etre (re)planifie. La tache pouvait rester non planifiee
 * indefiniment sur un site peu administre.
 *
 * `admin_init` se declenche a chaque chargement du back-office : c'est le hook
 * approprie pour un module qui n'existe que la. La verification
 * wp_next_scheduled() rend l'operation idempotente.
 */
add_action(
	'admin_init',
	function () {
		if ( ! wp_next_scheduled( 'swiftboard_sc_daily_submit' ) ) {
			wp_schedule_event( time(), 'daily', 'swiftboard_sc_daily_submit' );
		}
	}
);

add_action(
	'swiftboard_sc_daily_submit',
	function () {
		$settings = swiftboard_sc_get_settings();
		if (empty( $settings['service_account_json'] )) return;

		// Topics modifiés dans les dernières 24h
		$topics = get_posts(
			array(
				'post_type'      => 'topic',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'date_query'     => array(
					array(
						'column' => 'post_modified',
						'after'  => '24 hours ago',
					),
				),
			)
		);

		foreach ( $topics as $t ) {
			swiftboard_sc_submit_url( get_permalink( $t->ID ) );
			sleep( 1 ); // Rate limit : 1 req/sec pour Google Indexing API
		}
	}
);

// ============================================================================
// 7. PAGE ADMIN
// ============================================================================
/**
 * @return void
 */
function swiftboard_search_console_page() {
	if ( ! current_user_can( 'manage_options' )) wp_die( 'Forbidden' );

	// Sauvegarder les settings
	if ( isset( $_POST['save_sc_settings'] ) && check_admin_referer( 'swiftboard_sc_settings' ) ) {
		update_option( 'swiftboard_sc_service_account', swiftboard_sc_encrypt( sanitize_textarea_field( wp_unslash( $_POST['service_account_json'] ?? '' ) ) ) );
		update_option( 'swiftboard_sc_auto_submit', isset( $_POST['auto_submit'] ) ? 1 : 0 );
		echo '<div class="notice notice-success is-dismissible"><p>✅ Réglages enregistrés.</p></div>';
	}

	// Soumettre une URL manuellement
	if ( isset( $_POST['submit_url'] ) && check_admin_referer( 'swiftboard_sc_submit_url' ) ) {
		$url    = esc_url_raw( wp_unslash( $_POST['manual_url'] ?? '' ) );
		$result = swiftboard_sc_submit_url( $url );
		echo '<div class="notice notice-' . ( $result['success'] ? 'success' : 'error' ) . ' is-dismissible"><p>';
		echo $result['success'] ? '✅ URL soumise : ' . esc_html( $url ) : '❌ Erreur : ' . esc_html( $result['error'] );
		echo '</p></div>';
	}

	// Soumettre tout le forum
	if ( isset( $_POST['submit_all'] ) && check_admin_referer( 'swiftboard_sc_submit_all' ) ) {
		$topics  = get_posts(
			array(
				'post_type'      => 'topic',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
			)
		);
		$success = 0;
		$failed  = 0;
		foreach ( $topics as $t ) {
			$r = swiftboard_sc_submit_url( get_permalink( $t->ID ) );
			if ($r['success']) $success++;
			else $failed++;
			sleep( 1 ); // Rate limit
		}
		echo '<div class="notice notice-success is-dismissible"><p>';
		printf( '✅ %d URLs soumises, %d échecs', $success, $failed );
		echo '</p></div>';
	}

	$settings = swiftboard_sc_get_settings();
	$log      = swiftboard_sc_get_log();
	?>
	<div class="wrap">
		<h1>🔍 Google Search Console</h1>
		<p class="description">
			Soumettez automatiquement vos URLs à Google via l'Indexing API.
			Accélère l'indexation de vos nouveaux sujets et réponses.
		</p>

		<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:16px;margin:16px 0;max-width:800px;">
			<h3 style="margin-top:0;">📊 Statistiques</h3>
			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;">
				<div><strong><?php echo (int) $settings['stats']['submitted']; ?></strong> URLs soumises</div>
				<div><strong><?php echo (int) $settings['stats']['failed']; ?></strong> échecs</div>
				<div><strong><?php echo esc_html( $settings['last_submit'] ?: 'Jamais' ); ?></strong> dernière soumission</div>
			</div>
		</div>

		<h2>⚙️ Configuration</h2>
		<form method="post" action="" style="max-width:800px;">
			<?php wp_nonce_field( 'swiftboard_sc_settings' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="service_account_json"><?php esc_html_e( 'Service Account JSON', 'swiftboard' ); ?></label></th>
					<td>
						<textarea name="service_account_json" id="service_account_json"
									rows="8" class="large-text code"
									placeholder='{"type":"service_account","project_id":"...","private_key":"-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n","client_email":"...@....iam.gserviceaccount.com",...}'><?php echo esc_textarea( $settings['service_account_json'] ); ?></textarea>
						<p class="description">
							Collez ici le contenu du fichier JSON du Service Account Google Cloud.<br>
							<a href="https://developers.google.com/search/apis/indexing-api/v3/quickstart" target="_blank"><?php esc_html_e( 'Guide de création du Service Account', 'swiftboard' ); ?></a>
						</p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Soumission automatique', 'swiftboard' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="auto_submit" value="1" <?php checked( $settings['auto_submit'], 1 ); ?>>
							Soumettre automatiquement chaque nouveau sujet/réponse à Google
						</label>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" name="save_sc_settings" class="button button-primary">💾 Enregistrer</button>
			</p>
		</form>

		<h2>📤 Soumission manuelle</h2>
		<form method="post" action="" style="max-width:800px;">
			<?php wp_nonce_field( 'swiftboard_sc_submit_url' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="manual_url">URL à soumettre</label></th>
					<td>
						<input type="url" name="manual_url" id="manual_url" class="regular-text"
								placeholder="https://votre-site.com/forums/topic/...">
						<button type="submit" name="submit_url" class="button button-secondary">📤 Soumettre</button>
					</td>
				</tr>
			</table>
		</form>

		<h2>📋 Soumission en masse</h2>
		<form method="post" action="" style="max-width:800px;">
			<?php wp_nonce_field( 'swiftboard_sc_submit_all' ); ?>
			<p>
				<button type="submit" name="submit_all" class="button button-primary"
						data-confirm="⚠️ Cela va soumettre jusqu'à 100 URLs à Google (1 req/sec, ~2min). Continuer ?">
					🚀 Soumettre les 100 derniers sujets
				</button>
			</p>
			<p class="description">
				Limite Google Indexing API : 200 requêtes/jour, 1 req/sec.
			</p>
		</form>

		<?php if ( ! empty( $log ) ) : ?>
		<h2>📜 Journal des soumissions</h2>
		<table class="wp-list-table widefat fixed striped" style="max-width:1000px;">
			<thead><tr><th><?php esc_html_e( 'Date', 'swiftboard' ); ?></th><th>URL</th><th><?php esc_html_e( 'Status', 'swiftboard' ); ?></th><th><?php esc_html_e( 'Message', 'swiftboard' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( array_reverse( $log ) as $entry ) : ?>
				<tr>
					<td><?php echo esc_html( $entry['time'] ); ?></td>
					<td style="max-width:300px;word-break:break-all;"><a href="<?php echo esc_url( $entry['url'] ); ?>" target="_blank"><?php echo esc_html( substr( $entry['url'], 0, 60 ) ); ?>...</a></td>
					<td><?php echo $entry['status'] === 'success' ? '✅' : '❌'; ?> <?php echo esc_html( $entry['status'] ); ?></td>
					<td><?php echo esc_html( substr( $entry['message'], 0, 100 ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
	</div>
	<?php
}

