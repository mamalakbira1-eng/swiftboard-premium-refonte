<?php
/**
 * Security check: prevent direct access
 */
if (!defined("ABSPATH")) exit;
/**
 * Footer
 *
 * @package SwiftBoard
 */
?>
</div><!-- #main-content -->

<footer id="site-footer" class="site-footer" role="contentinfo" itemscope itemtype="https://schema.org/WPFooter">
    <div class="footer-inner">
        <?php if (has_nav_menu('footer')) : ?>
        <nav class="footer-navigation" aria-label="<?php esc_attr_e('Navigation pied de page', 'swiftboard'); ?>">
            <?php
            wp_nav_menu([
                'theme_location' => 'footer',
                'menu_id'        => 'footer-menu',
                'container'      => false,
                'depth'          => 1,
                'fallback_cb'    => false,
            ]);
            ?>
        </nav>
        <?php endif; ?>

        <div class="footer-about">
            <strong class="footer-about-title"><?php esc_html_e('À propos', 'swiftboard'); ?></strong>
            <p class="footer-about-desc"><?php echo esc_html(get_bloginfo('description')); ?></p>
        </div>

        <div class="footer-info">
            <p class="footer-legal-links" style="margin-bottom: 0.5rem; font-size: 0.85em;">
                <a href="<?php echo esc_url(home_url('/politique-de-confidentialite/')); ?>">Politique de Confidentialité</a> &middot;
                <a href="<?php echo esc_url(home_url('/conditions-generales-d-utilisation/')); ?>">CGU</a> &middot;
                <a href="<?php echo esc_url(home_url('/suppression-des-donnees/')); ?>">Suppression des données</a>
            </p>
            <p class="copyright">
                &copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?>.
                <?php esc_html_e('Tous droits réservés.', 'swiftboard'); ?>
            </p>
            <?php $footer_text = swiftboard_get_option('footer_text', ''); ?>
            <?php if ($footer_text): ?>
            <p class="footer-custom-text"><?php echo esc_html($footer_text); ?></p>
            <?php endif; ?>
            <p class="theme-credit">
                <?php esc_html_e('Propulsé par', 'swiftboard'); ?>
                <a href="https://wordpress.org/" rel="noopener nofollow" target="_blank">WordPress</a>
                &amp; <a href="https://swiftboard.dev" rel="noopener">SwiftBoard</a>.
            </p>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>

