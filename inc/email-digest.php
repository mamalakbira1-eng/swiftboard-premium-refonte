<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped — admin context with intentional HTML
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared — SQL uses internal $wpdb variables (safe)
/**
 * SwiftBoard - Email Digest Hebdomadaire
 *
 * Envoie chaque lundi à 6h du matin un résumé hebdomadaire aux utilisateurs
 * qui ont opt-in :
 *  - Top 3 des répondeurs de la semaine
 *  - Sujets chauds (top 3 par score de votes)
 *  - Statistiques personnelles (upvotes reçus, réponses reçues, score)
 *  - Promotions de grade éventuelles
 *
 * Architecture Hostinger-safe :
 *  - 1 cron hebdo déclenche un cron "batch" qui traite 50 users/tick
 *  - Chaque tick = 1 requête WP-Cron séparée (évite le timeout PHP 30s)
 *  - Délai de 5 min entre chaque batch (réparti sur ~2h pour 1000 users)
 *  - Opt-in par user (meta swiftboard_email_digest_enabled)
 *  - Template HTML + plain text (compatibilité email clients)
 *  - Quota vérifié : max 500 emails/heure (limite Hostinger standard)
 *  - Logs d'envoi en option pour audit
 *
 * @package SwiftBoard
 * @since 2.8.0
 */
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared — SQL queries use internal $wpdb->prefix variables (safe)

// ============================================================================
// 1. CONSTANTES & RÉGLAGES
// ============================================================================
define( 'SB_DIGEST_BATCH_SIZE', 50 );        // users par batch
define( 'SB_DIGEST_BATCH_DELAY', 5 * MINUTE_IN_SECONDS ); // 5 min entre batches
define( 'SB_DIGEST_MAX_PER_HOUR', 400 );     // sécurité Hostinger

// ============================================================================
// 2. OPTIONS PAR DÉFAUT À L'ACTIVATION
// ============================================================================
add_action(
	'after_switch_theme',
	function () {
		if ( get_option( 'swiftboard_digest_settings' ) === false ) {
			add_option(
				'swiftboard_digest_settings',
				array(
					'enabled'          => 1,
					'day_of_week'      => 'monday',   // lundi
					'send_hour'        => 6,           // 6h du matin
					'batch_size'       => SB_DIGEST_BATCH_SIZE,
					'from_name'        => get_bloginfo( 'name' ),
					'subject_template' => '📰 Votre digest hebdo de {site_name}',
					'footer_text'      => '— L\'équipe {site_name}',
				)
			);
		}
	}
);

/**
 * @return array<string, mixed>
 */
function swiftboard_digest_get_settings() {
	$defaults = array(
		'enabled'          => 1,
		'day_of_week'      => 'monday',
		'send_hour'        => 6,
		'batch_size'       => SB_DIGEST_BATCH_SIZE,
		'from_name'        => get_bloginfo( 'name' ),
		'subject_template' => '📰 Votre digest hebdo de {site_name}',
		'footer_text'      => '— L\'équipe {site_name}',
	);
	$stored   = get_option( 'swiftboard_digest_settings', array() );
	if ( ! is_array( $stored ) ) {
		$stored = array();
	}
	return array_merge( $defaults, $stored );
}

// ============================================================================
// 3. OPT-IN UTILISATEUR (meta)
// ============================================================================
/**
 * Vérifie si un utilisateur a opt-in pour le digest.
 * Par défaut : opt-in ON pour les utilisateurs actifs (au moins 1 post).
 * L'user peut désactiver dans son profil.
 */
/**
 * L'utilisateur a-t-il consenti au digest hebdomadaire ?
 *
 * DEFAUT CHANGE : opt-out -> opt-in.
 *
 * L'ancien comportement envoyait a 100 % de la base, y compris aux comptes
 * inscrits et jamais revenus. Ce n'est pas d'abord un sujet juridique, c'est
 * un sujet de DELIVRABILITE : Gmail et Yahoo exigent depuis 2024 un taux de
 * plainte pour spam sous 0,3 %. Les comptes dormants ne lisent pas, ne
 * cliquent pas, et signalent. Au-dela du seuil, ce n'est pas le digest qui
 * tombe en spam mais tout le domaine — donc aussi les e-mails de
 * reinitialisation de mot de passe et de confirmation d'inscription.
 * Sur un mutualise a IP partagee, il n'y a aucune reputation propre a opposer.
 *
 * Le consentement est desormais explicite. Il est pose automatiquement a la
 * premiere contribution reelle (cf. swiftboard_digest_optin_on_first_post) :
 * quelqu'un qui participe veut suivre le forum, quelqu'un qui n'est jamais
 * revenu non. On conserve ainsi l'essentiel de l'engagement avec une
 * fraction du volume.
 *
 * @param int $user_id ID utilisateur.
 * @return bool Vrai uniquement si un consentement explicite est enregistre.
 */
function swiftboard_digest_user_opted_in( $user_id ) {
	$value = get_user_meta( $user_id, 'swiftboard_email_digest_enabled', true );
	if ( $value === '' ) {
		// Aucun choix enregistre = pas de consentement = pas d'envoi.
		return false;
	}
	return (int) $value === 1;
}

/**
 * Consentement implicite a la premiere contribution.
 *
 * Publier un sujet ou une reponse est un signal d'engagement fort. On active
 * alors le digest, en le tracant (`swiftboard_digest_optin_source`) pour
 * pouvoir distinguer un consentement implicite d'un choix explicite.
 *
 * On n'ecrase JAMAIS une decision deja prise par l'utilisateur : si la meta
 * existe, elle fait foi — y compris un refus.
 *
 * @param int $post_id ID du contenu publie.
 * @return void
 */
function swiftboard_digest_optin_on_first_post( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || 'publish' !== $post->post_status ) {
		return;
	}

	$types = array( 'topic', 'reply' );
	if ( function_exists( 'bbp_get_topic_post_type' ) ) {
		$types = array( bbp_get_topic_post_type(), bbp_get_reply_post_type() );
	}
	if ( ! in_array( $post->post_type, $types, true ) ) {
		return;
	}

	$user_id = (int) $post->post_author;
	if ( ! $user_id ) {
		return;
	}

	// Un choix deja exprime n'est jamais ecrase.
	if ( '' !== get_user_meta( $user_id, 'swiftboard_email_digest_enabled', true ) ) {
		return;
	}

	swiftboard_digest_set_user_optin( $user_id, true );
	update_user_meta( $user_id, 'swiftboard_digest_optin_source', 'first_post' );
}
add_action( 'bbp_new_topic', 'swiftboard_digest_optin_on_first_post', 20 );
add_action( 'bbp_new_reply', 'swiftboard_digest_optin_on_first_post', 20 );

/**
 * swiftboard_digest_set_user_optin().
 *
 * @param int  $user_id Identifiant de l'utilisateur.
 * @param bool $enabled Nouvel état souhaité.
 * @return void
 */
function swiftboard_digest_set_user_optin( $user_id, $enabled ) {
	update_user_meta( $user_id, 'swiftboard_email_digest_enabled', $enabled ? 1 : 0 );
}



/**
 * Adresse d'expedition alignee sur le domaine du site (exigence DMARC).
 *
 * DMARC exige que le domaine du From: soit le meme que celui qui signe le
 * message en DKIM — soit le domaine du site. On derive donc l'adresse de
 * home_url() plutot que de get_option('admin_email'), qui pointe tres souvent
 * vers une boite personnelle chez un tiers.
 *
 * Le prefixe « www. » est retire : DMARC accepte l'alignement relaxe entre un
 * sous-domaine et son domaine organisationnel, mais l'alignement STRICT
 * (adkim=s) l'exige identique, et rien n'oblige un site a rester en relaxe.
 *
 * Le resultat est filtrable : un hebergeur qui impose une adresse d'envoi
 * differente doit pouvoir la fournir.
 *
 * @return string Adresse e-mail d'expedition.
 */
function swiftboard_digest_from_address() {
	$hote = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$hote = preg_replace( '/^www\./i', '', $hote );

	// Un hote sans point (« localhost ») n'est pas une adresse routable :
	// PHPMailer refuse le message. On se rabat alors sur l'adresse admin,
	// qui reste le moins mauvais choix en developpement.
	if ( $hote === '' || strpos( $hote, '.' ) === false ) {
		$adresse = (string) get_option( 'admin_email' );
	} else {
		$adresse = 'no-reply@' . strtolower( $hote );
	}

	/**
	 * Filtre l'adresse d'expedition du digest.
	 *
	 * @param string $adresse Adresse derivee du domaine du site.
	 */
	return apply_filters( 'swiftboard_digest_from_address', $adresse );
}


// ============================================================================
// 6. RENDU DU TEMPLATE EMAIL (HTML + plain text)
// ============================================================================
/**
 * swiftboard_digest_send_to_user().
 *
 * @param int $user_id Identifiant de l'utilisateur.
 * @return bool|string
 */
function swiftboard_digest_send_to_user( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user || ! $user->user_email ) {
		return false;
	}

	$settings = swiftboard_digest_get_settings();
	if ( ! (int) $settings['enabled'] ) {
		return false;
	}

	// Opt-in
	if ( ! swiftboard_digest_user_opted_in( $user_id ) ) {
		return false;
	}

	// Construire les données
	$data = swiftboard_digest_build_data( $user_id );

	// Vérifier qu'il y a quelque chose à envoyer (évite le spam inutile)
	if ( empty( $data['hot_topics'] ) && empty( $data['top_responders'] )
		&& empty( $data['promotion'] ) && $data['my_stats']['score'] === 0 ) {
		// Marquer comme envoyé quand même pour ne pas retraiter
		update_user_meta( $user_id, 'sb_digest_sent_' . gmdate( 'YW' ), 1 );
		return 'skipped';
	}

	$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$subject   = str_replace( '{site_name}', $site_name, $settings['subject_template'] );

	$html  = swiftboard_digest_render_html( $user_id, $data );
	$plain = swiftboard_digest_render_plain( $user_id, $data );

	// Expediteur ALIGNE avec le domaine du site (DMARC).
	//
	// Le From: posait auparavant get_option('admin_email'). Cette adresse est
	// saisie a l'installation et vaut tres souvent une boite personnelle
	// (gmail.com, orange.fr). Or DMARC n'accepte le message que si le domaine
	// du From: est aligne avec celui qui SIGNE en DKIM — c'est-a-dire le
	// domaine du site. Un From: gmail.com signe par exemple.com ne s'aligne
	// pas : Gmail met le digest en quarantaine MALGRE une signature
	// cryptographiquement valide.
	//
	// Mesure : qa/delivrabilite.py sur le message reel capture en SMTP.
	//
	// L'administrateur reste joignable : son adresse passe en Reply-To.
	$headers = array(
		'From: ' . $settings['from_name'] . ' <' . swiftboard_digest_from_address() . '>',
		'Reply-To: ' . get_option( 'admin_email' ),
	);

	// List-Unsubscribe : exige par Gmail et Yahoo depuis fevrier 2024 pour tout
	// envoi en masse. Il affiche le lien « Se desabonner » natif en tete du
	// message, ce qui detourne les utilisateurs du bouton « Signaler comme
	// spam » — c'est le levier principal pour tenir le taux de plainte sous
	// 0,3 %. One-Click permet le desabonnement sans quitter la messagerie.
	$unsub_url = home_url(
		'/profil/?digest_unsubscribe=1&uid=' . (int) $user_id
		. '&token=' . swiftboard_digest_unsubscribe_token( $user_id )
	);
	$headers[] = 'List-Unsubscribe: <' . $unsub_url . '>';
	$headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
	// Signale un envoi automatique : evite les reponses d'absence et exclut
	// le message des statistiques d'engagement des messageries.
	$headers[] = 'Auto-Submitted: auto-generated';
	$headers[] = 'Precedence: bulk';

	// Boundary pour multipart.
	//
	// wp_mail() PARSE l'en-tete Content-Type pour en extraire le charset, puis
	// le repositionne lui-meme via les filtres wp_mail_content_type et
	// wp_mail_charset. Pour « multipart/alternative » il ne trouve pas de
	// charset et en emet un VIDE : le message partait avec DEUX en-tetes
	// Content-Type, dont un « multipart/alternative; charset= ».
	//
	// Constate uniquement en passant par un vrai serveur SMTP : l'interception
	// via pre_wp_mail court-circuite PHPMailer et ne montre pas l'assemblage
	// final. Certains clients stricts refusent un message a Content-Type
	// duplique, ou n'affichent que la source brute.
	//
	// On impose donc le type par les filtres prevus a cet effet, et on retire
	// l'en-tete de la liste : wp_mail() ne le voit plus, PHPMailer n'en pose
	// qu'un seul.
	// Aucun Content-Type n'est place dans la liste : depuis la correction
	// DMARC, $headers[0] est le From:. Retirer un index en dur ici
	// supprimerait l'expediteur.
	$boundary = md5( uniqid( (string) time(), true ) );
	$headers  = array_values(
		array_filter(
			$headers,
			static function ( $h ) {
				return stripos( trim( (string) $h ), 'content-type:' ) !== 0;
			}
		)
	);

	$sb_type_multipart = static function () use ( $boundary ) {
		return 'multipart/alternative; boundary="' . $boundary . '"';
	};
	add_filter( 'wp_mail_content_type', $sb_type_multipart, 99 );
	$body = "--{$boundary}\r\n"
			. "Content-Type: text/plain; charset=UTF-8\r\n"
			. "Content-Transfer-Encoding: 7bit\r\n\r\n"
			. $plain . "\r\n\r\n"
			. "--{$boundary}\r\n"
			. "Content-Type: text/html; charset=UTF-8\r\n"
			. "Content-Transfer-Encoding: 7bit\r\n\r\n"
			. $html . "\r\n\r\n"
			. "--{$boundary}--\r\n";

	$sent = wp_mail( $user->user_email, $subject, $body, $headers );

	// Le filtre est retire immediatement : laisse en place, il forcerait le
	// type multipart sur TOUS les e-mails suivants de la requete (reinitialisation
	// de mot de passe, notification de commentaire...).
	remove_filter( 'wp_mail_content_type', $sb_type_multipart, 99 );

	if ( $sent ) {
		update_user_meta( $user_id, 'sb_digest_sent_' . gmdate( 'YW' ), 1 );
	}

	return $sent ? 'sent' : 'failed';
}

// ============================================================================
// 8. CRON HEBDO — DÉCLENCHEMENT INITIAL
// ============================================================================
/**
 * Planifie le déclenchement initial du digest hebdo.
 * Le cron 'swiftboard_digest_weekly_trigger' calcule l'offset courant et
 * programme le premier batch immédiatement.
 */
add_action(
	'wp',
	function () {
		if ( ! wp_next_scheduled( 'swiftboard_digest_weekly_trigger' ) ) {
			$settings = swiftboard_digest_get_settings();
			// Prochain lundi à l'heure configurée
			$next = strtotime( 'next ' . $settings['day_of_week'] . ' ' . sprintf( '%02d:00:00', $settings['send_hour'] ) );
			wp_schedule_event( $next, 'weekly', 'swiftboard_digest_weekly_trigger' );
		}
	}
);

add_filter(
	'cron_schedules',
	function ( $schedules ) {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Hebdomadaire', 'swiftboard' ),
			);
		}
		return $schedules;
	}
);

/**
 * Déclenchement hebdo : démarre le premier batch.
 * Les batches suivants sont programmés dynamiquement via wp_schedule_single_event.
 */
add_action(
	'swiftboard_digest_weekly_trigger',
	function () {
		$settings = swiftboard_digest_get_settings();
		if ( ! (int) $settings['enabled'] ) {
			return;
		}

		// Reset des meta "envoyé cette semaine" (au cas où on repasse)
		// Non : on garde les meta, elles sont datées par semaine (YWW).

		// Lancer le premier batch immédiatement
		wp_schedule_single_event( time() + 60, 'swiftboard_digest_send_batch', array( 0 ) );
	}
);

/**
 * Envoie un batch de N emails et programme le suivant.
 * Hook : swiftboard_digest_send_batch, arg : $offset
 */
add_action(
	'swiftboard_digest_send_batch',
	function ( $offset ) {
		$settings   = swiftboard_digest_get_settings();
		$batch_size = (int) $settings['batch_size'];

		// Vérifier le quota horaire (limite Hostinger)
		$hour_key = 'sb_digest_quota_' . gmdate( 'Y-m-d H' );
		$quota    = (int) get_transient( $hour_key );
		if ( $quota >= SB_DIGEST_MAX_PER_HOUR ) {
			// Repousser de 1 heure
			wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'swiftboard_digest_send_batch', array( $offset ) );
			return;
		}

		$user_ids = swiftboard_digest_get_next_batch( $offset, $batch_size );

		if ( empty( $user_ids ) ) {
			// Tous les users ont été traités → terminé
			return;
		}

		$sent_count = 0;
		foreach ( $user_ids as $user_id ) {
			$result = swiftboard_digest_send_to_user( $user_id );
			if ( $result === 'sent' ) {
				$sent_count++;
				$quota++;
			}
		}

		// Mettre à jour le quota horaire
		set_transient( $hour_key, $quota, 2 * HOUR_IN_SECONDS );

		// Logger
		if ( $sent_count > 0 ) {
			$log = get_option( 'swiftboard_digest_last_log', array() );
			if ( ! is_array( $log ) ) {
				$log = array();
			}
			$log[] = array(
				'time'   => current_time( 'mysql' ),
				'offset' => $offset,
				'sent'   => $sent_count,
				'total'  => count( $user_ids ),
			);
			// Garder que les 50 derniers
			$log = array_slice( $log, -50 );
			update_option( 'swiftboard_digest_last_log', $log, false );
		}

		// Programmer le batch suivant dans 5 minutes
		wp_schedule_single_event( time() + SB_DIGEST_BATCH_DELAY, 'swiftboard_digest_send_batch', array( $offset + $batch_size ) );
	}
);

// ============================================================================
// 9. PAGE ADMIN — RÉGLAGES DU DIGEST
// ============================================================================
add_action(
	'admin_menu',
	function () {
		add_submenu_page(
			'swiftboard-dashboard',
			__( 'Email digest', 'swiftboard' ),
			__( '📧 Email digest', 'swiftboard' ),
			'manage_options',
			'swiftboard-digest',
			'swiftboard_digest_admin_page'
		);
	}
);
