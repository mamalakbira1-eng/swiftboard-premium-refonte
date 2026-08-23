<?php
if (!defined("ABSPATH")) exit;
// phpcs:ignore WordPress.Security.NonceVerification — nonces handled by bbPress core
/**
 * Template override : Rôles utilisateur (édition profil)
 *
 * @package SwiftBoard
 */
?>

<div class="bbp-form-field">
    <label for="role"><?php esc_html_e('Rôle du blog', 'swiftboard'); ?></label>

    <?php bbp_edit_user_blog_role(); ?>

</div>

<div class="bbp-form-field">
    <label for="forum-role"><?php esc_html_e('Rôle du forum', 'swiftboard'); ?></label>

    <?php bbp_edit_user_forums_role(); ?>

</div>

