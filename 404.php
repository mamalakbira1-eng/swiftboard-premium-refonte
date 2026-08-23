<?php
/**
 * Security check: prevent direct access
 */
if (!defined("ABSPATH")) exit;
/**
 * 404 — Page non trouvée
 *
 * @package SwiftBoard
 */

get_header();
?>

<main id="primary" class="site-main error-404" role="main"
      aria-label="<?php esc_attr_e('Page non trouvée', 'swiftboard'); ?>">

    <article class="error-content">
        <header class="error-header">
            <h1 class="error-title">404</h1>
            <p style="font-size: var(--font-size-lg); color: var(--color-text-light); margin-bottom: var(--space-md);">
                <?php esc_html_e('Page introuvable', 'swiftboard'); ?>
            </p>
        </header>

        <div class="error-body">
            <p style="margin-bottom: var(--space-md);">
                <?php esc_html_e('Désolé, la page que vous cherchez n\'existe pas ou a été déplacée.', 'swiftboard'); ?>
            </p>

            <div class="error-actions" style="margin: var(--space-lg) 0;">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="btn-primary"><?php esc_html_e('Accueil', 'swiftboard'); ?></a>
                <?php if (function_exists('bbp_forums_url')) : ?>
                    <a href="<?php echo esc_url(bbp_forums_url('/')); ?>" class="btn-secondary"><?php esc_html_e('Forum', 'swiftboard'); ?></a>
                <?php endif; ?>
            </div>

            <div class="error-search">
                <?php get_search_form(); ?>
            </div>
        </div>
    </article>

</main>

<?php get_footer(); ?>

