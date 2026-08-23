<?php
/**
 * Security check: prevent direct access
 */
if (!defined("ABSPATH")) exit;
/**
 * Page template — Compatible Elementor & Gutenberg
 *
 * @package SwiftBoard
 */

get_header();
?>

<main id="primary" class="site-main" role="main" aria-label="<?php esc_attr_e('Contenu de la page', 'swiftboard'); ?>">

<?php while (have_posts()) : the_post(); ?>
    <article id="post-<?php the_ID(); ?>" <?php post_class('single-post'); ?>
             itemscope itemtype="https://schema.org/WebPage">

        <div class="single-post-container">
            <header class="entry-header">
                <h1 class="entry-title" itemprop="headline"><?php the_title(); ?></h1>
            </header>

            <div class="entry-content" itemprop="text">
                <?php the_content(); ?>
                <?php
                wp_link_pages([
                    'before' => '<nav class="page-links" aria-label="' . esc_attr__('Pagination de la page', 'swiftboard') . '">',
                    'after'  => '</nav>',
                ]);
                ?>
            </div>
        </div>

    </article>
<?php endwhile; ?>

</main>

<?php get_footer(); ?>

