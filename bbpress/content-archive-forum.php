<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Archive des forums (page d'accueil du forum)
 *
 * @package SwiftBoard
 */
?>

<div id="bbpress-forums" class="bbpress-wrapper">

    <?php // v5.3 (EXI-FEED-02) : header compact — le texte generique « Bienvenue… » est supprime. ?>
    <header class="forum-header forum-header-compact">
        <h1 class="forum-title"><?php esc_html_e('Forums', 'swiftboard'); ?></h1>
    </header>


    <?php
    // V2 restauration - pour shortcode [bbp-forum-index]
    do_action('bbp_template_before_forums_index');
    ?>

    <?php if (bbp_has_forums()) : ?>

        <?php bbp_get_template_part('loop', 'forums'); ?>

    <?php else : ?>

        <?php bbp_get_template_part('feedback', 'no-forums'); ?>

    <?php endif; ?>

</div>

