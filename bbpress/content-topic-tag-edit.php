<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Édition d'un mot-clé (topic tag)
 *
 * Affiche la description du tag puis charge le formulaire
 * de gestion (rename / merge / delete).
 *
 * @package SwiftBoard
 */
?>

<div id="bbpress-forums" class="bbpress-wrapper">


    <?php do_action('bbp_template_before_topic_tag_description'); ?>

    <?php bbp_topic_tag_description(['before' => '<div class="bbp-template-notice info"><ul><li>', 'after' => '</li></ul></div>']); ?>

    <?php do_action('bbp_template_after_topic_tag_description'); ?>

    <?php do_action('bbp_template_before_topic_tag_edit'); ?>

    <?php bbp_get_template_part('form', 'topic-tag'); ?>

    <?php do_action('bbp_template_after_topic_tag_edit'); ?>

</div>

