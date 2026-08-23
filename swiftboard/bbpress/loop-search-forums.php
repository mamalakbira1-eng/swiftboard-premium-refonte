<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Liste des forums pour search/shortcodes
 *
 * @package SwiftBoard
 */
?>

<div class="bbp-search-results-list">

    <?php while (bbp_forums()) : bbp_the_forum(); ?>

        <?php
        $forum_id    = bbp_get_forum_id();
        $forum_url   = bbp_get_forum_permalink($forum_id);
        $forum_title = bbp_get_forum_title($forum_id);
        ?>

        <article class="search-result-item">
            <h2 class="entry-title">
                <a href="<?php echo esc_url($forum_url); ?>"><?php echo esc_html($forum_title); ?></a>
            </h2>
            <div class="post-meta">
                <span class="post-type-badge forum-badge"><?php esc_html_e('Forum', 'swiftboard'); ?></span>
            </div>
        </article>

    <?php endwhile; ?>

</div>

