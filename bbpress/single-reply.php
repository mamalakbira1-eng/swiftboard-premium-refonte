<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Réponse unique
 *
 * @package SwiftBoard
 */
?>

<?php get_header(); ?>

<main id="primary" class="site-main" role="main" aria-label="Forum">
    <?php if (function_exists('swiftboard_breadcrumbs')) echo swiftboard_breadcrumbs(); // phpcs:ignore ?>
<div id="bbpress-forum" class="bbpress-reply-single">
    <?php bbp_get_template_part('content', 'single-reply'); ?>
</div>
</main>

<?php get_footer(); ?>

