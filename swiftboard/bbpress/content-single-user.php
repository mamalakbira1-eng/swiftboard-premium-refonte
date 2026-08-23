<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Profil utilisateur
 *
 * @package SwiftBoard
 */
?>

<div id="bbpress-forums" class="bbpress-wrapper">

    <?php do_action('bbp_template_notices'); ?>

    <?php do_action('bbp_template_before_user_wrapper'); ?>

    <div id="bbp-user-wrapper" class="user-profile-wrapper">

        <header class="user-profile-header">
            <div class="user-profile-avatar">
                <?php echo swiftboard_get_avatar(bbp_get_displayed_user_id(), 96); ?>
            </div>
            <div class="user-profile-info">
                <h1 class="user-profile-name" itemprop="name"><?php echo esc_html(bbp_get_displayed_user_field('display_name')); ?></h1>
                <p class="user-profile-handle">@<?php echo esc_html(bbp_get_displayed_user_field('user_nicename')); ?></p>
                <?php if (function_exists('swiftboard_display_grade_badge')) swiftboard_display_grade_badge(bbp_get_displayed_user_id()); ?>
                <?php if (bbp_get_displayed_user_field('description')) : ?>
                    <p class="user-profile-bio"><?php echo wp_kses_post(bbp_get_displayed_user_field('description')); ?></p>
                <?php endif; ?>

                <div class="user-profile-stats">
                    <div class="user-stat">
                        <strong><?php echo esc_html((string) bbp_get_user_topic_count_raw(bbp_get_displayed_user_id())); ?></strong>
                        <span><?php esc_html_e('sujets', 'swiftboard'); ?></span>
                    </div>
                    <div class="user-stat">
                        <strong><?php echo esc_html((string) bbp_get_user_reply_count_raw(bbp_get_displayed_user_id())); ?></strong>
                        <span><?php esc_html_e('réponses', 'swiftboard'); ?></span>
                    </div>
                    <div class="user-stat">
                        <strong><?php echo esc_html(date_i18n(get_option('date_format'), strtotime(bbp_get_displayed_user_field('user_registered')))); ?></strong>
                        <span><?php esc_html_e('inscrit le', 'swiftboard'); ?></span>
                    </div>
                </div>
            </div>
        </header>

        <nav class="user-profile-nav" aria-label="<?php esc_attr_e('Navigation profil', 'swiftboard'); ?>">
            <?php if (bbp_is_user_home() || current_user_can('edit_user', bbp_get_displayed_user_id())) : ?>
                <a href="<?php echo esc_url(bbp_get_user_profile_url()); ?>" class="user-nav-link <?php echo bbp_is_single_user_profile() ? 'active' : ''; ?>">
                    <?php esc_html_e('Profil', 'swiftboard'); ?>
                </a>
                <?php if (bbp_is_favorites_active()) : ?>
                    <a href="<?php echo esc_url(bbp_get_favorites_permalink(bbp_get_displayed_user_id())); ?>" class="user-nav-link <?php echo bbp_is_favorites() ? 'active' : ''; ?>">
                        <?php esc_html_e('Favoris', 'swiftboard'); ?>
                    </a>
                <?php endif; ?>
                <?php if (bbp_is_subscriptions_active()) : ?>
                    <a href="<?php echo esc_url(bbp_get_subscriptions_permalink(bbp_get_displayed_user_id())); ?>" class="user-nav-link <?php echo bbp_is_subscriptions() ? 'active' : ''; ?>">
                        <?php esc_html_e('Abonnements', 'swiftboard'); ?>
                    </a>
                <?php endif; ?>
                <a href="<?php echo esc_url(bbp_get_user_topics_created_url(bbp_get_displayed_user_id())); ?>" class="user-nav-link <?php echo bbp_is_single_user_topics() ? 'active' : ''; ?>">
                    <?php esc_html_e('Sujets créés', 'swiftboard'); ?>
                </a>
                <a href="<?php echo esc_url(bbp_get_user_replies_created_url(bbp_get_displayed_user_id())); ?>" class="user-nav-link <?php echo bbp_is_single_user_replies() ? 'active' : ''; ?>">
                    <?php esc_html_e('Réponses', 'swiftboard'); ?>
                </a>
                <?php if (bbp_is_user_home() || current_user_can('edit_user', bbp_get_displayed_user_id())) : ?>
                    <a href="<?php echo esc_url(bbp_get_user_profile_edit_url(bbp_get_displayed_user_id())); ?>" class="user-nav-link <?php echo bbp_is_single_user_edit() ? 'active' : ''; ?>">
                        <?php esc_html_e('Modifier', 'swiftboard'); ?>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>

        <div id="bbp-user-body" class="user-profile-body">
            <?php if (bbp_is_single_user_edit()) bbp_get_template_part('form', 'user-edit'); ?>
            <?php if (bbp_is_favorites()) bbp_get_template_part('user', 'favorites'); ?>
            <?php if (bbp_is_subscriptions()) bbp_get_template_part('user', 'subscriptions'); ?>
            <?php if (bbp_is_single_user_engagements()) bbp_get_template_part('user', 'engagements'); ?>
            <?php if (bbp_is_single_user_topics()) bbp_get_template_part('user', 'topics-created'); ?>
            <?php if (bbp_is_single_user_replies()) bbp_get_template_part('user', 'replies-created'); ?>

            <?php if (!bbp_is_favorites() && !bbp_is_subscriptions() && !bbp_is_single_user_engagements()
                      && !bbp_is_single_user_topics() && !bbp_is_single_user_replies() && !bbp_is_single_user_edit()) : ?>
                <p class="user-profile-empty"><?php esc_html_e('Aucune activité récente à afficher.', 'swiftboard'); ?></p>
            <?php endif; ?>
        </div>

    </div><!-- #bbp-user-wrapper -->

    <?php do_action('bbp_template_after_user_wrapper'); ?>

</div>

