<?php
if (!defined("ABSPATH")) exit;
// phpcs:ignore WordPress.Security.NonceVerification — nonces handled by bbPress core
/**
 * Template override : Création / édition de forum
 *
 * @package SwiftBoard
 */
if (bbp_is_forum_edit()) : ?>

<div id="bbpress-forums" class="bbpress-wrapper">


    <?php bbp_single_forum_description(['forum_id' => bbp_get_forum_id()]); ?>

<?php endif; ?>

<?php if (bbp_current_user_can_access_create_forum_form()) : ?>

    <div id="new-forum-<?php bbp_forum_id(); ?>" class="bbp-forum-form">

        <form id="new-post" name="new-post" method="post"
              role="form"
              aria-label="<?php esc_attr_e('Nouveau forum', 'swiftboard'); ?>">

            <?php do_action('bbp_theme_before_forum_form'); ?>

            <fieldset class="bbp-form">
                <legend>

                    <?php
                    if (bbp_is_forum_edit()) :
                        printf(esc_html__('Modification de « %s »', 'swiftboard'), bbp_get_forum_title());
                    else :
                        bbp_is_single_forum()
                            ? printf(esc_html__('Créer un forum dans « %s »', 'swiftboard'), bbp_get_forum_title())
                            : esc_html_e('Créer un nouveau forum', 'swiftboard');
                    endif;
                    ?>

                </legend>

                <?php do_action('bbp_theme_before_forum_form_notices'); ?>

                <?php if (!bbp_is_forum_edit() && bbp_is_forum_closed()) : ?>

                    <div class="bbp-template-notice warning">
                        <ul>
                            <li><?php esc_html_e('Ce forum est fermé aux nouveaux contenus, mais vos permissions vous permettent tout de même de publier.', 'swiftboard'); ?></li>
                        </ul>
                    </div>

                <?php endif; ?>

                <?php if (current_user_can('unfiltered_html')) : ?>

                    <div class="bbp-template-notice info">
                        <ul>
                            <li><?php esc_html_e('Votre compte peut publier du contenu HTML sans restriction.', 'swiftboard'); ?></li>
                        </ul>
                    </div>

                <?php endif; ?>

                <?php do_action('bbp_template_notices'); ?>

                <div>

                    <?php do_action('bbp_theme_before_forum_form_title'); ?>

                    <div class="bbp-form-field">
                        <label for="bbp_forum_title"><?php printf(esc_html__('Nom du forum (longueur max : %d) :', 'swiftboard'), bbp_get_title_max_length()); ?></label><br>
                        <input type="text" id="bbp_forum_title" value="<?php bbp_form_forum_title(); ?>" size="40" name="bbp_forum_title" maxlength="<?php bbp_title_max_length(); ?>">
                    </div>

                    <?php do_action('bbp_theme_after_forum_form_title'); ?>

                    <?php do_action('bbp_theme_before_forum_form_content'); ?>

                    <?php bbp_the_content(['context' => 'forum']); ?>

                    <?php do_action('bbp_theme_after_forum_form_content'); ?>

                    <?php bbp_get_template_part('form', 'allowed-tags'); ?>

                    <?php if (bbp_allow_forum_mods() && current_user_can('assign_moderators')) : ?>

                        <?php do_action('bbp_theme_before_forum_form_mods'); ?>

                        <div class="bbp-form-field">
                            <label for="bbp_moderators"><?php esc_html_e('Modérateurs du forum :', 'swiftboard'); ?></label><br>
                            <input type="text" value="<?php bbp_form_forum_moderators(); ?>" size="40" name="bbp_moderators" id="bbp_moderators">
                        </div>

                        <?php do_action('bbp_theme_after_forum_form_mods'); ?>

                    <?php endif; ?>

                    <?php do_action('bbp_theme_before_forum_form_type'); ?>

                    <div class="bbp-form-field">
                        <label for="bbp_forum_type"><?php esc_html_e('Type de forum :', 'swiftboard'); ?></label><br>
                        <?php bbp_form_forum_type_dropdown(); ?>
                    </div>

                    <?php do_action('bbp_theme_after_forum_form_type'); ?>

                    <?php do_action('bbp_theme_before_forum_form_status'); ?>

                    <div class="bbp-form-field">
                        <label for="bbp_forum_status"><?php esc_html_e('Statut :', 'swiftboard'); ?></label><br>
                        <?php bbp_form_forum_status_dropdown(); ?>
                    </div>

                    <?php do_action('bbp_theme_after_forum_form_status'); ?>

                    <?php do_action('bbp_theme_before_forum_visibility_status'); ?>

                    <div class="bbp-form-field">
                        <label for="bbp_forum_visibility"><?php esc_html_e('Visibilité :', 'swiftboard'); ?></label><br>
                        <?php bbp_form_forum_visibility_dropdown(); ?>
                    </div>

                    <?php do_action('bbp_theme_after_forum_visibility_status'); ?>

                    <?php do_action('bbp_theme_before_forum_form_parent'); ?>

                    <div class="bbp-form-field">
                        <label for="bbp_forum_parent_id"><?php esc_html_e('Forum parent :', 'swiftboard'); ?></label><br>

                        <?php
                        bbp_dropdown([
                            'select_id' => 'bbp_forum_parent_id',
                            'show_none' => esc_html__('— Aucun parent —', 'swiftboard'),
                            'selected'  => bbp_get_form_forum_parent(),
                            'exclude'   => bbp_get_forum_id(),
                        ]);
                        ?>
                    </div>

                    <?php do_action('bbp_theme_after_forum_form_parent'); ?>

                    <?php do_action('bbp_theme_before_forum_form_submit_wrapper'); ?>

                    <div class="bbp-submit-wrapper">

                        <?php do_action('bbp_theme_before_forum_form_submit_button'); ?>

                        <button type="submit" id="bbp_forum_submit" name="bbp_forum_submit" class="btn-primary"><?php esc_html_e('Créer le forum', 'swiftboard'); ?></button>

                        <?php do_action('bbp_theme_after_forum_form_submit_button'); ?>

                    </div>

                    <?php do_action('bbp_theme_after_forum_form_submit_wrapper'); ?>

                </div>

                <?php bbp_forum_form_fields(); ?>

            </fieldset>

            <?php do_action('bbp_theme_after_forum_form'); ?>

        </form>
    </div>

<?php elseif (bbp_is_forum_closed()) : ?>

    <div id="no-forum-<?php bbp_forum_id(); ?>" class="bbp-no-forum">
        <div class="bbp-template-notice error">
            <ul>
                <li><?php printf(esc_html__('Le forum « %s » est fermé aux nouveaux contenus.', 'swiftboard'), bbp_get_forum_title()); ?></li>
            </ul>
        </div>
    </div>

<?php else : ?>

    <div id="no-forum-<?php bbp_forum_id(); ?>" class="bbp-no-forum">
        <div class="bbp-template-notice info">
            <ul>
                <li><?php is_user_logged_in()
                    ? esc_html_e('Vous ne pouvez pas créer de nouveaux forums.', 'swiftboard')
                    : esc_html_e('Vous devez être connecté pour créer de nouveaux forums.', 'swiftboard');
                ?></li>
            </ul>
        </div>
    </div>

<?php endif; ?>

<?php if (bbp_is_forum_edit()) : ?>

</div>

<?php endif;

