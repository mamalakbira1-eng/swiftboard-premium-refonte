<?php
if (!defined("ABSPATH")) exit;
// phpcs:ignore WordPress.Security.NonceVerification — nonces handled by bbPress core
/**
 * Template override : Liste des balises HTML autorisées
 *
 * Affiché sous les éditeurs quand l'utilisateur ne bénéficie
 * pas du mode "unfiltered_html".
 *
 * @package SwiftBoard
 */
if (!(bbp_use_wp_editor() || current_user_can('unfiltered_html'))) : ?>

    <p class="form-allowed-tags">
        <label><?php printf(esc_html__('Balises %s et attributs autorisés :', 'swiftboard'), '<abbr title="HyperText Markup Language">HTML</abbr>'); ?></label><br>
        <code><?php bbp_allowed_tags(); ?></code>
    </p>

<?php endif;

