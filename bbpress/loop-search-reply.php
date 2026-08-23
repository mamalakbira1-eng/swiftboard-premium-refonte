<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Résultat de recherche — Réponse
 *
 * Utilise les avatars ninja SwiftBoard + grades militaires (cohérence UI).
 *
 * @package SwiftBoard
 */

$sb_search_reply_id = bbp_get_reply_id();
$sb_search_author_id = (int) bbp_get_reply_author_id($sb_search_reply_id);
?>

<div class="bbp-reply-header">
    <div class="bbp-meta">
        <span class="bbp-reply-post-date"><?php bbp_reply_post_date(); ?></span>
        <a href="<?php bbp_reply_url(); ?>" class="bbp-reply-permalink">#<?php bbp_reply_id(); ?></a>
    </div><!-- .bbp-meta -->

    <div class="bbp-reply-title">
        <h3><?php esc_html_e('En réponse à : ', 'swiftboard'); ?>
        <a class="bbp-topic-permalink" href="<?php bbp_topic_permalink(bbp_get_reply_topic_id()); ?>"><?php bbp_topic_title(bbp_get_reply_topic_id()); ?></a></h3>
    </div><!-- .bbp-reply-title -->
</div><!-- .bbp-reply-header -->

<div id="post-<?php bbp_reply_id(); ?>" <?php bbp_reply_class(); ?>>
    <div class="bbp-reply-author">

        <?php do_action('bbp_theme_before_reply_author_details'); ?>

        <?php echo swiftboard_get_avatar($sb_search_author_id, 32); ?>
        <span itemprop="author" itemscope itemtype="https://schema.org/Person">
            <a href="<?php echo esc_url(bbp_get_user_profile_url($sb_search_author_id)); ?>"><span itemprop="name"><?php echo esc_html(bbp_get_reply_author_display_name($sb_search_reply_id)); ?></span></a>
        </span>
        <?php if (function_exists('swiftboard_display_grade_badge')) swiftboard_display_grade_badge($sb_search_author_id); ?>

        <?php if (bbp_is_user_keymaster()) : ?>

            <?php do_action('bbp_theme_before_reply_author_admin_details'); ?>

            <div class="bbp-reply-ip"><?php bbp_author_ip(bbp_get_reply_id()); ?></div>

            <?php do_action('bbp_theme_after_reply_author_admin_details'); ?>

        <?php endif; ?>

        <?php do_action('bbp_theme_after_reply_author_details'); ?>

    </div><!-- .bbp-reply-author -->

    <div class="bbp-reply-content">

        <?php do_action('bbp_theme_before_reply_content'); ?>

        <?php bbp_reply_content(); ?>

        <?php do_action('bbp_theme_after_reply_content'); ?>

    </div><!-- .bbp-reply-content -->
</div><!-- #post-<?php bbp_reply_id(); ?> -->
