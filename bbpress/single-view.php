<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Vue personnalisée bbPress
 *
 * Wrapper SwiftBoard pour les pages /forums/view/xxx/
 * (ex: Popular Topics, No Replies, Tags)
 * Charge le content-single-view.php du thème avec le design system.
 *
 * @package SwiftBoard
 */
?>

<?php get_header(); ?>

<main id="primary" class="site-main" role="main" aria-label="<?php esc_attr_e('Forum', 'swiftboard'); ?>">
    <?php if (function_exists('swiftboard_breadcrumbs')) echo swiftboard_breadcrumbs(); // phpcs:ignore ?>

    <div id="bbpress-forum" class="bbpress-forum-view">

        <h1 class="sb-view-title"><?php bbp_view_title(); ?></h1>

        <?php bbp_get_template_part('content', 'single-view'); ?>

    </div>
</main>

<?php get_footer(); ?>
