<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Sujet unique (lead = premier message)
 *
 * Affiché avant la boucle des réponses.
 *
 * @package SwiftBoard
 */
?>

<article id="post-<?php bbp_topic_id(); ?>" class="bbp-topic-lead" itemscope itemtype="https://schema.org/DiscussionForumPosting">

    <header class="bbp-reply-header">
        <span class="bbp-reply-author">
            <?php echo swiftboard_get_avatar(bbp_get_topic_author_id(), 28); ?>
            <span itemprop="author" itemscope itemtype="https://schema.org/Person">
                <span itemprop="name"><?php bbp_topic_author_display_name(); ?></span>
            </span>
            <?php if (user_can((int) bbp_get_topic_author_id(), 'administrator')) : ?>
                <span class="bbp-author-role admin"><?php esc_html_e('Admin', 'swiftboard'); ?></span>
            <?php elseif (user_can((int) bbp_get_topic_author_id(), 'bbp_moderator') || user_can((int) bbp_get_topic_author_id(), 'edit_posts')) : ?>
                <span class="bbp-author-role moderator"><?php esc_html_e('Modo', 'swiftboard'); ?></span>
            <?php endif; ?>
            <span class="bbp-reply-meta-time">
                • <?php echo esc_html(swiftboard_time_ago(get_the_date('c', bbp_get_topic_id()))); ?>
                • <?php esc_html_e('#1 (auteur)', 'swiftboard'); ?>
            </span>
        </span>
    </header>

    <div class="bbp-reply-content" itemprop="text">
        <?php bbp_topic_content(); ?>
    </div>

    <?php if (bbp_current_user_can_access_create_reply_form()) : ?>
        <div class="bbp-reply-actions">
            <a class="bbp-reply-action" href="#new-reply-<?php bbp_topic_id(); ?>">
                <?php echo swiftboard_icon('reply',14); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?> <?php esc_html_e('Répondre', 'swiftboard'); ?>
            </a>
            <?php if (is_user_logged_in()) : ?>
                <a class="bbp-reply-action" href="<?php echo esc_url(bbp_get_topic_permalink() . '?edit=1'); ?>">
                    <?php echo swiftboard_icon('edit',14); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?> <?php esc_html_e('Éditer', 'swiftboard'); ?>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</article>

