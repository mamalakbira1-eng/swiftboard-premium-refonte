<?php
if (!defined("ABSPATH")) exit;
// phpcs:ignore WordPress.Security.NonceVerification — nonces handled by bbPress core
/**
 * Template override : Fusion de deux sujets
 *
 * @package SwiftBoard
 */
?>

<div id="bbpress-forums" class="bbpress-wrapper">


    <?php if (is_user_logged_in() && current_user_can('edit_topic', bbp_get_topic_id())) : ?>

        <div id="merge-topic-<?php bbp_topic_id(); ?>" class="bbp-topic-merge">

            <form id="merge_topic" name="merge_topic" method="post"
                  role="form"
                  aria-label="<?php esc_attr_e('Fusionner le sujet', 'swiftboard'); ?>">

                <fieldset class="bbp-form">

                    <legend><?php printf(esc_html__('Fusionner le sujet « %s »', 'swiftboard'), bbp_get_topic_title()); ?></legend>

                    <div>

                        <div class="bbp-template-notice info">
                            <ul>
                                <li><?php esc_html_e('Sélectionnez le sujet avec lequel fusionner. Le sujet de destination restera le sujet principal, et celui-ci deviendra une réponse.', 'swiftboard'); ?></li>
                                <li><?php esc_html_e('Pour conserver ce sujet comme sujet principal, ouvrez l\'autre sujet et utilisez l\'outil de fusion depuis celui-ci.', 'swiftboard'); ?></li>
                            </ul>
                        </div>

                        <div class="bbp-template-notice">
                            <ul>
                                <li><?php esc_html_e('Les réponses des deux sujets sont fusionnées chronologiquement, selon leur date de publication. Les sujets peuvent être décalés d\'une seconde pour maintenir l\'ordre chronologique.', 'swiftboard'); ?></li>
                            </ul>
                        </div>

                        <fieldset class="bbp-form">
                            <legend><?php esc_html_e('Destination', 'swiftboard'); ?></legend>
                            <div>

                                <?php if (bbp_has_topics(['show_stickies' => false, 'post_parent' => bbp_get_topic_forum_id(bbp_get_topic_id()), 'post__not_in' => [bbp_get_topic_id()]])) : ?>

                                    <label for="bbp_destination_topic"><?php esc_html_e('Fusionner avec ce sujet :', 'swiftboard'); ?></label>

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

                                <?php else : ?>

                                    <label><?php esc_html_e('Il n\'y a aucun autre sujet dans ce forum avec lequel fusionner.', 'swiftboard'); ?></label>

                                <?php endif; ?>

                            </div>
                        </fieldset>

                        <fieldset class="bbp-form">
                            <legend><?php esc_html_e('Options supplémentaires', 'swiftboard'); ?></legend>

                            <div class="bbp-form-checkbox">

                                <?php if (bbp_is_subscriptions_active()) : ?>

                                    <input name="bbp_topic_subscribers" id="bbp_topic_subscribers" type="checkbox" value="1" checked="checked">
                                    <label for="bbp_topic_subscribers"><?php esc_html_e('Fusionner les abonnés au sujet', 'swiftboard'); ?></label><br>

                                <?php endif; ?>

                                <input name="bbp_topic_favoriters" id="bbp_topic_favoriters" type="checkbox" value="1" checked="checked">
                                <label for="bbp_topic_favoriters"><?php esc_html_e('Fusionner les favoris', 'swiftboard'); ?></label><br>

                                <?php if (bbp_allow_topic_tags()) : ?>

                                    <input name="bbp_topic_tags" id="bbp_topic_tags" type="checkbox" value="1" checked="checked">
                                    <label for="bbp_topic_tags"><?php esc_html_e('Fusionner les mots-clés', 'swiftboard'); ?></label><br>

                                <?php endif; ?>

                            </div>
                        </fieldset>

                        <div class="bbp-template-notice error" role="alert">
                            <ul>
                                <li><?php esc_html_e('Cette action est irréversible.', 'swiftboard'); ?></li>
                            </ul>
                        </div>

                        <div class="bbp-submit-wrapper">
                            <button type="submit" id="bbp_merge_topic_submit" name="bbp_merge_topic_submit" class="btn-primary"><?php esc_html_e('Fusionner', 'swiftboard'); ?></button>
                        </div>
                    </div>

                    <?php bbp_merge_topic_form_fields(); ?>

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

