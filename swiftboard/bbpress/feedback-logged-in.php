<?php
if (!defined("ABSPATH")) exit;
/**
 * Template override : Utilisateur connecté (info banner)
 *
 * @package SwiftBoard
 */
?>
<p class="bbp-logged-in">
    <?php
    printf(
        /* translators: 1: user avatar, 2: user link, 3: logout link */
        esc_html__('Connecté en tant que %1$s %2$s. %3$s', 'swiftboard'),
        '<span class="avatar-mini">' . swiftboard_get_avatar(bbp_get_current_user_id(), 20) . '</span>',
        '<a href="' . esc_url(bbp_get_user_profile_url(bbp_get_current_user_id())) . '" class="bbp-user-link">' . esc_html(bbp_get_current_user_name()) . '</a>',
        '<a href="' . esc_url(wp_logout_url(site_url())) . '" class="bbp-logout-link">' . esc_html__('Se déconnecter ?', 'swiftboard') . '</a>'
    );
    ?>
</p>

