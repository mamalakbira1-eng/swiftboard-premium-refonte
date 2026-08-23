<?php
if (!defined("ABSPATH")) exit;
// phpcs:ignore WordPress.Security.NonceVerification — nonces handled by bbPress core
/**
 * Template override : Mot de passe perdu
 *
 * @package SwiftBoard
 */
?>

<form method="post" action="<?php bbp_wp_login_action(['action' => 'lostpassword', 'context' => 'login_post']); ?>"
      class="bbp-login-form"
      role="form"
      aria-label="<?php esc_attr_e('Mot de passe oublié', 'swiftboard'); ?>">

    <fieldset class="bbp-form">
        <legend><?php esc_html_e('Mot de passe oublié', 'swiftboard'); ?></legend>

        <div class="bbp-form-field bbp-username">
            <p>
                <label for="user_login" class="hide"><?php esc_html_e('Identifiant ou e-mail', 'swiftboard'); ?> : </label>
                <input type="text" name="user_login" value="" size="20" id="user_login" maxlength="100" autocomplete="off">
            </p>
        </div>

        <?php do_action('login_form', 'resetpass'); ?>

        <div class="bbp-submit-wrapper">

            <button type="submit" name="user-submit" class="btn-primary user-submit"><?php esc_html_e('Réinitialiser mon mot de passe', 'swiftboard'); ?></button>

            <?php bbp_user_lost_pass_fields(); ?>

        </div>
    </fieldset>
</form>

