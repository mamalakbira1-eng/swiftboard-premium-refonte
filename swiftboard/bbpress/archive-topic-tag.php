<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Page de tag de topic bbPress
 *
 * Affiche la description du tag + la liste des sujets portant ce tag.
 * (Avant : chargeait content-topic-tag-edit.php qui montrait le formulaire
 *  d'édition du tag au lieu de la liste des sujets — bug UX critique.)
 *
 * @package SwiftBoard
 */
?>

<?php get_header(); ?>

<main id="primary" class="site-main" role="main" aria-label="<?php esc_attr_e('Mot-clé', 'swiftboard'); ?>">
    <?php if (function_exists('swiftboard_breadcrumbs')) echo swiftboard_breadcrumbs(); // phpcs:ignore ?>

    <div id="bbpress-forum" class="bbpress-forum-topic-tag">

        <header class="forum-header">
            <h1 class="forum-title">
                <?php esc_html_e('Mot-clé :', 'swiftboard'); ?>
                <?php echo esc_html(single_term_title('', false)); ?>
            </h1>
            <?php
            $tag_desc = term_description();
            if ($tag_desc) :
            ?>
                <p class="forum-description"><?php echo wp_kses_post($tag_desc); ?></p>
            <?php endif; ?>
        </header>

        <?php if (bbp_has_topics()) : ?>

            <?php bbp_get_template_part('pagination', 'topics'); ?>

            <?php bbp_get_template_part('loop', 'topics'); ?>

            <?php bbp_get_template_part('pagination', 'topics'); ?>

        <?php else : ?>

            <?php bbp_get_template_part('feedback', 'no-topics'); ?>

        <?php endif; ?>

    </div>
</main>

<?php get_footer();
