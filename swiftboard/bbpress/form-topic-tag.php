<?php
if (!defined("ABSPATH")) exit;
// phpcs:ignore WordPress.Security.NonceVerification — nonces handled by bbPress core
/**
 * Template override : Gestion d'un mot-clé (tag) de sujet
 *
 * Trois formulaires : renommage, fusion et suppression.
 *
 * @package SwiftBoard
 */
if (current_user_can('edit_topic_tags')) : ?>

    <div id="edit-topic-tag-<?php bbp_topic_tag_id(); ?>" class="bbp-topic-tag-form">

        <fieldset class="bbp-form" id="bbp-edit-topic-tag">

            <legend><?php printf(esc_html__('Gérer le mot-clé « %s »', 'swiftboard'), bbp_get_topic_tag_name()); ?></legend>

            <fieldset class="bbp-form" id="tag-rename">

                <legend><?php esc_html_e('Renommer', 'swiftboard'); ?></legend>

                <div class="bbp-template-notice info">
                    <ul>
                        <li><?php esc_html_e('Laissez le slug vide pour en générer un automatiquement.', 'swiftboard'); ?></li>
                    </ul>
                </div>

                <div class="bbp-template-notice">
                    <ul>
                        <li><?php esc_html_e('Modifier le slug change son permalien. Les liens vers l\'ancien slug cesseront de fonctionner.', 'swiftboard'); ?></li>
                    </ul>
                </div>

                <form id="rename_tag" name="rename_tag" method="post"
                      role="form"
                      aria-label="<?php esc_attr_e('Renommer le mot-clé', 'swiftboard'); ?>">

                    <div class="bbp-form-field">
                        <label for="tag-name"><?php esc_html_e('Nom :', 'swiftboard'); ?></label>
                        <input type="text" id="tag-name" name="tag-name" size="20" maxlength="40" value="<?php echo esc_attr(bbp_get_topic_tag_name()); ?>">
                    </div>

                    <div class="bbp-form-field">
                        <label for="tag-slug"><?php esc_html_e('Slug :', 'swiftboard'); ?></label>
                        <input type="text" id="tag-slug" name="tag-slug" size="20" maxlength="40" value="<?php echo esc_attr(apply_filters('editable_slug', bbp_get_topic_tag_slug())); ?>">
                    </div>

                    <div class="bbp-form-field">
                        <label for="tag-description"><?php esc_html_e('Description :', 'swiftboard'); ?></label>
                        <input type="text" id="tag-description" name="tag-description" size="20" value="<?php echo esc_attr(bbp_get_topic_tag_description(['before' => '', 'after' => ''])); ?>">
                    </div>

                    <div class="bbp-submit-wrapper">
                        <button type="submit" class="btn-primary"><?php esc_html_e('Mettre à jour', 'swiftboard'); ?></button>

                        <input type="hidden" name="tag-id" value="<?php bbp_topic_tag_id(); ?>">
                        <input type="hidden" name="action" value="bbp-update-topic-tag">

                        <?php wp_nonce_field('update-tag_' . bbp_get_topic_tag_id()); ?>

                    </div>
                </form>

            </fieldset>

            <fieldset class="bbp-form" id="tag-merge">

                <legend><?php esc_html_e('Fusionner', 'swiftboard'); ?></legend>

                <div class="bbp-template-notice warning">
                    <ul>
                        <li><?php esc_html_e('La fusion de mots-clés est irréversible.', 'swiftboard'); ?></li>
                    </ul>
                </div>

                <form id="merge_tag" name="merge_tag" method="post"
                      role="form"
                      aria-label="<?php esc_attr_e('Fusionner le mot-clé', 'swiftboard'); ?>">

                    <div class="bbp-form-field">
                        <label for="tag-existing-name"><?php esc_html_e('Mot-clé existant :', 'swiftboard'); ?></label>
                        <input type="text" id="tag-existing-name" name="tag-existing-name" size="22" maxlength="40">
                    </div>

                    <div class="bbp-submit-wrapper">
                        <button type="submit" class="btn-secondary" data-confirm="<?php echo esc_attr(sprintf(__('Voulez-vous vraiment fusionner le mot-clé « %s » dans celui spécifié ?', 'swiftboard'), bbp_get_topic_tag_name())); ?>"><?php esc_html_e('Fusionner', 'swiftboard'); ?></button>

                        <input type="hidden" name="tag-id" value="<?php bbp_topic_tag_id(); ?>">
                        <input type="hidden" name="action" value="bbp-merge-topic-tag">

                        <?php wp_nonce_field('merge-tag_' . bbp_get_topic_tag_id()); ?>
                    </div>
                </form>

            </fieldset>

            <?php if (current_user_can('delete_topic_tags')) : ?>

                <fieldset class="bbp-form" id="delete-tag">

                    <legend><?php esc_html_e('Supprimer', 'swiftboard'); ?></legend>

                    <div class="bbp-template-notice info">
                        <ul>
                            <li><?php esc_html_e('Cela ne supprime pas vos sujets. Seul le mot-clé est supprimé.', 'swiftboard'); ?></li>
                        </ul>
                    </div>
                    <div class="bbp-template-notice warning">
                        <ul>
                            <li><?php esc_html_e('La suppression d\'un mot-clé est définitive.', 'swiftboard'); ?></li>
                            <li><?php esc_html_e('Tous les liens pointant vers ce mot-clé cesseront de fonctionner.', 'swiftboard'); ?></li>
                        </ul>
                    </div>

                    <form id="delete_tag" name="delete_tag" method="post"
                          role="form"
                          aria-label="<?php esc_attr_e('Supprimer le mot-clé', 'swiftboard'); ?>">

                        <div class="bbp-submit-wrapper">
                            <button type="submit" class="btn-secondary" data-confirm="<?php echo esc_attr(sprintf(__('Voulez-vous vraiment supprimer le mot-clé « %s » ? Cette action est définitive.', 'swiftboard'), bbp_get_topic_tag_name())); ?>"><?php esc_html_e('Supprimer', 'swiftboard'); ?></button>

                            <input type="hidden" name="tag-id" value="<?php bbp_topic_tag_id(); ?>">
                            <input type="hidden" name="action" value="bbp-delete-topic-tag">

                            <?php wp_nonce_field('delete-tag_' . bbp_get_topic_tag_id()); ?>
                        </div>
                    </form>

                </fieldset>

            <?php endif; ?>

        </fieldset>
    </div>

<?php endif;

