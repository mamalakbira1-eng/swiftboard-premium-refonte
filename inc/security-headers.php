<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — En-tetes de securite HTTP et Content-Security-Policy.
 *
 * EXI-ARCH-01 : extrait de inc/security.php. La CSP est emise en `enforce`
 * avec des empreintes SHA-256 calculees par requete — un nonce serait
 * inutilisable, le theme servant un cache de pages HTML dont le nonce fige
 * divergerait de l'en-tete regeneree.
 *
 * @package SwiftBoard
 * @since 5.1.0
 */

// CDC-SEC-08 : Blocage HTTP direct de debug.log, .env, .sql, .ini, .log, .bak
$sb_request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
if ( preg_match( '#(/debug\.log|\.(log|env|ini|sql|bak|old|config|lock|yml|yaml)$)#i', $sb_request_uri ) ) {
	if ( ! headers_sent() ) {
		header( 'HTTP/1.1 403 Forbidden' );
	}
	exit( 'Access denied.' );
}

/**
 * Empreintes SHA-256 des <script> inline que le theme ne controle pas.
 *
 * EXI-QUAL-06 — TROIS APPROCHES ESSAYEES, DEUX ECARTEES PAR LA MESURE
 * -------------------------------------------------------------------
 * 1. NONCE : inutilisable. Le theme sert un cache de pages HTML
 *    (inc/page-cache.php). Le nonce de l'en-tete est regenere a chaque
 *    requete alors que le HTML servi est fige : les deux divergent et le
 *    navigateur bloque le script. Constate : deux requetes consecutives,
 *    nonce d'en-tete different, nonce HTML identique.
 *
 * 2. HASH EN DUR : insuffisant. Le bloc `bbpress-engagements-js-extra`
 *    contient l'`object_id` du sujet affiche : son empreinte change d'une
 *    page a l'autre. Mesure : deux hash differents sur deux pages.
 *
 * 3. HASH CALCULE A LA VOLEE : retenu. On demande a WP_Scripts le contenu
 *    exact qu'il va imprimer, et on en derive l'empreinte AVANT l'envoi des
 *    en-tetes. L'empreinte suit donc le contenu, page par page.
 *
 * Tous les scripts du THEME ont ete externalises vers assets/js/ ; il ne
 * reste ici que ceux produits par WordPress et par bbPress.
 *
 * @return string[] Empreintes au format `'sha256-...'`, apostrophes incluses
 *                  (elles font partie de la syntaxe CSP).
 */
function swiftboard_csp_hashes_inline() {
	$hashes = array();

	// Blocs a contenu FIXE, connus a l'avance.
	//
	// Ces scripts sont IMPOSES par bbPress ou par le coeur de WordPress : le
	// theme ne peut ni les externaliser ni les supprimer sans perdre une
	// fonctionnalite. Leur empreinte est donc publiee dans la CSP.
	//
	// Un audit externe a releve que WordPress 6.8+ ajoute « speculationrules »
	// (prefetch natif), un bloc inline supplementaire. Il n'existe pas sur
	// WordPress 6.7 — verifie ici : 0 script bloque sur 6 combinaisons
	// page x etat de connexion. L'empreinte est neanmoins declaree pour que la
	// mise a jour du coeur ne casse pas le prefetch en silence.
	$fixes = array(
		// bbPress : document.body.className.replace('bbp-no-js','bbp-js')
		'i9AvAaw9nYHwJHsjgTGKQyBvvcXe7bictD7Yw+dQfkk=',
		// WordPress 6.8+ : regles de speculation (prefetch au survol)
		'l3s0lZ7odUvf01+E4YLD2BPw5Ujl9D8KFss27VsoauM=',
	);
	// WordPress imprime `wp_customize_support_script()` juste apres l'ouverture
	// du <body>, pour tout utilisateur qui peut personnaliser le theme. Ce bloc
	// n'est PAS dans la file de wp_scripts() : le balayage ci-dessous ne le voit
	// pas, et il etait donc bloque par la CSP en contexte connecte.
	//
	// Constate sur WordPress 7.0.2 : « Executing inline script violates ... »
	// sur toutes les pages, uniquement une fois authentifie. Invisible sur 6.7
	// faute d'instance pour le verifier — l'empreinte speculationrules avait
	// ete declaree a l'aveugle, celle-ci manquait.
	//
	// On capture la sortie reelle de la fonction plutot que de coder une
	// empreinte en dur : son contenu depend de la version du coeur.
	// PHP interdit ob_start() a l'interieur d'un callback de buffer de sortie
	// (« Cannot use output buffering in output buffering display handlers »).
	// Or cette fonction est atteignable depuis le callback du page-cache : sur
	// la requete qui GENERE le cache, l'appel ci-dessous levait une erreur
	// FATALE et le visiteur recevait une page blanche en HTTP 200.
	// On neutralise donc la capture quand un handler de buffer est en cours.
	$sb_dans_handler = false;
	foreach ( (array) ob_get_status( true ) as $sb_niveau ) {
		if ( ! empty( $sb_niveau['name'] ) && 'default output handler' !== $sb_niveau['name'] ) {
			$sb_dans_handler = true;
			break;
		}
	}

	if ( ! $sb_dans_handler && function_exists( 'wp_customize_support_script' ) && current_user_can( 'customize' ) ) {
		ob_start();
		wp_customize_support_script();
		$sb_bloc_customize = (string) ob_get_clean();

		if ( preg_match( '#<script[^>]*>(.*?)</script>#s', $sb_bloc_customize, $sb_m ) ) {
			foreach ( array( $sb_m[1], "\n" . $sb_m[1] . "\n" ) as $sb_variante ) {
				$hashes[] = "'sha256-" . base64_encode( hash( 'sha256', $sb_variante, true ) ) . "'";
			}
		}
	}

	foreach ( $fixes as $h ) {
		$hashes[] = "'sha256-{$h}'";
	}

	// JSON-LD SEO : ce sont des donnees structurees conservees pour Google,
	// mais elles restent dans des noeuds script soumis a script-src. Leur
	// contenu varie selon l’URL, le titre, les reponses et les votes ; les
	// hashes sont donc calcules sur le rendu exact de la requete courante.
	// Le buffer sert uniquement au calcul et n’envoie aucun doublon HTML.
	$sb_schema_callbacks = array();
	if ( function_exists( 'swiftboard_schema_website' ) ) {
		$sb_schema_callbacks[] = 'swiftboard_schema_website';
	}
	if ( function_exists( 'swiftboard_schema_page' ) && ! is_front_page() ) {
		$sb_schema_callbacks[] = 'swiftboard_schema_page';
	}
	foreach ( $sb_schema_callbacks as $sb_schema_callback ) {
		if ( $sb_dans_handler ) {
			break;
		}
		ob_start();
		call_user_func( $sb_schema_callback );
		$sb_schema_output = (string) ob_get_clean();
		if ( preg_match_all( '#<script[^>]*>(.*?)</script>#s', $sb_schema_output, $sb_schema_matches ) ) {
			foreach ( $sb_schema_matches[1] as $sb_schema_body ) {
				foreach ( array( $sb_schema_body, "\n{$sb_schema_body}\n" ) as $sb_schema_variant ) {
					$hashes[] = "'sha256-" . base64_encode( hash( 'sha256', $sb_schema_variant, true ) ) . "'";
				}
			}
		}
	}

	// Blocs a contenu VARIABLE (wp_localize_script de WordPress, bbPress,
	// extensions tierces) : on interroge la file d'attente des scripts pour
	// obtenir le texte exact qui sera imprime.
	$wp_scripts = wp_scripts();
	if ( $wp_scripts instanceof WP_Scripts ) {
		foreach ( (array) $wp_scripts->queue as $handle ) {
			// On demande a WP_Scripts le texte EXACT qu'il imprimera
			// ($display = false), au lieu de reconstruire la chaine nous-meme.
			// WordPress y ajoute un commentaire « //# sourceURL=... » que
			// notre hachage manuel ignorait : l'empreinte ne correspondait
			// alors a rien et le script restait bloque.
			$donnees = $wp_scripts->print_extra_script( $handle, false );
			if ( is_string( $donnees ) && $donnees !== '' ) {
				// WordPress imprime ce bloc entoure de sauts de ligne :
				// <script id="...">\nvar x = {...};\n</script>
				// Le hash CSP porte sur le contenu EXACT du noeud, sauts de
				// ligne compris. Hacher la seule chaine `$donnees` produisait
				// une empreinte qui ne correspondait a rien et le script
				// restait bloque — diagnostique en comparant l'empreinte du
				// texte rendu a celle du texte source.
				$hashes[] = "'sha256-" . base64_encode( hash( 'sha256', "\n" . $donnees . "\n", true ) ) . "'";
			}
			foreach ( array( 'before', 'after' ) as $position ) {
				$bloc = $wp_scripts->get_inline_script_data( $handle, $position );
				if ( is_string( $bloc ) && $bloc !== '' ) {
					// Idem : on autorise les deux formes (avec et sans sauts
					// de ligne encadrants) car WordPress ne les ajoute pas de
					// maniere uniforme selon la version et le type de bloc.
					$hashes[] = "'sha256-" . base64_encode( hash( 'sha256', $bloc, true ) ) . "'";
					$hashes[] = "'sha256-" . base64_encode( hash( 'sha256', "\n" . $bloc . "\n", true ) ) . "'";
				}
			}
		}
	}

	/**
	 * Permet a un site d'ajouter l'empreinte d'un script inline tiers sans
	 * modifier le theme ni retomber sur 'unsafe-inline'.
	 *
	 * @param string[] $hashes Empreintes autorisees.
	 */
	return array_values( array_unique( (array) apply_filters( 'swiftboard_csp_script_hashes', $hashes ) ) );
}

// ============================================================================
// 1. HEADERS HTTP DE SÉCURITÉ
// ============================================================================

/**
 * Emet les en-tetes anti-clickjacking, anti-MIME-sniffing ET la CSP.
 *
 * POURQUOI send_headers ET PAS wp_enqueue_scripts
 * --------------------------------------------------
 * La CSP était émise sur wp_enqueue_scripts priorité 9999 pour avoir les
 * empreintes SHA-256 des scripts inline. PROBLÈME : à ce moment, header.php
 * a déjà commencé à produire du HTML → headers_sent() = true → header()
 * échoue silencieusement → LA CSP N'EST JAMAIS ENVOYÉE.
 *
 * FIX : on envoie la CSP sur send_headers (avant tout output). Les empreintes
 * fixes (bbPress no-js, WordPress speculation rules) sont connues à l'avance.
 * Les empreintes dynamiques (wp_localize_script) ne sont plus nécessaires :
 * le thème utilise des attributs data-* au lieu de scripts inline.
 *
 * @return void
 */
function swiftboard_envoyer_headers_basiques() {
	if ( headers_sent() ) {
		return;
	}
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: SAMEORIGIN' );

	// La CSP complète est émise plus tard par swiftboard_envoyer_csp(),
	// lorsque les scripts inline dynamiques sont connus. Ne pas émettre ici une
	// seconde CSP partielle : plusieurs politiques CSP se cumulent dans le
	// navigateur et la politique partielle bloquerait les scripts autorisés par
	// la politique complète. Les headers ci-dessus restent précoces et sûrs.
}
add_action( 'send_headers', 'swiftboard_envoyer_headers_basiques' );

/**
 * Emet l'en-tete Content-Security-Policy avant tout rendu, y compris avant
 * le cache de pages.
 *
 * Les donnees dynamiques du theme sont desormais portees par des attributs
 * `data-*`, et les scripts du theme sont externes. La seule empreinte front
 * permanente est donc celle du bloc bbPress; les empreintes de scripts Core
 * optionnels sont calculees si elles sont deja connues. Emettre sur
 * `send_headers` garantit que la politique est presente sur les MISS comme
 * sur les HIT du cache, car `template_redirect` peut servir puis terminer la
 * requete avant `wp_enqueue_scripts`.
 *
 * @return void
 */
function swiftboard_envoyer_csp() {
	$sb_request_uri_csp = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	if ( is_admin() || headers_sent() || in_array( $GLOBALS['pagenow'] ?? '', array( 'wp-login.php', 'wp-register.php' ), true ) || ( strpos( $sb_request_uri_csp, 'wp-login.php' ) !== false ) || ( strpos( $sb_request_uri_csp, '/edit/' ) !== false ) ) {
		return;
	}
	// ========================================================================
	// Content-Security-Policy — EXI-QUAL-06 : passage en ENFORCE
	// ========================================================================
	//
	// Le blocage tenait a quatre <script> inline restants sur le front :
	// - le code applicatif (autocomplete, RUM Web Vitals) a ete externalise
	// vers assets/js/ ;
	// - les trois blocs de DONNEES generes par wp_localize_script
	// (swiftBoardSearch, swiftBoardVotes, swiftBoardRUM) passent desormais
	// par des attributs data-* sur une balise vide ;
	// - reste le bloc de bbPress (bbp-swap-no-js-body-class), produit par le
	// plugin : autorise par son empreinte SHA-256.
	//
	// Les trois blocs de donnees du theme ont finalement ete convertis en
	// attributs data-* : il ne reste que celui de bbPress, autorise par son
	// EMPREINTE SHA-256 (voir swiftboard_csp_hashes_inline).
	//
	// Un nonce avait ete essaye d'abord : inutilisable ici, car le theme sert
	// un cache de pages. Le HTML fige conserve l'ancien nonce tandis que
	// l'en-tete en genere un nouveau a chaque requete — les deux divergent et
	// le script est bloque. Constate par la mesure avant correction.
	//
	// 'unsafe-inline' est conserve UNIQUEMENT pour style-src : WordPress et
	// bbPress emettent de nombreux attributs style="" que nous ne controlons
	// pas. Un navigateur ignore 'unsafe-inline' des qu'un hash ou un nonce
	// figure dans la MEME directive : c'est pourquoi script-src et style-src
	// restent strictement separees.
	$hashes = implode( ' ', swiftboard_csp_hashes_inline() );

	$csp = "default-src 'self'; "
		. "script-src 'self' {$hashes}; "
		. "style-src 'self' 'unsafe-inline'; "
		. "img-src 'self' data: https:; "
		. "font-src 'self' data: ; "
		. "connect-src 'self'; "
		. "frame-ancestors 'self'; "
		. "base-uri 'self'; "
		. "form-action 'self'; "
		. "object-src 'none'";

	// Bascule de secours : define('SWIFTBOARD_CSP_REPORT_ONLY', true) dans
	// wp-config.php remet la CSP en observation sans toucher au code, par
	// exemple apres l'ajout d'une extension tierce qui injecte du JS inline.
	$entete = ( defined( 'SWIFTBOARD_CSP_REPORT_ONLY' ) && SWIFTBOARD_CSP_REPORT_ONLY )
		? 'Content-Security-Policy-Report-Only: '
		: 'Content-Security-Policy: ';
	header( $entete . $csp );
}
// Priorite tardive dans `send_headers`, mais toujours avant tout output et
// avant `template_redirect`/le page-cache. Le hook `wp_enqueue_scripts` est
// volontairement evite : il est trop tard ou n'est jamais atteint sur un HIT.
add_action( 'send_headers', 'swiftboard_envoyer_csp', 100 );

// ============================================================================
// CDC-SEC-07 — REST CORS WHITELIST HARDENING
// ============================================================================
/**
 * Restreint strictement les origines CORS sur l'API REST authentifiée.
 * Interdit à WordPress Core de renvoyer Access-Control-Allow-Origin: https://evil.com
 *
 * @param bool             $served  Whether the request has already been served.
 * @param WP_HTTP_Response $result  Result to send to the client.
 * @param WP_REST_Request  $request Request used to generate the response.
 * @return bool
 */
function swiftboard_rest_cors_whitelist_hardening( $served, $result, $request ) {
	$origin = get_http_origin();
	if ( ! empty( $origin ) ) {
		$allowed_origins = array(
			home_url(),
			site_url(),

		);
		$allowed = false;
		foreach ( $allowed_origins as $ok ) {
			if ( rtrim( $origin, '/' ) === rtrim( $ok, '/' ) ) {
				$allowed = true;
				break;
			}
		}
		if ( ! $allowed ) {
			header_remove( 'Access-Control-Allow-Origin' );
			header_remove( 'Access-Control-Allow-Credentials' );
		}
	}
	return $served;
}
add_filter( 'rest_pre_serve_request', 'swiftboard_rest_cors_whitelist_hardening', 20, 3 );
