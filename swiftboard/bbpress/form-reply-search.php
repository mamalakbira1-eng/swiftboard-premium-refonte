<?php
if (!defined("ABSPATH")) exit;
// phpcs:ignore WordPress.Security.NonceVerification — nonces handled by bbPress core
/**
 * Template override : Recherche de réponses (mini-form)
 *
 * @package SwiftBoard
 */
if (bbp_allow_search()) : ?>

    <div class="bbp-search-form">
        <form role="search" method="get" id="bbp-reply-search-form"
              aria-label="<?php esc_attr_e('Recherche de réponses', 'swiftboard'); ?>">
            <div class="bbp-form-field">
                <label class="screen-reader-text" for="rs"><?php esc_html_e('Rechercher des réponses :', 'swiftboard'); ?></label>
                <input type="text" value="<?php bbp_search_terms(); ?>" name="rs" id="rs"
                       placeholder="<?php esc_attr_e('Rechercher dans les réponses…', 'swiftboard'); ?>">
                <input class="btn-primary" type="submit" id="bbp_search_submit" value="<?php esc_attr_e('Rechercher', 'swiftboard'); ?>">
            </div>
        </form>
    </div>

<?php endif;

