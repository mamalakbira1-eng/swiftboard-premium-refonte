<?php
/**
 * SwiftBoard — Rendu d'un sujet dans la liste, façon Reddit (carte + vote).
 *
 * Ce fichier est VERSIONNÉ. Il était auparavant généré sur le disque par
 * swiftboard_ensure_reddit_template() sur `admin_init` : absent après un
 * déploiement, impossible à écrire sur un hébergement en lecture seule, et
 * jamais créé pour un visiteur anonyme sur une instance fraîche. Le forum
 * retombait alors sur le rendu bbPress par défaut.
 *
 * @package SwiftBoard
 */

defined( 'ABSPATH' ) || exit;

// Repli défensif : titre cliquable a minima, plutôt qu'une ligne vide.
//
// On n'appelle PAS bbp_get_template_part('loop','single-topic') : le filtre
// de reddit-layout.php nous renverrait ici même → récursion infinie.
if ( ! function_exists( 'swiftboard_reddit_topic_card' ) ) {
	?>
	<li class="bbp-body">
		<h3 class="bbp-topic-title">
			<a href="<?php bbp_topic_permalink(); ?>"><?php bbp_topic_title(); ?></a>
		</h3>
	</li>
	<?php
	return;
}

swiftboard_reddit_topic_card();

