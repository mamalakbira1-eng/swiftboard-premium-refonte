<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Archive des sujets (tous les sujets récents)
 *
 * Wrapper SwiftBoard pour la page /forums/topics/
 * Charge le content-archive-topic.php du thème avec le design system.
 *
 * @package SwiftBoard
 */
?>

<?php get_header(); ?>

<main id="primary" class="site-main" role="main" aria-label="<?php esc_attr_e('Sujets récents', 'swiftboard'); ?>">
    <?php if (function_exists('swiftboard_breadcrumbs')) echo swiftboard_breadcrumbs(); // phpcs:ignore ?>

    <div id="bbpress-forum" class="bbpress-forum-archive-topic">

        <?php bbp_get_template_part('content', 'archive-topic'); ?>

    </div>
</main>

<?php get_footer(); ?>
