<?php
/**
 * Security check: prevent direct access
 */
if (!defined("ABSPATH")) exit;
/**
 * Search form template
 *
 * @package SwiftBoard
 */
?>
<form role="search" method="get" class="search-form"
      action="<?php echo esc_url(home_url('/')); ?>"
      aria-label="<?php esc_attr_e('Rechercher sur le site', 'swiftboard'); ?>">
    <label>
        <span class="screen-reader-text"><?php esc_html_e('Rechercher :', 'swiftboard'); ?></span>
        <input type="search" class="search-field"
               placeholder="<?php esc_attr_e('Rechercher…', 'swiftboard'); ?>"
               value="<?php echo esc_attr( get_search_query() ); ?>" name="s"
               autocomplete="off">
    </label>
    <button type="submit" class="search-submit">
        <?php esc_html_e('Rechercher', 'swiftboard'); ?>
    </button>
</form>

