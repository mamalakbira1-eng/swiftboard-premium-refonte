<?php
/**
 * SwiftBoard — Diagnostic du site (LECTURE SEULE).
 *
 * A QUOI SERT CE FICHIER
 * Il repond a : « pourquoi, sur MON site, le theme ne se comporte pas comme
 * prevu ? » Plutot que de deviner a distance, on demande au site de se decrire
 * ET on verifie que ses pages repondent reellement.
 *
 * CE QUE LA VERSION 1 RATAIT
 * Elle affichait « aucun probleme » sur un site depourvu des correctifs
 * livres. Un rapport vert sur un site qui dysfonctionne est pire qu'absent.
 * Trois manques ont ete corriges :
 *   1. le numero de version ne prouve RIEN — un theme peut afficher 11.0.5 et
 *      contenir, ou non, les correctifs. On detecte donc par SIGNATURE DE CODE ;
 *   2. aucun test fonctionnel — on charge desormais les pages cles et on lit
 *      leur code de reponse ;
 *   3. le CSS n'etait cherche qu'au Customizer, alors qu'un theme enfant ou un
 *      reglage de forum peut contenir le meme bloc casse.
 *
 * GARANTIE DE NON-MODIFICATION
 * Ce script n'ecrit RIEN : ni base, ni disque, ni options. Aucun envoi vers
 * l'exterieur. Les requetes de verification sont des GET en boucle locale.
 *
 * COMMENT L'UTILISER
 *   1. Connectez-vous en administrateur.
 *   2. Dans le MEME navigateur, ouvrez :
 *      https://votre-site.tld/wp-content/themes/swiftboard/tests/diagnostic-site.php
 *   3. Cliquez sur « Copier le rapport » et transmettez-le.
 *
 * @package SwiftBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
	$sb_dir    = __DIR__;
	$sb_trouve = false;
	for ( $i = 0; $i < 10; $i++ ) {
		$sb_dir = dirname( $sb_dir );
		if ( file_exists( $sb_dir . '/wp-load.php' ) ) {
			require_once $sb_dir . '/wp-load.php';
			$sb_trouve = true;
			break;
		}
	}
	if ( ! $sb_trouve ) {
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo "SwiftBoard — diagnostic\n\nWordPress introuvable depuis ce dossier.\n";
		echo "Le fichier doit rester dans wp-content/themes/swiftboard/tests/.\n";
		exit( 1 );
	}
}

if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
	status_header( 403 );
	header( 'Content-Type: text/html; charset=utf-8' );
	echo '<!doctype html><meta charset="utf-8"><title>Accès refusé</title>';
	echo '<body style="font:15px/1.6 system-ui,sans-serif;max-width:34em;margin:12vh auto;padding:0 1em">';
	echo '<h1 style="font-size:1.3em">Accès refusé</h1>';
	echo '<p>Ce diagnostic n’est accessible qu’à un administrateur connecté.</p>';
	echo '<p><a href="' . esc_url( wp_login_url( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' ) ) . '">Se connecter</a></p></body>';
	exit;
}

$sb_resultats = array();

/**
 * Enregistre un controle.
 *
 * @param string $etat   ok|attention|probleme|info.
 * @param string $groupe Rubrique.
 * @param string $titre  Intitule.
 * @param string $detail Observation.
 * @param string $action Correction conseillee.
 * @return void
 */
function sb_diag( $etat, $groupe, $titre, $detail, $action = '' ) {
	global $sb_resultats;
	$sb_resultats[] = compact( 'etat', 'groupe', 'titre', 'detail', 'action' );
}

$sb_racine = get_template_directory();

/**
 * Cherche un motif dans un fichier du theme.
 *
 * @param string $relatif Chemin relatif au theme.
 * @param string $motif   Chaine recherchee.
 * @return bool
 */
function sb_fichier_contient( $relatif, $motif ) {
	global $sb_racine;
	$chemin = $sb_racine . '/' . $relatif;
	if ( ! is_readable( $chemin ) ) {
		return false;
	}
	return false !== strpos( (string) file_get_contents( $chemin ), $motif );
}

// ===========================================================================
// 1. CORRECTIFS PRESENTS — le controle le plus important.
// Le numero de version ne prouve rien : deux paquets « 11.0.5 » peuvent
// differer. On verifie donc la presence reelle du code de chaque correctif.
// ===========================================================================
$sb_correctifs = array(
	array(
		'cle'      => 'purge-cache-reglages',
		'nom'      => 'Purge du cache après changement de réglage',
		'fichier'  => 'inc/page-cache.php',
		'motif'    => 'update_option_theme_mods_',
		'symptome' => 'Vous changez une couleur, publiez, et la page continue d’afficher l’ancienne.',
	),
	array(
		'cle'      => 'avatar-customizer',
		'nom'      => 'Couleur des avatars pilotée par le Customizer',
		'fichier'  => 'inc/avatars.php',
		'motif'    => "\$bleu = get_theme_mod( 'swiftboard_avatar_fallback_color'",
		'symptome' => 'Le réglage « Couleur des avatars » reste sans effet : la couleur est figée dans le code.',
	),
	array(
		'cle'      => 'garde-fou-css',
		'nom'      => 'Garde-fou du CSS additionnel',
		'fichier'  => 'inc/custom-css-guard.php',
		'motif'    => 'swiftboard_css_squelette',
		'symptome' => 'Un bloc CSS incomplet bloque toutes les règles suivantes, sans message d’erreur.',
	),
	array(
		'cle'      => 'repli-persistant',
		'nom'      => 'Repli des fils de commentaires conservé',
		'fichier'  => 'assets/js/nested-comments.js',
		'motif'    => 'swiftboard_fils_replies',
		'symptome' => 'Les fils repliés se rouvrent à chaque rechargement de page.',
	),
	array(
		'cle'      => 'bouton-abonnement',
		'nom'      => 'Bouton d’abonnement au forum fonctionnel',
		'fichier'  => 'inc/forum-customizer.php',
		'motif'    => 'data-login-url',
		'symptome' => 'Le bouton change d’apparence mais n’enregistre rien : l’état disparaît au rechargement.',
	),
);

$sb_absents = array();
foreach ( $sb_correctifs as $sb_c ) {
	$sb_present = sb_fichier_contient( $sb_c['fichier'], $sb_c['motif'] );
	if ( $sb_present ) {
		sb_diag( 'ok', 'Correctifs installés', $sb_c['nom'], 'présent' );
	} else {
		$sb_absents[] = $sb_c['nom'];
		sb_diag(
			'probleme',
			'Correctifs installés',
			$sb_c['nom'],
			'ABSENT de ' . $sb_c['fichier'],
			'Symptôme attendu : ' . $sb_c['symptome'] . ' Mettez le thème à jour avec le dernier paquet livré.'
		);
	}
}
if ( $sb_absents ) {
	sb_diag(
		'probleme',
		'Correctifs installés',
		'Version du thème installée',
		sprintf( '%d correctif(s) sur %d manquant(s)', count( $sb_absents ), count( $sb_correctifs ) ),
		'Le numéro de version affiché ne suffit pas à distinguer deux paquets. Réinstallez le ZIP le plus récent, puis relancez ce diagnostic.'
	);
}

// ===========================================================================
// 2. TESTS FONCTIONNELS — les pages repondent-elles vraiment ?
// Lire la configuration ne dit pas si le site fonctionne. On charge donc les
// pages cles en boucle locale et on lit le code de reponse.
// ===========================================================================
/**
 * Charge une URL du site et retourne code HTTP, poids et duree.
 *
 * @param string $url Adresse a tester.
 * @return array{code:int,octets:int,ms:int,erreur:string}
 */
function sb_sonder( $url ) {
	$debut = microtime( true );
	$rep   = wp_remote_get(
		$url,
		array(
			'timeout'     => 8,
			'redirection' => 3,
			'sslverify'   => false,
			'headers'     => array( 'Cache-Control' => 'no-cache' ),
		)
	);
	$ms = (int) round( ( microtime( true ) - $debut ) * 1000 );
	if ( is_wp_error( $rep ) ) {
		return array(
			'code'   => 0,
			'octets' => 0,
			'ms'     => $ms,
			'erreur' => $rep->get_error_message(),
		);
	}
	return array(
		'code'   => (int) wp_remote_retrieve_response_code( $rep ),
		'octets' => strlen( (string) wp_remote_retrieve_body( $rep ) ),
		'ms'     => $ms,
		'erreur' => '',
	);
}

$sb_pages = array( 'Accueil' => home_url( '/' ) );

if ( function_exists( 'bbp_get_forum_post_type' ) ) {
	$sb_f = get_posts(
		array(
			'post_type'      => bbp_get_forum_post_type(),
			'posts_per_page' => 1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		)
	);
	if ( $sb_f ) {
		$sb_pages['Page de communauté'] = get_permalink( $sb_f[0] );
	}
	$sb_t = get_posts(
		array(
			'post_type'      => bbp_get_topic_post_type(),
			'posts_per_page' => 1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		)
	);
	if ( $sb_t ) {
		$sb_pages['Page de sujet'] = get_permalink( $sb_t[0] );
	}
}

$sb_lent = 0;
foreach ( $sb_pages as $sb_nom => $sb_url ) {
	$sb_r = sb_sonder( $sb_url );

	if ( 0 === $sb_r['code'] ) {
		// Un serveur mono-processus (php -S, certains hebergements mutualises)
		// ne peut pas se repondre a lui-meme pendant qu'il traite cette page :
		// l'echec ne dit alors RIEN sur l'etat reel du site. On informe sans
		// alarmer, plutot que de signaler un faux probleme.
		sb_diag(
			'info',
			'Pages du site',
			$sb_nom,
			'non vérifiable depuis le serveur',
			'Le serveur ne peut pas s’interroger lui-même (configuration mono-processus ou restriction sortante). Cela ne dit rien sur l’état du site : ouvrez la page dans votre navigateur.'
		);
		continue;
	}

	$sb_etat = ( 200 === $sb_r['code'] ) ? 'ok' : 'probleme';
	if ( 200 === $sb_r['code'] && $sb_r['octets'] < 2000 ) {
		$sb_etat = 'probleme';
	}
	if ( 200 === $sb_r['code'] && $sb_r['ms'] > 3000 ) {
		$sb_etat = 'attention';
		++$sb_lent;
	}

	$sb_action = '';
	if ( 200 !== $sb_r['code'] ) {
		$sb_action = 'Code inattendu. Réenregistrez les permaliens (Réglages → Permaliens), puis relancez.';
	} elseif ( $sb_r['octets'] < 2000 ) {
		$sb_action = 'Page pratiquement vide : signe d’une erreur PHP silencieuse. Activez temporairement le journal des erreurs.';
	} elseif ( $sb_r['ms'] > 3000 ) {
		$sb_action = 'Temps de réponse élevé. Activez un cache objet (Redis) ou vérifiez la charge du serveur.';
	}

	sb_diag(
		$sb_etat,
		'Pages du site',
		$sb_nom,
		sprintf( 'HTTP %d · %s Ko · %d ms', $sb_r['code'], number_format_i18n( $sb_r['octets'] / 1024, 1 ), $sb_r['ms'] ),
		$sb_action
	);
}

// ===========================================================================
// 3. ENVIRONNEMENT
// ===========================================================================
global $wp_version, $wpdb;
$sb_theme = wp_get_theme();

sb_diag( 'info', 'Environnement', 'WordPress', $wp_version );
sb_diag(
	version_compare( PHP_VERSION, '8.0', '>=' ) ? 'ok' : 'probleme',
	'Environnement',
	'PHP',
	PHP_VERSION,
	version_compare( PHP_VERSION, '8.0', '>=' ) ? '' : 'SwiftBoard exige PHP 8.0 ou supérieur.'
);
sb_diag( 'info', 'Environnement', 'Version déclarée du thème', $sb_theme->get( 'Version' ) . ' (indicative : voir la rubrique Correctifs)' );

$sb_enfant = $sb_theme->get_stylesheet() !== $sb_theme->get_template();
if ( $sb_enfant ) {
	// Un theme enfant peut redefinir des gabarits et neutraliser un correctif.
	$sb_surcharges = array();
	foreach ( array( 'functions.php', 'header.php', 'front-page.php', 'single.php', 'style.css' ) as $sb_g ) {
		if ( file_exists( get_stylesheet_directory() . '/' . $sb_g ) ) {
			$sb_surcharges[] = $sb_g;
		}
	}
	sb_diag(
		'attention',
		'Environnement',
		'Thème enfant actif',
		$sb_theme->get( 'Name' ) . ( $sb_surcharges ? ' — remplace : ' . implode( ', ', $sb_surcharges ) : '' ),
		'Un gabarit redéfini dans le thème enfant remplace celui du thème parent : un correctif du parent peut y être neutralisé.'
	);
} else {
	sb_diag( 'ok', 'Environnement', 'Thème actif', $sb_theme->get( 'Name' ) );
}

$sb_sgbd = method_exists( $wpdb, 'db_server_info' ) ? (string) $wpdb->db_server_info() : 'inconnu';
if ( defined( 'DB_ENGINE' ) && 'sqlite' === constant( 'DB_ENGINE' ) ) {
	$sb_sgbd = 'SQLite';
}
$sb_sqlite = ( false !== stripos( $sb_sgbd, 'sqlite' ) );
sb_diag(
	$sb_sqlite ? 'attention' : 'ok',
	'Environnement',
	'Base de données',
	$sb_sgbd,
	$sb_sqlite ? 'Le classement « Tendances » nécessite MySQL ou MariaDB : le pilote SQLite refuse les valeurs de vote négatives.' : ''
);

$sb_manquantes = array();
foreach ( array( 'mbstring', 'gd', 'curl', 'json' ) as $sb_e ) {
	if ( ! extension_loaded( $sb_e ) ) {
		$sb_manquantes[] = $sb_e;
	}
}
sb_diag(
	$sb_manquantes ? 'attention' : 'ok',
	'Environnement',
	'Extensions PHP',
	$sb_manquantes ? 'manquantes : ' . implode( ', ', $sb_manquantes ) : 'mbstring, gd, curl, json présentes',
	$sb_manquantes ? 'Demandez leur activation à votre hébergeur.' : ''
);

// ===========================================================================
// 4. bbPress
// ===========================================================================
if ( function_exists( 'bbp_get_version' ) ) {
	sb_diag( 'ok', 'bbPress', 'Plugin actif', 'version ' . bbp_get_version() );
	$sb_abo = function_exists( 'bbp_is_subscriptions_active' ) && bbp_is_subscriptions_active();
	sb_diag(
		$sb_abo ? 'ok' : 'attention',
		'bbPress',
		'Abonnements aux forums',
		$sb_abo ? 'actifs' : 'désactivés',
		$sb_abo ? '' : 'Sans eux, le bouton « Rejoindre » d’une communauté ne peut rien enregistrer.'
	);
	$sb_cf = wp_count_posts( 'forum' );
	$sb_ct = wp_count_posts( 'topic' );
	sb_diag(
		'info',
		'bbPress',
		'Contenus publiés',
		sprintf(
			'%d forum(s), %d sujet(s)',
			isset( $sb_cf->publish ) ? (int) $sb_cf->publish : 0,
			isset( $sb_ct->publish ) ? (int) $sb_ct->publish : 0
		)
	);
} else {
	sb_diag( 'probleme', 'bbPress', 'Plugin', 'inactif ou absent', 'SwiftBoard est un thème de forum : installez et activez bbPress 2.6 ou supérieur.' );
}

// ===========================================================================
// 5. CSS PERSONNALISE — toutes les sources, pas seulement le Customizer.
// ===========================================================================
/**
 * Analyse la structure d'un CSS en ignorant commentaires et chaines.
 *
 * @param string $css Feuille de style.
 * @return array{o:int,f:int,commentaire:bool,ligne:int}
 */
function sb_analyser_css( $css ) {
	$sq = preg_replace( '#/\*.*?\*/#s', '', (string) $css );
	$sq = preg_replace( '/"(?:[^"\\\\]|\\\\.)*"/s', '""', (string) $sq );
	$sq = preg_replace( "/'(?:[^'\\\\]|\\\\.)*'/s", "''", (string) $sq );

	$ligne = 0;
	$prof  = 0;
	foreach ( explode( "\n", (string) $sq ) as $i => $l ) {
		$prof += substr_count( $l, '{' ) - substr_count( $l, '}' );
		if ( $prof < 0 && ! $ligne ) {
			$ligne = $i + 1;
		}
	}
	return array(
		'o'           => substr_count( (string) $sq, '{' ),
		'f'           => substr_count( (string) $sq, '}' ),
		'commentaire' => substr_count( (string) $css, '/*' ) > substr_count( (string) $css, '*/' ),
		'ligne'       => $ligne,
	);
}

$sb_sources = array();
$sb_post_css = function_exists( 'wp_get_custom_css_post' ) ? wp_get_custom_css_post() : null;
if ( $sb_post_css && trim( (string) $sb_post_css->post_content ) !== '' ) {
	$sb_sources['CSS additionnel (Customizer)'] = (string) $sb_post_css->post_content;
}
// Un theme enfant peut avoir son propre CSS additionnel enregistre.
if ( $sb_enfant && function_exists( 'wp_get_custom_css' ) ) {
	$sb_parent_css = wp_get_custom_css( $sb_theme->get_template() );
	if ( trim( (string) $sb_parent_css ) !== '' ) {
		$sb_sources['CSS additionnel (thème parent)'] = (string) $sb_parent_css;
	}
}
$sb_opt_css = get_option( 'swiftboard_custom_css' );
if ( $sb_opt_css && trim( (string) $sb_opt_css ) !== '' ) {
	$sb_sources['CSS du thème (réglages SwiftBoard)'] = (string) $sb_opt_css;
}

if ( ! $sb_sources ) {
	sb_diag( 'ok', 'CSS personnalisé', 'Contenu', 'aucun CSS personnalisé enregistré' );
} else {
	foreach ( $sb_sources as $sb_nom => $sb_css ) {
		$sb_a = sb_analyser_css( $sb_css );
		if ( $sb_a['o'] !== $sb_a['f'] || $sb_a['commentaire'] ) {
			sb_diag(
				'probleme',
				'CSS personnalisé',
				$sb_nom,
				sprintf(
					'%d accolades ouvrantes pour %d fermantes%s%s',
					$sb_a['o'],
					$sb_a['f'],
					$sb_a['commentaire'] ? ', commentaire non refermé' : '',
					$sb_a['ligne'] ? ', rupture ligne ' . $sb_a['ligne'] : ''
				),
				'Le navigateur ARRÊTE de lire le CSS au point de rupture : toute règle écrite ensuite est ignorée. Voir documentation/nettoyage-css-additionnel.md.'
			);
		} else {
			sb_diag( 'ok', 'CSS personnalisé', $sb_nom, sprintf( '%s octets, %d accolades équilibrées', number_format_i18n( strlen( $sb_css ) ), $sb_a['o'] ) );
		}
		if ( preg_match( '/sb-home-reddit-v2|sb-home-red\b|staging patch/i', $sb_css ) ) {
			sb_diag(
				'attention',
				'CSS personnalisé',
				'Correctif de pré-production',
				'bloc « staging patch » détecté dans : ' . $sb_nom,
				'Ce bloc cible des classes absentes du rendu actuel. Il peut être retiré sans effet visible.'
			);
		}
	}
}

// ===========================================================================
// 6. CACHES
// ===========================================================================
$sb_up   = wp_upload_dir();
$sb_dirc = trailingslashit( $sb_up['basedir'] ) . 'swiftboard-cache/pages';
if ( is_dir( $sb_dirc ) ) {
	$sb_n  = 0;
	$sb_age = 0;
	$sb_it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $sb_dirc, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $sb_it as $sb_f ) {
		if ( $sb_f->isFile() ) {
			++$sb_n;
			$sb_age = max( $sb_age, time() - $sb_f->getMTime() );
		}
	}
	sb_diag(
		$sb_age > DAY_IN_SECONDS ? 'attention' : 'info',
		'Caches',
		'Cache de pages SwiftBoard',
		$sb_n ? sprintf( '%d fichier(s), le plus ancien : %s', $sb_n, human_time_diff( time() - $sb_age ) ) : 'vide',
		$sb_age > DAY_IN_SECONDS ? 'Videz-le si vos modifications n’apparaissent pas (barre d’administration → Vider le cache).' : ''
	);
} else {
	sb_diag( 'info', 'Caches', 'Cache de pages SwiftBoard', 'inactif ou vide' );
}

$sb_pc = array(
	'litespeed-cache/litespeed-cache.php' => 'LiteSpeed Cache',
	'wp-rocket/wp-rocket.php'             => 'WP Rocket',
	'w3-total-cache/w3-total-cache.php'   => 'W3 Total Cache',
	'wp-super-cache/wp-cache.php'         => 'WP Super Cache',
	'autoptimize/autoptimize.php'         => 'Autoptimize',
	'wp-optimize/wp-optimize.php'         => 'WP-Optimize',
	'nitropack/main.php'                  => 'NitroPack',
	'sg-cachepress/sg-cachepress.php'     => 'SiteGround Optimizer',
);
$sb_ca = array();
foreach ( $sb_pc as $sb_f => $sb_nom ) {
	if ( is_plugin_active( $sb_f ) ) {
		$sb_ca[] = $sb_nom;
	}
}
sb_diag(
	$sb_ca ? 'attention' : 'ok',
	'Caches',
	'Plugins de cache tiers',
	$sb_ca ? implode( ', ', $sb_ca ) : 'aucun',
	$sb_ca ? 'Videz AUSSI ce cache après toute modification, puis rechargez en navigation privée. Un CSS peut rester servi plusieurs jours.' : ''
);
sb_diag( 'info', 'Caches', 'Cache objet persistant', wp_using_ext_object_cache() ? 'actif' : 'inactif' );

// ===========================================================================
// 7. PLUGINS INTERFERENTS
// ===========================================================================
$sb_motifs  = array( 'route-guard', 'routeguard', 'encoding-guard', 'hotfix', 'html-transform', 'sb_root' );
$sb_suspects = array();
foreach ( (array) get_option( 'active_plugins', array() ) as $sb_p ) {
	foreach ( $sb_motifs as $sb_m ) {
		if ( false !== stripos( $sb_p, $sb_m ) ) {
			$sb_suspects[] = $sb_p;
			break;
		}
	}
}
sb_diag(
	$sb_suspects ? 'attention' : 'ok',
	'Plugins',
	'Correctifs temporaires détectés',
	$sb_suspects ? implode( ', ', array_unique( $sb_suspects ) ) : 'aucun',
	$sb_suspects ? 'Ces extensions n’appartiennent pas au thème et peuvent rediriger des URL ou modifier le HTML. Désactivez-les avant tout diagnostic.' : ''
);
sb_diag( 'info', 'Plugins', 'Extensions actives', count( (array) get_option( 'active_plugins', array() ) ) );

// ===========================================================================
// 8. FICHIERS DU THEME
// ===========================================================================
$sb_att = array( 'assets/css/main.css', 'assets/css/reddit-refonte.css', 'inc/custom-css-guard.php', 'inc/page-cache.php', 'inc/icons.php', 'functions.php' );
$sb_abs = array();
foreach ( $sb_att as $sb_r2 ) {
	if ( ! file_exists( $sb_racine . '/' . $sb_r2 ) ) {
		$sb_abs[] = $sb_r2;
	}
}
sb_diag(
	$sb_abs ? 'probleme' : 'ok',
	'Fichiers',
	'Fichiers essentiels',
	$sb_abs ? 'manquants : ' . implode( ', ', $sb_abs ) : 'tous présents',
	$sb_abs ? 'Installation incomplète : réinstallez le thème depuis le ZIP d’origine.' : ''
);

$sb_sc = array();
foreach ( (array) glob( $sb_racine . '/assets/css/*.css' ) as $sb_cf ) {
	if ( '_' === substr( basename( $sb_cf ), 0, 1 ) ) {
		continue;
	}
	$sb_tete = (string) file_get_contents( $sb_cf, false, null, 0, 40 );
	if ( 0 !== strpos( ltrim( $sb_tete, "\xEF\xBB\xBF" ), '@charset' ) ) {
		$sb_sc[] = basename( $sb_cf );
	}
}
sb_diag(
	$sb_sc ? 'attention' : 'ok',
	'Fichiers',
	'Déclaration @charset des CSS',
	$sb_sc ? 'absente dans : ' . implode( ', ', $sb_sc ) : 'présente partout',
	$sb_sc ? 'Si le serveur ne précise pas le charset, les accents peuvent s’afficher incorrectement.' : ''
);

// ===========================================================================
// 9. VOTES
// ===========================================================================
$sb_table = $wpdb->prefix . 'swiftboard_votes';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- diagnostic lecture seule.
$sb_ex = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $sb_table ) ) === $sb_table );
if ( $sb_ex ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nom de table interne.
	$sb_nb = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$sb_table`" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nom de table interne.
	$sb_neg = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$sb_table` WHERE vote < 0" );
	sb_diag( 'ok', 'Votes', 'Table des votes', sprintf( '%s enregistrement(s), dont %s négatif(s)', number_format_i18n( $sb_nb ), number_format_i18n( $sb_neg ) ) );
	if ( $sb_nb > 0 && 0 === $sb_neg && ! $sb_sqlite ) {
		sb_diag( 'info', 'Votes', 'Votes négatifs', 'aucun', 'Normal si personne n’a voté « contre ». Le classement « Tendances » reste fonctionnel.' );
	}
} else {
	sb_diag( 'attention', 'Votes', 'Table des votes', 'absente', 'Elle est créée à l’activation du thème. Réactivez le thème si les votes ne fonctionnent pas.' );
}

// ===========================================================================
// 10. DIVERS
// ===========================================================================
sb_diag(
	defined( 'WP_DEBUG' ) && WP_DEBUG ? 'attention' : 'ok',
	'Divers',
	'Mode débogage',
	defined( 'WP_DEBUG' ) && WP_DEBUG ? 'ACTIF' : 'inactif',
	defined( 'WP_DEBUG' ) && WP_DEBUG ? 'À désactiver en production : des messages d’erreur peuvent s’afficher aux visiteurs.' : ''
);
sb_diag(
	get_option( 'permalink_structure' ) ? 'ok' : 'probleme',
	'Divers',
	'Permaliens',
	get_option( 'permalink_structure' ) ? get_option( 'permalink_structure' ) : 'simples',
	get_option( 'permalink_structure' ) ? '' : 'bbPress exige des permaliens explicites. Réglages → Permaliens → « Titre de la publication ».'
);
sb_diag( 'info', 'Divers', 'Langue du site', get_locale() );
sb_diag(
	is_writable( $sb_up['basedir'] ) ? 'ok' : 'probleme',
	'Divers',
	'Dossier uploads inscriptible',
	is_writable( $sb_up['basedir'] ) ? 'oui' : 'NON',
	is_writable( $sb_up['basedir'] ) ? '' : 'Le cache de pages et l’envoi de médias échoueront.'
);

// ===========================================================================
// Restitution
// ===========================================================================
$sb_pb  = count( array_filter( $sb_resultats, function ( $r ) { return 'probleme' === $r['etat']; } ) );
$sb_at  = count( array_filter( $sb_resultats, function ( $r ) { return 'attention' === $r['etat']; } ) );

$sb_texte  = "SwiftBoard — diagnostic du site\n";
$sb_texte .= 'Genere le ' . gmdate( 'Y-m-d H:i' ) . " UTC\n";
$sb_texte .= str_repeat( '=', 64 ) . "\n";

// Les problemes sont repris en tete : c'est ce qu'on lit en premier.
if ( $sb_pb || $sb_at ) {
	$sb_texte .= "\nA TRAITER EN PRIORITE\n";
	foreach ( $sb_resultats as $sb_r ) {
		if ( 'probleme' === $sb_r['etat'] ) {
			$sb_texte .= ' XX ' . $sb_r['titre'] . ' : ' . $sb_r['detail'] . "\n";
		}
	}
	foreach ( $sb_resultats as $sb_r ) {
		if ( 'attention' === $sb_r['etat'] ) {
			$sb_texte .= ' !! ' . $sb_r['titre'] . ' : ' . $sb_r['detail'] . "\n";
		}
	}
	$sb_texte .= str_repeat( '-', 64 ) . "\n";
}

$sb_gc = '';
foreach ( $sb_resultats as $sb_r ) {
	if ( $sb_r['groupe'] !== $sb_gc ) {
		$sb_gc     = $sb_r['groupe'];
		$sb_texte .= "\n[" . $sb_gc . "]\n";
	}
	$sb_m = array(
		'ok'        => ' OK ',
		'attention' => ' !! ',
		'probleme'  => ' XX ',
		'info'      => ' .. ',
	);
	// mb_strlen : str_pad compte les octets, « thème » en pèse 7 pour 6 caractères.
	$sb_pad    = max( 0, 34 - mb_strlen( $sb_r['titre'], 'UTF-8' ) );
	$sb_texte .= $sb_m[ $sb_r['etat'] ] . $sb_r['titre'] . str_repeat( ' ', $sb_pad ) . ' : ' . $sb_r['detail'] . "\n";
	if ( $sb_r['action'] ) {
		$sb_texte .= '      -> ' . $sb_r['action'] . "\n";
	}
}
$sb_texte .= "\n" . str_repeat( '=', 64 ) . "\n";
$sb_texte .= sprintf( "BILAN : %d probleme(s), %d point(s) d attention\n", $sb_pb, $sb_at );

header( 'Content-Type: text/html; charset=utf-8' );
header( 'X-Robots-Tag: noindex, nofollow' );
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>SwiftBoard — diagnostic du site</title>
<style>
* { box-sizing: border-box; }
body { font: 15px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
	max-width: 62em; margin: 0 auto; padding: 2em 1.2em 4em; color: #1a1a1b; background: #f6f7f8; }
h1 { font-size: 1.5em; margin: 0 0 .2em; }
.sous { color: #6b7280; margin: 0 0 1.5em; font-size: .92em; }
.bilan { padding: 1em 1.2em; border-radius: 10px; margin-bottom: 1.5em; font-weight: 600; }
.bilan.vert { background: #ecfdf5; border: 1px solid #6ee7b7; color: #065f46; }
.bilan.orange { background: #fffbeb; border: 1px solid #fcd34d; color: #92400e; }
.bilan.rouge { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }
.prio { background: #fff; border: 1px solid #fca5a5; border-left: 4px solid #dc2626;
	border-radius: 8px; padding: 1em 1.2em; margin-bottom: 1.8em; }
.prio h2 { margin: 0 0 .5em; font-size: 1em; border: 0; padding: 0; color: #991b1b; }
.prio ol { margin: 0; padding-left: 1.3em; }
.prio li { margin-bottom: .5em; }
h2 { font-size: 1.05em; margin: 1.8em 0 .6em; padding-bottom: .3em; border-bottom: 2px solid #e5e7eb; }
table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px;
	overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,.06); }
td { padding: .7em .9em; border-bottom: 1px solid #f1f2f3; vertical-align: top; }
tr:last-child td { border-bottom: 0; }
.etat { width: 2.2em; text-align: center; font-weight: 700; }
.ok .etat { color: #059669; } .attention .etat { color: #d97706; }
.probleme .etat { color: #dc2626; } .info .etat { color: #9ca3af; }
.titre { width: 17em; font-weight: 600; }
.probleme td { background: #fffbfb; }
.action { display: block; margin-top: .4em; padding: .5em .7em; background: #f9fafb;
	border-left: 3px solid #d1d5db; border-radius: 3px; font-size: .89em; color: #4b5563; }
.probleme .action { background: #fef2f2; border-left-color: #dc2626; color: #7f1d1d; }
button { font: inherit; padding: .6em 1.2em; border: 0; border-radius: 6px;
	background: #006cbd; color: #fff; cursor: pointer; font-weight: 600; }
button:hover { background: #005a9e; }
textarea { width: 100%; height: 16em; margin-top: 1em; font: 12px/1.5 ui-monospace, Menlo, Consolas, monospace;
	padding: 1em; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; }
.note { font-size: .88em; color: #6b7280; margin-top: 2.5em; padding-top: 1em; border-top: 1px solid #e5e7eb; }
</style>
</head>
<body>

<h1>SwiftBoard — diagnostic du site</h1>
<p class="sous">Lecture seule : ce script ne modifie ni votre base de données, ni vos fichiers, ni vos réglages.</p>

<?php
$sb_cl  = 'vert';
$sb_msg = 'Aucun problème détecté.';
if ( $sb_pb ) {
	$sb_cl  = 'rouge';
	$sb_msg = sprintf( '%d problème(s) à corriger', $sb_pb ) . ( $sb_at ? sprintf( ', %d point(s) d’attention', $sb_at ) : '' );
} elseif ( $sb_at ) {
	$sb_cl  = 'orange';
	$sb_msg = sprintf( '%d point(s) d’attention, aucun problème bloquant', $sb_at );
}
?>
<div class="bilan <?php echo esc_attr( $sb_cl ); ?>"><?php echo esc_html( $sb_msg ); ?></div>

<?php if ( $sb_pb ) : ?>
<div class="prio">
	<h2>À traiter en priorité</h2>
	<ol>
	<?php foreach ( $sb_resultats as $sb_r ) : ?>
		<?php if ( 'probleme' === $sb_r['etat'] ) : ?>
			<li><strong><?php echo esc_html( $sb_r['titre'] ); ?></strong> — <?php echo esc_html( $sb_r['detail'] ); ?></li>
		<?php endif; ?>
	<?php endforeach; ?>
	</ol>
</div>
<?php endif; ?>

<?php
$sb_gc = '';
foreach ( $sb_resultats as $sb_r ) :
	if ( $sb_r['groupe'] !== $sb_gc ) :
		if ( '' !== $sb_gc ) :
			echo '</table>';
		endif;
		$sb_gc = $sb_r['groupe'];
		echo '<h2>' . esc_html( $sb_gc ) . '</h2><table>';
	endif;
	$sb_g = array(
		'ok'        => '✓',
		'attention' => '!',
		'probleme'  => '✕',
		'info'      => '·',
	);
	?>
	<tr class="<?php echo esc_attr( $sb_r['etat'] ); ?>">
		<td class="etat"><?php echo esc_html( $sb_g[ $sb_r['etat'] ] ); ?></td>
		<td class="titre"><?php echo esc_html( $sb_r['titre'] ); ?></td>
		<td>
			<?php echo esc_html( $sb_r['detail'] ); ?>
			<?php if ( $sb_r['action'] ) : ?>
				<span class="action"><?php echo esc_html( $sb_r['action'] ); ?></span>
			<?php endif; ?>
		</td>
	</tr>
	<?php
endforeach;
echo '</table>';
?>

<h2>Rapport à transmettre au support</h2>
<button type="button" id="sb-copier">Copier le rapport</button>
<textarea id="sb-rapport" readonly><?php echo esc_textarea( $sb_texte ); ?></textarea>

<p class="note">
	Ce rapport ne contient aucun mot de passe, clé d’API, adresse e-mail ni contenu d’article.
	Les chemins serveur sont tronqués.
	<br><br>
	<strong>Après diagnostic :</strong> ce fichier peut rester en place — il refuse tout accès
	non administrateur — ou être supprimé.
</p>

<script>
document.getElementById('sb-copier').addEventListener('click', function () {
	var zone = document.getElementById('sb-rapport');
	var btn = this;
	zone.select();
	var ok = false;
	try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
	if (navigator.clipboard && navigator.clipboard.writeText) {
		navigator.clipboard.writeText(zone.value).then(function () {
			btn.textContent = 'Rapport copié';
		}).catch(function () {});
	}
	btn.textContent = ok ? 'Rapport copié' : 'Sélectionnez le texte puis Ctrl+C';
});
</script>

</body>
</html>
