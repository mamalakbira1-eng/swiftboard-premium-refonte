<?php
/**
 * Security check: prevent direct access
 */
if (!defined("ABSPATH")) exit;
/**
 * Comments template — Reddit-style threaded
 *
 * @package SwiftBoard
 */

if (post_password_required()) {
    return;
}

if (!comments_open() && !get_comments_number()) {
    return;
}
?>

<section id="comments" class="comments-area" aria-label="<?php esc_attr_e('Commentaires', 'swiftboard'); ?>">

    <?php if (have_comments()) : ?>
        <h2 class="comments-title">
            <?php
            $comment_count = (int) get_comments_number();
            printf(
                esc_html(_n('%d commentaire', '%d commentaires', $comment_count, 'swiftboard')),
                number_format_i18n($comment_count)
            );
            ?>
        </h2>

        <ol class="comment-list" itemscope itemtype="https://schema.org/Comment">
            <?php
            wp_list_comments([
                'style'       => 'ol',
                'short_ping'  => true,
                'avatar_size' => 32,
            ]);
            ?>
        </ol>

        <?php if (get_comment_pages_count() > 1) : ?>
            <nav class="comment-navigation" aria-label="<?php esc_attr_e('Navigation des commentaires', 'swiftboard'); ?>">
                <?php paginate_comments_links(); ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

    <?php
    comment_form([
        'title_reply'          => esc_html__('Laisser un commentaire', 'swiftboard'),
        'title_reply_before'   => '<h3 class="comment-reply-title">',
        'title_reply_after'    => '</h3>',
        'label_submit'         => esc_html__('Publier', 'swiftboard'),
        'class_submit'         => 'btn-primary',
        'comment_notes_before' => '',
        'comment_notes_after'  => '',
    ]);
    ?>

</section>

