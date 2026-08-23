<?php
if (!defined("ABSPATH")) exit;
// phpcs:ignore WordPress.Security.NonceVerification — nonces handled by bbPress core
/**
 * Template override : Champs utilisateur anonyme
 *
 * Affiché quand un visiteur non connecté peut poster un sujet
 * ou une réponse (si l'option bbPress est activée).
 *
 * @package SwiftBoard
 */
if (bbp_current_user_can_access_anonymous_user_form()) : ?>

    <?php do_action('bbp_theme_before_anonymous_form'); ?>

    <fieldset class="bbp-form">
        <legend><?php (bbp_is_topic_edit() || bbp_is_reply_edit())
            ? esc_html_e('Informations sur l\'auteur', 'swiftboard')
            : esc_html_e('Vos informations :', 'swiftboard');
        ?></legend>

        <?php do_action('bbp_theme_anonymous_form_extras_top'); ?>

        <div class="bbp-form-field">
            <label for="bbp_anonymous_author"><?php esc_html_e('Nom (requis) :', 'swiftboard'); ?></label><br>
            <input type="text" id="bbp_anonymous_author" value="<?php bbp_author_display_name(); ?>"
                   size="40" maxlength="100" name="bbp_anonymous_name" autocomplete="off">
        </div>

        <div class="bbp-form-field">
            <label for="bbp_anonymous_email"><?php esc_html_e('Email (non publié) (requis) :', 'swiftboard'); ?></label><br>
            <input type="email" id="bbp_anonymous_email" value="<?php bbp_author_email(); ?>"
                   size="40" maxlength="100" name="bbp_anonymous_email">
        </div>

        <div class="bbp-form-field">
            <label for="bbp_anonymous_website"><?php esc_html_e('Site web :', 'swiftboard'); ?></label><br>
            <input type="text" id="bbp_anonymous_website" value="<?php bbp_author_url(); ?>"
                   size="40" maxlength="200" name="bbp_anonymous_website">
        </div>

        <?php do_action('bbp_theme_anonymous_form_extras_bottom'); ?>

    </fieldset>

    <?php do_action('bbp_theme_after_anonymous_form'); ?>

<?php endif;

