<?php
/**
 * SwiftBoard — Front Page (Home)
 *
 * Layout Reddit-like 2 colonnes :
 *  - Gauche (main) : feed mixte (topics chauds + derniers articles de blog)
 *  - Droite (sidebar) : about site + hot topics + top répondeurs + règles
 *
 * Hero en haut : titre + description + stats forum + CTA
 *
 * @package SwiftBoard
 */

if (!defined('ABSPATH')) exit;

get_header();

// Récupérer les données
$site_name = get_bloginfo('name');
$site_desc = get_bloginfo('description');

// Aperçu image de carte : miniature mise en avant, sinon première image du contenu.
// Le bloc image est rendu séparément du texte par le markup de la carte.
$sb_get_preview_image = static function ( int $post_id ): string {
    $thumbnail = get_the_post_thumbnail_url( $post_id, 'medium_large' );
    if ( $thumbnail ) {
        return (string) $thumbnail;
    }
    $content = (string) get_post_field( 'post_content', $post_id );
    if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $match ) ) {
        return esc_url_raw( $match[1] );
    }
    return '';
};

// v5.3 (EXI-FEED-01) : les compteurs du hero (sujets/reponses/membres/forums)
// n'etaient affiches que dans la banniere, qui est desormais compacte et sans
// stats. Le calcul (4 aggregations COUNT toutes les 5 min, cf. EXI-SCALE-03)
// est supprime : plus aucun appel wp_count_posts() sur l'accueil.

// v5.3.1 — tri du feed d'accueil (memes boutons que partout ailleurs)
$home_sort = isset($_GET['sort']) ? sanitize_key(wp_unslash($_GET['sort'])) : 'hot';
if (!in_array($home_sort, ['hot', 'new', 'top', 'rising'], true)) {
    $home_sort = 'hot';
}

// Hot topics pour la sidebar et le flux principal.
// Le flux principal reçoit assez de lignes pour assurer sa pagination locale ;
// la sidebar reste limitée à huit éléments.
$sb_page = max(1, (int) ($_GET['sb_paged'] ?? 1));
$sb_per_page = 15;
$hot_topics = function_exists('swiftboard_get_hot_topics') ? swiftboard_get_hot_topics('all', 8) : [];
$hot_feed_topics = function_exists('swiftboard_get_hot_topics') ? swiftboard_get_hot_topics('all', $sb_per_page * 3) : [];

// Normalise les sujets WordPress afin que chaque mode de tri utilise la même
// structure. Les modes sont volontairement distincts :
// hot = score Reddit (votes + réponses pondérées), top = votes nets,
// rising = activité récente, new = date de publication.
$sb_build_feed_topics = static function ($posts) {
    $topics = [];
    foreach ($posts as $sb_t) {
        $sb_f = get_post($sb_t->post_parent);
        $votes = function_exists('swiftboard_get_vote_count') ? (int) swiftboard_get_vote_count($sb_t->ID) : (int) get_post_meta($sb_t->ID, '_swiftboard_vote_score', true);
        $replies = function_exists('bbp_get_topic_reply_count') ? (int) bbp_get_topic_reply_count($sb_t->ID, true) : 0;
        $activity = get_post_meta($sb_t->ID, '_bbp_last_active_time', true) ?: $sb_t->post_modified;
        $age_hours = max(1, (current_time('timestamp') - (int) get_post_timestamp($sb_t->ID)) / HOUR_IN_SECONDS);
        $hot_score = $votes + ($replies * 2);
        $topics[] = [
            'id' => (int) $sb_t->ID,
            'title' => $sb_t->post_title,
            'url' => get_permalink($sb_t->ID),
            'author_id' => (int) $sb_t->post_author,
            'author_name' => get_the_author_meta('display_name', $sb_t->post_author),
            'date' => $sb_t->post_date,
            'activity_date' => $activity,
            'time_ago' => function_exists('swiftboard_time_ago') ? swiftboard_time_ago(get_the_date('c', $sb_t->ID)) : '',
            'vote_score' => $votes,
            'reply_count' => $replies,
            'hot_score' => $hot_score,
            // Rising favorise l’engagement récent plutôt qu’un simple total
            // de votes : score chaud amorti par l’âge du sujet.
            'rising_score' => $hot_score / sqrt($age_hours),
            'forum_name' => $sb_f ? $sb_f->post_title : '',
            'forum_url' => $sb_f ? get_permalink($sb_f->ID) : '',
            'forum_id' => $sb_f ? (int) $sb_f->ID : 0,
            'excerpt' => wp_trim_words(wp_strip_all_tags($sb_t->post_content), 30, '…'),
        ];
    }
    return $topics;
};

if ($home_sort === 'hot') {
    $feed_topics = $hot_feed_topics;
} elseif ($home_sort === 'new') {
    $feed_topics = $sb_build_feed_topics(get_posts([
        'post_type' => 'topic', 'post_status' => 'publish',
        'posts_per_page' => $sb_per_page * 3, 'orderby' => 'date', 'order' => 'DESC',
    ]));
} else {
    // top/rising travaillent sur le même univers complet puis appliquent
    // chacun sa clé de classement : aucune paire d’onglets ne partage un
    // fallback silencieux vers hot.
    $feed_topics = $sb_build_feed_topics(get_posts([
        'post_type' => 'topic', 'post_status' => 'publish',
        'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'DESC',
    ]));
    usort($feed_topics, static function ($a, $b) use ($home_sort) {
        if ($home_sort === 'top') {
            $key_a = (int) $a['vote_score'];
            $key_b = (int) $b['vote_score'];
        } else {
            $key_a = (float) ($a['rising_score'] ?? 0);
            $key_b = (float) ($b['rising_score'] ?? 0);
        }
        return $key_a === $key_b ? ((int) $b['id'] <=> (int) $a['id']) : ($key_b <=> $key_a);
    });
}

// Anti-N+1 : précharger les auteurs utilisés par les deux surfaces.
$sb_hot_author_ids = array_unique(array_filter(wp_list_pluck(array_merge($hot_topics, $feed_topics), 'author_id')));
if (!empty($sb_hot_author_ids)) {
    cache_users($sb_hot_author_ids);
}

// Top répondeurs
$weekly_top = function_exists('swiftboard_get_weekly_top') ? swiftboard_get_weekly_top() : ['top' => []];

// Derniers articles de blog
$blog_posts = new WP_Query([
    'post_type' => 'post',
    'post_status' => 'publish',
    'posts_per_page' => 4,
    'orderby' => 'date',
    'order' => 'DESC',
]);

// Mélanger topics + blog posts par date
$mixed_feed = [];

if (!empty($feed_topics)) {
    foreach ($feed_topics as $t) {
        $mixed_feed[] = [
            'type' => 'topic',
            'id' => $t['id'],
            'title' => $t['title'],
            'url' => $t['url'],
            'author' => $t['author_name'],
            'author_id' => $t['author_id'],
            'date' => $t['date'],
            'time_ago' => $t['time_ago'],
            'votes' => $t['vote_score'],
            'replies' => $t['reply_count'],
            'forum_name' => $t['forum_name'],
            'forum_url' => $t['forum_url'],
            'forum_id' => $t['forum_id'] ?? 0,
            'excerpt' => $t['excerpt'] ?? '',
            'views' => (int) get_post_meta($t['id'], '_bbp_voice_count', true),
            'hot_score' => (int) ($t['hot_score'] ?? (($t['vote_score'] ?? 0) + (($t['reply_count'] ?? 0) * 2))),
            'rising_score' => (float) ($t['rising_score'] ?? 0),
            'activity_date' => $t['activity_date'] ?? $t['date'],
            'image' => $sb_get_preview_image( (int) $t['id'] ),
        ];
    }
}

// D5 : Anti-N+1 — précharger les auteurs des blog posts en un seul lot.
if ($blog_posts->have_posts()) {
    $blog_author_ids = array_unique( array_filter( wp_list_pluck( $blog_posts->posts, 'post_author' ) ) );
    if ( ! empty( $blog_author_ids ) ) {
        cache_users( $blog_author_ids );
    }
    while ($blog_posts->have_posts()) {
        $blog_posts->the_post();
        $mixed_feed[] = [
            'type' => 'blog',
            'id' => get_the_ID(),
            'title' => get_the_title(),
            'url' => get_permalink(),
            'author' => get_the_author_meta('display_name'),
            'date' => get_the_date('c'),
            'time_ago' => function_exists('swiftboard_time_ago') ? swiftboard_time_ago(strtotime(get_post_field('post_date'))) : '',
            'votes' => function_exists('swiftboard_get_vote_count') ? swiftboard_get_vote_count(get_the_ID()) : 0,
            'replies' => get_comments_number(),
            'hot_score' => (int) (function_exists('swiftboard_get_vote_count') ? swiftboard_get_vote_count(get_the_ID()) : 0),
            'activity_date' => get_post_modified_time('c'),
            'forum_name' => 'Blog',
            'forum_url' => home_url('/blog/'),
            'excerpt' => wp_trim_words(wp_strip_all_tags(get_the_content()), 30, '…'),
            'image' => $sb_get_preview_image( (int) get_the_ID() ),
        ];
    }
    wp_reset_postdata();
}

// Trier le mix selon la clé propre à l’onglet sélectionné.
usort($mixed_feed, function ($a, $b) use ($home_sort) {
    if ($home_sort === 'new') {
        $key_a = strtotime($a['date']);
        $key_b = strtotime($b['date']);
    } elseif ($home_sort === 'rising') {
        $key_a = (float) ($a['rising_score'] ?? 0);
        $key_b = (float) ($b['rising_score'] ?? 0);
    } elseif ($home_sort === 'top') {
        $key_a = (int) $a['votes'];
        $key_b = (int) $b['votes'];
    } else {
        $key_a = (int) ($a['hot_score'] ?? $a['votes']);
        $key_b = (int) ($b['hot_score'] ?? $b['votes']);
    }
    if ($key_a !== $key_b) {
        return $key_b <=> $key_a;
    }
    $date_compare = strtotime($b['date']) <=> strtotime($a['date']);
    return $date_compare ?: ((int) $b['id'] <=> (int) $a['id']);
});

// Pagination : slice selon la page courante (pas de limite artificielle)
$sb_total_feed = count($mixed_feed);
$mixed_feed = array_slice($mixed_feed, ($sb_page - 1) * $sb_per_page, $sb_per_page);
$sb_total_pages = max(1, (int) ceil($sb_total_feed / $sb_per_page));

// Forum URL
$forum_url = function_exists('bbp_get_forums_url') ? bbp_get_forums_url() : home_url('/?post_type=forum');
?>

<div class="sb-home">

    <!-- ============================================================================
         BANNIERE COMPACTE — anonymes uniquement (EXI-FEED-01, v5.3)
         Un membre connecté arrive directement sur le feed (comme Reddit).
         Sans risque pour le cache de pages : il ne sert que les anonymes
         (inc/page-cache.php exclut is_user_logged_in()).
         ============================================================================ -->
    <?php if (!is_user_logged_in()): ?>
    <section class="sb-home-hero-compact" aria-labelledby="sb-home-hero-title">
        <div class="sb-home-hero-compact-inner">
            <div class="sb-home-hero-brand">
                <?php
                if (has_custom_logo()) {
                    the_custom_logo();
                } else {
                    echo '<span class="sb-home-hero-logo-icon" aria-hidden="true">' . esc_html(strtoupper(substr($site_name, 0, 1))) . '</span>';
                }
                ?>
                <div class="sb-home-hero-tagline">
                    <strong id="sb-home-hero-title" class="sb-home-hero-name"><?php echo esc_html($site_name); ?></strong>
                    <?php if ($site_desc): ?>
                    <span class="sb-home-hero-desc"><?php echo esc_html($site_desc); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="sb-home-hero-actions">
                <a href="<?php echo esc_url(wp_login_url()); ?>" class="sb-home-btn-secondary">Connexion</a>
                <?php if (get_option('users_can_register')): ?>
                <a href="<?php echo esc_url(wp_registration_url()); ?>" class="sb-home-btn-primary" data-open-onboarding="true">S'inscrire</a>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============================================================================
         LAYOUT 2 COLONNES
         ============================================================================ -->
    <div class="sb-home-container">

        <!-- ============================================================================
             NAVIGATION LATERALE (gauche) — parite Reddit
             Colonne de navigation persistante : Accueil, Populaires, Nouveautes,
             Explorer, plus la liste des forums. Sur mobile elle devient un rail
             horizontal defilable (voir reddit-refonte.css) : jamais masquee.
             ============================================================================ -->
        <?php
        // Navigation laterale mutualisee (voir inc/nav-lateral.php) : la page
        // d'accueil et les pages de forum partagent le meme balisage.
        swiftboard_render_nav_laterale();
        ?>

        <!-- ============================================================================
             FEED PRINCIPAL (centre)
             ============================================================================ -->
        <main class="sb-home-main">

            <!-- En-tête du feed : le H1 vit ici désormais (EXI-FEED-01).
                 Avant v5.3 il était dans la bannière — qui n'existe plus
                 pour les membres connectés. -->
            <?php
            $sb_feed_titles = [
                'hot'    => __('Tendances', 'swiftboard'),
                'new'    => __('Nouveau', 'swiftboard'),
                'top'    => __('Top', 'swiftboard'),
                'rising' => __('Rising', 'swiftboard'),
            ];
            ?>
            <div class="sb-home-feed-head">
                <h1 class="sb-home-feed-title"><?php esc_html_e('Discussions', 'swiftboard'); ?></h1>
                <nav class="sb-home-feed-links" aria-label="<?php esc_attr_e('Navigation rapide', 'swiftboard'); ?>">
                    <a href="<?php echo esc_url($forum_url); ?>"><?php esc_html_e('Tous les forums', 'swiftboard'); ?></a>
                    <a href="<?php echo esc_url(home_url('/blog/')); ?>"><?php esc_html_e('Blog', 'swiftboard'); ?></a>
                </nav>
            </div>

            <!-- Sort bar unifiée (v5.3.1) : memes boutons que les pages forum,
                 sans filtre periode, hot par defaut. -->
            <div class="sb-sort-bar">
                <div class="sb-sort-tabs" role="navigation" aria-label="<?php esc_attr_e('Trier les sujets', 'swiftboard'); ?>">
                    <?php foreach ($sb_feed_titles as $sb_s => $sb_label): ?>
                    <a href="<?php echo esc_url(home_url('/?sort=' . $sb_s)); ?>"
                       class="sb-sort-tab <?php echo $home_sort === $sb_s ? 'active' : ''; ?>"
                       <?php echo $home_sort === $sb_s ? 'aria-current="page"' : ''; ?>><?php
                        // L'icone est un SVG statique : esc_html() la detruirait.
                        echo swiftboard_icon($sb_s, 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique.
                        ?><span><?php echo esc_html($sb_label); ?></span></a>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="sb-compact-view-toggle" data-compact-toggle data-view="compact" aria-pressed="false">
                    <span aria-hidden="true">▤</span>
                    <span><?php esc_html_e( 'Vue compacte', 'swiftboard' ); ?></span>
                </button>
            </div>

            <?php
            // Feed personnalisé « Mes subreddits » : affiché quand un membre est connecté.
            $sb_my_subreddits = [];
            if (is_user_logged_in() && function_exists('swiftboard_get_followed_subreddit_topics')) {
                $sb_my_topic_ids = swiftboard_get_followed_subreddit_topics(get_current_user_id(), 15);
                if (!empty($sb_my_topic_ids)) {
                    $sb_my_topics = get_posts([
                        'post_type'      => 'topic',
                        'post_status'    => 'publish',
                        'post__in'       => $sb_my_topic_ids,
                        'orderby'        => 'post__in',
                        'posts_per_page' => 15,
                    ]);
                    foreach ($sb_my_topics as $sb_t) {
                        $sb_forum = get_post($sb_t->post_parent);
                        $sb_my_subreddits[] = [
                            'type' => 'topic',
                            'id' => $sb_t->ID,
                            'title' => $sb_t->post_title,
                            'url' => get_permalink($sb_t->ID),
                            'author' => get_the_author_meta('display_name', $sb_t->post_author),
                            'author_id' => (int) $sb_t->post_author,
                            'date' => $sb_t->post_date,
                            'time_ago' => swiftboard_time_ago(get_the_date('c', $sb_t->ID)),
                            'votes' => function_exists('swiftboard_get_vote_count') ? swiftboard_get_vote_count($sb_t->ID) : 0,
                            'replies' => function_exists('bbp_get_topic_reply_count') ? bbp_get_topic_reply_count($sb_t->ID, true) : 0,
                            'forum_name' => $sb_forum ? $sb_forum->post_title : '',
                            'forum_url' => $sb_forum ? get_permalink($sb_forum->ID) : '',
                            'excerpt' => wp_trim_words(wp_strip_all_tags($sb_t->post_content), 25, '…'),
                            'image' => $sb_get_preview_image( (int) $sb_t->ID ),
                        ];
                    }
                }
            }
            ?>

            <?php if (!empty($sb_my_subreddits)): ?>
            <div class="sb-my-subreddits">
                <div class="sb-my-subreddits-header">
                    <h2 class="sb-my-subreddits-title">🎯 <?php esc_html_e('Mes subreddits', 'swiftboard'); ?></h2>
                </div>
                <?php foreach ($sb_my_subreddits as $item): ?>
                    <article class="sb-post-card sb-home-card sb-home-type-topic" id="topic-<?php echo esc_attr($item['id']); ?>">
                        <div class="sb-post-votes">
                            <button class="sb-vote-btn up" data-post-id="<?php echo esc_attr($item['id']); ?>" aria-label="Upvoter">
                                <svg class="sb-icon" width="18" height="18" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2.75 17.5 10.25H14V17.5H6V10.25H2.5L10 2.75z"/></svg>
                            </button>
                            <span class="sb-vote-count"><?php echo esc_html(swiftboard_format_count($item['votes'])); ?></span>
                            <button class="sb-vote-btn down" data-post-id="<?php echo esc_attr($item['id']); ?>" aria-label="Downvoter">
                                <svg class="sb-icon" width="18" height="18" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 17.25 2.5 9.75H6V2.5h8v7.25h3.5L10 17.25z"/></svg>
                            </button>
                        </div>
                        <div class="sb-post-body">
                            <div class="sb-post-meta-top">
                                <a href="<?php echo esc_url($item['forum_url']); ?>" class="sb-forum-pill">
                                    r/<?php echo esc_html($item['forum_name']); ?>
                                </a>
                                <span class="sb-meta-sep">·</span>
                                <?php
                                // Format Reddit : « [avatar] u/pseudo ». Voir inc/author-line.php.
                                $sb_ms_author_id = (int) ($item['author_id'] ?? 0);
                                swiftboard_render_author_line($sb_ms_author_id, $item['author'] ?? '');
                                if ($sb_ms_author_id && function_exists('swiftboard_display_grade_badge')) {
                                    swiftboard_display_grade_badge($sb_ms_author_id);
                                }
                                ?>
                                <span class="sb-meta-sep">·</span>
                                <span class="sb-post-time"><?php echo esc_html($item['time_ago']); ?></span>
                                <?php if (!empty($item['views'])): ?>
                                <span class="sb-meta-sep">·</span>
                                <span>👁 <?php echo esc_html(swiftboard_format_count($item['views'])); ?> vues</span>
                                <?php endif; ?>
                                <?php
                                // Bouton « Rejoindre » aligne a droite (voir inc/join-button.php).
                                if (function_exists('swiftboard_render_join_button')) {
                                    swiftboard_render_join_button((int) ($item['forum_id'] ?? 0));
                                }
                                ?>
                            </div>
                            <h2 class="sb-post-title"><a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['title']); ?></a></h2>
                            <div class="sb-post-content"><?php echo esc_html($item['excerpt']); ?></div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Feed items -->
            <?php if (!empty($mixed_feed)): ?>
                <?php foreach ($mixed_feed as $item): ?>
                    <article class="sb-post-card sb-home-card sb-home-type-<?php echo esc_attr($item['type']); ?>" id="<?php echo esc_attr($item['type']); ?>-<?php echo esc_attr($item['id']); ?>">
                        <div class="sb-post-votes">
                            <button class="sb-vote-btn up" data-post-id="<?php echo esc_attr($item['id']); ?>" aria-label="Upvoter">
                                <svg class="sb-icon" width="18" height="18" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2.75 17.5 10.25H14V17.5H6V10.25H2.5L10 2.75z"/></svg>
                            </button>
                            <span class="sb-vote-count"><?php echo esc_html(function_exists('swiftboard_format_count') ? swiftboard_format_count($item['votes']) : $item['votes']); ?></span>
                            <button class="sb-vote-btn down" data-post-id="<?php echo esc_attr($item['id']); ?>" aria-label="Downvoter">
                                <svg class="sb-icon" width="18" height="18" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 17.25 2.5 9.75H6V2.5h8v7.25h3.5L10 17.25z"/></svg>
                            </button>
                        </div>
                        <div class="sb-post-body">
                            <div class="sb-post-meta-top">
                                <a href="<?php echo esc_url($item['forum_url']); ?>" class="sb-forum-pill">
                                    r/<?php echo esc_html($item['forum_name']); ?>
                                </a>
                                <span class="sb-meta-sep">·</span>
                                <?php
                                // Repli si l'auteur est introuvable : un contenu importe ou
                                // migre peut avoir post_author = 0. Sans cela on affiche
                                // « Par · » suivi d'un vide, ce qui donne une page cassee.
                                $sb_author = trim((string) $item['author']);
                                if ('' === $sb_author) {
                                    $sb_author = __('Membre supprimé', 'swiftboard');
                                }
                                $sb_author_id = (int) ($item['author_id'] ?? 0);
                                ?>
                                <?php
                                swiftboard_render_author_line((int) $sb_author_id, $sb_author);
                                if ($sb_author_id && function_exists('swiftboard_display_grade_badge')) {
                                    swiftboard_display_grade_badge($sb_author_id);
                                }
                                ?>
                                <span class="sb-meta-sep">·</span>
                                <span class="sb-post-time"><?php echo esc_html($item['time_ago']); ?></span>
                                <?php if (!empty($item['views'])): ?>
                                <span class="sb-meta-sep">·</span>
                                <span>👁 <?php echo esc_html(swiftboard_format_count($item['views'])); ?> vues</span>
                                <?php endif; ?>
                                <?php if ($item['type'] === 'blog'): ?>
                                <span class="sb-post-flair sb-flair-blog"><?php echo swiftboard_icon('article', 13); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?> Article</span>
                                <?php else: ?>
                                <span class="sb-post-flair sb-flair-discussion"><?php echo swiftboard_icon('discuss', 13); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?> Discussion</span>
                                <?php endif; ?>
                                <?php
                                // Bouton « Rejoindre » aligne a droite (voir inc/join-button.php).
                                if (function_exists('swiftboard_render_join_button')) {
                                    swiftboard_render_join_button((int) ($item['forum_id'] ?? 0));
                                }
                                ?>
                            </div>
                            <h2 class="sb-post-title">
                                <a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['title']); ?></a>
                            </h2>
                            <?php if (!empty($item['excerpt'])): ?>
                            <div class="sb-post-content"><?php echo esc_html($item['excerpt']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($item['image'])): ?>
                            <a href="<?php echo esc_url($item['url']); ?>" class="sb-post-image-link">
                                <img class="sb-post-image" src="<?php echo esc_url($item['image']); ?>" alt="<?php echo esc_attr($item['title']); ?>" width="300" height="200" loading="lazy">
                            </a>
                            <?php endif; ?>
                            <?php
                            // Zone d'actions produite par UNE seule fonction
                            // (inc/ui-corrections.php). Auparavant dupliquee
                            // ici et dans inc/reddit-layout.php avec des
                            // boutons DIFFERENTS : « Cacher » n'existait que
                            // dans l'autre gabarit, et son handler JS pilotait
                            // un bouton jamais rendu sur l'accueil.
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML construit et echappe dans la fonction.
                            echo swiftboard_actions_carte_html(
                                (int) $item['id'],
                                $item['url'],
                                (int) $item['replies']
                            );
                            ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="sb-home-empty">
                    <h2>🎉 Bienvenue sur <?php echo esc_html($site_name); ?> !</h2>
                    <p>Le forum vient de démarrer. Soyez le premier à <a href="<?php echo esc_url($forum_url); ?>">créer un sujet</a> !</p>
                </div>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if ($sb_total_pages > 1): ?>
            <nav class="pagination sb-home-pagination">
                <?php
                echo paginate_links([
                    'base' => home_url('/?sort=' . $home_sort . '&sb_paged=%#%'),
                    'format' => '&sb_paged=%#%',
                    'current' => $sb_page,
                    'total' => $sb_total_pages,
                    'prev_text' => '‹ ' . __('Précédent', 'swiftboard'),
                    'next_text' => __('Suivant', 'swiftboard') . ' ›',
                ]);
                ?>
            </nav>
            <?php endif; ?>

        </main>

        <!-- ============================================================================
             SIDEBAR (droite)
             ============================================================================ -->
        <aside class="sb-home-sidebar">

            <!-- Subreddits populaires (forums + sous-forums) -->
            <?php
            $popular_subreddits = function_exists('swiftboard_get_popular_subreddits') ? swiftboard_get_popular_subreddits(10) : [];
            if (!empty($popular_subreddits)):
            ?>
            <div class="sb-sidebar-card">
                <div class="sb-sidebar-card-header sb-header-blue"><?php echo swiftboard_icon('groups', 14); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?> Subreddits populaires</div>
                <div class="sb-sidebar-card-body">
                    <?php foreach ($popular_subreddits as $sub):
                        $display_name = !empty($sub['parent_title'])
                            ? $sub['parent_title'] . '/' . $sub['title']
                            : $sub['title'];
                    ?>
                        <a href="<?php echo esc_url($sub['url']); ?>" class="sb-sidebar-forum-item">
                            <span class="sb-sidebar-forum-icon">r/</span>
                            <div class="sb-sidebar-forum-info">
                                <div class="sb-sidebar-forum-name"><?php echo esc_html($display_name); ?></div>
                                <div class="sb-sidebar-forum-count"><?php echo (int) $sub['topic_count']; ?> sujets</div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            

            <!-- Hot topics -->
            <?php if (!empty($hot_topics)): ?>
            <div class="sb-sidebar-card">
                <div class="sb-sidebar-card-header sb-header-blue"><?php echo swiftboard_icon('flame', 14); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?> Sujets chauds</div>
                <div class="sb-sidebar-card-body">
                    <?php foreach (array_slice($hot_topics, 0, 5) as $i => $t): ?>
                        <div class="sb-sidebar-hot-item">
                            <span class="sb-sidebar-hot-rank <?php echo $i < 3 ? 'rank-' . ($i + 1) : ''; ?>"><?php echo $i + 1; ?></span>
                            <div>
                                <a href="<?php echo esc_url($t['url']); ?>" class="sb-sidebar-hot-title"><?php echo esc_html($t['title']); ?></a>
                                <div class="sb-sidebar-hot-meta"><?php echo swiftboard_icon('top', 12); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?> <?php echo (int) $t['vote_score']; ?> · <?php echo swiftboard_icon('discuss', 12); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?> <?php echo (int) $t['reply_count']; ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            

            <!-- Sujets récents -->
            <?php
            $recent_topics = get_posts([
                'post_type'      => 'topic',
                'post_status'    => 'publish',
                'posts_per_page' => 5,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]);
            if (!empty($recent_topics)):
            ?>
            <div class="sb-sidebar-card">
                <div class="sb-sidebar-card-header sb-header-blue"><?php echo swiftboard_icon('recent', 14); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?> Récents</div>
                <div class="sb-sidebar-card-body">
                    <?php foreach ($recent_topics as $rt): ?>
                        <div class="sb-sidebar-hot-item">
                            <div>
                                <a href="<?php echo esc_url(get_permalink($rt->ID)); ?>" class="sb-sidebar-hot-title"><?php echo esc_html($rt->post_title); ?></a>
                                <div class="sb-sidebar-hot-meta"><?php echo swiftboard_icon('recent', 12); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?> <?php echo esc_html(swiftboard_time_ago(get_the_date('c', $rt->ID))); ?> · <?php echo swiftboard_icon('discuss', 12); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?> <?php echo esc_html(function_exists('bbp_get_topic_reply_count') ? bbp_get_topic_reply_count($rt->ID, true) : 0); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            

            <!-- Top répondeurs -->
            <?php if (!empty($weekly_top['top'])): ?>
            <div class="sb-sidebar-card">
                <div class="sb-sidebar-card-header sb-header-blue"><?php echo swiftboard_icon('top', 14); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?> Top répondeurs</div>
                <div class="sb-sidebar-card-body">
                    <?php foreach ($weekly_top['top'] as $r):
                        $u = get_userdata($r['user_id']);
                        if (!$u) continue;
                        $medals = ['🥇', '🥈', '🥉'];
                    ?>
                        <div class="sb-sidebar-top-item">
                            <span class="sb-sidebar-top-rank"><?php echo $medals[$r['rank'] - 1] ?? '#' . $r['rank']; ?></span>
                            <span class="sb-sidebar-top-avatar"><?php echo esc_html(strtoupper(substr($u->display_name, 0, 1))); ?></span>
                            <div class="sb-sidebar-top-info">
                                <div class="sb-sidebar-top-name"><?php echo esc_html($u->display_name); ?></div>
                                <div class="sb-sidebar-top-count"><?php echo (int) $r['count']; ?> réponses</div>
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
                <a href="<?php echo esc_url($forum_url); ?>"><?php esc_html_e('Forum', 'swiftboard'); ?></a> ·
                <a href="<?php echo esc_url(home_url('/wp-login.php')); ?>"><?php esc_html_e('Connexion', 'swiftboard'); ?></a>
            </div>

        </aside>

    </div>
</div>

<!-- v4.6.2 : CSS home déplacé vers assets/css/main.css (fix W3C <style> dans <div>) -->

<?php get_footer(); ?>

