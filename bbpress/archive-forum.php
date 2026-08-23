<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Archive du forum (index)
 *
 * Layout 2 colonnes comme la home :
 *  - Gauche : feed de TOUS les sujets récents (cards façon Reddit)
 *  - Droite : liste des forums + sujets chauds
 *
 * @package SwiftBoard
 */
get_header();

// Récupérer le tri
$forum_sort = isset($_GET['sort']) ? sanitize_key(wp_unslash($_GET['sort'])) : 'new';
if (!in_array($forum_sort, ['hot', 'new', 'top', 'rising'], true)) {
    $forum_sort = 'new';
}

// Pagination
$sb_page = max(1, (int) ($_GET['paged'] ?? get_query_var('paged') ?: 1));
$sb_per_page = 15;

// Récupérer les sujets
if ($forum_sort === 'new') {
    $all_topics = get_posts([
        'post_type'      => 'topic',
        'post_status'    => 'publish',
        'posts_per_page' => $sb_per_page,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'paged'          => $sb_page,
    ]);
    $total_topics = wp_count_posts('topic')->publish;
} else {
    $hot = function_exists('swiftboard_get_hot_topics') ? swiftboard_get_hot_topics('all', 999) : [];
    $total_topics = count($hot);
    $all_topics = [];
    $offset = ($sb_page - 1) * $sb_per_page;
    $slice = array_slice($hot, $offset, $sb_per_page);
    foreach ($slice as $t) {
        $all_topics[] = get_post($t['id']);
    }
}
$sb_total_pages = max(1, (int) ceil($total_topics / $sb_per_page));

// Hot topics pour la sidebar
$hot_topics = function_exists('swiftboard_get_hot_topics') ? swiftboard_get_hot_topics('all', 8) : [];

// Forums
$forums_query = new WP_Query([
    'post_type'      => 'forum',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
]);

$site_name = get_bloginfo('name');
$forum_url = function_exists('bbp_get_forums_url') ? bbp_get_forums_url() : home_url('/?post_type=forum');
?>

<div class="sb-home">

    <div class="sb-home-container">

        <!-- FEED PRINCIPAL (gauche) -->
        <main class="sb-home-main">

            <div class="sb-home-feed-head">
                <h1 class="sb-home-feed-title"><?php esc_html_e('Forum', 'swiftboard'); ?></h1>
                <nav class="sb-home-feed-links" aria-label="<?php esc_attr_e('Navigation rapide', 'swiftboard'); ?>">
                    <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Accueil', 'swiftboard'); ?></a>
                    <a href="<?php echo esc_url(home_url('/blog/')); ?>"><?php esc_html_e('Blog', 'swiftboard'); ?></a>
                </nav>
            </div>

            <!-- Sort bar -->
            <div class="sb-sort-bar">
                <div class="sb-sort-tabs" role="navigation" aria-label="<?php esc_attr_e('Trier les sujets', 'swiftboard'); ?>">
                    <?php
                    $sb_sort_labels = [
                        'new'    => '🆕 ' . __('Nouveau', 'swiftboard'),
                        'hot'    => '🔥 ' . __('Tendances', 'swiftboard'),
                        'top'    => '📈 ' . __('Top', 'swiftboard'),
                        'rising' => '🚀 ' . __('Rising', 'swiftboard'),
                    ];
                    foreach ($sb_sort_labels as $sb_s => $sb_label):
                    ?>
                    <a href="<?php echo esc_url(home_url('/forums/?sort=' . $sb_s)); ?>"
                       class="sb-sort-tab <?php echo $forum_sort === $sb_s ? 'active' : ''; ?>"
                       <?php echo $forum_sort === $sb_s ? 'aria-current="page"' : ''; ?>><?php echo esc_html($sb_label); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Feed items -->
            <?php if (!empty($all_topics)): ?>
                <?php foreach ($all_topics as $sb_t):
                    $sb_forum = get_post($sb_t->post_parent);
                    $sb_author_id = (int) $sb_t->post_author;
                    $sb_author = get_the_author_meta('display_name', $sb_author_id);
                    if ('' === trim($sb_author)) $sb_author = __('Membre supprimé', 'swiftboard');
                    $sb_votes = function_exists('swiftboard_get_vote_count') ? swiftboard_get_vote_count($sb_t->ID) : 0;
                    $sb_replies = function_exists('bbp_get_topic_reply_count') ? bbp_get_topic_reply_count($sb_t->ID, true) : 0;
                    $sb_forum_name = $sb_forum ? $sb_forum->post_title : '';
                    $sb_forum_url = $sb_forum ? get_permalink($sb_forum->ID) : '';
                ?>
                    <article class="sb-post-card sb-home-card sb-home-type-topic" id="topic-<?php echo esc_attr((string) $sb_t->ID); ?>">
                        <div class="sb-post-votes">
                            <button class="sb-vote-btn up" data-post-id="<?php echo esc_attr((string) $sb_t->ID); ?>" aria-label="<?php esc_attr_e('Upvoter', 'swiftboard'); ?>">
                                <svg class="sb-icon" width="18" height="18" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2.75 17.5 10.25H14V17.5H6V10.25H2.5L10 2.75z"/></svg>
                            </button>
                            <span class="sb-vote-count"><?php echo esc_html(function_exists('swiftboard_format_count') ? swiftboard_format_count($sb_votes) : $sb_votes); ?></span>
                            <button class="sb-vote-btn down" data-post-id="<?php echo esc_attr((string) $sb_t->ID); ?>" aria-label="<?php esc_attr_e('Downvoter', 'swiftboard'); ?>">
                                <svg class="sb-icon" width="18" height="18" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 17.25 2.5 9.75H6V2.5h8v7.25h3.5L10 17.25z"/></svg>
                            </button>
                        </div>
                        <div class="sb-post-body">
                            <div class="sb-post-meta-top">
                                <?php if ($sb_forum_url): ?>
                                <a href="<?php echo esc_url($sb_forum_url); ?>" class="sb-forum-pill">
                                    <span class="sb-forum-icon" aria-hidden="true"><?php echo esc_html(strtoupper(substr($sb_forum_name, 0, 1))); ?></span>
                                    r/<?php echo esc_html($sb_forum_name); ?>
                                </a>
                                <span class="sb-meta-sep">·</span>
                                <?php endif; ?>
                                <span class="sb-post-author"><?php if ($sb_author_id && function_exists('swiftboard_get_avatar')) echo swiftboard_get_avatar($sb_author_id, 20); ?> Par <strong><?php echo esc_html($sb_author); ?></strong><?php
                                if ($sb_author_id && function_exists('swiftboard_display_grade_badge')) {
                                    swiftboard_display_grade_badge($sb_author_id);
                                }
                                ?></span>
                                <span class="sb-meta-sep">·</span>
                                <span class="sb-post-time"><?php echo esc_html(function_exists('swiftboard_time_ago') ? swiftboard_time_ago(get_the_date('c', $sb_t->ID)) : ''); ?></span>
                                <span class="sb-meta-sep">·</span>
                                <span>👁 <?php echo esc_html(swiftboard_format_count((int) get_post_meta($sb_t->ID, '_bbp_voice_count', true))); ?> vues</span>
                                <span class="sb-post-flair sb-flair-discussion">💬 Discussion</span>
                            </div>
                            <h2 class="sb-post-title">
                                <a href="<?php echo esc_url(get_permalink($sb_t->ID)); ?>"><?php echo esc_html($sb_t->post_title); ?></a>
                            </h2>
                            <div class="sb-post-content"><?php echo esc_html(wp_trim_words(wp_strip_all_tags($sb_t->post_content), 30, '…')); ?></div>
                            <?php
                            if (function_exists('swiftboard_actions_carte_html')) {
                                echo swiftboard_actions_carte_html(
                                    (int) $sb_t->ID,
                                    get_permalink($sb_t->ID),
                                    (int) $sb_replies
                                );
                            }
                            ?>
                        </div>
                    </article>
                <?php endforeach; ?>

                <!-- Pagination -->
                <?php if ($sb_total_pages > 1): ?>
                <nav class="pagination sb-home-pagination">
                    <?php
                    echo paginate_links([
                        'base' => home_url('/forums/?sort=' . $forum_sort . '&paged=%#%'),
                        'format' => '&paged=%#%',
                        'current' => $sb_page,
                        'total' => $sb_total_pages,
                        'prev_text' => '‹ ' . __('Précédent', 'swiftboard'),
                        'next_text' => __('Suivant', 'swiftboard') . ' ›',
                    ]);
                    ?>
                </nav>
                <?php endif; ?>

            <?php else: ?>
                <div class="sb-home-empty">
                    <h2>🎉 <?php esc_html_e('Bienvenue sur le forum !', 'swiftboard'); ?></h2>
                    <p><?php esc_html_e('Aucun sujet pour le moment. Soyez le premier à créer un sujet !', 'swiftboard'); ?></p>
                </div>
            <?php endif; ?>

        </main>

        <!-- SIDEBAR (droite) -->
        <aside class="sb-home-sidebar">

            <!-- Liste des forums -->
            <?php if ($forums_query->have_posts()): ?>
            <div class="sb-sidebar-card">
                <div class="sb-sidebar-card-header sb-header-blue">📋 <?php esc_html_e('Forums', 'swiftboard'); ?></div>
                <div class="sb-sidebar-card-body">
                    <?php while ($forums_query->have_posts()): $forums_query->the_post();
                        $fid = get_the_ID();
                        $topics = function_exists('bbp_get_forum_topic_count') ? bbp_get_forum_topic_count($fid) : 0;
                    ?>
                        <a href="<?php echo esc_url(get_permalink($fid)); ?>" class="sb-sidebar-forum-item">
                            <span class="sb-sidebar-forum-icon"><?php echo esc_html(strtoupper(substr(get_the_title(), 0, 1))); ?></span>
                            <div class="sb-sidebar-forum-info">
                                <div class="sb-sidebar-forum-name"><?php echo esc_html(get_the_title()); ?></div>
                                <div class="sb-sidebar-forum-count"><?php echo (int) $topics; ?> <?php esc_html_e('sujets', 'swiftboard'); ?></div>
                            </div>
                        </a>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Sujets chauds -->
            <?php if (!empty($hot_topics)): ?>
            <div class="sb-sidebar-card">
                <div class="sb-sidebar-card-header sb-header-blue">🔥 <?php esc_html_e('Sujets chauds', 'swiftboard'); ?></div>
                <div class="sb-sidebar-card-body">
                    <?php foreach (array_slice($hot_topics, 0, 5) as $i => $t): ?>
                        <div class="sb-sidebar-hot-item">
                            <span class="sb-sidebar-hot-rank <?php echo $i < 3 ? 'rank-' . ($i + 1) : ''; ?>"><?php echo $i + 1; ?></span>
                            <div>
                                <a href="<?php echo esc_url($t['url']); ?>" class="sb-sidebar-hot-title"><?php echo esc_html($t['title']); ?></a>
                                <div class="sb-sidebar-hot-meta">▲ <?php echo (int) $t['vote_score']; ?> · 💬 <?php echo (int) $t['reply_count']; ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Footer mini -->
            <div class="sb-sidebar-mini-footer">
                <?php echo esc_html(date('Y')); ?> © <?php echo esc_html($site_name); ?><br>
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Accueil', 'swiftboard'); ?></a> ·
                <a href="<?php echo esc_url($forum_url); ?>"><?php esc_html_e('Forum', 'swiftboard'); ?></a>
            </div>

        </aside>

    </div>
</div>

<?php get_footer();
