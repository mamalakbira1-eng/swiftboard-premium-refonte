<?php
/**
 * Security check: prevent direct access
 */
if (!defined("ABSPATH")) exit;
/**
 * Template Name: Forum
 *
 * Template PHP natif pour le forum (bbPress).
 * Ultra-rapide — ne passe PAS par Elementor.
 * Markup sémantique optimisé SEO + LLM.
 *
 * @package SwiftBoard
 */

get_header();

$has_sidebar = is_active_sidebar('forum-sidebar');
?>

<main id="primary" class="site-main <?php echo $has_sidebar ? 'layout-with-sidebar' : ''; ?>" role="main"
      aria-label="<?php esc_attr_e('Forum de discussion', 'swiftboard'); ?>">

    <div class="<?php echo $has_sidebar ? 'main-col' : ''; ?>">
    <?php if (function_exists('is_bbpress') && is_bbpress()) : ?>

        <?php echo swiftboard_breadcrumbs(); // phpcs:ignore ?>

        <?php while (have_posts()) : the_post(); ?>

            <?php if (bbp_is_forum_archive()) : ?>
                <?php // v5.3 (EXI-FEED-02) : header compact — texte generique supprime. ?>
                <header class="forum-header forum-header-compact">
                    <h1 class="forum-title"><?php esc_html_e('Forums', 'swiftboard'); ?></h1>
                </header>
            <?php endif; ?>

            <div class="forum-content" itemprop="text">
                <?php the_content(); ?>
            </div>

        <?php endwhile; ?>

    <?php else : ?>
        <div class="bbp-template-notice error">
            <p><?php esc_html_e('Le plugin bbPress n\'est pas installé ou activé.', 'swiftboard'); ?></p>
            <p><?php esc_html_e('Installez bbPress pour activer le forum.', 'swiftboard'); ?></p>
        </div>
    <?php endif; ?>
    </div>

    <?php if ($has_sidebar) : ?>
        <aside class="sidebar forum-sidebar" role="complementary"
               aria-label="<?php esc_attr_e('Barre latérale du forum', 'swiftboard'); ?>">
            <?php dynamic_sidebar('forum-sidebar'); ?>
        </aside>
    <?php endif; ?>

</main>

<?php get_footer(); ?>

