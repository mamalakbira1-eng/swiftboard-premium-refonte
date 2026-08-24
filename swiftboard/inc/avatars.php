<?php
if ( ! defined( 'ABSPATH' )) exit;

/**
 * SwiftBoard — Avatars du forum (style Reddit)
 *
 * Remplace les avatars Gravatar par un jeu d'avatars prédéfinis hébergés
 * localement, dans un style manga/ninja original (inspiré de l'univers naruto
 * sans copier les personnages protégés) : 15 images WebP : 9 ninjas + 6 archétypes de la société en ninjas (étudiant, femme mature, chef, médecin, athlète, gardien).
 *
 * Pourquoi :
 *  - Les comptes importés ont des emails `@imported.swiftboard.test` :
 *    ils n'ont jamais de vrai Gravatar -> tous les avatars de réponses étaient
 *    des silhouettes grises par défaut, incohérentes.
 *  - Plus de dépendance externe (perf + RGPD), pas de requête vers
 *    secure.gravatar.com, et des avatars variés pour les membres.
 *
 * Chaque membre choisit son avatar dans son profil, stocké en user_meta
 * `swiftboard_avatar`. Valeur par défaut = avatar attribué selon l'ID.
 *
 * @package SwiftBoard
 */
/**
 * Liste des avatars disponibles.
 *
 * @return array<int, array{file:string,label:string}>
 */
function swiftboard_get_avatars_list() {
	$base = SWIFTBOARD_URI . '/assets/img/avatars/';
	return array(
		1  => array(
			'file'  => 'avatar-01.webp',
			'label' => __( 'Renard', 'swiftboard' ),
		),
		2  => array(
			'file'  => 'avatar-02.webp',
			'label' => __( 'Loup', 'swiftboard' ),
		),
		3  => array(
			'file'  => 'avatar-03.webp',
			'label' => __( 'Lapin', 'swiftboard' ),
		),
		4  => array(
			'file'  => 'avatar-04.webp',
			'label' => __( 'Tortue sage', 'swiftboard' ),
		),
		5  => array(
			'file'  => 'avatar-05.webp',
			'label' => __( 'Lionceau', 'swiftboard' ),
		),
		6  => array(
			'file'  => 'avatar-06.webp',
			'label' => __( 'Chat', 'swiftboard' ),
		),
		7  => array(
			'file'  => 'avatar-07.webp',
			'label' => __( 'Raton laveur', 'swiftboard' ),
		),
		8  => array(
			'file'  => 'avatar-08.webp',
			'label' => __( 'Panda roux', 'swiftboard' ),
		),
		9  => array(
			'file'  => 'avatar-09.webp',
			'label' => __( 'Hibou savant', 'swiftboard' ),
		),
		10 => array(
			'file'  => 'avatar-10.webp',
			'label' => __( 'Ourson', 'swiftboard' ),
		),
		11 => array(
			'file'  => 'avatar-11.webp',
			'label' => __( 'Biche élégante', 'swiftboard' ),
		),
		12 => array(
			'file'  => 'avatar-12.webp',
			'label' => __( 'Chat basketteur', 'swiftboard' ),
		),
		13 => array(
			'file'  => 'avatar-13.webp',
			'label' => __( 'Hibou médecin', 'swiftboard' ),
		),
		14 => array(
			'file'  => 'avatar-14.webp',
			'label' => __( 'Guépard athlète', 'swiftboard' ),
		),
		15 => array(
			'file'  => 'avatar-15.webp',
			'label' => __( 'Berger gardien', 'swiftboard' ),
		),
	);
}

/**
 * Clé d'avatar choisie par un membre (1..15), ou 0 si aucun choix.
 *
 * @param int $user_id
 * @return int
 */
function swiftboard_get_user_avatar_id( $user_id = 0 ) {
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}
	$key = (int) get_user_meta( $user_id, 'swiftboard_avatar', true );
	$max = count( swiftboard_get_avatars_list() );
	return ( $key >= 1 && $key <= $max ) ? $key : 0;
}

/**
 * URL (ou data-URI pour SVG) de l'avatar d'un membre.
 *
 * Retourne un <img> prêt à l'emploi.
 *
 * @param int    $user_id
 * @param int    $size    Taille en px (affichage).
 * @param string $class   Classes CSS additionnelles.
 * @return string
 */
function swiftboard_get_user_avatar_html( $user_id, $size = 32, $class = '' ) {
	$user_id = (int) $user_id;
	$size    = max( 16, (int) $size );
	$avatars = swiftboard_get_avatars_list();

	// Avatar choisi par le membre.
	$key = swiftboard_get_user_avatar_id( $user_id );
	if ( $key && isset( $avatars[ $key ] ) ) {
		$info  = $avatars[ $key ];
		$url   = SWIFTBOARD_URI . '/assets/img/avatars/' . $info['file'];
		$label = $info['label'];
		return '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $label )
			. '" width="' . $size . '" height="' . $size
			. '" loading="lazy" decoding="async" class="' . esc_attr( $class ) . '">';
	}

	// Repli (aucun avatar choisi) : initiale du nom sur fond BLEU,
	// même couleur que l'avatar « Athlète » (#6BC0E8). Style identique au
	// fallback initiales+couleur existant.
	$user     = get_userdata( $user_id );
	$initiale = $user ? mb_strtoupper( mb_substr( $user->display_name, 0, 1 ) ) : '?';
	// La couleur vient du reglage « Couleur des avatars par defaut » du
	// Customizer. Elle etait codee en dur (#6BC0E8) : le reglage existait dans
	// l'interface mais n'avait aucun effet, y compris apres cablage CSS, car un
	// style inline l'emporte sur toute feuille de style. Constate en test
	// dynamique : avatar peint rgb(107,192,232) quelle que soit la valeur choisie.
	$bleu = get_theme_mod( 'swiftboard_avatar_fallback_color', '#6BC0E8' );
	if ( ! preg_match( '/^#[0-9a-fA-F]{3,8}$/', (string) $bleu ) ) {
		$bleu = '#6BC0E8';
	}
	$fs       = max( 10, (int) round( $size * 0.55 ) );
	return '<span class="avatar-mock sb-avatar-fallback" role="img"'
		. ' aria-label="' . esc_attr( $initiale ) . '"'
		. ' style="background:' . $bleu . ';width:' . $size . 'px;height:' . $size
		. 'px;font-size:' . $fs . 'px;line-height:' . $size . 'px;'
		. 'display:inline-block;text-align:center;border-radius:50%;color:#fff;'
		. 'font-weight:700;overflow:hidden;">' . esc_html( $initiale ) . '</span>';
}

/**
 * Affichage du sélecteur d'avatar dans le profil.
 * Appelé depuis le profil membre.
 */
function swiftboard_avatar_picker(): void {
	if ( ! is_user_logged_in()) return;
	$uid     = get_current_user_id();
	$current = swiftboard_get_user_avatar_id( $uid );
	$avatars = swiftboard_get_avatars_list();
	?>
	<div class="sb-avatar-picker">
		<h2 class="sb-profile-section-title"><?php esc_html_e( 'Avatar', 'swiftboard' ); ?></h2>
		<p style="font-size:13px;color:var(--color-text-muted);margin:0 0 12px;">
			<?php esc_html_e( 'Choisissez votre avatar :', 'swiftboard' ); ?>
		</p>
		<div class="sb-avatar-grid">
			<?php foreach ( $avatars as $key => $info ) : ?>
				<button type="button"
						class="sb-avatar-option <?php echo $current === $key ? 'selected' : ''; ?>"
						data-avatar-id="<?php echo (int) $key; ?>"
						data-nonce="<?php echo esc_attr( wp_create_nonce( 'sb_avatar_' . $uid ) ); ?>"
						aria-pressed="<?php echo $current === $key ? 'true' : 'false'; ?>"
						aria-label="<?php echo esc_attr( $info['label'] ); ?>">
					<img src="<?php echo esc_url( SWIFTBOARD_URI . '/assets/img/avatars/' . $info['file'] ); ?>"
						alt="<?php echo esc_attr( $info['label'] ); ?>" width="40" height="40" loading="lazy">
				</button>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}

/**
 * Endpoint REST pour changer d'avatar.
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'swiftboard/v1',
			'/avatar',
			array(
				'methods'             => 'POST',
				'permission_callback' => function () {
					return is_user_logged_in();
				},
				'callback'            => function ( WP_REST_Request $req ) {
					$uid   = get_current_user_id();
					$key   = (int) $req->get_param( 'avatar_id' );
					$nonce = sanitize_text_field( (string) $req->get_param( 'nonce' ) );

					if ( ! wp_verify_nonce( $nonce, 'sb_avatar_' . $uid ) ) {
						return new WP_REST_Response( array( 'error' => 'nonce' ), 403 );
					}
					$max = count( swiftboard_get_avatars_list() );
					if ( $key < 1 || $key > $max ) {
						return new WP_REST_Response( array( 'error' => 'invalid' ), 400 );
					}
					update_user_meta( $uid, 'swiftboard_avatar', $key );
					update_user_meta( $uid, 'swiftboard_avatar_id', $key );
					return new WP_REST_Response(
						array(
							'ok'        => true,
							'avatar_id' => $key,
						)
					);
				},
			)
		);
	}
);

/**
 * JS du sélecteur d'avatar (chargé sur le profil).
 * CSP-safe: externalisé vers assets/js/avatar-select.js, config via data-*.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! is_user_logged_in() ) {
			return;
		}
		wp_enqueue_script(
			'swiftboard-avatar-select',
			SWIFTBOARD_ASSETS . '/js/avatar-select.js',
			array(),
			SWIFTBOARD_VERSION,
			true
		);
		// Config via data-* (CSP-safe)
		add_action( 'wp_footer', 'swiftboard_print_avatar_config', 5 );
	}
);

// ============================================================================
// CHOIX D'AVATAR À L'INSCRIPTION
// ============================================================================
/**
 * Affiche le sélecteur d'avatar dans le formulaire d'inscription.
 * Chaque nouvel inscrit choisit son avatar ; s'il n'en choisit aucun, le
 * fallback par défaut (initiale sur fond bleu) s'applique automatiquement.
 */
add_action(
	'register_form',
	function () {
		$avatars = swiftboard_get_avatars_list();
		?>
	<div class="sb-avatar-picker sb-avatar-register">
		<h2 class="sb-profile-section-title"><?php esc_html_e( 'Choisissez votre avatar', 'swiftboard' ); ?></h2>
		<p style="font-size:13px;color:var(--color-text-muted);margin:0 0 12px;">
			<?php esc_html_e( 'Sélectionnez un avatar (facultatif) :', 'swiftboard' ); ?>
		</p>
		<div class="sb-avatar-grid">
			<?php foreach ( $avatars as $key => $info ) : ?>
				<button type="button" class="sb-avatar-option sb-reg-avatar" data-avatar-id="<?php echo (int) $key; ?>"
						aria-pressed="false" aria-label="<?php echo esc_attr( $info['label'] ); ?>">
					<img src="<?php echo esc_url( SWIFTBOARD_URI . '/assets/img/avatars/' . $info['file'] ); ?>"
						alt="<?php echo esc_attr( $info['label'] ); ?>" width="40" height="40" loading="lazy">
				</button>
			<?php endforeach; ?>
		</div>
		<input type="hidden" name="swiftboard_avatar_choice" id="swiftboard_avatar_choice" value="">
		<p style="font-size:12px;color:var(--color-text-muted);margin-top:8px;">
			<?php esc_html_e( 'Aucun choix ? Vous aurez automatiquement votre initiale sur fond bleu.', 'swiftboard' ); ?>
		</p>
	</div>
		<?php
		// Avatar selection JS is handled by avatar-select.js (CSP-safe, external file)
	}
);

/**
 * Sauvegarde l'avatar choisi à l'inscription.
 */
add_action(
	'user_register',
	function ( $user_id ) {
		// Valeur envoyée par le formulaire d'inscription.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- user_register is a WP core hook fired after registration, not a direct form submission.
		$choice = isset( $_POST['swiftboard_avatar_choice'] ) ? (int) wp_unslash( $_POST['swiftboard_avatar_choice'] ) : 0;
		$max    = count( swiftboard_get_avatars_list() );
		if ( $choice >= 1 && $choice <= $max ) {
			update_user_meta( $user_id, 'swiftboard_avatar', $choice );
		}
		// Si aucun choix : on ne stocke rien -> le fallback initiale-bleue s'applique.
	}
);


// === LOT 11 : Avatar fallback Reddit-like (cercle + initiale + couleur Customizer) ===

/**
 * Génère un avatar fallback Reddit-like (cercle + initiale + couleur Customizer).
 *
 * @param int $user_id ID utilisateur.
 * @param int $size    Taille en pixels (défaut 48).
 * @return string HTML de l'avatar.
 */
function swiftboard_avatar_reddit_fallback( int $user_id, int $size = 48 ): string {
	$user = get_userdata( $user_id );
	if ( ! $user) return '';

	$name      = $user->display_name ?: $user->user_login;
	$initial   = mb_strtoupper( mb_substr( $name, 0, 1 ) );
	$color     = get_theme_mod( 'swiftboard_avatar_fallback_color', '#006cbd' );
	$font_size = (int) ( $size * 0.4 );

	return sprintf(
		'<span class="sb-avatar-fallback" role="img" aria-label="%s" '
		. 'style="background:%s;color:#fff;width:%dpx;height:%dpx;'
		. 'border-radius:50%%;display:inline-flex;align-items:center;'
		. 'justify-content:center;font-weight:700;font-size:%dpx;'
		. 'font-family:var(--font-sans,system-ui,-apple-system,sans-serif);'
		. 'line-height:1;flex-shrink:0;">%s</span>',
		esc_attr( $name ),
		esc_attr( $color ),
		$size,
		$size,
		$font_size,
		esc_html( $initial )
	);
}

/**
 * Télécharge une image d'avatar depuis une URL HTTPS et la stocke localement.
 * Garde-fous : HTTPS obligatoire, IPs privées bloquées, taille max 2 Mo.
 *
 * @param string $url      URL HTTPS de l'image.
 * @param int    $user_id  ID utilisateur.
 * @return string|false Chemin relatif ou false si échec.
 */
function swiftboard_download_avatar( $url, $user_id ) {
	if (strpos( $url, 'https://' ) !== 0) return false;

	$host = parse_url( $url, PHP_URL_HOST );
	if ( $host ) {
		$ip = gethostbyname( $host );
		if ( $ip !== $host && filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
			return false;
		}
	}

	$response = wp_remote_get(
		$url,
		array(
			'timeout'     => 10,
			'redirection' => 2,
			'headers'     => array( 'Accept' => 'image/*' ),
		)
	);
	if (is_wp_error( $response )) return false;

	$body = wp_remote_retrieve_body( $response );
	if (strlen( $body ) < 1000 || strlen( $body ) > 2 * 1024 * 1024) return false;

	if ( function_exists( 'finfo_open' ) ) {
		$finfo = new finfo( FILEINFO_MIME_TYPE );
		$mime  = $finfo->buffer( $body );
	} else {
		$ext  = strtolower( pathinfo( parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		$mime = array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'webp' => 'image/webp',
			'gif'  => 'image/gif',
		)[ $ext ] ?? 'application/octet-stream';
	}

	$allowed = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' );
	if ( ! in_array( $mime, $allowed )) return false;

	$upload_dir = wp_upload_dir();
	$avatar_dir = $upload_dir['basedir'] . '/swiftboard-avatars';
	if ( ! file_exists( $avatar_dir )) wp_mkdir_p( $avatar_dir );

	$ext      = array(
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
		'image/webp' => 'webp',
		'image/gif'  => 'gif',
	)[ $mime ] ?? 'jpg';
	$filename = 'avatar-' . $user_id . '-' . time() . '.' . $ext;
	$filepath = $avatar_dir . '/' . $filename;

	if (file_put_contents( $filepath, $body ) === false) return false;

	return 'swiftboard-avatars/' . $filename;
}

// ============================================================================
// v9.8 — Filtre get_avatar() pour remplacer Gravatar par nos avatars ninja
// dans tout WordPress (profil admin, commentaires, barre admin, etc.)
// ============================================================================
add_filter('get_avatar', function($avatar_html, $id_or_email, $size, $default, $alt) {
    // Récupérer l'ID utilisateur
    $user_id = 0;
    if (is_numeric($id_or_email)) {
        $user_id = (int) $id_or_email;
    } elseif (is_object($id_or_email)) {
        if (isset($id_or_email->user_id)) {
            $user_id = (int) $id_or_email->user_id;
        } elseif (isset($id_or_email->ID)) {
            $user_id = (int) $id_or_email->ID;
        }
    } elseif (is_string($id_or_email)) {
        $user = get_user_by('email', $id_or_email);
        if ($user) {
            $user_id = $user->ID;
        }
    }
    
    if (!$user_id) {
        return $avatar_html; // Garder Gravatar si pas d'utilisateur
    }
    
    // Utiliser notre système d'avatar ninja
    if (function_exists('swiftboard_get_avatar')) {
        $custom = swiftboard_get_avatar($user_id, $size);
        if ($custom) {
            // Ajouter les classes que WordPress attend
            $custom = str_replace(
                'class="sb-avatar-themed"',
                'class="avatar sb-avatar-themed"',
                $custom
            );
            $custom = str_replace(
                'class="sb-avatar-custom"',
                'class="avatar sb-avatar-custom"',
                $custom
            );
            $custom = str_replace(
                'class="sb-avatar-fallback"',
                'class="avatar sb-avatar-fallback"',
                $custom
            );
            // Si aucune classe avatar n'a été ajoutée (fallback initiale)
            if (strpos($custom, 'class="avatar') === false && strpos($custom, 'class="sb-avatar') !== false) {
                $custom = str_replace('class="sb-', 'class="avatar sb-', $custom);
            }
            // Ajouter le style border-radius:50% si pas déjà présent
            if (strpos($custom, 'border-radius') === false) {
                $custom = str_replace('<img ', '<img style="border-radius:50%;object-fit:cover;" ', $custom);
            }
            return $custom;
        }
    }
    
    return $avatar_html; // Fallback: garder Gravatar
}, 10, 5);

// ============================================================================
/**
 * Imprime la div de config pour l'avatar picker (CSP-safe, data-*).
 */
function swiftboard_print_avatar_config() {
	printf(
		'<div id="sb-avatar-config" hidden data-rest-url="%s"></div>',
		esc_attr( esc_url_raw( rest_url() ) )
	);
}

// CSS pour forcer les avatars en cercle dans l'admin WordPress
// ============================================================================
add_action('admin_head', function() {
    echo '<style>
    .avatar { border-radius: 50% !important; object-fit: cover !important; }
    .sb-avatar-themed, .sb-avatar-custom, .sb-avatar-fallback { border-radius: 50% !important; }
    #profile-page .avatar, .user-edit-php .avatar { border-radius: 50% !important; width: 96px; height: 96px; object-fit: cover; }
    #wpadminbar .avatar { border-radius: 50% !important; }
    </style>';
});
