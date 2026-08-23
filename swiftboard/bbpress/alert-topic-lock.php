<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Alerte de sujet verrouillé
 *
 * Affiche une modale d'avertissement quand l'utilisateur tente
 * de quitter un sujet en cours de rédaction d'une réponse.
 *
 * @package SwiftBoard
 */
do_action('bbp_theme_before_alert_topic_lock'); ?>

<?php if (bbp_show_topic_lock_alert()) : ?>

    <div class="bbp-alert-outer" role="alertdialog" aria-modal="true" aria-labelledby="bbp-alert-title">
        <div class="bbp-alert-inner">
            <p id="bbp-alert-title" class="bbp-alert-description"><?php bbp_topic_lock_description(); ?></p>
            <p class="bbp-alert-actions">
                <a class="bbp-alert-back btn-secondary" href="<?php bbp_forum_permalink(bbp_get_topic_forum_id()); ?>"><?php esc_html_e('Quitter', 'swiftboard'); ?></a>
                <a class="bbp-alert-close btn-primary" href="#"><?php esc_html_e('Rester', 'swiftboard'); ?></a>
            </p>
        </div>
    </div>

<?php endif;

do_action('bbp_theme_after_alert_topic_lock');

