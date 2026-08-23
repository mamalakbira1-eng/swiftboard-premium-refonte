<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Résultats de recherche du forum
 *
 * @package SwiftBoard
 */
?>

<div id="bbpress-forums" class="bbpress-wrapper">

    <header class="forum-header">
        <h2 class="forum-title">
            <?php esc_html_e('Résultats de recherche', 'swiftboard'); ?>
        </h2>
        <p class="forum-description">
            <?php if (bbp_get_search_terms()) :
                printf(
                    /* translators: %s = search terms */
                    esc_html__('Pour : %s', 'swiftboard'),
                    '<strong>' . esc_html(bbp_get_search_terms()) . '</strong>'
                );
            endif; ?>
        </p>
    </header>


    <?php bbp_get_template_part('form', 'search'); ?>

    <?php if (bbp_has_search_results()) : ?>

        <?php bbp_get_template_part('pagination', 'search'); ?>
        <?php bbp_get_template_part('loop', 'search'); ?>
        <?php bbp_get_template_part('pagination', 'search'); ?>

    <?php elseif (bbp_get_search_terms()) : ?>

        <?php bbp_get_template_part('feedback', 'no-search'); ?>

    <?php else : ?>

        <div class="bbp-template-notice info">
            <p><?php esc_html_e('Saisissez des mots-clés pour rechercher dans le forum.', 'swiftboard'); ?></p>
        </div>

    <?php endif; ?>

</div>

