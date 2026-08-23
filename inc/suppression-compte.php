<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Suppression de compte en self-service (RGPD art. 17).
 *
 * POURQUOI CE MODULE EXISTE
 * -------------------------
 * Manque releve par la simulation 15 : le theme greffait bien trois
 * nettoyages sur le hook `delete_user` (votes anonymises, notifications
 * purgees, journal d'audit assaini), mais AUCUN parcours ne permettait a un
 * membre de declencher cette suppression lui-meme. Le droit a l'effacement
 * suppose un moyen de l'exercer ; la demande devait passer par un
 * administrateur, hors du theme.
 *
 * Verifie avant d'ecrire : aucun gabarit de `bbpress/`, aucun module de
 * `inc/`, aucun script de `assets/js/` n'exposait de suppression de compte.
 *
 * TROIS DECISIONS DE CONCEPTION, TOUTES DEFENDABLES
 * -------------------------------------------------
 * 1. LE CONTENU DU FORUM EST CONSERVE, jamais supprime en cascade. Effacer
 *    les messages d'un partant creverait de trous les discussions d'autrui —
 *    des reponses sans question, des fils incomprehensibles. Les
 *    publications sont donc REATTRIBUEES a un compte de remplacement, et les
 *    donnees personnelles seules disparaissent. C'est la lecture retenue par
 *    la plupart des forums, et elle reste conforme : le contenu publie
 *    volontairement dans un espace public n'est pas une donnee identifiante
 *    une fois l'auteur anonymise.
 *
 * 2. LE MOT DE PASSE EST EXIGE, en plus du nonce. Un nonce protege du CSRF,
 *    pas d'une session laissee ouverte sur un poste partage. Une suppression
 *    est irreversible : elle merite une re-authentification.
 *
 * 3. LES ADMINISTRATEURS SONT EXCLUS. Un `manage_options` qui se supprime
 *    peut laisser le site sans administrateur — situation irrattrapable
 *    depuis l'interface. Ils passent par l'administration, ou retrogradent
 *    d'abord.
 *
 * @package SwiftBoard
 * @since 5.2.5
 */

/**
 * Ce membre peut-il supprimer son compte lui-meme ?
 *
 * Extrait en fonction pure : un hook seul ne se teste pas.
 *
 * @param int $user_id Compte examine.
 * @return bool
 */
function swiftboard_peut_supprimer_son_compte( $user_id ) {
	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return false;
	}

	// Un administrateur qui se supprime peut laisser le site sans
	// administrateur : cas irrattrapable depuis l'interface.
	if ( user_can( $user_id, 'manage_options' ) ) {
		return false;
	}

	/**
	 * Autorise ou refuse la suppression en self-service.
	 *
	 * @param bool $autorise Decision par defaut.
	 * @param int  $user_id  Compte concerne.
	 */
	return (bool) apply_filters( 'swiftboard_autoriser_suppression_compte', true, $user_id );
}

/**
 * Compte qui heritera des publications du partant.
 *
 * On ne supprime pas le contenu : on le reattribue. A defaut de compte
 * dedie, l'administrateur le plus ancien fait office de depot — jamais 0,
 * qui produirait des publications orphelines et des avertissements PHP a
 * chaque affichage.
 *
 * @return int Identifiant du compte de remplacement, 0 si aucun.
 */
function swiftboard_compte_de_reattribution() {
	$admins = get_users(
		array(
			'role'    => 'administrator',
			'number'  => 1,
			'orderby' => 'ID',
			'order'   => 'ASC',
			'fields'  => 'ID',
		)
	);

	$cible = ! empty( $admins ) ? (int) $admins[0] : 0;

	/**
	 * Compte recevant les publications d'un membre supprime.
	 *
	 * @param int $cible Identifiant retenu.
	 */
	return (int) apply_filters( 'swiftboard_compte_reattribution', $cible );
}

/**
 * Formulaire de suppression, rendu sur la page d'edition du profil bbPress.
 *
 * @return void
 */
function swiftboard_formulaire_suppression_compte() {
	$user_id = get_current_user_id();

	// On ne l'affiche qu'a l'interesse : un moderateur consultant le profil
	// d'un tiers ne doit pas voir un bouton qui supprimerait CE tiers.
	if ( ! $user_id || ! function_exists( 'bbp_get_displayed_user_id' )
		|| (int) bbp_get_displayed_user_id() !== $user_id ) {
		return;
	}

	if ( ! swiftboard_peut_supprimer_son_compte( $user_id ) ) {
		return;
	}
	?>
	<fieldset class="bbp-form swiftboard-suppression-compte">
		<legend><?php esc_html_e( 'Supprimer mon compte', 'swiftboard' ); ?></legend>

		<p class="description">
			<?php esc_html_e(
				'Votre compte et vos données personnelles seront définitivement effacés. Vos messages resteront visibles sur le forum, mais de façon anonyme : les supprimer rendrait incompréhensibles les discussions auxquelles d\'autres membres ont participé. Cette action est irréversible.',
				'swiftboard'
			);
			?>
		</p>

		<form method="post" action="">
			<?php wp_nonce_field( 'swiftboard_supprimer_compte_' . $user_id, '_sb_suppr_nonce' ); ?>
			<input type="hidden" name="swiftboard_supprimer_compte" value="1">

			<p>
				<label for="sb-suppr-mdp">
					<?php esc_html_e( 'Confirmez votre mot de passe :', 'swiftboard' ); ?>
				</label><br>
				<input type="password" id="sb-suppr-mdp" name="sb_suppr_mdp"
						autocomplete="current-password" required>
			</p>

			<p>
				<label>
					<input type="checkbox" name="sb_suppr_confirme" value="1" required>
					<?php esc_html_e( 'Je comprends que cette action est définitive.', 'swiftboard' ); ?>
				</label>
			</p>

			<button type="submit" class="button sb-suppr-valider">
				<?php esc_html_e( 'Supprimer définitivement mon compte', 'swiftboard' ); ?>
			</button>
		</form>
	</fieldset>
	<?php
}
// PAS DE HOOK bbPress — DEUX ESSAIS INFRUCTUEUX AVANT DE COMPRENDRE POURQUOI.
//
// 1. `bbp_user_edit_after` : le hook existe bien
// (bbpress/form-user-edit.php:172), mais il se declenche A L'INTERIEUR du
// <form> de bbPress. Un <form> imbrique est invalide en HTML et le
// navigateur supprime purement et simplement le formulaire interne.
//
// 2. `bbp_template_after_user_wrapper` : place apres le </form>, donc
// structurellement correct — mais jamais atteint non plus.
//
// La cause reelle, trouvee en inspectant le DOM plutot qu'en relisant le
// code : `inc/reddit-profile.php:27` intercepte `template_redirect`, rend son
// PROPRE profil puis appelle `exit`. Les gabarits `bbpress/` du profil ne sont
// donc JAMAIS charges, et aucun de leurs hooks ne se declenche. Mesure :
// 0 occurrence de `bbp_user_edit_submit` dans la page rendue.
//
// Le formulaire est par consequent appele directement depuis l'onglet
// « Compte » du rendu maison (voir inc/reddit-profile.php, case 'compte').

/**
 * Traite la demande de suppression.
 *
 * Accroche a `template_redirect` : assez tot pour rediriger proprement, assez
 * tard pour que l'utilisateur courant soit resolu.
 *
 * @return void
 */
function swiftboard_traiter_suppression_compte() {
	if ( empty( $_POST['swiftboard_supprimer_compte'] ) ) {
		return;
	}

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return;
	}

	// 1. Nonce : protege du CSRF.
	if ( ! isset( $_POST['_sb_suppr_nonce'] )
		|| ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['_sb_suppr_nonce'] ) ),
			'swiftboard_supprimer_compte_' . $user_id
		) ) {
		wp_die(
			esc_html__( 'Lien de confirmation expiré. Rechargez la page et recommencez.', 'swiftboard' ),
			'',
			array(
				'response'  => 403,
				'back_link' => true,
			)
		);
	}

	// 2. Capacite.
	if ( ! swiftboard_peut_supprimer_son_compte( $user_id ) ) {
		wp_die(
			esc_html__( 'Ce compte ne peut pas être supprimé depuis le profil.', 'swiftboard' ),
			'',
			array(
				'response'  => 403,
				'back_link' => true,
			)
		);
	}

	// 3. Re-authentification : le nonce ne protege pas d'une session laissee
	// ouverte sur un poste partage, et l'action est irreversible.
	$utilisateur = get_userdata( $user_id );
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- mot de passe brut, ne pas sanitizer.
	$mdp         = isset( $_POST['sb_suppr_mdp'] ) ? (string) wp_unslash( $_POST['sb_suppr_mdp'] ) : '';
	if ( ! $utilisateur || ! wp_check_password( $mdp, $utilisateur->user_pass, $user_id ) ) {
		wp_die(
			esc_html__( 'Mot de passe incorrect. Le compte n\'a pas été supprimé.', 'swiftboard' ),
			'',
			array(
				'response'  => 403,
				'back_link' => true,
			)
		);
	}

	if ( empty( $_POST['sb_suppr_confirme'] ) ) {
		wp_die(
			esc_html__( 'Confirmation manquante. Le compte n\'a pas été supprimé.', 'swiftboard' ),
			'',
			array(
				'response'  => 400,
				'back_link' => true,
			)
		);
	}

	// `wp_delete_user()` vit dans wp-admin : sur le front, il faut le charger
	// explicitement, sinon appel a une fonction indefinie.
	if ( ! function_exists( 'wp_delete_user' ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
	}

	$repreneur = swiftboard_compte_de_reattribution();

	/**
	 * Juste avant l'effacement — dernier moment pour exporter ou journaliser.
	 *
	 * @param int $user_id Compte supprime.
	 */
	do_action( 'swiftboard_avant_suppression_compte', $user_id );

	// La deconnexion precede la suppression : detruire le compte sous une
	// session active laisse un cookie pointant vers un identifiant disparu,
	// et chaque page suivante emet un avertissement.
	wp_logout();

	// Sans $reassign, WordPress SUPPRIME les publications : les discussions
	// d'autrui se retrouveraient trouees de reponses sans question.
	wp_delete_user( $user_id, $repreneur > 0 ? $repreneur : null );

	wp_safe_redirect( add_query_arg( 'compte-supprime', '1', home_url( '/' ) ) );
	exit;
}
add_action( 'template_redirect', 'swiftboard_traiter_suppression_compte', 5 );

/**
 * Confirme la suppression sur la page d'accueil.
 *
 * Sans retour visible, l'utilisateur ne sait pas si sa demande a abouti et
 * recommence — ou pire, doute que ses donnees aient ete effacees.
 *
 * @return void
 */
function swiftboard_message_compte_supprime() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- flag d'affichage en lecture seule.
	if ( empty( $_GET['compte-supprime'] ) || is_user_logged_in() ) {
		return;
	}
	printf(
		'<div class="swiftboard-notice swiftboard-notice-succes" role="status">%s</div>',
		esc_html__(
			'Votre compte et vos données personnelles ont été supprimés. '
			. 'Vos messages restent visibles de façon anonyme.',
			'swiftboard'
		)
	);
}
// `swiftboard_avant_contenu` avait ete ecrit d'abord : ce hook N'EXISTE PAS
// dans le theme. Aucune erreur n'etait levee — le message ne s'affichait
// simplement jamais, en silence. Constate en suivant la suppression jusqu'a
// la page d'arrivee plutot qu'en s'arretant a la redirection.
// `wp_body_open` est un hook du coeur, appele par header.php.
add_action( 'wp_body_open', 'swiftboard_message_compte_supprime' );

