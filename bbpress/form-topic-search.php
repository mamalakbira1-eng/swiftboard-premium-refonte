<?php
if (!defined("ABSPATH")) exit;
// phpcs:ignore WordPress.Security.NonceVerification — nonces handled by bbPress core
/**
 * Template override : Recherche de sujets (mini-form)
 *
 * @package SwiftBoard
 */
if (bbp_allow_search()) : ?>

    <div class="bbp-search-form">
        <form role="search" method="get" id="bbp-topic-search-form"
              aria-label="<?php esc_attr_e('Recherche de sujets', 'swiftboard'); ?>">
            <div class="bbp-form-field">
                <label class="screen-reader-text" for="ts"><?php esc_html_e('Rechercher des sujets :', 'swiftboard'); ?></label>
                <input type="text" value="<?php bbp_search_terms(); ?>" name="ts" id="ts"
                       placeholder="<?php esc_attr_e('Rechercher dans les sujets…', 'swiftboard'); ?>">
                <input class="btn-primary" type="submit" id="bbp_search_submit" value="<?php esc_attr_e('Rechercher', 'swiftboard'); ?>">
            </div>
        </form>
    </div>

<?php endif;

