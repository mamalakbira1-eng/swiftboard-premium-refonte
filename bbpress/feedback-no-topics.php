<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Aucun sujet
 *
 * @package SwiftBoard
 */
?>
<div class="bbp-no-topic">
    <p><?php esc_html_e('Aucun sujet dans ce forum pour le moment.', 'swiftboard'); ?></p>
    <?php if (is_user_logged_in() && bbp_current_user_can_access_create_topic_form()) : ?>
        <p style="margin-top: var(--space-md);">
            <a href="#bbp-new-topic" class="btn-primary"><?php esc_html_e('Créer le premier sujet', 'swiftboard'); ?></a>
        </p>
    <?php endif; ?>
</div>

