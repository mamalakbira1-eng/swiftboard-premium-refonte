<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Profil utilisateur
 *
 * Bloc d'en-tête du profil public : pseudo, date d'inscription,
 * biographie, site web, statistiques et rôle.
 *
 * @package SwiftBoard
 */
do_action('bbp_template_before_user_profile'); ?>

<div id="bbp-user-profile" class="bbp-user-profile">
    <h2 class="entry-title">@<?php bbp_displayed_user_field('user_nicename'); ?></h2>
    <div class="bbp-user-section">
        <h3><?php esc_html_e('Profil', 'swiftboard'); ?></h3>
        <p class="bbp-user-forum-role"><?php printf(esc_html__('Inscrit depuis : %s', 'swiftboard'), bbp_get_time_since(bbp_get_displayed_user_field('user_registered'))); ?></p>

        <?php if (bbp_get_displayed_user_field('description')) : ?>

            <p class="bbp-user-description"><?php echo bbp_rel_nofollow(bbp_get_displayed_user_field('description')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- contenu utilisateur déjà filtré ?></p>

        <?php endif; ?>

        <?php if (bbp_get_displayed_user_field('user_url')) : ?>

            <p class="bbp-user-website"><?php printf(esc_html__('Site web : %s', 'swiftboard'), bbp_rel_nofollow(bbp_make_clickable(bbp_get_displayed_user_field('user_url')))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- clickable + nofollow ?></p>

        <?php endif; ?>

        <hr>
        <h3><?php esc_html_e('Forums', 'swiftboard'); ?></h3>

        <?php if (bbp_get_user_last_posted()) : ?>

            <p class="bbp-user-last-activity"><?php printf(esc_html__('Dernière activité : %s', 'swiftboard'), bbp_get_time_since(bbp_get_user_last_posted(), false, true)); ?></p>

        <?php endif; ?>

        <p class="bbp-user-topic-count"><?php printf(esc_html__('Sujets créés : %s', 'swiftboard'), bbp_get_user_topic_count()); ?></p>
        <p class="bbp-user-reply-count"><?php printf(esc_html__('Réponses créées : %s', 'swiftboard'), bbp_get_user_reply_count()); ?></p>
        <p class="bbp-user-forum-role"><?php printf(esc_html__('Rôle sur le forum : %s', 'swiftboard'), bbp_get_user_display_role()); ?></p>
    </div>
</div><!-- #bbp-author-topics-started -->

<?php do_action('bbp_template_after_user_profile');

