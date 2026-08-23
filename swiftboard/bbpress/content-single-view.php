<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Contenu d'une vue personnalisée bbPress
 *
 * Affiche le fil d'Ariane, la pagination et la boucle des sujets
 * correspondant à la "view" demandée (ex : "populaires", "non-résolus").
 *
 * @package SwiftBoard
 */
?>

<div id="bbpress-forums" class="bbpress-wrapper">

    <?php bbp_set_query_name(bbp_get_view_rewrite_id()); ?>

    <?php if (bbp_view_query()) : ?>

        <?php bbp_get_template_part('pagination', 'topics'); ?>

        <?php bbp_get_template_part('loop', 'topics'); ?>

        <?php bbp_get_template_part('pagination', 'topics'); ?>

    <?php else : ?>

        <?php bbp_get_template_part('feedback', 'no-topics'); ?>

    <?php endif; ?>

    <?php bbp_reset_query_name(); ?>

</div>

