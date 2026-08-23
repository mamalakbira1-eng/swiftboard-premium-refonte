<?php
if (!defined("ABSPATH")) exit;
// phpcs:ignore WordPress.Security.NonceVerification — nonces handled by bbPress core
/**
 * Template override : Formulaire de protection par mot de passe
 *
 * Affiché pour les forums/sujets protégés par mot de passe.
 *
 * @package SwiftBoard
 */
?>

<div id="bbpress-forums" class="bbpress-wrapper">
    <fieldset class="bbp-form" id="bbp-protected">
        <legend><?php esc_html_e('Protégé', 'swiftboard'); ?></legend>

        <?php echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fonction WP native ?>

    </fieldset>
</div>

