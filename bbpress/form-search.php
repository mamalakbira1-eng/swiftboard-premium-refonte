<?php
if (!defined("ABSPATH")) exit;
// phpcs:ignore WordPress.Security.NonceVerification — nonces handled by bbPress core
/**
 * Template override : Formulaire de recherche du forum
 *
 * Remplace le champ texte par un champ search moderne avec ARIA.
 * Plus besoin d'output buffer.
 *
 * @package SwiftBoard
 */
if (bbp_allow_search()) : ?>

<div class="bbp-search-form">
    <form role="search" method="get" id="bbp-search-form"
          aria-label="<?php esc_attr_e('Rechercher dans le forum', 'swiftboard'); ?>"
          action="<?php echo esc_url(bbp_get_search_url()); ?>">
        <label class="screen-reader-text" for="bbp_search"><?php esc_html_e('Rechercher dans le forum :', 'swiftboard'); ?></label>
        <input type="hidden" name="action" value="bbp-search-request" />
        <input type="search" value="<?php bbp_search_terms(); ?>" name="bbp_search" id="bbp_search"
               placeholder="<?php esc_attr_e('Rechercher dans le forum…', 'swiftboard'); ?>"
               aria-label="<?php esc_attr_e('Rechercher dans le forum', 'swiftboard'); ?>"
               autocomplete="off" />
        <button type="submit" id="bbp_search_submit" class="btn-primary">
            <?php esc_html_e('Rechercher', 'swiftboard'); ?>
        </button>
    </form>
</div>

<?php endif;

