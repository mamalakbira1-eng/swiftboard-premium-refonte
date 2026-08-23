<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Réponse unique (vue standalone)
 *
 * @package SwiftBoard
 */
?>

<div id="bbpress-forums" class="bbpress-wrapper">


    <article id="reply-<?php bbp_reply_id(); ?>" class="bbp-reply" itemscope itemtype="https://schema.org/Comment">

        <header class="bbp-reply-header">
            <span class="bbp-reply-author">
                <?php echo swiftboard_get_avatar(bbp_get_reply_author_id(), 24); ?>
                <span itemprop="author" itemscope itemtype="https://schema.org/Person">
                    <span itemprop="name"><?php bbp_reply_author_display_name(); ?></span>
                </span>
                <?php if (user_can((int) bbp_get_reply_author_id(), 'moderate')) : ?>
                    <span class="bbp-author-role moderator"><?php esc_html_e('Modo', 'swiftboard'); ?></span>
                <?php endif; ?>
                <span class="bbp-reply-meta-time">
                    • <?php echo esc_html(swiftboard_time_ago(get_the_date('c'))); ?>
                    • <?php printf(esc_html__('#%d', 'swiftboard'), bbp_get_reply_position()); ?>
                </span>
            </span>
        </header>

        <div class="bbp-reply-content" itemprop="text">
            <?php bbp_reply_content(); ?>
        </div>

        <div class="bbp-reply-actions">
            <a class="bbp-reply-action" href="<?php echo esc_url(bbp_get_topic_permalink(bbp_get_reply_topic_id())); ?>">
                ↩ <?php esc_html_e('Voir le sujet', 'swiftboard'); ?>
            </a>
        </div>

    </article>

</div>

