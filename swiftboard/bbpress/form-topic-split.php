<?php
if (!defined("ABSPATH")) exit;
// phpcs:ignore WordPress.Security.NonceVerification — nonces handled by bbPress core
/**
 * Template override : Scission (split) d'un sujet
 *
 * @package SwiftBoard
 */
?>

<div id="bbpress-forums" class="bbpress-wrapper">


    <?php if (is_user_logged_in() && current_user_can('edit_topic', bbp_get_topic_id())) : ?>

        <div id="split-topic-<?php bbp_topic_id(); ?>" class="bbp-topic-split">

            <form id="split_topic" name="split_topic" method="post"
                  role="form"
                  aria-label="<?php esc_attr_e('Scinder le sujet', 'swiftboard'); ?>">

                <fieldset class="bbp-form">

                    <legend><?php printf(esc_html__('Scinder le sujet « %s »', 'swiftboard'), bbp_get_topic_title()); ?></legend>

                    <div>

                        <div class="bbp-template-notice info">
                            <ul>
                                <li><?php esc_html_e('Scinder un sujet le coupe en deux à partir de la réponse sélectionnée. Vous pouvez en faire un nouveau sujet, ou fusionner ces réponses avec un sujet existant.', 'swiftboard'); ?></li>
                            </ul>
                        </div>

                        <div class="bbp-template-notice">
                            <ul>
                                <li><?php esc_html_e('Si vous choisissez l\'option « sujet existant », les réponses seront fusionnées chronologiquement selon leur date de publication.', 'swiftboard'); ?></li>
                            </ul>
                        </div>

                        <fieldset class="bbp-form">
                            <legend><?php esc_html_e('Méthode de scission', 'swiftboard'); ?></legend>

                            <div class="bbp-form-field">
                                <input name="bbp_topic_split_option" id="bbp_topic_split_option_reply" type="radio" checked="checked" value="reply">
                                <label for="bbp_topic_split_option_reply"><?php printf(esc_html__('Nouveau sujet dans %s intitulé :', 'swiftboard'), bbp_get_forum_title(bbp_get_topic_forum_id(bbp_get_topic_id()))); ?></label>
                                <input type="text" id="bbp_topic_split_destination_title" value="<?php printf(esc_html__('Scission : %s', 'swiftboard'), bbp_get_topic_title()); ?>" size="35" name="bbp_topic_split_destination_title">
                            </div>

                            <?php if (bbp_has_topics(['show_stickies' => false, 'post_parent' => bbp_get_topic_forum_id(bbp_get_topic_id()), 'post__not_in' => [bbp_get_topic_id()]])) : ?>

                                <div class="bbp-form-field">
                                    <input name="bbp_topic_split_option" id="bbp_topic_split_option_existing" type="radio" value="existing">
                                    <label for="bbp_topic_split_option_existing"><?php esc_html_e('Utiliser un sujet existant dans ce forum :', 'swiftboard'); ?></label>

                                    <?php
                                    bbp_dropdown([
                                        'post_type'   => bbp_get_topic_post_type(),
                                        'post_parent' => bbp_get_topic_forum_id(bbp_get_topic_id()),
                                        'post_status' => bbp_get_public_topic_statuses(),
                                        'selected'    => -1,
                                        'exclude'     => bbp_get_topic_id(),
                                        'select_id'   => 'bbp_destination_topic',
                                    ]);
                                    ?>

                                </div>

                            <?php endif; ?>

                        </fieldset>

                        <fieldset class="bbp-form">
                            <legend><?php esc_html_e('Options supplémentaires', 'swiftboard'); ?></legend>

                            <div class="bbp-form-checkbox">

                                <?php if (bbp_is_subscriptions_active()) : ?>

                                    <input name="bbp_topic_subscribers" id="bbp_topic_subscribers" type="checkbox" value="1" checked="checked">
                                    <label for="bbp_topic_subscribers"><?php esc_html_e('Copier les abonnés vers le nouveau sujet', 'swiftboard'); ?></label><br>

                                <?php endif; ?>

                                <input name="bbp_topic_favoriters" id="bbp_topic_favoriters" type="checkbox" value="1" checked="checked">
                                <label for="bbp_topic_favoriters"><?php esc_html_e('Copier les favoris vers le nouveau sujet', 'swiftboard'); ?></label><br>

                                <?php if (bbp_allow_topic_tags()) : ?>

                                    <input name="bbp_topic_tags" id="bbp_topic_tags" type="checkbox" value="1" checked="checked">
                                    <label for="bbp_topic_tags"><?php esc_html_e('Copier les mots-clés vers le nouveau sujet', 'swiftboard'); ?></label><br>

                                <?php endif; ?>

                            </div>
                        </fieldset>

                        <div class="bbp-template-notice error" role="alert" tabindex="-1">
                            <ul>
                                <li><?php esc_html_e('Cette action est irréversible.', 'swiftboard'); ?></li>
                            </ul>
                        </div>

                        <div class="bbp-submit-wrapper">
                            <button type="submit" id="bbp_merge_topic_submit" name="bbp_merge_topic_submit" class="btn-primary"><?php esc_html_e('Scinder', 'swiftboard'); ?></button>
                        </div>
                    </div>

                    <?php bbp_split_topic_form_fields(); ?>

                </fieldset>
            </form>
        </div>

    <?php else : ?>

        <div id="no-topic-<?php bbp_topic_id(); ?>" class="bbp-no-topic">
            <div class="bbp-template-notice error">
                <p><?php is_user_logged_in()
                    ? esc_html_e('Vous n\'avez pas la permission de modifier ce sujet.', 'swiftboard')
                    : esc_html_e('Vous ne pouvez pas modifier ce sujet.', 'swiftboard');
                ?></p>
            </div>
        </div>

    <?php endif; ?>

</div>

