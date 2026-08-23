<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Consentement et desabonnement du digest.
 *
 * EXI-ARCH-01 : extrait de inc/email-digest.php. Ce module DOIT rester en
 * front : le lien de desabonnement est ouvert depuis une boite mail, par un
 * destinataire generalement deconnecte. Depuis un module admin-only, le
 * handler `template_redirect` ne s'executerait jamais et le lien serait mort
 * — un manquement direct au RGPD.
 *
 * Le jeton vaut authentification : imprevisible sans acces a la base, ce qui
 * rend un nonce inutile (et impossible, le lien vivant dans un e-mail).
 *
 * @package SwiftBoard
 * @since 5.1.0
 */
/**
 * Jeton de desabonnement d'un utilisateur.
 *
 * DEFAUT CORRIGE : l'ancien jeton valait wp_hash($uid . 'unsubscribe').
 * Il ne dependait que d'un identifiant SEQUENTIEL, n'expirait jamais, et
 * restait identique apres un reabonnement. Tout lien ayant fuite (e-mail
 * transfere, archive, capture) permettait de desabonner ce compte
 * indefiniment.
 *
 * Le jeton integre desormais l'e-mail et le mot de passe hache : il devient
 * imprevisible sans acces a la base, et il est INVALIDE automatiquement si
 * l'utilisateur change d'adresse ou de mot de passe.
 *
 * @param int $user_id ID utilisateur.
 * @return string Jeton, ou chaine vide si l'utilisateur n'existe pas.
 */
function swiftboard_digest_unsubscribe_token( $user_id ) {
	$user = get_userdata( (int) $user_id );
	if ( ! $user ) {
		return '';
	}
	return wp_hash( (int) $user_id . '|' . $user->user_email . '|' . $user->user_pass . '|digest_unsubscribe' );
}

// ============================================================================
// 10. ENDPOINT DE DÉSABONNEMENT (URL dans l'email)
// ============================================================================
add_action(
	'template_redirect',
	function () {
		// L'en-tete List-Unsubscribe-Post declare One-Click : Gmail et Yahoo
		// envoient alors une requete POST vers cette URL, sans intervention de
		// l'utilisateur. Ne lire que $_GET ferait echouer ce desabonnement en
		// silence — exactement ce que les messageries penalisent. On accepte donc
		// les deux methodes, en prenant les parametres dans l'URL (ils y sont dans
		// les deux cas) et le declencheur One-Click dans le corps du POST.
		$params = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$one_click = isset( $_POST['List-Unsubscribe'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		&& 'One-Click' === sanitize_text_field( wp_unslash( $_POST['List-Unsubscribe'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! isset( $params['digest_unsubscribe'], $params['uid'], $params['token'] ) ) {
			return;
		}

		$uid      = (int) $params['uid'];
		$token    = sanitize_text_field( wp_unslash( $params['token'] ) );
		$expected = swiftboard_digest_unsubscribe_token( $uid );

		// Le jeton EST l'authentification : il est imprevisible sans acces a la
		// base, ce qui rend une attaque CSRF sans objet (et un nonce serait de
		// toute facon impossible, le lien vivant dans un e-mail).
		if ( '' === $expected || ! hash_equals( $expected, $token ) ) {
			wp_die( __( 'Lien de désabonnement invalide ou expiré.', 'swiftboard' ), 403 );
		}

		swiftboard_digest_set_user_optin( $uid, false );
		update_user_meta( $uid, 'swiftboard_digest_optin_source', 'unsubscribe_link' );

		// One-Click attend une reponse machine, pas une page HTML.
		if ( $one_click || 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
			status_header( 200 );
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo 'Unsubscribed';
			exit;
		}

		// Le desabonnement a REUSSI : la reponse doit etre 200, quelle que soit
		// l'URL utilisee. Sans cela WordPress conserve le 404 herite du routage
		// (verifie : la page /profil/ n'existe pas forcement selon les sites) et
		// l'utilisateur voit « Desabonnement confirme » servi avec un code
		// d'erreur — que les messageries interpretent comme un lien casse.
		status_header( 200 );
		add_filter( 'wp_robots', 'wp_robots_no_robots' );

		// m-8: Styled unsubscribe confirmation page
		get_header();
		echo '<div style="max-width:600px;margin:80px auto;text-align:center;padding:40px;background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,0.08);">';
		echo '<div style="font-size:64px;margin-bottom:16px;">✅</div>';
		echo '<h1 style="color:#006cbd;font-size:28px;margin-bottom:12px;">' . esc_html__( 'Désabonnement confirmé', 'swiftboard' ) . '</h1>';
		echo '<p style="font-size:16px;color:#4d4d4d;margin-bottom:24px;">' . esc_html__( 'Vous ne recevrez plus le digest hebdomadaire. Vous pouvez vous réabonner à tout moment depuis votre profil.', 'swiftboard' ) . '</p>';
		echo '<a href="' . esc_url( home_url( '/' ) ) . '" style="display:inline-block;background:#006cbd;color:#fff;padding:12px 32px;border-radius:9999px;font-size:14px;font-weight:700;text-decoration:none;">' . esc_html__( 'Retour au forum', 'swiftboard' ) . '</a>';
		echo '</div>';
		get_footer();
		exit;
	}
);

// Le champ « Email digest » a été retiré du profil à la demande du client.
// Le digest reste géré en back-end (email-digest.php) mais sans case dans le profil.

// ============================================================================
// 12. NETTOYAGE DU CRON À LA DÉSACTIVATION
// ============================================================================
add_action(
	'switch_theme',
	function () {
		wp_clear_scheduled_hook( 'swiftboard_digest_weekly_trigger' );
		wp_clear_scheduled_hook( 'swiftboard_digest_send_batch' );
	}
);
// ============================================================================
// 14. MIGRATION OPT-OUT -> OPT-IN (a executer une seule fois)
// ============================================================================
/**
 * Bascule les comptes EXISTANTS vers le consentement explicite.
 *
 * Le defaut est passe de opt-out a opt-in. Appliquer ce nouveau defaut
 * brutalement ferait perdre l'integralite de la liste, y compris les membres
 * reellement actifs qui n'ont simplement jamais eu a cocher une case.
 *
 * Regle retenue, alignee sur le signal d'engagement deja utilise a l'inscription
 * (cf. swiftboard_digest_optin_on_first_post) :
 *   - a publie au moins un sujet ou une reponse  -> consentement implicite
 *     conserve, trace `migration_actif`
 *   - n'a jamais rien publie                      -> aucun envoi
 *
 * Un compte qui n'a jamais contribue n'ouvrira pas le digest : c'est
 * exactement la population qui genere les plaintes pour spam et qui degrade
 * la reputation d'expediteur du domaine.
 *
 * Idempotent : ne touche jamais un utilisateur ayant deja une preference
 * enregistree, et ne s'execute qu'une fois (option de garde).
 *
 * @param bool $dry_run Si vrai, ne modifie rien et retourne seulement le compte.
 * @return array<string, mixed> Statistiques de migration.
 */
function swiftboard_digest_migrer_vers_optin( $dry_run = false ) {
	global $wpdb;

	$actifs = $wpdb->get_col(
		"SELECT DISTINCT post_author FROM {$wpdb->posts}
         WHERE post_type IN ('topic','reply')
           AND post_status = 'publish'
           AND post_author > 0"
	);
	$actifs = array_map( 'intval', (array) $actifs );

	$stats = array(
		'actifs'      => 0,
		'inactifs'    => 0,
		'deja_choisi' => 0,
	);

	foreach ( get_users( array( 'fields' => 'ID' ) ) as $user_id ) {
		$user_id = (int) $user_id;

		// Une preference explicite fait toujours foi.
		if ( '' !== get_user_meta( $user_id, 'swiftboard_email_digest_enabled', true ) ) {
			++$stats['deja_choisi'];
			continue;
		}

		if ( in_array( $user_id, $actifs, true ) ) {
			++$stats['actifs'];
			if ( ! $dry_run ) {
				swiftboard_digest_set_user_optin( $user_id, true );
				update_user_meta( $user_id, 'swiftboard_digest_optin_source', 'migration_actif' );
			}
		} else {
			++$stats['inactifs'];
			if ( ! $dry_run ) {
				swiftboard_digest_set_user_optin( $user_id, false );
				update_user_meta( $user_id, 'swiftboard_digest_optin_source', 'migration_inactif' );
			}
		}
	}

	if ( ! $dry_run ) {
		update_option( 'swiftboard_digest_migration_optin_done', time() );
	}

	return $stats;
}

/**
 * Declenche la migration une seule fois, apres mise a jour du theme.
 */
add_action(
	'after_switch_theme',
	function () {
		if ( ! get_option( 'swiftboard_digest_migration_optin_done' ) ) {
			swiftboard_digest_migrer_vers_optin();
		}
	},
	30
);
