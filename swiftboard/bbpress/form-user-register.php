<?php
if (!defined("ABSPATH")) exit;
// phpcs:ignore WordPress.Security.NonceVerification — nonces handled by bbPress core
/**
 * Template override : Inscription utilisateur
 *
 * @package SwiftBoard
 */
?>

<form method="post" action="<?php bbp_wp_login_action(['context' => 'login_post']); ?>"
      class="bbp-login-form"
      role="form"
      aria-label="<?php esc_attr_e('Inscription', 'swiftboard'); ?>"
      data-msg-mismatch="<?php esc_attr_e('Les mots de passe ne correspondent pas.', 'swiftboard'); ?>"
      data-msg-short="<?php esc_attr_e('Le mot de passe doit faire au moins 8 caractères.', 'swiftboard'); ?>">

    <fieldset class="bbp-form">
        <legend><?php esc_html_e('Créer un compte', 'swiftboard'); ?></legend>

        <?php do_action('bbp_template_before_register_fields'); ?>

        <div class="bbp-template-notice info">
            <ul>
                <li><?php esc_html_e('Votre identifiant doit être unique et ne pourra pas être modifié ultérieurement.', 'swiftboard'); ?></li>
                <li><?php esc_html_e('Choisissez un mot de passe robuste (min. 8 caractères).', 'swiftboard'); ?></li>
            </ul>
        </div>

        <div class="bbp-form-field bbp-username">
            <label for="user_login"><?php esc_html_e('Identifiant', 'swiftboard'); ?> : </label>
            <input type="text" name="user_login" value="<?php bbp_sanitize_val('user_login'); ?>" size="20" id="user_login" maxlength="100" autocomplete="off">
        </div>

        <div class="bbp-form-field bbp-email">
            <label for="user_email"><?php esc_html_e('E-mail', 'swiftboard'); ?> : </label>
            <input type="email" name="user_email" value="<?php bbp_sanitize_val('user_email'); ?>" size="20" id="user_email" maxlength="100" autocomplete="off">
        </div>

        <div class="bbp-form-field bbp-password">
            <label for="user_pass"><?php esc_html_e('Mot de passe', 'swiftboard'); ?> : </label>
            <input type="password" name="user_pass" size="20" id="user_pass" maxlength="100" autocomplete="new-password" minlength="8" required>
        </div>

        <div class="bbp-form-field bbp-password-confirm">
            <label for="user_pass_confirm"><?php esc_html_e('Confirmer le mot de passe', 'swiftboard'); ?> : </label>
            <input type="password" name="user_pass_confirm" size="20" id="user_pass_confirm" maxlength="100" autocomplete="new-password" minlength="8" required>
        </div>

        <div class="sb-reg-notice" role="alert" style="display:none;"></div>

        <?php do_action('register_form'); ?>

        <div class="bbp-submit-wrapper">

            <button type="submit" name="user-submit" class="btn-primary user-submit"><?php esc_html_e('S\'inscrire', 'swiftboard'); ?></button>

            <?php bbp_user_register_fields(); ?>

        </div>

        <?php do_action('bbp_template_after_register_fields'); ?>

    </fieldset>
</form>

