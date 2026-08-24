<?php
/**
 * Security check: prevent direct access
 */
if (!defined("ABSPATH")) exit;
/**
 * Header — Design Reddit-inspired
 *
 * @package SwiftBoard
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php echo esc_attr(get_bloginfo('charset') ?: 'UTF-8'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?> itemscope itemtype="https://schema.org/WebPage">
<?php wp_body_open(); ?>

<header id="site-header" class="site-header" role="banner" itemscope itemtype="https://schema.org/WPHeader">
    <a href="#main-content" class="skip-link screen-reader-text">
        <?php esc_html_e('Aller au contenu', 'swiftboard'); ?>
    </a>
    <div class="header-inner">
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-menu" aria-label="<?php esc_attr_e('Ouvrir le menu principal', 'swiftboard'); ?>">
            <svg class="menu-toggle-icon" aria-hidden="true" viewBox="0 0 24 24" width="20" height="20" focusable="false"><path d="M3 6h18M3 12h18M3 18h18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            <span class="screen-reader-text"><?php esc_html_e('Menu', 'swiftboard'); ?></span>
        </button>

        <div class="site-branding">
            <?php swiftboard_logo(); ?>
            <?php $description = get_bloginfo('description', 'display');
            if ($description) : ?>
                <p class="site-description"><?php echo esc_html($description); ?></p>
            <?php endif; ?>
        </div>

        <div class="header-search">
            <form role="search" method="get" class="search-form" action="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php esc_attr_e('Rechercher sur le site', 'swiftboard'); ?>">
                <svg class="search-icon" aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="7"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <?php
                // Pastille de contexte — parite Reddit : sur une page de
                // communaute, la recherche est portee a ce forum et l'affiche
                // par une puce fermable. Le champ cache transmet la portee au
                // moteur ; la croix renvoie a la recherche globale.
                $sb_forum_scope = 0;
                if ( function_exists( 'bbp_is_single_forum' ) && bbp_is_single_forum() ) {
                    $sb_forum_scope = (int) get_queried_object_id();
                } elseif ( function_exists( 'bbp_is_single_topic' ) && bbp_is_single_topic() ) {
                    $sb_forum_scope = (int) bbp_get_topic_forum_id();
                }

                if ( $sb_forum_scope ) :
                    ?>
                    <span class="sb-search-scope">
                        <span class="sb-search-scope-label">r/<?php echo esc_html( get_the_title( $sb_forum_scope ) ); ?></span>
                        <a class="sb-search-scope-clear"
                           href="<?php echo esc_url( home_url( '/' ) ); ?>"
                           aria-label="<?php esc_attr_e( 'Rechercher sur tout le site', 'swiftboard' ); ?>">×</a>
                    </span>
                    <input type="hidden" name="forum_id" value="<?php echo esc_attr( (string) $sb_forum_scope ); ?>">
                <?php endif; ?>
                <label>
                    <span class="screen-reader-text"><?php esc_html_e('Rechercher :', 'swiftboard'); ?></span>
                    <input type="search" class="search-field"
                           placeholder="<?php echo $sb_forum_scope
                               ? esc_attr( sprintf( __( 'Rechercher dans r/%s', 'swiftboard' ), get_the_title( $sb_forum_scope ) ) )
                               : esc_attr__( 'Rechercher…', 'swiftboard' ); ?>"
                           value="<?php echo esc_attr( get_search_query() ); ?>" name="s" autocomplete="off">
                </label>
            </form>
        </div>

        <nav id="site-navigation" class="main-navigation" role="navigation"
             aria-label="<?php esc_attr_e('Navigation principale', 'swiftboard'); ?>"
             itemscope itemtype="https://schema.org/SiteNavigationElement">
            <?php
            if (has_nav_menu('primary')) {
                wp_nav_menu([
                    'theme_location' => 'primary',
                    'menu_id'        => 'primary-menu',
                    'container'      => false,
                    'fallback_cb'    => false,
                    'depth'          => 2,
                    'items_wrap'     => '<ul id="%1$s" class="%2$s">%3$s</ul>',
                ]);
            } else {
                echo '<ul id="primary-menu" class="menu">';
                echo '<li><a href="' . esc_url(home_url('/')) . '">' . esc_html__('Accueil', 'swiftboard') . '</a></li>';
                // v5.3.1 : « Tous les forums » + « Blog » rejoignent le menu (donc le
                // hamburger mobile) ; les liens rapides du haut de l'accueil sont
                // masques sous 640px (demande produit : feed epure sur mobile).
                echo '<li><a href="' . esc_url(home_url('/blog/')) . '">' . esc_html__('Blog', 'swiftboard') . '</a></li>';
                if (function_exists('bbp_get_forums_url')) {
                    // v4.6.1 : bbp_get_forums_url() retourne l'URL (ne echo pas)
                    // bbp_forums_url() echo directement — c'était ça qui insérait l'URL en texte brut
                    echo '<li><a href="' . esc_url(bbp_get_forums_url('/')) . '">' . esc_html__('Tous les forums', 'swiftboard') . '</a></li>';
                }
                echo '</ul>';
            }
            ?>
        </nav>

        <div class="header-actions">
            <?php if (is_user_logged_in()) :
                // EXI-CTA-01 (v5.3.2) : bouton primary « + Creer » dans le header.
                // Destination : le formulaire de sujet du forum courant quand on
                // s'y trouve (ancre #new-post, comme bbPress), sinon la liste des
                // forums (ou l'on choisit son forum). Cache : ce bouton n'existe
                // que pour les membres — le HTML anonyme mis en cache (boutons
                // Connexion / S'inscrire) est strictement inchange.
                // Destination du bouton « Créer ».
                //
                // Defaut corrige : hors page de forum, le bouton renvoyait vers
                // la LISTE des forums. L'utilisateur arrivait sur un annuaire
                // sans formulaire ni indication, et devait deviner qu'il fallait
                // d'abord ouvrir un forum. Constate en test : clic depuis
                // l'accueil -> /forums/ -> 0 formulaire de creation.
                //
                // On vise desormais toujours un endroit ou l'on peut REELLEMENT
                // ecrire : le forum courant, celui du sujet consulte, ou a
                // defaut le premier forum ouvert aux nouveaux sujets.
                $sb_create_url   = '';
                $sb_create_label = __('Créer un sujet', 'swiftboard');

                if (function_exists('bbp_is_single_forum') && bbp_is_single_forum()) {
                    $sb_create_url   = '#new-post';
                    $sb_create_label = __('Créer un sujet dans ce forum', 'swiftboard');
                } elseif (function_exists('bbp_is_single_topic') && bbp_is_single_topic() && function_exists('bbp_get_topic_forum_id')) {
                    // Depuis un sujet, on reste dans sa communaute.
                    $sb_forum_parent = bbp_get_topic_forum_id();
                    if ($sb_forum_parent) {
                        $sb_create_url   = trailingslashit(get_permalink($sb_forum_parent)) . '#new-post';
                        $sb_create_label = __('Créer un sujet dans cette communauté', 'swiftboard');
                    }
                }

                if (!$sb_create_url && function_exists('bbp_get_forum_post_type')) {
                    // Premier forum acceptant des sujets : une categorie n'en
                    // accepte pas, et un forum ferme non plus.
                    $sb_forums_ouverts = get_posts(array(
                        'post_type'      => bbp_get_forum_post_type(),
                        'posts_per_page' => 10,
                        'post_status'    => 'publish',
                        'orderby'        => 'menu_order title',
                        'order'          => 'ASC',
                        'fields'         => 'ids',
                    ));
                    foreach ($sb_forums_ouverts as $sb_fid) {
                        $sb_est_categorie = function_exists('bbp_is_forum_category') && bbp_is_forum_category($sb_fid);
                        $sb_est_ferme     = function_exists('bbp_is_forum_closed') && bbp_is_forum_closed($sb_fid);
                        if (!$sb_est_categorie && !$sb_est_ferme) {
                            $sb_create_url   = trailingslashit(get_permalink($sb_fid)) . '#new-post';
                            $sb_create_label = __('Créer un sujet', 'swiftboard');
                            break;
                        }
                    }
                }

                if (!$sb_create_url) {
                    // Aucun forum ouvert : on renvoie vers l'annuaire plutot que
                    // vers une page ou l'ecriture est impossible.
                    $sb_create_url = function_exists('bbp_get_forums_url')
                        ? bbp_get_forums_url('/')
                        : home_url('/');
                }
                ?>
                <a href="<?php echo esc_url($sb_create_url); ?>"
                   class="btn-primary sb-create-cta"
                   aria-label="<?php echo esc_attr($sb_create_label); ?>"
                   title="<?php echo esc_attr($sb_create_label); ?>">
                    <svg class="sb-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <span class="btn-text"><?php esc_html_e('Créer', 'swiftboard'); ?></span>
                </a>
            <?php endif; ?>

            <button class="btn-icon theme-toggle" aria-label="<?php esc_attr_e('Changer de thème', 'swiftboard'); ?>" title="<?php esc_attr_e('Mode clair/sombre', 'swiftboard'); ?>">
                <?php echo swiftboard_icon('theme', 18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?>
            </button>

            <?php if (is_user_logged_in()) :
                $current_user = wp_get_current_user();
                $profile_url = function_exists('bbp_get_user_profile_url') ? bbp_get_user_profile_url($current_user->ID) : get_edit_profile_url();
                ?>
                <?php
                // EXI-MBR-04 : menu utilisateur accessible (remplace l'avatar nu)
                $sb_unread = function_exists('swiftboard_get_unread_count')
                    ? (int) swiftboard_get_unread_count($current_user->ID) : 0;
                ?>
                <div class="sb-user-menu">
                    <button type="button"
                            class="btn-icon sb-user-menu-toggle"
                            id="sb-user-menu-toggle"
                            aria-haspopup="menu"
                            aria-expanded="false"
                            aria-controls="sb-user-menu-list"
                            aria-label="<?php esc_attr_e('Menu utilisateur', 'swiftboard'); ?>">
                        <?php echo swiftboard_get_avatar($current_user->ID, 24); ?>
                        <?php if ($sb_unread > 0) : ?>
                            <span class="sb-user-menu-dot" aria-hidden="true"></span>
                        <?php endif; ?>
                    </button>
                    <ul class="sb-user-menu-list" id="sb-user-menu-list" role="menu"
                        aria-labelledby="sb-user-menu-toggle">
                        <li role="none"><a role="menuitem" href="<?php echo esc_url($profile_url); ?>">
                            <?php esc_html_e('Mon profil', 'swiftboard'); ?></a></li>
                        <li role="none"><a role="menuitem" href="<?php echo esc_url(add_query_arg('tab', 'notifications', $profile_url)); ?>">
                            <?php esc_html_e('Mes notifications', 'swiftboard'); ?>
                            <?php if ($sb_unread > 0) : ?>
                                <span class="sb-user-menu-badge"><?php echo (int) $sb_unread; ?></span>
                            <?php endif; ?></a></li>
                        <li role="none"><a role="menuitem" href="<?php echo esc_url(add_query_arg('tab', 'posts', $profile_url)); ?>">
                            <?php esc_html_e('Mes sujets', 'swiftboard'); ?></a></li>
                        <li role="none"><a role="menuitem" href="<?php echo esc_url(add_query_arg('tab', 'saved', $profile_url)); ?>">
                            <?php esc_html_e('Sauvegardés', 'swiftboard'); ?></a></li>
                        <li role="none"><a role="menuitem" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">
                            <?php esc_html_e('Déconnexion', 'swiftboard'); ?></a></li>
                    </ul>
                </div>
            <?php else : ?>
                <a href="<?php echo esc_url(wp_login_url()); ?>" class="btn-secondary btn-text"><?php esc_html_e('Connexion', 'swiftboard'); ?></a>
                <?php
                // Appel a l'inscription toujours visible, comme sur Reddit.
                // Quand les inscriptions publiques sont fermees, on pointe vers
                // l'ecran de connexion plutot que de masquer le bouton : c'est
                // la principale action de conversion pour un visiteur anonyme.
                $sb_inscription_ouverte = (bool) get_option('users_can_register');
                $sb_url_inscription     = $sb_inscription_ouverte ? wp_registration_url() : wp_login_url();
                ?>
                <a href="<?php echo esc_url($sb_url_inscription); ?>" class="btn-primary btn-text sb-r-signup-btn-header" data-open-onboarding="true">
                    <?php esc_html_e('S\'inscrire', 'swiftboard'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<div id="main-content" class="site-content">

