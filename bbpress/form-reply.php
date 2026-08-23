<?php
if (!defined("ABSPATH")) exit;
// phpcs:ignore WordPress.Security.NonceVerification — nonces handled by bbPress core
/**
 * Template override : Formulaire de création/édition de réponse
 *
 * Markup Reddit-style avec ARIA labels intégrés.
 *
 * @package SwiftBoard
 */
if (bbp_is_reply_edit()) : ?>
<div id="bbpress-forums" class="bbpress-wrapper">
<?php endif; ?>

<?php if (bbp_current_user_can_access_create_reply_form()) : ?>

    <div id="new-reply-<?php bbp_topic_id(); ?>" class="bbp-reply-form">
        <form id="new-post" name="new-post" method="post"
              role="form"
              aria-label="<?php esc_attr_e('Répondre à la discussion', 'swiftboard'); ?>">

            <?php do_action('bbp_theme_before_reply_form'); ?>

            <fieldset class="bbp-form">
                <legend><?php
                    printf(
                        /* translators: %s = topic title */
                        esc_html__('Répondre à : %s', 'swiftboard'),
                        '<strong>' . esc_html(bbp_get_topic_title()) . '</strong>'
                    );
                ?></legend>

                <?php do_action('bbp_theme_before_reply_form_notices'); ?>

                <?php if (!bbp_is_topic_open() && !bbp_is_reply_edit()) : ?>
                    <div class="bbp-template-notice warning">
                        <p><?php esc_html_e('Ce sujet est marqué comme fermé aux nouvelles réponses.', 'swiftboard'); ?></p>
                    </div>
                <?php endif; ?>

                <?php do_action('bbp_template_notices'); ?>

                <div class="bbp-form-field">
                    <?php do_action('bbp_theme_before_reply_form_content'); ?>
                    <p>
                        <label for="bbp_reply_content"><?php esc_html_e('Votre réponse', 'swiftboard'); ?> <span class="required" aria-hidden="true">*</span></label><br>
                        <textarea id="bbp_reply_content" tabindex="<?php bbp_tab_index(); ?>" name="bbp_reply_content"
                                  cols="60" rows="6" required aria-required="true"
                                  placeholder="<?php esc_attr_e('Rédigez votre réponse…', 'swiftboard'); ?>"><?php bbp_form_reply_content(); ?></textarea>
                    </p>
                    <?php do_action('bbp_theme_after_reply_form_content'); ?>
                </div>

                <?php if (bbp_allow_revisions() && bbp_is_reply_edit()) : ?>
                    <div class="bbp-form-field bbp-form-checkbox">
                        <?php do_action('bbp_theme_before_reply_form_revisions'); ?>
                        <fieldset class="bbp-form">
                            <legend><?php esc_html_e('Journal des révisions', 'swiftboard'); ?></legend>
                            <div>
                                <input type="checkbox" id="bbp_log_reply_edit" name="bbp_log_reply_edit" value="1"
                                       tabindex="<?php bbp_tab_index(); ?>"<?php bbp_form_reply_log_edit(); ?>>
                                <label for="bbp_log_reply_edit"><?php esc_html_e('Garder un journal de cette modification', 'swiftboard'); ?></label>
                            </div>
                            <p>
                                <label for="bbp_reply_edit_reason"><?php esc_html_e('Raison (optionnel)', 'swiftboard'); ?></label><br>
                                <input type="text" id="bbp_reply_edit_reason" value="<?php bbp_form_reply_edit_reason(); ?>"
                                       tabindex="<?php bbp_tab_index(); ?>" size="40" name="bbp_reply_edit_reason">
                            </p>
                        </fieldset>
                        <?php do_action('bbp_theme_after_reply_form_revisions'); ?>
                    </div>
                <?php endif; ?>

                <?php if (bbp_is_subscriptions_active() && !bbp_is_anonymous()) : ?>
                    <div class="bbp-form-field bbp-form-checkbox">
                        <?php do_action('bbp_theme_before_reply_form_subscription'); ?>
                        <p>
                            <input type="checkbox" id="bbp_topic_subscription" name="bbp_topic_subscription"
                                   tabindex="<?php bbp_tab_index(); ?>"<?php bbp_form_topic_subscribed(); ?>>
                            <label for="bbp_topic_subscription"><?php esc_html_e('M\'avertir des nouvelles réponses par email', 'swiftboard'); ?></label>
                        </p>
                        <?php do_action('bbp_theme_after_reply_form_subscription'); ?>
                    </div>
                <?php endif; ?>

                <?php do_action('bbp_theme_before_reply_form_submit_wrapper'); ?>

                <div class="bbp-submit-wrapper">
                    <?php do_action('bbp_theme_before_reply_form_submit_button'); ?>
                    <button type="submit" id="bbp_reply_submit" name="bbp_reply_submit" tabindex="<?php bbp_tab_index(); ?>"
                            class="btn-primary">
                        <?php echo bbp_is_reply_edit()
                            ? esc_html__('Mettre à jour', 'swiftboard')
                            : esc_html__('Publier la réponse', 'swiftboard'); ?>
                    </button>
                    <?php do_action('bbp_theme_after_reply_form_submit_button'); ?>
                </div>

                <?php do_action('bbp_theme_after_reply_form_submit_wrapper'); ?>

            </fieldset>

            <?php bbp_reply_form_fields(); ?>

            <?php do_action('bbp_theme_after_reply_form'); ?>
        </form>
    </div>

<?php endif; ?>
<?php
// Branche morte retiree (EXI-ARCH-04, detectee par PHPStan) : le `elseif`
// reprenait bbp_current_user_can_access_create_reply_form(), deja fausse a ce
// point du `if` — le message ne s'est jamais affiche.
//
// Il etait de toute facon redondant : content-single-topic.php rend deja
// « Vous devez vous connecter pour repondre a ce sujet. » AVEC le lien vers
// wp-login.php et le retour sur le sujet. Verifie en anonyme sur WordPress
// reel : le message est bien present sur /forums/topic/<slug>/.
?>

<?php if (bbp_is_reply_edit()) : ?>
</div>
<?php endif;

