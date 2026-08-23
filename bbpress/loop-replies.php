<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Loop des réponses (Reddit-style threaded cards)
 *
 * @package SwiftBoard
 */
?>

<?php
// V2 restauration - tri sur page réponses créées
do_action('bbp_template_before_replies_loop');
?>

<?php while (bbp_replies()) : bbp_the_reply(); ?>

    <?php
    $reply_id    = bbp_get_reply_id();
    $r_author_id = bbp_get_reply_author_id($reply_id);
    $r_author    = bbp_get_reply_author_display_name($reply_id);
    $r_date      = get_the_date('c', $reply_id);
    $r_content   = bbp_get_reply_content($reply_id);
    $r_position  = bbp_get_reply_position($reply_id);

    $r_role = '';
    if (user_can((int) $r_author_id, 'administrator')) {
        $r_role = '<span class="bbp-author-role admin">' . esc_html__('Admin', 'swiftboard') . '</span>';
    } elseif (user_can((int) $r_author_id, 'bbp_moderator') || user_can((int) $r_author_id, 'edit_posts')) {
        $r_role = '<span class="bbp-author-role moderator">' . esc_html__('Modo', 'swiftboard') . '</span>';
    }
    ?>

    <div id="reply-<?php echo esc_attr((string) $reply_id); ?>" class="bbp-reply" itemprop="comment" itemscope itemtype="https://schema.org/Comment">
        <header class="bbp-reply-header">
            <span class="bbp-reply-author">
                <?php echo swiftboard_get_avatar($r_author_id, 24); ?>
                <span itemprop="author" itemscope itemtype="https://schema.org/Person">
                    <span itemprop="name"><?php echo esc_html($r_author); ?></span>
                </span>
                <?php echo $r_role; // phpcs:ignore ?>
                <?php if (function_exists('swiftboard_display_grade_badge')) swiftboard_display_grade_badge((int) $r_author_id); ?>
                <span class="bbp-reply-meta-time">• <?php echo esc_html(swiftboard_time_ago($r_date)); ?> • #<?php echo intval($r_position); ?></span>
            </span>
        </header>
        <div class="bbp-reply-content" itemprop="text">
            <?php echo $r_content; // phpcs:ignore ?>
        </div>
        <div class="bbp-reply-actions">
            <?php if (swiftboard_get_option('show_vote_count', 1)) : ?>
                <button class="bbp-reply-action vote-btn upvote" aria-label="<?php esc_attr_e('Upvoter', 'swiftboard'); ?>">▲ <?php esc_html_e('Utile', 'swiftboard'); ?></button>
            <?php endif; ?>
            <button class="bbp-reply-action"><?php echo swiftboard_icon('link',14); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?> <?php esc_html_e('Partager', 'swiftboard'); ?></button>
            <button class="bbp-reply-action"><?php echo swiftboard_icon('star',14); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?> <?php esc_html_e('Enregistrer', 'swiftboard'); ?></button>
        </div>
    </div>

<?php endwhile; ?>

