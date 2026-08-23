<?php
/**
 * Security check: prevent direct access
 */
if (!defined("ABSPATH")) exit;
/**
 * Index — Fallback / Blog list
 *
 * @package SwiftBoard
 */

get_header();

$has_sidebar = is_active_sidebar('blog-sidebar');
?>

<main id="primary" class="site-main <?php echo $has_sidebar ? 'layout-with-sidebar' : ''; ?>" role="main" aria-label="<?php esc_attr_e('Articles', 'swiftboard'); ?>">

    <div class="<?php echo $has_sidebar ? 'main-col' : ''; ?>">
    <?php if (have_posts()) : ?>

        <header class="page-header" style="margin-bottom: var(--space-md);">
            <h1 class="page-title"><?php esc_html_e('Articles', 'swiftboard'); ?></h1>
        </header>

        <?php while (have_posts()) : the_post(); ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class('post-item'); ?>
                     itemscope itemtype="https://schema.org/BlogPosting">

                <?php echo swiftboard_vote_html(get_the_ID()); // phpcs:ignore ?>

                <div class="post-body">
                    <header class="entry-header">
                        <div class="post-meta">
                            <?php
                            $categories = get_the_category();
                            if (!empty($categories)) :
                                $cat = $categories[0];
                                ?>
                                <a href="<?php echo esc_url(get_category_link($cat->term_id)); ?>" class="post-forum-tag">
                                    r/<?php echo esc_html($cat->name); ?>
                                </a>
                            <?php endif; ?>
                            <span><?php esc_html_e('Publié par', 'swiftboard'); ?>
                                <a href="<?php echo esc_url(get_author_posts_url((int) get_the_author_meta('ID'))); ?>">
                                    <?php the_author(); ?>
                                </a>
                            </span>
                            <span class="post-meta-sep">•</span>
                            <time datetime="<?php echo esc_attr(get_the_date('c')); ?>" itemprop="datePublished">
                                <?php echo esc_html(swiftboard_time_ago(get_the_date('c'))); ?>
                            </time>
                        </div>

                        <h2 class="entry-title" itemprop="headline">
                            <a href="<?php the_permalink(); ?>" rel="bookmark" itemprop="url">
                                <?php the_title(); ?>
                            </a>
                        </h2>
                    </header>

                    <div class="entry-summary" itemprop="description">
                        <?php the_excerpt(); ?>
                    </div>

                    <div class="post-actions">
                        <a href="<?php the_permalink(); ?>" class="post-action">
                            💬 <?php echo get_comments_number() ? esc_html(swiftboard_format_count(get_comments_number())) : '0'; ?>
                            <?php esc_html_e('commentaires', 'swiftboard'); ?>
                        </a>
                        <a href="<?php the_permalink(); ?>" class="post-action">🔗 <?php esc_html_e('Partager', 'swiftboard'); ?></a>
                        <a href="<?php the_permalink(); ?>" class="post-action">⭐ <?php esc_html_e('Enregistrer', 'swiftboard'); ?></a>
                    </div>
                </div>
            </article>
        <?php endwhile; ?>

        <?php
        the_posts_pagination([
            'prev_text' => '<span role="img" aria-label="' . esc_attr__('Page précédente', 'swiftboard') . '">←</span>',
            'next_text' => '<span role="img" aria-label="' . esc_attr__('Page suivante', 'swiftboard') . '">→</span>',
        ]);
        ?>

    <?php else : ?>
        <p><?php esc_html_e('Aucun article trouvé.', 'swiftboard'); ?></p>
    <?php endif; ?>
    </div>

    <?php if ($has_sidebar) : ?>
        <aside class="sidebar" role="complementary" aria-label="<?php esc_attr_e('Barre latérale', 'swiftboard'); ?>">
            <?php dynamic_sidebar('blog-sidebar'); ?>
        </aside>
    <?php endif; ?>

</main>

<?php get_footer(); ?>

