<?php
/**
 * Security check: prevent direct access
 */
if (!defined("ABSPATH")) exit;
/**
 * Single post template — Reddit-style article view
 *
 * @package SwiftBoard
 */

get_header();
?>

<main id="primary" class="site-main" role="main" aria-label="<?php esc_attr_e('Article', 'swiftboard'); ?>">

<?php while (have_posts()) : the_post(); ?>
    <article id="post-<?php the_ID(); ?>" <?php post_class('single-post'); ?>
             itemscope itemtype="https://schema.org/BlogPosting">

        <div class="single-post-container">
            <header class="entry-header">
                <div class="post-meta" style="margin-bottom: var(--space-sm);">
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
                        <a href="<?php echo esc_url(get_author_posts_url((int) get_the_author_meta('ID'))); ?>" itemprop="author" itemscope itemtype="https://schema.org/Person">
                            <span itemprop="name"><?php the_author(); ?></span>
                        </a>
                    </span>
                    <span class="post-meta-sep">•</span>
                    <time datetime="<?php echo esc_attr(get_the_date('c')); ?>" itemprop="datePublished">
                        <?php echo esc_html(swiftboard_time_ago(get_the_date('c'))); ?>
                    </time>
                </div>

                <h1 class="entry-title" itemprop="headline"><?php the_title(); ?></h1>

                <?php if (has_category()) : ?>
                <div class="entry-categories">
                    <?php the_category(' '); ?>
                </div>
                <?php endif; ?>
            </header>

            <?php if (has_post_thumbnail()) : ?>
            <figure class="post-thumbnail" itemprop="image" itemscope itemtype="https://schema.org/ImageObject">
                <?php the_post_thumbnail('large', ['itemprop' => 'url', 'loading' => 'lazy']); ?>
            </figure>
            <?php endif; ?>

            <div style="display:flex; align-items:flex-start; gap: var(--space-md); margin-bottom: var(--space-md);">
                <?php echo swiftboard_vote_html(get_the_ID()); // phpcs:ignore ?>
                <div class="entry-content" itemprop="articleBody" style="flex:1;">
                    <?php the_content(); ?>
                </div>
            </div>

            <div class="post-actions" style="border-top: 1px solid var(--color-border-light); padding-top: var(--space-sm);">
                <a href="#comments" class="post-action">
                    💬 <?php echo get_comments_number() ? esc_html(swiftboard_format_count(get_comments_number())) : '0'; ?>
                    <?php esc_html_e('commentaires', 'swiftboard'); ?>
                </a>
                <button class="post-action sb-share-btn" data-share-url="<?php echo esc_url(get_permalink()); ?>" data-share-title="<?php echo esc_attr(get_the_title()); ?>">🔗 <?php esc_html_e('Partager', 'swiftboard'); ?></button>
                <button class="post-action sb-save-btn" data-post-id="<?php echo (int) get_the_ID(); ?>">⭐ <?php esc_html_e('Enregistrer', 'swiftboard'); ?></button>
                <button class="post-action sb-report-btn" data-post-id="<?php echo (int) get_the_ID(); ?>">⚠️ <?php esc_html_e('Signaler', 'swiftboard'); ?></button>
            </div>

            <?php if (has_tag()) : ?>
            <footer class="entry-footer">
                <div class="entry-tags">
                    <?php the_tags('<span class="tags-label">' . esc_html__('Mots-clés :', 'swiftboard') . '</span> ', ' ', ''); ?>
                </div>
            </footer>
            <?php endif; ?>
        </div>

        <?php
        the_post_navigation([
            'prev_text' => '<span aria-hidden="true">←</span> %title',
            'next_text' => '%title <span aria-hidden="true">→</span>',
        ]);

        if (comments_open() || get_comments_number()) {
            comments_template();
        }
        ?>

    </article>
<?php endwhile; ?>

</main>

<?php get_footer(); ?>

