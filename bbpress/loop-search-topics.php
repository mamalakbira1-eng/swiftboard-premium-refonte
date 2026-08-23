<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Liste des sujets pour recherche
 *
 * @package SwiftBoard
 */
?>

<ul class="bbp-topics-list">

    <?php while (bbp_topics()) : bbp_the_topic(); ?>

        <?php
        $topic_id    = bbp_get_topic_id();
        $topic_url   = bbp_get_topic_permalink($topic_id);
        $topic_title = bbp_get_topic_title($topic_id);
        $author_id   = bbp_get_topic_author_id($topic_id);
        $author_name = bbp_get_topic_author_display_name($topic_id);
        $reply_count = bbp_get_topic_reply_count($topic_id);
        $post_date   = get_the_date('c', $topic_id);
        $vote_count  = swiftboard_get_vote_count($topic_id);
        ?>

        <li>
            <article class="topic-item" itemscope itemtype="https://schema.org/DiscussionForumPosting">
                <?php if (swiftboard_get_option('show_vote_count', 1)) : ?>
                    <div class="topic-vote" data-post-id="<?php echo esc_attr((string) $topic_id); ?>">
                        <button class="vote-btn upvote" aria-label="<?php esc_attr_e('Upvoter', 'swiftboard'); ?>">▲</button>
                        <span class="vote-count"><?php echo esc_html(swiftboard_format_count($vote_count)); ?></span>
                        <button class="vote-btn downvote" aria-label="<?php esc_attr_e('Downvoter', 'swiftboard'); ?>">▼</button>
                    </div>
                <?php endif; ?>

                <div class="topic-body">
                    <header class="topic-header">
                        <h2 class="topic-title" itemprop="headline">
                            <a href="<?php echo esc_url($topic_url); ?>" itemprop="url"><?php echo esc_html($topic_title); ?></a>
                        </h2>
                    </header>
                    <div class="topic-meta">
                        <?php echo swiftboard_get_avatar($author_id, 24); ?>
                        <span itemprop="author" itemscope itemtype="https://schema.org/Person">
                            <span itemprop="name"><?php echo esc_html($author_name); ?></span>
                        </span>
                        <?php if (function_exists('swiftboard_display_grade_badge')) swiftboard_display_grade_badge((int) $author_id); ?>
                        <span class="topic-meta-sep">•</span>
                        <time datetime="<?php echo esc_attr($post_date); ?>"><?php echo esc_html(swiftboard_time_ago($post_date)); ?></time>
                    </div>
                    <div class="topic-stats">
                        💬 <?php echo esc_html(swiftboard_format_count($reply_count)); ?> <?php esc_html_e('réponses', 'swiftboard'); ?>
                    </div>
                </div>
            </article>
        </li>

    <?php endwhile; ?>

</ul>

