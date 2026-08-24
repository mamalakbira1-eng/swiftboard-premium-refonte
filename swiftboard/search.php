<?php

if (!defined('ABSPATH')) exit;
/**
 * @param mixed $text
 * @param mixed $terms
 * @return mixed
 */
function swiftboard_search_highlight($text, $terms) {
    if (empty($terms)) return $text;
    $terms = preg_quote($terms, '/');
    return preg_replace('/(' . $terms . ')/i', '<mark style="background:#fef3c7;padding:2px 4px;border-radius:3px;">\1</mark>', $text);
}
?><?php
/**
 * Security check: prevent direct access
 */
/**
 * Search results template
 *
 * @package SwiftBoard
 */

get_header();

// $wp_query est une globale de WordPress : sans cette declaration le template
// lisait une variable locale indefinie des qu'il etait inclus depuis une
// fonction (le cas dans les tests de rendu).
global $wp_query;
?>

<main id="primary" class="site-main" role="main" aria-label="<?php esc_attr_e('Résultats de recherche', 'swiftboard'); ?>">

    <header class="page-header" style="margin-bottom: var(--space-md);">
        <h1 class="page-title">
            <?php printf(
                esc_html__('Résultats pour : %s', 'swiftboard'),
                '<span class="search-query">' . esc_html( get_search_query() ) . '</span>'
            ); ?>
        </h1>
        <p class="search-count" style="color: var(--color-text-muted); font-size: var(--font-size-sm);">
            <?php printf(
                esc_html(_n('%d résultat trouvé', '%d résultats trouvés', (int) $wp_query->found_posts, 'swiftboard')),
                (int) $wp_query->found_posts
            ); ?>
        </p>
    </header>

<!-- M-8: Filtres de recherche -->
<div class="sb-search-filters" style="background:#fff;border:1px solid #edeff1;border-radius:8px;padding:12px 16px;margin-bottom:16px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
    <form method="get" action="<?php echo esc_url(home_url('/')); ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;flex:1;">
        <input type="hidden" name="s" value="<?php echo esc_attr(get_search_query()); ?>">
        
        <label class="screen-reader-text" for="sb-filter-forum"><?php esc_html_e('Filtrer par forum', 'swiftboard'); ?></label>
        <select name="forum_id" id="sb-filter-forum" style="padding:6px 10px;border:1px solid #edeff1;border-radius:9999px;font-size:13px;">
            <option value=""><?php esc_html_e('Tous les forums', 'swiftboard'); ?></option>
            <?php
            $forums = get_posts(['post_type' => 'forum', 'post_status' => 'publish', 'numberposts' => -1]);
            $current_forum = isset($_GET['forum_id']) ? intval($_GET['forum_id']) : 0;
            foreach ($forums as $f) :
            ?>
                <option value="<?php echo esc_attr((string) $f->ID); ?>" <?php selected($current_forum, $f->ID); ?>>
                    <?php echo esc_html($f->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label class="screen-reader-text" for="sb-filter-date"><?php esc_html_e('Filtrer par date', 'swiftboard'); ?></label>
        <select name="date_filter" id="sb-filter-date" style="padding:6px 10px;border:1px solid #edeff1;border-radius:9999px;font-size:13px;">
            <option value=""><?php esc_html_e('Toutes les dates', 'swiftboard'); ?></option>
            <?php $current_date = isset($_GET['date_filter']) ? sanitize_text_field(wp_unslash($_GET['date_filter'])) : ''; ?>
            <option value="24h" <?php selected($current_date, '24h'); ?>><?php esc_html_e('24 dernières heures', 'swiftboard'); ?></option>
            <option value="7d" <?php selected($current_date, '7d'); ?>><?php esc_html_e('7 derniers jours', 'swiftboard'); ?></option>
            <option value="30d" <?php selected($current_date, '30d'); ?>><?php esc_html_e('30 derniers jours', 'swiftboard'); ?></option>
            <option value="365d" <?php selected($current_date, '365d'); ?>><?php esc_html_e('Cette année', 'swiftboard'); ?></option>
        </select>

        <label class="screen-reader-text" for="sb-filter-sort"><?php esc_html_e('Trier les résultats', 'swiftboard'); ?></label>
        <select name="sort" id="sb-filter-sort" style="padding:6px 10px;border:1px solid #edeff1;border-radius:9999px;font-size:13px;">
            <?php $current_sort = isset($_GET['sort']) ? sanitize_text_field(wp_unslash($_GET['sort'])) : 'relevance'; ?>
            <option value="relevance" <?php selected($current_sort, 'relevance'); ?>><?php esc_html_e('Pertinence', 'swiftboard'); ?></option>
            <option value="new" <?php selected($current_sort, 'new'); ?>><?php esc_html_e('Plus récents', 'swiftboard'); ?></option>
            <option value="old" <?php selected($current_sort, 'old'); ?>><?php esc_html_e('Plus anciens', 'swiftboard'); ?></option>
        </select>

        <button type="submit" class="btn-primary" style="padding:6px 16px;border-radius:9999px;font-size:13px;font-weight:600;background:#006cbd;color:#fff;border:none;cursor:pointer;">
            <?php esc_html_e('Filtrer', 'swiftboard'); ?>
        </button>
    </form>
</div>

<?php
// M-8: Apply filters to the query
add_filter('pre_get_posts', function($query) {
    if (!is_search() || !$query->is_main_query()) return $query;

    // Forum filter
    if (!empty($_GET['forum_id'])) {
        $forum_id = intval($_GET['forum_id']);
        $query->set('post_parent', $forum_id);
    }

    // Date filter
    if (!empty($_GET['date_filter'])) {
        $date_map = [
            '24h'  => '1 day ago',
            '7d'   => '7 days ago',
            '30d'  => '30 days ago',
            '365d' => '365 days ago',
        ];
        $date_filter = sanitize_text_field(wp_unslash($_GET['date_filter']));
        if (isset($date_map[$date_filter])) {
            $query->set('date_query', [
                ['column' => 'post_date', 'after' => $date_map[$date_filter]],
            ]);
        }
    }

    // Sort filter
    if (!empty($_GET['sort'])) {
        $sort = sanitize_text_field(wp_unslash($_GET['sort']));
        if ($sort === 'new') {
            $query->set('orderby', 'date');
            $query->set('order', 'DESC');
        } elseif ($sort === 'old') {
            $query->set('orderby', 'date');
            $query->set('order', 'ASC');
        }
    }

    return $query;
});
?>


    <?php if (have_posts()) : ?>
        <div class="search-results-list">
            <?php while (have_posts()) : the_post(); ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class('post-item'); ?>>
                    <div class="post-body" style="padding: 0 var(--space-md);">
                        <h2 class="entry-title">
                            <a href="<?php the_permalink(); ?>" rel="bookmark">
                                <?php echo swiftboard_search_highlight(get_the_title(), get_search_query());; ?>
                            </a>
                        </h2>
                        <div class="post-meta">
                            <time datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                                <?php echo esc_html(swiftboard_time_ago(get_the_date('c'))); ?>
                            </time>
                            <?php if (function_exists('bbp_get_topic_post_type') && get_post_type() === bbp_get_topic_post_type()) : ?>
                                <span class="post-type-badge forum-badge"><?php esc_html_e('Sujet du forum', 'swiftboard'); ?></span>
                            <?php elseif (function_exists('bbp_get_reply_post_type') && get_post_type() === bbp_get_reply_post_type()) : ?>
                                <span class="post-type-badge forum-badge"><?php esc_html_e('Réponse du forum', 'swiftboard'); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="entry-summary">
                            <?php echo swiftboard_search_highlight(get_the_excerpt(), get_search_query());; ?>
                        </div>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>

        <?php
        the_posts_pagination([
            'prev_text' => '<span role="img" aria-label="' . esc_attr__('Page précédente', 'swiftboard') . '">←</span>',
            'next_text' => '<span role="img" aria-label="' . esc_attr__('Page suivante', 'swiftboard') . '">→</span>',
        ]);
        ?>

    <?php else : ?>
        <div class="no-results">
            <h2 style="margin-bottom: var(--space-sm);"><?php esc_html_e('Aucun résultat trouvé', 'swiftboard'); ?></h2>
            <p><?php esc_html_e('Essayez une autre recherche.', 'swiftboard'); ?></p>
            <?php get_search_form( array( 'aria_label' => __( 'Rechercher dans les résultats', 'swiftboard' ) ) ); ?>
        </div>
    <?php endif; ?>

</main>

<?php get_footer(); ?>

