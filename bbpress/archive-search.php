<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Page de recherche bbPress
 *
 * Wrapper SwiftBoard pour la page /forums/search/
 * Charge le content-search.php du thème avec le design system.
 *
 * @package SwiftBoard
 */
?>

<?php get_header(); ?>

<main id="primary" class="site-main" role="main" aria-label="<?php esc_attr_e('Recherche', 'swiftboard'); ?>">
    <?php if (function_exists('swiftboard_breadcrumbs')) echo swiftboard_breadcrumbs(); // phpcs:ignore ?>

    <div id="bbpress-forum" class="bbpress-forum-search">

        <?php bbp_get_template_part('content', 'search'); ?>

    </div>
</main>

<?php get_footer(); ?>
