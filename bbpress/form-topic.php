<?php
if (!defined("ABSPATH")) exit;
// phpcs:ignore WordPress.Security.NonceVerification — nonces handled by bbPress core
/**
 * Template override : Formulaire de création/édition de sujet
 *
 * Markup Reddit-style avec ARIA labels intégrés directement
 * (remplace l'output buffer qui cassait avec les plugins cache).
 *
 * @package SwiftBoard
 */
if (!bbp_is_single_forum()) : ?>
<div id="bbpress-forums" class="bbpress-wrapper">
<?php endif; ?>

<?php if (bbp_is_topic_edit()) :
    bbp_topic_tag_list(bbp_get_topic_id());
    bbp_single_topic_description(['topic_id' => bbp_get_topic_id()]);
    bbp_get_template_part('alert', 'topic-lock');
endif; ?>

<?php if (bbp_current_user_can_access_create_topic_form()) : ?>

    <div id="new-topic-<?php bbp_topic_id(); ?>" class="bbp-topic-form">
        <form id="new-post" name="new-post" method="post"
              role="form"
              aria-label="<?php esc_attr_e('Nouveau sujet de discussion', 'swiftboard'); ?>">

            <?php do_action('bbp_theme_before_topic_form'); ?>

            <fieldset class="bbp-form">
                <legend><?php
                    echo bbp_is_topic_edit()
                        ? esc_html__('Modifier le sujet', 'swiftboard')
                        : esc_html__('Créer un sujet', 'swiftboard');
                ?></legend>

                <?php do_action('bbp_theme_before_topic_form_notices'); ?>

                <?php if (!bbp_is_topic_edit() && bbp_is_forum_closed()) : ?>
                    <div class="bbp-template-notice warning">
                        <p><?php esc_html_e('Ce forum est marqué comme fermé aux nouveaux sujets.', 'swiftboard'); ?></p>
                    </div>
                <?php endif; ?>

                <?php do_action('bbp_template_notices'); ?>

                <div class="bbp-form-field">
                    <?php do_action('bbp_theme_before_topic_form_title'); ?>
                    <p>
                        <label for="bbp_topic_title"><?php esc_html_e('Titre du sujet', 'swiftboard'); ?> <span class="required" aria-hidden="true">*</span></label><br>
                        <input type="text" id="bbp_topic_title" value="<?php bbp_form_topic_title(); ?>"
                               tabindex="<?php bbp_tab_index(); ?>" size="40" name="bbp_topic_title"
                               maxlength="<?php bbp_title_max_length(); ?>"
                               required aria-required="true"
                               placeholder="<?php esc_attr_e('Décrivez votre question en une phrase…', 'swiftboard'); ?>">
                    </p>
                    <?php do_action('bbp_theme_after_topic_form_title'); ?>
                </div>

                <?php if ( ! bbp_is_single_forum() ) : ?>
                    <div class="bbp-form-field">
                        <p>
                            <label for="bbp_forum_id"><?php esc_html_e( 'Forum', 'swiftboard' ); ?> <span class="required" aria-hidden="true">*</span></label><br>
                            <?php bbp_dropdown( array( 'show_none' => esc_html__( '&mdash; Sans forum &mdash;', 'swiftboard' ), 'selected' => bbp_get_form_topic_forum() ) ); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if ( bbp_is_topic_edit() ) : ?>
                    <input type="hidden" name="bbp_forum_id" value="<?php bbp_form_topic_forum(); ?>" />
                <?php endif; ?>

                <?php if (!bbp_is_topic_edit() || current_user_can('edit_topic', bbp_get_topic_id())) : ?>
                    <div class="bbp-form-field">
                        <?php do_action('bbp_theme_before_topic_form_content'); ?>
                        <p>
                            <label for="bbp_topic_content"><?php esc_html_e('Message', 'swiftboard'); ?> <span class="required" aria-hidden="true">*</span></label><br>
                            <textarea id="bbp_topic_content" tabindex="<?php bbp_tab_index(); ?>" name="bbp_topic_content"
                                      cols="60" rows="6" required aria-required="true"
                                      placeholder="<?php esc_attr_e('Détaillez votre question…', 'swiftboard'); ?>"><?php bbp_form_topic_content(); ?></textarea>
                        </p>
                        <?php do_action('bbp_theme_after_topic_form_content'); ?>
                    </div>
                <?php endif; ?>

                <?php if (bbp_allow_topic_tags() && current_user_can('assign_topic_tags')) : ?>
                    <div class="bbp-form-field">
                        <?php do_action('bbp_theme_before_topic_form_tags'); ?>
                        <p>
                            <label for="bbp_topic_tags"><?php esc_html_e('Mots-clés', 'swiftboard'); ?></label><br>
                            <input type="text" id="bbp_topic_tags" value="<?php bbp_form_topic_tags(); ?>"
                                   tabindex="<?php bbp_tab_index(); ?>" size="40" name="bbp_topic_tags"
                                   placeholder="<?php esc_attr_e('Séparés par des virgules', 'swiftboard'); ?>">
                        </p>
                        <?php do_action('bbp_theme_after_topic_form_tags'); ?>
                    </div>
                <?php endif; ?>

                <?php if (!bbp_is_topic_edit() && bbp_is_subscriptions_active() && !bbp_is_anonymous()) : ?>
                    <div class="bbp-form-field bbp-form-checkbox">
                        <?php do_action('bbp_theme_before_topic_form_subscriptions'); ?>
                        <p>
                            <input type="checkbox" id="bbp_topic_subscription" name="bbp_topic_subscription"
                                   tabindex="<?php bbp_tab_index(); ?>"<?php bbp_form_topic_subscribed(); ?>>
                            <label for="bbp_topic_subscription"><?php esc_html_e('M\'avertir des nouvelles réponses par email', 'swiftboard'); ?></label>
                        </p>
                        <?php do_action('bbp_theme_after_topic_form_subscriptions'); ?>
                    </div>
                <?php endif; ?>

                <?php if (bbp_allow_revisions() && bbp_is_topic_edit()) : ?>
                    <div class="bbp-form-field bbp-form-checkbox">
                        <?php do_action('bbp_theme_before_topic_form_revisions'); ?>
                        <fieldset class="bbp-form">
                            <legend><?php esc_html_e('Journal des révisions', 'swiftboard'); ?></legend>
                            <div>
                                <input type="checkbox" id="bbp_log_topic_edit" name="bbp_log_topic_edit" value="1"
                                       tabindex="<?php bbp_tab_index(); ?>"<?php bbp_form_topic_log_edit(); ?>>
                                <label for="bbp_log_topic_edit"><?php esc_html_e('Garder un journal de cette modification', 'swiftboard'); ?></label>
                            </div>
                            <p>
                                <label for="bbp_topic_edit_reason"><?php esc_html_e('Raison (optionnel)', 'swiftboard'); ?></label><br>
                                <input type="text" id="bbp_topic_edit_reason" value="<?php bbp_form_topic_edit_reason(); ?>"
                                       tabindex="<?php bbp_tab_index(); ?>" size="40" name="bbp_topic_edit_reason">
                            </p>
                        </fieldset>
                        <?php do_action('bbp_theme_after_topic_form_revisions'); ?>
                    </div>
                <?php endif; ?>

                <?php do_action('bbp_theme_before_topic_form_submit_wrapper'); ?>

                <div class="bbp-submit-wrapper">
                    <?php do_action('bbp_theme_before_topic_form_submit_button'); ?>
                    <button type="submit" id="bbp_topic_submit" name="bbp_topic_submit" tabindex="<?php bbp_tab_index(); ?>"
                            class="btn-primary">
                        <?php echo bbp_is_topic_edit()
                            ? esc_html__('Mettre à jour', 'swiftboard')
                            : esc_html__('Publier le sujet', 'swiftboard'); ?>
                    </button>
                    <?php do_action('bbp_theme_after_topic_form_submit_button'); ?>
                </div>

                <?php do_action('bbp_theme_after_topic_form_submit_wrapper'); ?>

            </fieldset>

            <?php bbp_topic_form_fields(); ?>

            <?php do_action('bbp_theme_after_topic_form'); ?>
        </form>
    </div>

<?php endif; ?>
<?php
// Meme branche morte que dans form-reply.php : la condition reprenait
// bbp_current_user_can_access_create_topic_form(), deja fausse dans le `else`.
// content-single-forum.php rend deja le message equivalent avec son lien de
// connexion.
?>

<?php if (!bbp_is_single_forum()) : ?>
</div>
<?php endif;

