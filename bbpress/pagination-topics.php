<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Pagination des sujets
 *
 * Affiché au-dessus et en-dessous de la boucle des sujets
 * d'un forum (quand la pagination est activée).
 *
 * @package SwiftBoard
 */
do_action('bbp_template_before_pagination_loop'); ?>

<div class="bbp-pagination">
    <div class="bbp-pagination-count"><?php bbp_forum_pagination_count(); ?></div>
    <div class="bbp-pagination-links"><?php bbp_forum_pagination_links(); ?></div>
</div>

<?php do_action('bbp_template_after_pagination_loop');

