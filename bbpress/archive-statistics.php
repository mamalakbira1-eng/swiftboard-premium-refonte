<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Page de statistiques bbPress
 *
 * Wrapper SwiftBoard pour la page /forums/statistics/
 * Charge le content-statistics.php du thème avec le design system.
 *
 * @package SwiftBoard
 */
?>

<?php get_header(); ?>

<main id="primary" class="site-main" role="main" aria-label="<?php esc_attr_e('Statistiques', 'swiftboard'); ?>">
    <?php if (function_exists('swiftboard_breadcrumbs')) echo swiftboard_breadcrumbs(); // phpcs:ignore ?>

    <div id="bbpress-forum" class="bbpress-forum-statistics">

        <?php bbp_get_template_part('content', 'statistics'); ?>

    </div>
</main>

<?php get_footer(); ?>
