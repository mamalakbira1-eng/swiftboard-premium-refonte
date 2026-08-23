<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Forum unique (subreddit)
 *
 * Layout 2 colonnes façon Reddit :
 *  - Gauche : header r/Forum + bouton Rejoindre + sort bar + cards
 *  - Droite : About + Subreddits de la même catégorie + Sujets chauds
 *
 * @package SwiftBoard
 */
?>

<?php get_header(); ?>

<?php $forum_id = bbp_get_forum_id(); ?>
<?php $forum_title = bbp_get_forum_title($forum_id); ?>
<?php $forum_url = get_permalink($forum_id); ?>
<?php $forum_content = bbp_get_forum_content($forum_id); ?>
<?php $forum_topics = bbp_get_forum_topic_count($forum_id); ?>
<?php $forum_replies = bbp_get_forum_reply_count($forum_id); ?>

<?php
// Sort
$forum_sort = isset($_GET['sort']) ? sanitize_key(wp_unslash($_GET['sort'])) : 'new';
if (!in_array($forum_sort, ['hot', 'new', 'top', 'rising'], true)) {
    $forum_sort = 'new';
}

// Pagination
$sb_page = max(1, (int) ($_GET['paged'] ?? get_query_var('paged') ?: 1));
$sb_per_page = 15;

// Récupérer les topics de ce forum (+ sous-forums si c'est un forum parent)
$child_forums = get_posts([
    'post_type' => 'forum',
    'post_status' => 'publish',
    'post_parent' => $forum_id,
    'numberposts' => -1,
    'fields' => 'ids',
]);
$forum_ids = array_merge([$forum_id], $child_forums);

$topic_args = [
    'post_type'      => 'topic',
    'post_status'    => 'publish',
    'post_parent__in' => $forum_ids,
    'posts_per_page' => $sb_per_page,
    'paged'          => $sb_page,
];

if ($forum_sort === 'new') {
    $topic_args['orderby'] = 'date';
    $topic_args['order'] = 'DESC';
} elseif ($forum_sort === 'top' || $forum_sort === 'hot') {
    $topic_args['orderby'] = 'meta_value_num';
    $topic_args['meta_key'] = '_swiftboard_vote_score';
    $topic_args['order'] = 'DESC';
} else {
    $topic_args['orderby'] = 'date';
    $topic_args['order'] = 'DESC';
}

// Anti-N+1: sera fait apres have_posts
$topics_query = new WP_Query($topic_args);
$sb_total_pages = max(1, (int) $topics_query->max_num_pages);

// Subreddits de la même catégorie (forums frères)
$parent_forum = wp_get_post_parent_id($forum_id);
if ($parent_forum) {
    $siblings = get_posts([
        'post_type' => 'forum',
        'post_status' => 'publish',
        'post_parent' => $parent_forum,
        'posts_per_page' => 10,
        'exclude' => [$forum_id],
    ]);
} else {
    // Forum racine : afficher les sous-forums
    $siblings = get_posts([
        'post_type' => 'forum',
        'post_status' => 'publish',
        'post_parent' => $forum_id,
        'posts_per_page' => 10,
    ]);
}

// Hot topics global pour sidebar
$hot_topics = function_exists('swiftboard_get_hot_topics') ? swiftboard_get_hot_topics('all', 5) : [];
?>

<div class="sb-home">

    <!-- ============================================================================
         EN-TETE DE COMMUNAUTE — pleine largeur
         Reference : reference/sub-desktop-anonyme.png. Chez Reddit la banniere
         couvre le feed ET la colonne de droite ; les actions (Creer / Rejoindre)
         se posent au-dessus de la sidebar, pas dans le feed. Le hero vit donc
         HORS de .sb-home-container, qui ne porte que les trois colonnes.
         ============================================================================ -->
    <header class="sb-subreddit-hero">
        <div class="sb-subreddit-banner" aria-hidden="true"></div>
        <div class="sb-subreddit-bar">
            <div class="sb-subreddit-identity">
                <span class="sb-subreddit-avatar" aria-hidden="true"><?php
                    echo esc_html( mb_strtoupper( mb_substr( $forum_title, 0, 1 ) ) );
                ?></span>
                <h1 class="sb-subreddit-title">
                    <span class="sb-subreddit-r">r/</span><?php echo esc_html($forum_title); ?>
                </h1>
            </div>
            <div class="sb-subreddit-actions">
                <?php if ( is_user_logged_in() && function_exists('bbp_get_forum_permalink') ) : ?>
                    <a class="sb-r-btn-ghost" href="<?php echo esc_url( get_permalink($forum_id) . '#new-post' ); ?>">
                        <?php echo swiftboard_icon('new', 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
                        <?php esc_html_e('Créer une publication', 'swiftboard'); ?>
                    </a>
                <?php endif; ?>
                <?php if (function_exists('swiftboard_subreddit_join_button')) echo swiftboard_subreddit_join_button($forum_id); ?>
            </div>
        </div>

        <?php
        // Onglets « Flux / À propos » — parite Reddit. Sur les petits ecrans
        // la colonne « A propos » passe sous le feed : ces onglets permettent
        // d'y acceder directement sans faire defiler toute la page.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture d'un parametre d'affichage public.
        $sb_vue = ( isset( $_GET['vue'] ) && 'apropos' === sanitize_key( wp_unslash( $_GET['vue'] ) ) ) ? 'apropos' : 'flux';
        ?>
        <nav class="sb-forum-tabs" aria-label="<?php esc_attr_e( 'Vues de la communauté', 'swiftboard' ); ?>">
            <a class="sb-forum-tab<?php echo ( 'flux' === $sb_vue ) ? ' active' : ''; ?>"
               href="<?php echo esc_url( get_permalink( $forum_id ) ); ?>"
               <?php echo ( 'flux' === $sb_vue ) ? 'aria-current="page"' : ''; ?>>
                <?php esc_html_e( 'Flux', 'swiftboard' ); ?>
            </a>
            <a class="sb-forum-tab<?php echo ( 'apropos' === $sb_vue ) ? ' active' : ''; ?>"
               href="<?php echo esc_url( add_query_arg( 'vue', 'apropos', get_permalink( $forum_id ) ) ); ?>"
               <?php echo ( 'apropos' === $sb_vue ) ? 'aria-current="page"' : ''; ?>>
                <?php esc_html_e( 'À propos', 'swiftboard' ); ?>
            </a>
        </nav>
    </header>

    <div class="sb-home-container<?php echo ( 'apropos' === $sb_vue ) ? ' is-vue-apropos' : ''; ?>">

        <?php
        // Meme navigation laterale que la page d'accueil : un visiteur ne doit
        // pas perdre ses reperes en entrant dans une communaute.
        swiftboard_render_nav_laterale( $forum_id );
        ?>

        <!-- FEED PRINCIPAL (centre) -->
        <main class="sb-home-main">



            <!-- Sort bar -->
            <div class="sb-sort-bar">
                <div class="sb-sort-tabs" role="navigation" aria-label="<?php esc_attr_e('Trier les sujets', 'swiftboard'); ?>">
                    <?php
                    $sb_sort_labels = [
                        'new'    => __('Nouveau', 'swiftboard'),
                        'hot'    => __('Tendances', 'swiftboard'),
                        'top'    => __('Top', 'swiftboard'),
                        'rising' => __('Rising', 'swiftboard'),
                    ];
                    foreach ($sb_sort_labels as $sb_s => $sb_label):
                    ?>
                    <a href="<?php echo esc_url(add_query_arg('sort', $sb_s)); ?>"
                       class="sb-sort-tab <?php echo $forum_sort === $sb_s ? 'active' : ''; ?>"
                       <?php echo $forum_sort === $sb_s ? 'aria-current="page"' : ''; ?>><?php
                        echo swiftboard_icon($sb_s, 16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique.
                        ?><span><?php echo esc_html($sb_label); ?></span></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Topics -->
            <?php if ($topics_query->have_posts()): ?>
                <?php
                // D5 : Anti-N+1 — précharger les auteurs des topics en un seul lot.
                $sb_forum_author_ids = array_unique( array_filter( wp_list_pluck( $topics_query->posts, 'post_author' ) ) );
                if ( ! empty( $sb_forum_author_ids ) ) {
                    cache_users( $sb_forum_author_ids );
                }
                ?>
                <?php while ($topics_query->have_posts()): $topics_query->the_post();
                    $topic_id = get_the_ID();
                    $author_id = (int) get_post_field('post_author', $topic_id);
                    $author_name = get_the_author_meta('display_name', $author_id);
                    $vote_count = function_exists('swiftboard_get_vote_count') ? swiftboard_get_vote_count($topic_id) : 0;
                    $reply_count = function_exists('bbp_get_topic_reply_count') ? bbp_get_topic_reply_count($topic_id, true) : 0;
                    $has_image = get_post_meta($topic_id, '_swiftboard_has_image', true);
                    $image_url = get_post_meta($topic_id, '_swiftboard_image_url', true);
                ?>
                    <article class="sb-post-card sb-home-card sb-home-type-topic" id="topic-<?php echo esc_attr((string) $topic_id); ?>">
                        <div class="sb-post-votes">
                            <button class="sb-vote-btn up" data-post-id="<?php echo esc_attr((string) $topic_id); ?>" aria-label="Upvoter">
                                <svg class="sb-icon" width="18" height="18" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2.75 17.5 10.25H14V17.5H6V10.25H2.5L10 2.75z"/></svg>
                            </button>
                            <span class="sb-vote-count"><?php echo esc_html(swiftboard_format_count($vote_count)); ?></span>
                            <button class="sb-vote-btn down" data-post-id="<?php echo esc_attr((string) $topic_id); ?>" aria-label="Downvoter">
                                <svg class="sb-icon" width="18" height="18" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 17.25 2.5 9.75H6V2.5h8v7.25h3.5L10 17.25z"/></svg>
                            </button>
                        </div>
                        <div class="sb-post-body">
                            <div class="sb-post-meta-top">
                                <?php
                                swiftboard_render_author_line((int) $author_id, $author_name);
                                if ($author_id && function_exists('swiftboard_display_grade_badge')) swiftboard_display_grade_badge($author_id);
                                ?>
                                <span class="sb-meta-sep">·</span>
                                <span class="sb-post-time"><?php echo esc_html(swiftboard_time_ago(get_the_date('c'))); ?></span>
                                <span class="sb-meta-sep">·</span>
                                <span><?php echo swiftboard_icon('explore', 12); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?> <?php echo esc_html(swiftboard_format_count((int) get_post_meta($topic_id, '_bbp_voice_count', true))); ?> vues</span>
                                <span class="sb-post-flair sb-flair-discussion"><?php echo swiftboard_icon('discuss', 13); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?> Discussion</span>
                            </div>
                            <h2 class="sb-post-title">
                                <a href="<?php echo esc_url(get_permalink($topic_id)); ?>"><?php echo esc_html(get_the_title()); ?></a>
                            </h2>
                            <div class="sb-post-content"><?php echo esc_html(wp_trim_words(wp_strip_all_tags(get_the_content()), 30, '…')); ?></div>
                            <?php if ($has_image && $image_url): ?>
                            <a href="<?php echo esc_url(get_permalink($topic_id)); ?>" class="sb-post-image-link">
                                <img class="sb-post-image" src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" loading="lazy">
                            </a>
                            <?php endif; ?>
                            <?php
                            if (function_exists('swiftboard_actions_carte_html')) {
                                echo swiftboard_actions_carte_html((int) $topic_id, get_permalink($topic_id), (int) $reply_count);
                            }
                            ?>
                        </div>
                    </article>
                <?php endwhile; wp_reset_postdata(); ?>

                <!-- Pagination -->
                <?php if ($sb_total_pages > 1): ?>
                <nav class="pagination sb-home-pagination">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg('sort', $forum_sort) . '&paged=%#%',
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
                    <h2>🎉 <?php esc_html_e('Aucun sujet dans ce forum', 'swiftboard'); ?></h2>
                    <p><?php esc_html_e('Soyez le premier à créer un sujet dans cette communauté !', 'swiftboard'); ?></p>
                    <?php if (is_user_logged_in() && function_exists('bbp_current_user_can_access_create_topic_form') && bbp_current_user_can_access_create_topic_form()): ?>
                    <p style="margin-top: var(--space-md);">
                        <a href="#bbp-new-topic" class="btn-primary">📝 <?php esc_html_e('Créer le premier sujet', 'swiftboard'); ?></a>
                    </p>
                    <?php elseif (!is_user_logged_in()): ?>
                    <p style="margin-top: var(--space-md);">
                        <a href="<?php echo esc_url(wp_login_url($forum_url)); ?>" class="btn-primary"><?php esc_html_e('Connectez-vous pour créer un sujet', 'swiftboard'); ?></a>
                    </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Formulaire de création de sujet -->
            <?php if (is_user_logged_in() && function_exists('bbp_current_user_can_access_create_topic_form') && bbp_current_user_can_access_create_topic_form()): ?>
                <?php bbp_get_template_part('form', 'topic'); ?>
            <?php elseif (!is_user_logged_in()): ?>
                <div class="bbp-template-notice info" style="margin-top: var(--space-md);">
                    <?php printf(
                        esc_html__('Vous devez %s pour créer un sujet.', 'swiftboard'),
                        '<a href="' . esc_url(wp_login_url($forum_url)) . '">' . esc_html__('vous connecter', 'swiftboard') . '</a>'
                    ); ?>
                </div>
            <?php endif; ?>

        </main>

        <!-- SIDEBAR (droite) -->
        <aside class="sb-home-sidebar">

            <!-- ====================================================================
                 A PROPOS — parite Reddit : description, date de creation,
                 visibilite, stats en gros chiffres, favoris et regles.
                 ==================================================================== -->
            <div class="sb-sidebar-card sb-about-card">
                <div class="sb-sidebar-card-body">
                    <h2 class="sb-about-title"><?php echo esc_html($forum_title); ?></h2>
                    <?php if ($forum_content): ?>
                        <div class="sb-about-desc"><?php echo wp_kses_post($forum_content); ?></div>
                    <?php endif; ?>

                    <ul class="sb-about-facts">
                        <li>
                            <?php echo swiftboard_icon('recent', 14); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
                            <?php
                            printf(
                                /* translators: %s: date de creation du forum. */
                                esc_html__( 'Créée le %s', 'swiftboard' ),
                                esc_html( get_the_date( 'j M Y', $forum_id ) )
                            );
                            ?>
                        </li>
                        <li>
                            <?php echo swiftboard_icon('explore', 14); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
                            <?php
                            // Un forum bbPress prive/cache ne doit pas etre annonce « Public ».
                            $sb_visibilite = ( function_exists('bbp_is_forum_public') && ! bbp_is_forum_public($forum_id) )
                                ? __( 'Privée', 'swiftboard' )
                                : __( 'Publique', 'swiftboard' );
                            echo esc_html( $sb_visibilite );
                            ?>
                        </li>
                    </ul>

                    <div class="sb-about-stats">
                        <div class="sb-about-stat">
                            <strong><?php echo esc_html(swiftboard_format_count($forum_topics)); ?></strong>
                            <span><?php esc_html_e('Sujets', 'swiftboard'); ?></span>
                        </div>
                        <div class="sb-about-stat">
                            <strong><?php echo esc_html(swiftboard_format_count($forum_replies)); ?></strong>
                            <span><?php esc_html_e('Réponses', 'swiftboard'); ?></span>
                        </div>
                        <?php if (function_exists('swiftboard_subreddit_member_count')): ?>
                        <div class="sb-about-stat">
                            <strong><?php echo esc_html(swiftboard_subreddit_member_count($forum_id)); ?></strong>
                            <span><?php esc_html_e('Membres', 'swiftboard'); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- FAVORIS DE LA COMMUNAUTE -->
            <div class="sb-sidebar-card">
                <div class="sb-sidebar-card-header"><?php esc_html_e('Favoris de la communauté', 'swiftboard'); ?></div>
                <div class="sb-sidebar-card-body sb-about-links">
                    <a class="sb-r-chip" href="<?php echo esc_url(get_permalink($forum_id)); ?>"><?php esc_html_e('Tous les sujets', 'swiftboard'); ?></a>
                    <a class="sb-r-chip" href="<?php echo esc_url(add_query_arg('sort', 'top', get_permalink($forum_id))); ?>"><?php esc_html_e('Meilleurs sujets', 'swiftboard'); ?></a>
                    <a class="sb-r-chip" href="<?php echo esc_url(bbp_get_forums_url()); ?>"><?php esc_html_e('Toutes les communautés', 'swiftboard'); ?></a>
                </div>
            </div>

            <!-- REGLES : editables par forum via le champ dedie. -->
            <?php
            // Les regles vivent dans un meta du forum, une par ligne. Sans
            // valeur saisie on n'affiche RIEN plutot qu'un bloc vide.
            $sb_regles_brut = (string) get_post_meta( $forum_id, '_swiftboard_forum_rules', true );
            $sb_regles      = array_values( array_filter( array_map( 'trim', explode( "\n", $sb_regles_brut ) ) ) );
            if ( ! empty( $sb_regles ) ) :
            ?>
            <div class="sb-sidebar-card">
                <div class="sb-sidebar-card-header">
                    <?php
                    printf(
                        /* translators: %s: nom du forum. */
                        esc_html__( 'Règles de r/%s', 'swiftboard' ),
                        esc_html( $forum_title )
                    );
                    ?>
                </div>
                <div class="sb-sidebar-card-body">
                    <ol class="sb-about-rules">
                        <?php foreach ( $sb_regles as $sb_i => $sb_regle ) : ?>
                            <li><span class="sb-rule-num"><?php echo (int) ( $sb_i + 1 ); ?></span><span><?php echo esc_html( $sb_regle ); ?></span></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>
            <?php endif; ?>

            <!-- Subreddits de la même catégorie -->
            <?php if (!empty($siblings)): ?>
            <div class="sb-sidebar-card">
                <div class="sb-sidebar-card-header sb-header-blue">
                    <?php echo swiftboard_icon('groups', 14); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?> <?php esc_html_e('Communautés similaires', 'swiftboard'); ?>
                </div>
                <div class="sb-sidebar-card-body">
                    <?php foreach ($siblings as $s): 
                        $s_topics = function_exists('bbp_get_forum_topic_count') ? bbp_get_forum_topic_count($s->ID) : 0;
                    ?>
                        <a href="<?php echo esc_url(get_permalink($s->ID)); ?>" class="sb-sidebar-forum-item">
                            <span class="sb-sidebar-forum-icon">r/</span>
                            <div class="sb-sidebar-forum-info">
                                <div class="sb-sidebar-forum-name"><?php echo esc_html($s->post_title); ?></div>
                                <div class="sb-sidebar-forum-count"><?php echo (int) $s_topics; ?> sujets</div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Sujets chauds (global) -->
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

            <!-- Footer mini -->
            <div class="sb-sidebar-mini-footer">
                <?php echo esc_html(date('Y')); ?> © <?php echo esc_html(get_bloginfo('name')); ?><br>
                <a href="<?php echo esc_url(home_url('/')); ?>">Accueil</a> ·
                <a href="<?php echo esc_url(bbp_get_forums_url()); ?>">Forum</a>
            </div>

        </aside>

    </div>
</div>

<?php get_footer();
