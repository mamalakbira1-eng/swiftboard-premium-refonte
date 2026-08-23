<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Contenu d'un forum unique
 *
 * Affiche le header du forum + la liste des sous-forums + liste des sujets.
 *
 * @package SwiftBoard
 */
?>

<?php $forum_id = bbp_get_forum_id(); ?>

<?php
// V2 restauration - Famille A4/A5: hero forum + notice permission
// NOTE: bbp_template_before_single_forum est INTENTIONNELLEMENT non déclenché.
// Le hero est rendu ci-dessous (subreddit-head + h1), et le bloc Grades & karma
// est appelé directement via swiftboard_render_forum_about() plus bas.
// Déclencher le hook ferait double emploi : double H1 + double bloc grades.
?>

<article id="bbp-forum-<?php echo esc_attr((string) $forum_id); ?>" class="forum-content" itemscope itemtype="https://schema.org/DiscussionForumPosting">

    <header class="forum-header">
        <div class="sb-subreddit-head">
            <h1 class="forum-title" itemprop="headline"><span class="sb-subreddit-r">r/</span><?php echo esc_html(bbp_get_forum_title($forum_id)); ?></h1>
            <div class="sb-subreddit-actions">
                <span class="sb-subreddit-members" data-forum-id="<?php echo esc_attr($forum_id); ?>">
                    <?php echo esc_html(swiftboard_subreddit_member_count($forum_id)); ?>
                </span>
                <?php echo swiftboard_subreddit_join_button($forum_id); // phpcs:ignore ?>
            </div>
        </div>
        <?php $content = bbp_get_forum_content($forum_id); ?>
        <?php if ($content) : ?>
            <p class="forum-description" itemprop="text"><?php echo wp_kses_post($content); ?></p>
        <?php endif; ?>
    </header>

    <?php
    // v5.3.8 — EXI-KARMA-03 : bloc « A propos / Regles / Grades & karma ».
    // Le hook natif bbp_template_before_single_forum n'etait JAMAIS declenche
    // par cet override — le hero forum et ce bloc etaient donc du code mort.
    // On ne restaure PAS le hook : il rendrait aussi le hero (forum-customizer)
    // et ferait double emploi avec le subreddit-head ci-dessus. On appelle
    // directement la section about, extraite du hero dans forum-customizer.php,
    // SOUS l'en-tete : une seule en-tete, puis les regles — comme sur Reddit.
    if (function_exists('swiftboard_render_forum_about')) {
        swiftboard_render_forum_about($forum_id);
    }
    ?>

    <div class="bbp-forum-meta" style="display: flex; gap: var(--space-md); font-size: var(--font-size-xs); color: var(--color-text-muted); margin-bottom: var(--space-md); flex-wrap: wrap;">
        <span class="bbp-forum-topic-count">
            <?php echo esc_html(swiftboard_format_count(bbp_get_forum_topic_count($forum_id))); ?>
            <?php esc_html_e('sujets', 'swiftboard'); ?>
        </span>
        <span class="bbp-forum-reply-count">
            <?php echo esc_html(swiftboard_format_count(bbp_get_forum_reply_count($forum_id))); ?>
            <?php esc_html_e('réponses', 'swiftboard'); ?>
        </span>
        <?php $freshness = bbp_get_forum_last_active_time($forum_id); ?>
        <?php if ($freshness) : ?>
            <span class="bbp-forum-freshness">
                <?php esc_html_e('Dernier :', 'swiftboard'); ?> <?php echo esc_html($freshness); ?>
            </span>
        <?php endif; ?>
    </div>

    <?php if (!bbp_is_forum_category()) : ?>

        <?php bbp_get_template_part('alert', 'topic-lock'); ?>

        <?php if (bbp_has_topics()) : ?>

            <?php if (bbp_is_topic_archive()) : ?>
                <nav class="bbp-pagination">
                    <span class="bbp-pagination-count">
                        <?php bbp_forum_pagination_count(); ?>
                    </span>
                    <div class="bbp-pagination-links">
                        <?php bbp_forum_pagination_links(); ?>
                    </div>
                </nav>
            <?php endif; ?>

            <?php
            // Déclencher le hook pour la sort-bar + toggle Card/Compact.
            do_action('bbp_template_before_topics_loop');
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
                    $last_active = bbp_get_topic_last_active_time($topic_id);
                    $vote_count  = swiftboard_get_vote_count($topic_id);
                    ?>

                    <li>
                        <article class="topic-item" itemscope itemtype="https://schema.org/DiscussionForumPosting" id="topic-<?php echo esc_attr((string) $topic_id); ?>">

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
                                        <a href="<?php echo esc_url($topic_url); ?>" itemprop="url" rel="bookmark">
                                            <?php echo esc_html($topic_title); ?>
                                        </a>
                                    </h2>
                                </header>

                            <div class="topic-meta">
                                <?php echo swiftboard_get_avatar($author_id, 24); ?>
                                <span itemprop="author" itemscope itemtype="https://schema.org/Person">
                                    <span itemprop="name"><?php echo esc_html($author_name); ?></span>
                                </span>
                                <?php if (function_exists('swiftboard_display_grade_badge')) swiftboard_display_grade_badge((int) $author_id); ?>
                                <span class="topic-meta-sep">•</span>
                                <time datetime="<?php echo esc_attr($post_date); ?>" itemprop="datePublished">
                                    <?php echo esc_html(swiftboard_time_ago($post_date)); ?>
                                </time>
                                <span class="topic-meta-sep">•</span>
                                <span>👁 <?php echo esc_html(swiftboard_format_count((int) get_post_meta($topic_id, '_bbp_voice_count', true))); ?> <?php esc_html_e('vues', 'swiftboard'); ?></span>
                            </div>

                                <div class="topic-stats">
                                    <span class="topic-replies" itemprop="interactionStatistic" itemscope itemtype="https://schema.org/InteractionCounter">
                                        <meta itemprop="interactionType" content="https://schema.org/ReplyAction">
                                        💬 <?php echo esc_html(swiftboard_format_count($reply_count)); ?>
                                        <?php echo esc_html(_n('réponse', 'réponses', $reply_count, 'swiftboard')); ?>
                                    </span>
                                    <?php if ($last_active) : ?>
                                        <span class="bbp-topic-freshness">
                                            🕒 <?php echo esc_html($last_active); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </article>
                    </li>

                <?php endwhile; ?>
            </ul>

            <?php
            // Point d'extension standard de bbPress, perdu lors de la reecriture
            // du rendu facon Reddit. C'est lui qui permet aux modules de
            // s'accrocher apres la liste des sujets — sans lui, la pagination
            // par curseur (inc/cursor-pagination.php) n'etait jamais rendue,
            // meme avec 5 000 sujets dans le forum.
            do_action('bbp_template_after_topics_loop');
            ?>

            <?php if (bbp_is_topic_archive()) : ?>
                <nav class="bbp-pagination">
                    <span class="bbp-pagination-count">
                        <?php bbp_forum_pagination_count(); ?>
                    </span>
                    <div class="bbp-pagination-links">
                        <?php bbp_forum_pagination_links(); ?>
                    </div>
                </nav>
            <?php endif; ?>

        <?php else : ?>

            <div class="bbp-no-topic">
                <p><?php esc_html_e('Aucun sujet dans ce forum pour le moment.', 'swiftboard'); ?></p>
                <?php if (is_user_logged_in() && bbp_current_user_can_access_create_topic_form()) : ?>
                    <p style="margin-top: var(--space-md);">
                        <a href="#bbp-new-topic" class="btn-primary"><?php esc_html_e('Créer le premier sujet', 'swiftboard'); ?></a>
                    </p>
                <?php endif; ?>
            </div>

        <?php endif; ?>

        <?php if (is_user_logged_in() && bbp_current_user_can_access_create_topic_form()) : ?>
            <?php bbp_get_template_part('form', 'topic'); ?>
        <?php elseif (!is_user_logged_in()) : ?>
            <div class="bbp-template-notice info">
                <?php printf(
                    /* translators: %s = login URL */
                    esc_html__('Vous devez %s pour créer un sujet.', 'swiftboard'),
                    '<a href="' . esc_url(wp_login_url(get_permalink())) . '">' . esc_html__('vous connecter', 'swiftboard') . '</a>'
                ); ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</article>

