<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Loop des forums (cartes)
 *
 * @package SwiftBoard
 */
?>

<ul class="bbp-forums-list">

    <?php while (bbp_forums()) : bbp_the_forum(); ?>

        <?php
        $forum_id    = bbp_get_forum_id();
        $forum_url   = bbp_get_forum_permalink($forum_id);
        $forum_title = bbp_get_forum_title($forum_id);
        $forum_desc  = bbp_get_forum_content($forum_id);
        $topics      = bbp_get_forum_topic_count($forum_id);
        $replies     = bbp_get_forum_reply_count($forum_id);
        $freshness   = bbp_get_forum_last_active_time($forum_id);
        $initial     = mb_strtoupper(mb_substr($forum_title, 0, 1));
        ?>

        <li>
            <a href="<?php echo esc_url($forum_url); ?>" class="bbp-forum">
                <span class="bbp-forum-icon" aria-hidden="true"><?php echo esc_html($initial); ?></span>
                <div class="bbp-forum-body">
                    <h3 class="bbp-forum-title"><?php echo esc_html($forum_title); ?></h3>
                    <?php if ($forum_desc) : ?>
                        <p class="bbp-forum-description"><?php echo wp_kses_post($forum_desc); ?></p>
                    <?php endif; ?>
                    <div class="bbp-forum-meta">
                        <span class="bbp-forum-topic-count"><?php echo esc_html(swiftboard_format_count($topics)); ?> <?php esc_html_e('sujets', 'swiftboard'); ?></span>
                        <span class="bbp-forum-reply-count"><?php echo esc_html(swiftboard_format_count($replies)); ?> <?php esc_html_e('réponses', 'swiftboard'); ?></span>
                        <?php if ($freshness) : ?>
                            <span class="bbp-forum-freshness"><?php esc_html_e('Dernier :', 'swiftboard'); ?> <?php echo esc_html($freshness); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        </li>

    <?php endwhile; ?>

</ul>

