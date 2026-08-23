<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Détails utilisateur (avatar + navigation)
 *
 * Affiché en en-tête de la page de profil utilisateur bbPress.
 *
 * @package SwiftBoard
 */
do_action('bbp_template_before_user_details'); ?>

<div id="bbp-single-user-details">
    <div id="bbp-user-avatar">
        <span class='vcard'>
            <a class="url fn n" href="<?php bbp_user_profile_url(); ?>" title="<?php bbp_displayed_user_field('display_name'); ?>" rel="me">
                <?php echo swiftboard_get_avatar(bbp_get_displayed_user_id(), 96); ?>
            </a>
        </span>
        <?php if (function_exists('swiftboard_display_grade_badge')) swiftboard_display_grade_badge(bbp_get_displayed_user_id()); ?>
    </div>

    <?php do_action('bbp_template_before_user_details_menu_items'); ?>

    <div id="bbp-user-navigation">
        <ul>
            <li class="<?php if (bbp_is_single_user_profile()) : ?>current<?php endif; ?>">
                <span class="vcard bbp-user-profile-link">
                    <a class="url fn n" href="<?php bbp_user_profile_url(); ?>" title="<?php printf(esc_attr__('Profil de %s', 'swiftboard'), bbp_get_displayed_user_field('display_name')); ?>" rel="me"><?php esc_html_e('Profil', 'swiftboard'); ?></a>
                </span>
            </li>

            <li class="<?php if (bbp_is_single_user_topics()) : ?>current<?php endif; ?>">
                <span class='bbp-user-topics-created-link'>
                    <a href="<?php bbp_user_topics_created_url(); ?>" title="<?php printf(esc_attr__('Sujets créés par %s', 'swiftboard'), bbp_get_displayed_user_field('display_name')); ?>"><?php esc_html_e('Sujets créés', 'swiftboard'); ?></a>
                </span>
            </li>

            <li class="<?php if (bbp_is_single_user_replies()) : ?>current<?php endif; ?>">
                <span class='bbp-user-replies-created-link'>
                    <a href="<?php bbp_user_replies_created_url(); ?>" title="<?php printf(esc_attr__('Réponses créées par %s', 'swiftboard'), bbp_get_displayed_user_field('display_name')); ?>"><?php esc_html_e('Réponses créées', 'swiftboard'); ?></a>
                </span>
            </li>

            <?php if (bbp_is_engagements_active()) : ?>
                <li class="<?php if (bbp_is_single_user_engagements()) : ?>current<?php endif; ?>">
                    <span class='bbp-user-engagements-created-link'>
                        <a href="<?php bbp_user_engagements_url(); ?>" title="<?php printf(esc_attr__('Participations de %s', 'swiftboard'), bbp_get_displayed_user_field('display_name')); ?>"><?php esc_html_e('Participations', 'swiftboard'); ?></a>
                    </span>
                </li>
            <?php endif; ?>

            <?php if (bbp_is_favorites_active()) : ?>
                <li class="<?php if (bbp_is_favorites()) : ?>current<?php endif; ?>">
                    <span class="bbp-user-favorites-link">
                        <a href="<?php bbp_favorites_permalink(); ?>" title="<?php printf(esc_attr__('Favoris de %s', 'swiftboard'), bbp_get_displayed_user_field('display_name')); ?>"><?php esc_html_e('Favoris', 'swiftboard'); ?></a>
                    </span>
                </li>
            <?php endif; ?>

            <?php if (bbp_is_user_home() || current_user_can('edit_user', bbp_get_displayed_user_id())) : ?>

                <?php if (bbp_is_subscriptions_active()) : ?>
                    <li class="<?php if (bbp_is_subscriptions()) : ?>current<?php endif; ?>">
                        <span class="bbp-user-subscriptions-link">
                            <a href="<?php bbp_subscriptions_permalink(); ?>" title="<?php printf(esc_attr__('Abonnements de %s', 'swiftboard'), bbp_get_displayed_user_field('display_name')); ?>"><?php esc_html_e('Abonnements', 'swiftboard'); ?></a>
                        </span>
                    </li>
                <?php endif; ?>

                <li class="<?php if (bbp_is_single_user_edit()) : ?>current<?php endif; ?>">
                    <span class="bbp-user-edit-link">
                        <a href="<?php bbp_user_profile_edit_url(); ?>" title="<?php printf(esc_attr__('Modifier le profil de %s', 'swiftboard'), bbp_get_displayed_user_field('display_name')); ?>"><?php esc_html_e('Modifier', 'swiftboard'); ?></a>
                    </span>
                </li>

            <?php endif; ?>

        </ul>

        <?php do_action('bbp_template_after_user_details_menu_items'); ?>

    </div>
</div>

<?php do_action('bbp_template_after_user_details');

