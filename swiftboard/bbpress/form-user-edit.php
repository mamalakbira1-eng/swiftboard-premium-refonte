<?php
if (!defined("ABSPATH")) exit;
// phpcs:ignore WordPress.Security.NonceVerification — nonces handled by bbPress core
/**
 * Template override : Édition du profil utilisateur bbPress (VERSION SIMPLIFIÉE)
 *
 * Le formulaire ne garde que l'essentiel : Prénom, Nom, Pseudo, E-mail et
 * mot de passe. Les sections « Coordonnées » (site web, contacts), « À propos
 * de vous » (biographie) et « Langue » ont été retirées à la demande du client
 * pour une UX/UI très simple.
 *
 * @package SwiftBoard
 */
?>

<form id="bbp-your-profile" method="post" enctype="multipart/form-data"
      role="form"
      aria-label="<?php esc_attr_e('Édition du profil', 'swiftboard'); ?>">

    <h2 class="entry-title"><?php esc_html_e('Identité', 'swiftboard'); ?></h2>

    <?php do_action('bbp_user_edit_before'); ?>

    <fieldset class="bbp-form">
        <legend><?php esc_html_e('Identité', 'swiftboard'); ?></legend>

        <?php do_action('bbp_user_edit_before_name'); ?>

        <div class="bbp-form-field">
            <label for="first_name"><?php esc_html_e('Prénom', 'swiftboard'); ?></label>
            <input type="text" name="first_name" id="first_name" value="<?php bbp_displayed_user_field('first_name', 'edit'); ?>" class="regular-text">
        </div>

        <div class="bbp-form-field">
            <label for="last_name"><?php esc_html_e('Nom', 'swiftboard'); ?></label>
            <input type="text" name="last_name" id="last_name" value="<?php bbp_displayed_user_field('last_name', 'edit'); ?>" class="regular-text">
        </div>

        <div class="bbp-form-field">
            <label for="nickname"><?php esc_html_e('Pseudo', 'swiftboard'); ?></label>
            <input type="text" name="nickname" id="nickname" value="<?php bbp_displayed_user_field('nickname', 'edit'); ?>" class="regular-text">
        </div>

        <?php do_action('bbp_user_edit_after_name'); ?>

    </fieldset>

    <fieldset class="bbp-form">
        <legend><?php esc_html_e('Compte', 'swiftboard'); ?></legend>

        <?php do_action('bbp_user_edit_before_account'); ?>

        <div class="bbp-form-field">
            <label for="user_login"><?php esc_html_e('Nom d\'utilisateur', 'swiftboard'); ?></label>
            <input type="text" name="user_login" id="user_login" value="<?php bbp_displayed_user_field('user_login', 'edit'); ?>" maxlength="100" disabled="disabled" class="regular-text">
            <p class="description"><?php esc_html_e('Le nom d\'utilisateur ne peut pas être modifié.', 'swiftboard'); ?></p>
        </div>

        <div class="bbp-form-field">
            <label for="email"><?php esc_html_e('E-mail', 'swiftboard'); ?></label>
            <input type="email" name="email" id="email" value="<?php bbp_displayed_user_field('user_email', 'edit'); ?>" maxlength="100" class="regular-text" autocomplete="off">
        </div>

        <?php bbp_get_template_part('form', 'user-passwords'); ?>

        <?php do_action('bbp_user_edit_after_account'); ?>

    </fieldset>

    <?php if (!bbp_is_user_home_edit() && current_user_can('promote_user', bbp_get_displayed_user_id())) : ?>

        <h2 class="entry-title"><?php esc_html_e('Rôle utilisateur', 'swiftboard'); ?></h2>

        <fieldset class="bbp-form">
            <legend><?php esc_html_e('Rôle utilisateur', 'swiftboard'); ?></legend>

            <?php do_action('bbp_user_edit_before_role'); ?>

            <?php if (is_multisite() && is_super_admin() && current_user_can('manage_network_options')) : ?>

                <div class="bbp-form-field bbp-form-checkbox">
                    <label for="super_admin"><?php esc_html_e('Rôle réseau', 'swiftboard'); ?></label>
                    <label>
                        <input class="checkbox" type="checkbox" id="super_admin" name="super_admin"<?php checked(is_super_admin(bbp_get_displayed_user_id())); ?>>
                        <?php esc_html_e('Accorder à cet utilisateur les privilèges super-admin du réseau.', 'swiftboard'); ?>
                    </label>
                </div>

            <?php endif; ?>

            <?php bbp_get_template_part('form', 'user-roles'); ?>

            <?php do_action('bbp_user_edit_after_role'); ?>

        </fieldset>

    <?php endif; ?>

    <?php do_action('bbp_user_edit_after'); ?>

    <fieldset class="bbp-form submit">
        <legend><?php esc_html_e('Enregistrer les modifications', 'swiftboard'); ?></legend>
        <div class="bbp-submit-wrapper">

            <?php bbp_edit_user_form_fields(); ?>

            <button type="submit" id="bbp_user_edit_submit" name="bbp_user_edit_submit" class="btn-primary user-submit"><?php bbp_is_user_home_edit()
                ? esc_html_e('Mettre à jour le profil', 'swiftboard')
                : esc_html_e('Mettre à jour l\'utilisateur', 'swiftboard');
            ?></button>
        </div>
    </fieldset>
</form>
