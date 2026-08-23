<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Onboarding Reddit-like (Wizard 3 étapes en modale)
 *
 * Étape 1 : Choix du genre (Homme, Femme, Autre — SANS icône/emoji)
 * Étape 2 : Choix de l'avatar parmi les 15 avatars par défaut
 * Étape 3 : Connexion sociale 1 clic (Google, GitHub, Facebook, Email)
 *
 * Rendu 100% statique dans wp_footer pour zéro conflit cache.
 *
 * @package SwiftBoard
 * @since 5.4.0
 */
/**
 * Affiche la structure HTML statique de la modale d'onboarding dans le footer.
 */
function swiftboard_render_onboarding_modal() {
	if ( is_admin() || is_user_logged_in() ) {
		return;
	}

	$avatars = function_exists( 'swiftboard_get_avatars_list' ) ? swiftboard_get_avatars_list() : array();
	if ( empty( $avatars ) ) {
		// Fallback généreux de 1 à 15 si la fonction n'est pas chargée
		for ( $i = 1; $i <= 15; $i++ ) {
			$num           = str_pad( (string) $i, 2, '0', STR_PAD_LEFT );
			$avatars[ $i ] = array(
				'file'  => 'avatar-' . $num . '.webp',
				'label' => 'Avatar ' . $num,
			);
		}
	}

	$base_url   = defined( 'SWIFTBOARD_URI' ) ? SWIFTBOARD_URI : get_template_directory_uri();
	$avatar_dir = $base_url . '/assets/img/avatars/';
	?>
	<div id="sb-onboarding-modal" class="sb-modal-overlay" hidden role="dialog" aria-modal="true" aria-labelledby="sb-onb-title">
		<div class="sb-modal-content">
			<button type="button" class="sb-modal-close" aria-label="Fermer la modale" data-action="close">&times;</button>
			<div class="sb-modal-header">
				<span class="sb-modal-logo">S</span>
				<span class="sb-modal-title" id="sb-onb-title">Rejoignez la communauté</span>
			</div>

			<!-- ÉTAPE 1 : CHOIX DU GENRE -->
			<div id="sb-onb-step-1" class="sb-onb-step active">
				<h2 class="sb-step-title">Comment souhaitez-vous qu'on vous identifie ?</h2>
				<div class="sb-gender-choices">
					<button type="button" class="sb-onb-gender-btn" data-gender="homme">Homme</button>
					<button type="button" class="sb-onb-gender-btn" data-gender="femme">Femme</button>
					<button type="button" class="sb-onb-gender-btn" data-gender="autre">Autre / Préfère ne pas le dire</button>
				</div>
				<div class="sb-step-footer">
					<button type="button" class="sb-onb-skip-link" data-gender="autre">Passer cette étape</button>
				</div>
			</div>

			<!-- ÉTAPE 2 : CHOIX DE L'AVATAR -->
			<div id="sb-onb-step-2" class="sb-onb-step" hidden>
				<h2 class="sb-step-title">Choisissez votre avatar par défaut :</h2>
				<div class="sb-avatars-grid" role="radiogroup" aria-label="Choix de l'avatar">
					<?php
					foreach ( $avatars as $id => $info ) :
						$img_url = $avatar_dir . $info['file'];
						?>
						<button type="button" class="sb-onb-avatar-btn" data-avatar-id="<?php echo esc_attr( (string) $id ); ?>" aria-label="<?php echo esc_attr( $info['label'] ); ?>">
							<img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $info['label'] ); ?>" width="56" height="56" loading="lazy" />
						</button>
					<?php endforeach; ?>
				</div>
				<div class="sb-step-footer">
					<button type="button" class="sb-onb-back-btn" data-to-step="1">&larr; Retour au choix du genre</button>
				</div>
			</div>

			<!-- ÉTAPE 3 : CONNEXION SOCIALE 1 CLIC -->
			<div id="sb-onb-step-3" class="sb-onb-step" hidden>
				<h2 class="sb-step-title">Finalisez votre inscription en un clic :</h2>
				<div class="sb-social-buttons">
					<button type="button" class="sb-onb-social-btn sb-btn-google" data-provider="google">
						<span class="sb-social-label">Continuer avec Google</span>
					</button>
					<button type="button" class="sb-onb-social-btn sb-btn-github" data-provider="github">
						<span class="sb-social-label">Continuer avec GitHub</span>
					</button>
					<button type="button" class="sb-onb-social-btn sb-btn-facebook" data-provider="facebook">
						<span class="sb-social-label">Continuer avec Facebook</span>
					</button>
				</div>

				<div class="sb-onb-separator">
					<span>OU</span>
				</div>

				<div class="sb-onb-email-form">
					<label for="sb-onb-email" class="screen-reader-text">Adresse e-mail</label>
					<input type="email" id="sb-onb-email" class="sb-onb-input" placeholder="votre@email.com" autocomplete="email" />
					<button type="button" id="sb-onb-email-submit" class="sb-onb-submit">Continuer avec mon e-mail</button>
				</div>

				<div id="sb-onb-status" class="sb-onb-status" aria-live="polite"></div>

				<div class="sb-step-footer">
					<button type="button" class="sb-onb-back-btn" data-to-step="2">&larr; Retour au choix d'avatar</button>
				</div>
			</div>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'swiftboard_render_onboarding_modal', 20 );
