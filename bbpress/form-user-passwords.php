<?php
if (!defined("ABSPATH")) exit;
// phpcs:ignore WordPress.Security.NonceVerification — nonces handled by bbPress core
/**
 * Template override : Générateur de mot de passe utilisateur
 *
 * Affiché dans le formulaire d'édition de profil.
 *
 * @package SwiftBoard
 */
// Filtre l'affichage des champs de mot de passe.
// bbpress()->displayed_user est une propriete magique (__get) : elle n'est pas
// declaree sur la classe bbPress. On passe par bbp_get_displayed_user_id(), qui
// est l'accesseur public documente, et on retombe sur l'utilisateur courant.
$sb_displayed_user = get_userdata(bbp_get_displayed_user_id());
if (apply_filters('show_password_fields', true, $sb_displayed_user)) : ?>

<div id="password" class="user-pass1-wrap bbp-form-field">
    <label for="user_login"><?php esc_html_e('Mot de passe', 'swiftboard'); ?></label>
    <button type="button" class="btn-secondary wp-generate-pw hide-if-no-js"><?php esc_html_e('Générer un mot de passe', 'swiftboard'); ?></button>

    <fieldset class="bbp-form password wp-pwd hide-if-js">
        <span class="password-input-wrapper">
            <input type="password" name="pass1" id="pass1" class="regular-text" value="" autocomplete="off" data-pw="<?php echo esc_attr(wp_generate_password(24)); ?>" aria-describedby="pass-strength-result">
        </span>

        <span class="password-button-wrapper">
            <button type="button" class="btn-secondary wp-hide-pw hide-if-no-js" data-toggle="0" aria-label="<?php esc_attr_e('Masquer le mot de passe', 'swiftboard'); ?>">
                <span class="dashicons dashicons-hidden"></span>
                <span class="text"><?php esc_html_e('Masquer', 'swiftboard'); ?></span>
            </button><button type="button" class="btn-secondary wp-cancel-pw hide-if-no-js" data-toggle="0" aria-label="<?php esc_attr_e('Annuler le changement de mot de passe', 'swiftboard'); ?>">
                <span class="dashicons dashicons-no"></span>
                <span class="text"><?php esc_html_e('Annuler', 'swiftboard'); ?></span>
            </button>
        </span>

        <div style="display:none" id="pass-strength-result" aria-live="polite"></div>
    </fieldset>
</div>

<div class="user-pass2-wrap hide-if-js bbp-form-field">
    <label for="pass2"><?php esc_html_e('Répéter le nouveau mot de passe', 'swiftboard'); ?></label>
    <input name="pass2" type="password" id="pass2" class="regular-text" value="" autocomplete="off">
    <p class="description"><?php esc_html_e('Saisissez à nouveau votre nouveau mot de passe.', 'swiftboard'); ?></p>
</div>



<?php endif;

