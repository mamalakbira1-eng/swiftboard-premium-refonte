<?php
/**
 * SwiftBoard — Rendu d'une réponse façon Reddit (commentaire imbriqué).
 *
 * Ce fichier est VERSIONNÉ, et ce n'est pas un détail.
 * Il était auparavant écrit sur le disque à l'exécution par
 * swiftboard_ensure_reddit_reply_template(), accrochée à `admin_init`.
 * Conséquences observées :
 *   - absent après un déploiement (rsync / git pull) tant que personne
 *     n'avait ouvert l'admin ;
 *   - impossible à créer sur un hébergement dont wp-content est en lecture
 *     seule, ou avec DISALLOW_FILE_MODS ;
 *   - jamais présent pour un visiteur anonyme sur une instance fraîche.
 * Dans tous ces cas le forum retombait silencieusement sur le rendu bbPress
 * par défaut, sans vote, sans imbrication.
 *
 * @package SwiftBoard
 */

defined( 'ABSPATH' ) || exit;

// Repli défensif : si le module de rendu n'est pas chargé, on affiche au
// minimum l'auteur et le contenu, plutôt qu'une réponse vide.
//
// On n'appelle SURTOUT PAS bbp_get_template_part('loop','single-reply') ici :
// le filtre de nested-comments.php nous renverrait vers ce même fichier,
// donc récursion infinie.
if ( ! function_exists( 'swiftboard_reddit_reply' ) ) {
	?>
	<div <?php bbp_reply_class(); ?> id="reply-<?php bbp_reply_id(); ?>">
		<div class="bbp-reply-author"><?php bbp_reply_author_link(); ?></div>
		<div class="bbp-reply-content"><?php bbp_reply_content(); ?></div>
	</div>
	<?php
	return;
}

swiftboard_reddit_reply();

