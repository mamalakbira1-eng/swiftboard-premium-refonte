<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Page statistiques du forum
 *
 * @package SwiftBoard
 */
$stats = bbp_get_statistics();
?>

<div class="bbpress-wrapper">
    <header class="forum-header">
        <h1 class="forum-title"><?php esc_html_e('Statistiques du forum', 'swiftboard'); ?></h1>
        <p class="forum-description"><?php esc_html_e('Vue d\'ensemble de l\'activité de la communauté.', 'swiftboard'); ?></p>
    </header>

    <?php do_action('bbp_before_statistics'); ?>

    <div class="bbp-statistics">
        <dl>
            <dt><?php esc_html_e('Utilisateurs enregistrés', 'swiftboard'); ?></dt>
            <dd><?php echo esc_html(swiftboard_format_count($stats['user_count'] ?? 0)); ?></dd>
        </dl>
        <dl>
            <dt><?php esc_html_e('Forums', 'swiftboard'); ?></dt>
            <dd><?php echo esc_html(swiftboard_format_count($stats['forum_count'] ?? 0)); ?></dd>
        </dl>
        <dl>
            <dt><?php esc_html_e('Sujets', 'swiftboard'); ?></dt>
            <dd><?php echo esc_html(swiftboard_format_count($stats['topic_count'] ?? 0)); ?></dd>
        </dl>
        <dl>
            <dt><?php esc_html_e('Réponses', 'swiftboard'); ?></dt>
            <dd><?php echo esc_html(swiftboard_format_count($stats['reply_count'] ?? 0)); ?></dd>
        </dl>
        <?php if (!empty($stats['topic_tag_count'])) : ?>
            <dl>
                <dt><?php esc_html_e('Mots-clés', 'swiftboard'); ?></dt>
                <dd><?php echo esc_html(swiftboard_format_count($stats['topic_tag_count'])); ?></dd>
            </dl>
        <?php endif; ?>
    </div>

    <?php if (!empty($stats['popular_topics']) && is_array($stats['popular_topics'])) : ?>
        <section class="user-profile-body">
            <h2><?php esc_html_e('Sujets populaires', 'swiftboard'); ?></h2>
            <ul class="bbp-topics-list">
                <?php foreach ($stats['popular_topics'] as $topic_id) :
                    $topic_id = (int) $topic_id; ?>
                    <li><article class="topic-item">
                        <div class="topic-body">
                            <header class="topic-header">
                                <h3 class="topic-title">
                                    <a href="<?php echo esc_url(bbp_get_topic_permalink($topic_id)); ?>"><?php echo esc_html(bbp_get_topic_title($topic_id)); ?></a>
                                </h3>
                            </header>
                            <div class="topic-stats">
                                <span>💬 <?php echo esc_html(swiftboard_format_count(bbp_get_topic_reply_count($topic_id))); ?> <?php esc_html_e('réponses', 'swiftboard'); ?></span>
                            </div>
                        </div>
                    </article></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php do_action('bbp_after_statistics'); ?>
</div>

