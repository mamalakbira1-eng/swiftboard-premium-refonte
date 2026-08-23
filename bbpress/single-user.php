<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Profil utilisateur bbPress (fallback)
 *
 * Ce wrapper est un fallback : en conditions normales, reddit-profile.php
 * intercepte les profils via template_redirect et rend son propre template.
 * Mais si l'interception échoue (plugin désactivé, erreur, etc.), bbPress
 * fallback sur ce wrapper qui charge content-single-user.php.
 *
 * @package SwiftBoard
 */
?>

<?php get_header(); ?>

<main id="primary" class="site-main" role="main" aria-label="<?php esc_attr_e('Profil utilisateur', 'swiftboard'); ?>">
    <?php if (function_exists('swiftboard_breadcrumbs')) echo swiftboard_breadcrumbs(); // phpcs:ignore ?>

    <div id="bbpress-forum" class="bbpress-forum-user">

        <?php bbp_get_template_part('content', 'single-user'); ?>

    </div>
</main>

<?php get_footer(); ?>
