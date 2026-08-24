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
$search_form_args = isset( $args ) && is_array( $args ) ? $args : array();
$search_aria_label = ! empty( $search_form_args['aria_label'] )
    ? (string) $search_form_args['aria_label']
    : __( 'Rechercher sur le site', 'swiftboard' );
?>
<form role="search" method="get" class="search-form"
      action="<?php echo esc_url(home_url('/')); ?>"
      aria-label="<?php echo esc_attr( $search_aria_label ); ?>">
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

