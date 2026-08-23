<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Archive des sujets (tous les sujets récents)
 *
 * @package SwiftBoard
 */
?>

<div id="bbpress-forums" class="bbpress-wrapper">

    <header class="forum-header">
        <h1 class="forum-title"><?php esc_html_e('Sujets récents', 'swiftboard'); ?></h1>
        <p class="forum-description"><?php esc_html_e('Tous les derniers sujets de discussion du forum.', 'swiftboard'); ?></p>
    </header>


    <?php if (bbp_has_topics()) : ?>

        <?php bbp_get_template_part('pagination', 'topics'); ?>
        <?php bbp_get_template_part('loop', 'topics'); ?>
        <?php bbp_get_template_part('pagination', 'topics'); ?>

    <?php else : ?>

        <?php bbp_get_template_part('feedback', 'no-topics'); ?>

    <?php endif; ?>

</div>

