<?php
if (!defined("ABSPATH")) exit;
// phpcs:ignore WordPress.Security.NonceVerification — nonces handled by bbPress core
/**
 * Template override : Connexion utilisateur
 *
 * @package SwiftBoard
 */
?>

<form method="post" action="<?php bbp_wp_login_action(['context' => 'login_post']); ?>"
      class="bbp-login-form"
      role="form"
      aria-label="<?php esc_attr_e('Connexion', 'swiftboard'); ?>">

    <fieldset class="bbp-form">
        <legend><?php esc_html_e('Connexion', 'swiftboard'); ?></legend>

        <div class="bbp-form-field bbp-username">
            <label for="user_login"><?php esc_html_e('Identifiant', 'swiftboard'); ?> : </label>
            <input type="text" name="log" value="<?php bbp_sanitize_val('user_login', 'text'); ?>" size="20" maxlength="100" id="user_login" autocomplete="off">
        </div>

        <div class="bbp-form-field bbp-password">
            <label for="user_pass"><?php esc_html_e('Mot de passe', 'swiftboard'); ?> : </label>
            <input type="password" name="pwd" value="<?php bbp_sanitize_val('user_pass', 'password'); ?>" size="20" id="user_pass" autocomplete="off">
        </div>

        <div class="bbp-form-field bbp-form-checkbox bbp-remember-me">
            <input type="checkbox" name="rememberme" value="forever" <?php checked(bbp_get_sanitize_val('rememberme', 'checkbox')); ?> id="rememberme">
            <label for="rememberme"><?php esc_html_e('Rester connecté', 'swiftboard'); ?></label>
        </div>

        <?php do_action('login_form'); ?>

        <div class="bbp-submit-wrapper">

            <button type="submit" name="user-submit" id="user-submit" class="btn-primary user-submit"><?php esc_html_e('Se connecter', 'swiftboard'); ?></button>

            <?php bbp_user_login_fields(); ?>

        </div>
    </fieldset>
</form>

