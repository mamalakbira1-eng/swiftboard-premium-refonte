<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Accès refusé
 *
 * @package SwiftBoard
 */
?>
<div class="bbp-template-notice error">
    <p><?php esc_html_e('Vous n\'avez pas la permission d\'accéder à cette page.', 'swiftboard'); ?></p>
    <?php if (!is_user_logged_in()) : ?>
        <p>
            <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="btn-primary">
                <?php esc_html_e('Se connecter', 'swiftboard'); ?>
            </a>
        </p>
    <?php endif; ?>
</div>

