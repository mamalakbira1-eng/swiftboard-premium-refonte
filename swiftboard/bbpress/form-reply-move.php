<?php
if (!defined("ABSPATH")) exit;
// phpcs:ignore WordPress.Security.NonceVerification — nonces handled by bbPress core
/**
 * Template override : Déplacement d'une réponse
 *
 * Permet de transformer une réponse en nouveau sujet ou de la
 * fusionner avec un sujet existant.
 *
 * @package SwiftBoard
 */
?>

<div id="bbpress-forums" class="bbpress-wrapper">


    <?php if (is_user_logged_in() && current_user_can('edit_topic', bbp_get_topic_id())) : ?>

        <div id="move-reply-<?php bbp_topic_id(); ?>" class="bbp-reply-move">

            <form id="move_reply" name="move_reply" method="post"
                  role="form"
                  aria-label="<?php esc_attr_e('Déplacer la réponse', 'swiftboard'); ?>">

                <fieldset class="bbp-form">

                    <legend><?php printf(esc_html__('Déplacer la réponse « %s »', 'swiftboard'), bbp_get_reply_title()); ?></legend>

                    <div>

                        <div class="bbp-template-notice info">
                            <ul>
                                <li><?php esc_html_e('Vous pouvez transformer cette réponse en nouveau sujet, ou la fusionner dans un sujet existant.', 'swiftboard'); ?></li>
                            </ul>
                        </div>

                        <div class="bbp-template-notice">
                            <ul>
                                <li><?php esc_html_e('Si vous choisissez un sujet existant, les réponses seront ordonnées par leur date de création.', 'swiftboard'); ?></li>
                            </ul>
                        </div>

                        <fieldset class="bbp-form">
                            <legend><?php esc_html_e('Méthode de déplacement', 'swiftboard'); ?></legend>

                            <div class="bbp-form-field">
                                <input name="bbp_reply_move_option" id="bbp_reply_move_option_reply" type="radio" checked="checked" value="topic">
                                <label for="bbp_reply_move_option_reply"><?php printf(esc_html__('Nouveau sujet dans %s intitulé :', 'swiftboard'), bbp_get_forum_title(bbp_get_reply_forum_id(bbp_get_reply_id()))); ?></label>
                                <input type="text" id="bbp_reply_move_destination_title" value="<?php printf(esc_html__('Déplacé : %s', 'swiftboard'), bbp_get_reply_title()); ?>" size="35" name="bbp_reply_move_destination_title">
                            </div>

                            <?php if (bbp_has_topics(['show_stickies' => false, 'post_parent' => bbp_get_reply_forum_id(bbp_get_reply_id()), 'post__not_in' => [bbp_get_reply_topic_id(bbp_get_reply_id())]])) : ?>

                                <div class="bbp-form-field">
                                    <input name="bbp_reply_move_option" id="bbp_reply_move_option_existing" type="radio" value="existing">
                                    <label for="bbp_reply_move_option_existing"><?php esc_html_e('Utiliser un sujet existant dans ce forum :', 'swiftboard'); ?></label>

                                    <?php
                                    bbp_dropdown([
                                        'post_type'   => bbp_get_topic_post_type(),
                                        'post_parent' => bbp_get_reply_forum_id(bbp_get_reply_id()),
                                        'selected'    => -1,
                                        'exclude'     => bbp_get_reply_topic_id(bbp_get_reply_id()),
                                        'select_id'   => 'bbp_destination_topic',
                                    ]);
                                    ?>

                                </div>

                            <?php endif; ?>

                        </fieldset>

                        <div class="bbp-template-notice error" role="alert" tabindex="-1">
                            <ul>
                                <li><?php esc_html_e('Cette action est irréversible.', 'swiftboard'); ?></li>
                            </ul>
                        </div>

                        <div class="bbp-submit-wrapper">
                            <button type="submit" id="bbp_move_reply_submit" name="bbp_move_reply_submit" class="btn-primary"><?php esc_html_e('Déplacer', 'swiftboard'); ?></button>
                        </div>
                    </div>

                    <?php bbp_move_reply_form_fields(); ?>

                </fieldset>
            </form>
        </div>

    <?php else : ?>

        <div id="no-reply-<?php bbp_reply_id(); ?>" class="bbp-no-reply">
            <div class="bbp-template-notice error">
                <p><?php is_user_logged_in()
                    ? esc_html_e('Vous n\'avez pas la permission de modifier cette réponse.', 'swiftboard')
                    : esc_html_e('Vous ne pouvez pas modifier cette réponse.', 'swiftboard');
                ?></p>
            </div>
        </div>

    <?php endif; ?>

</div>

