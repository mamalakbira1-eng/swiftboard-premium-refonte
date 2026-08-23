<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Résultat de recherche — Sujet
 *
 * Utilise les avatars ninja SwiftBoard + grades militaires (cohérence UI).
 *
 * @package SwiftBoard
 */

$sb_search_topic_id = bbp_get_topic_id();
$sb_search_author_id = (int) bbp_get_topic_author_id($sb_search_topic_id);
?>

<div class="bbp-topic-header">
    <div class="bbp-meta">
        <span class="bbp-topic-post-date"><?php bbp_topic_post_date($sb_search_topic_id); ?></span>
        <a href="<?php bbp_topic_permalink(); ?>" class="bbp-topic-permalink">#<?php bbp_topic_id(); ?></a>
    </div><!-- .bbp-meta -->

    <div class="bbp-topic-title">

        <?php do_action('bbp_theme_before_topic_title'); ?>

        <h3><?php esc_html_e('Sujet :', 'swiftboard'); ?>
        <a href="<?php bbp_topic_permalink(); ?>"><?php bbp_topic_title(); ?></a></h3>

        <div class="bbp-topic-title-meta">

            <?php if (function_exists('bbp_is_forum_group_forum') && bbp_is_forum_group_forum(bbp_get_topic_forum_id())) : ?>

                <?php esc_html_e('dans un forum de groupe ', 'swiftboard'); ?>

            <?php else : ?>

                <?php esc_html_e('dans le forum ', 'swiftboard'); ?>

            <?php endif; ?>

            <a href="<?php bbp_forum_permalink(bbp_get_topic_forum_id()); ?>"><?php bbp_forum_title(bbp_get_topic_forum_id()); ?></a>

        </div><!-- .bbp-topic-title-meta -->

        <?php do_action('bbp_theme_after_topic_title'); ?>

    </div><!-- .bbp-topic-title -->

</div><!-- .bbp-topic-header -->

<div id="post-<?php bbp_topic_id(); ?>" <?php bbp_topic_class(); ?>>
    <div class="bbp-topic-author">

        <?php do_action('bbp_theme_before_topic_author_details'); ?>

        <?php echo swiftboard_get_avatar($sb_search_author_id, 32); ?>
        <span itemprop="author" itemscope itemtype="https://schema.org/Person">
            <a href="<?php echo esc_url(bbp_get_user_profile_url($sb_search_author_id)); ?>"><span itemprop="name"><?php echo esc_html(bbp_get_topic_author_display_name($sb_search_topic_id)); ?></span></a>
        </span>
        <?php if (function_exists('swiftboard_display_grade_badge')) swiftboard_display_grade_badge($sb_search_author_id); ?>

        <?php if (bbp_is_user_keymaster()) : ?>

            <?php do_action('bbp_theme_before_topic_author_admin_details'); ?>

            <div class="bbp-reply-ip"><?php bbp_author_ip(bbp_get_topic_id()); ?></div>

            <?php do_action('bbp_theme_after_topic_author_admin_details'); ?>

        <?php endif; ?>

        <?php do_action('bbp_theme_after_topic_author_details'); ?>

    </div><!-- .bbp-topic-author -->

    <div class="bbp-topic-content">

        <?php do_action('bbp_theme_before_topic_content'); ?>

        <?php bbp_topic_content(); ?>

        <?php do_action('bbp_theme_after_topic_content'); ?>

    </div><!-- .bbp-topic-content -->
</div><!-- #post-<?php bbp_topic_id(); ?> -->
