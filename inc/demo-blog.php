<?php
/**
 * SwiftBoard — Import d'articles de blog de demonstration.
 *
 * POURQUOI CE MODULE
 * L'import de demo existant ne cree que du contenu bbPress : forums, sujets,
 * reponses. Le blog restait vide, et une page /blog/ sans article donne
 * l'impression d'un theme inacheve.
 *
 * Ce module publie dix articles illustres par les visuels deja livres dans
 * assets/img/blog et assets/img/topics. Les images sont importees dans la
 * mediatheque, ce qui permet a WordPress de generer ses tailles intermediaires
 * et de servir une image adaptee a chaque ecran.
 *
 * PRUDENCE
 *  - Aucun article n'est cree deux fois : chaque entree porte un marqueur
 *    _swiftboard_demo_blog, verifie avant insertion.
 *  - Rien n'est supprime. La desinstallation est proposee separement et ne
 *    touche qu'aux contenus portant ce marqueur.
 *  - L'import est manuel : jamais declenche a l'activation du theme.
 *
 * @package SwiftBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Catalogue des articles de demonstration.
 *
 * Les images referencees existent dans le theme ; un fichier manquant ne
 * bloque pas l'import, l'article est simplement publie sans illustration.
 *
 * @return array<int,array<string,string>>
 */
function swiftboard_demo_blog_articles() {
	return array(
		array(
			'titre'    => 'Cinq remèdes de grand-mère qui tiennent la route',
			'image'    => 'blog/blog-01-remedes.webp',
			'categorie' => 'Bien-être',
			'extrait'  => 'Toutes les recettes transmises en famille ne se valent pas. Voici celles que la recherche moderne ne contredit pas.',
			'contenu'  => "<p>Chaque famille garde ses recettes : une tisane contre le rhume, un cataplasme pour les courbatures, un sirop maison quand la gorge pique. Certaines relèvent de la croyance, d'autres reposent sur des mécanismes bien documentés.</p>

<h2>Le miel contre la toux nocturne</h2>
<p>C'est le remède le mieux étayé de cette liste. Une cuillère de miel avant le coucher apaise une toux sèche chez l'adulte et l'enfant de plus d'un an. Sa texture tapisse la gorge et calme l'irritation.</p>
<p><strong>Jamais avant un an</strong> : le miel peut contenir des spores responsables du botulisme infantile.</p>

<h2>L'inhalation de vapeur</h2>
<p>Elle ne raccourcit pas un rhume, mais elle fluidifie les sécrétions et rend la respiration plus confortable. Un bol d'eau chaude et une serviette suffisent.</p>

<h2>Le gargarisme à l'eau salée</h2>
<p>Une demi-cuillère de sel dans un verre d'eau tiède, deux à trois fois par jour. Le mécanisme est simple : l'eau salée réduit l'œdème des tissus enflammés.</p>

<h2>Ce qui ne fonctionne pas</h2>
<p>L'ail cru en application locale peut brûler la peau. Les huiles essentielles pures sur une muqueuse sont irritantes. Un remède naturel n'est pas un remède inoffensif.</p>

<p><em>Ces conseils ne remplacent pas un avis médical. Une fièvre qui dure plus de trois jours, une gêne respiratoire ou une douleur intense justifient une consultation.</em></p>",
		),
		array(
			'titre'    => 'Le régime méditerranéen, sans le folklore',
			'image'    => 'blog/blog-02-mediterranee.webp',
			'categorie' => 'Nutrition',
			'extrait'  => 'Ce n\'est ni un régime amaigrissant ni une mode : c\'est une façon de manger étudiée depuis soixante ans.',
			'contenu'  => "<p>On l'associe à des photos de tables ensoleillées. Derrière l'image, il y a l'une des rares alimentations dont les bénéfices sont mesurés sur des décennies.</p>

<h2>Ce qu'il contient vraiment</h2>
<p>Beaucoup de légumes, de légumineuses et de céréales complètes. De l'huile d'olive comme corps gras principal. Du poisson plusieurs fois par semaine. Peu de viande rouge, peu de produits transformés.</p>

<h2>Ce qui change concrètement</h2>
<p>Les études de suivi montrent une réduction du risque cardiovasculaire et un meilleur profil métabolique. L'effet ne vient pas d'un aliment miracle, mais de la structure globale des repas.</p>

<h2>L'adapter sans se ruiner</h2>
<p>Les légumineuses sèches coûtent peu et se conservent longtemps. Les légumes de saison remplacent avantageusement les produits importés. Le poisson en conserve garde l'essentiel de ses apports.</p>

<p>Le point le plus souvent oublié : ce mode d'alimentation s'accompagne traditionnellement de repas partagés et d'une activité physique régulière. Le contexte compte autant que le contenu de l'assiette.</p>",
		),
		array(
			'titre'    => 'Santé mentale : reconnaître les signaux faibles',
			'image'    => 'blog/blog-03-sante-mentale.webp',
			'categorie' => 'Bien-être',
			'extrait'  => 'Avant l\'épuisement, il y a presque toujours des signes. Encore faut-il savoir les nommer.',
			'contenu'  => "<p>On parle volontiers du burn-out une fois qu'il est là. On parle beaucoup moins de la pente qui y mène, alors que c'est là que l'on peut encore agir facilement.</p>

<h2>Les signaux que l'on minimise</h2>
<ul>
<li>Un sommeil qui ne répare plus, même après une nuit complète</li>
<li>Une irritabilité inhabituelle sur des détails</li>
<li>La perte d'intérêt pour ce qui procurait du plaisir</li>
<li>Des difficultés de concentration sur des tâches simples</li>
</ul>

<h2>Pourquoi on les ignore</h2>
<p>Parce qu'ils s'installent lentement et qu'ils ressemblent à de la fatigue ordinaire. On se dit qu'un week-end suffira. Quand plusieurs signes persistent au-delà de deux semaines, ce n'est plus de la fatigue.</p>

<h2>Ce qui aide réellement</h2>
<p>Parler à quelqu'un — un proche, un médecin, un professionnel. Réduire ce qui peut l'être, même temporairement. Retrouver un rythme de sommeil régulier, qui est souvent le premier levier.</p>

<p><em>En cas de détresse, un professionnel de santé reste l'interlocuteur adapté. Des lignes d'écoute existent dans la plupart des pays.</em></p>",
		),
		array(
			'titre'    => 'Le jeûne intermittent : ce que l\'on sait, ce que l\'on ignore',
			'image'    => 'blog/blog-04-jeune.webp',
			'categorie' => 'Nutrition',
			'extrait'  => 'Une pratique très étudiée depuis dix ans, avec des résultats plus nuancés que ne le laissent croire les réseaux sociaux.',
			'contenu'  => "<p>Le principe est simple : concentrer les prises alimentaires sur une plage horaire réduite. Les promesses qui l'accompagnent, elles, sont souvent excessives.</p>

<h2>Ce qui est établi</h2>
<p>Le jeûne intermittent permet une perte de poids comparable à celle d'une restriction calorique classique. Sa force est pratique : pour certaines personnes, il est plus simple de ne pas manger le matin que de peser ses portions.</p>

<h2>Ce qui reste discuté</h2>
<p>Les effets métaboliques indépendants de la perte de poids font l'objet de résultats contradictoires. Les études à long terme chez l'humain sont encore peu nombreuses.</p>

<h2>Pour qui c'est déconseillé</h2>
<p>Femmes enceintes ou allaitantes, personnes diabétiques sous traitement, antécédents de troubles du comportement alimentaire, adolescents en croissance. Dans ces situations, un avis médical est indispensable.</p>

<p>Le meilleur schéma alimentaire reste celui que l'on peut tenir des années sans y penser.</p>",
		),
		array(
			'titre'    => 'L\'huile d\'argan, entre tradition et laboratoire',
			'image'    => 'blog/blog-05-argan.webp',
			'categorie' => 'Beauté',
			'extrait'  => 'Alimentaire ou cosmétique, ce n\'est pas la même huile — et la confusion coûte cher.',
			'contenu'  => "<p>Produite dans le sud-ouest marocain depuis des siècles, l'huile d'argan est arrivée dans les rayons cosmétiques du monde entier. Sa composition explique une partie de sa réputation.</p>

<h2>Deux huiles, deux usages</h2>
<p>L'huile <strong>alimentaire</strong> est extraite d'amandons torréfiés : goût de noisette prononcé, couleur ambrée. L'huile <strong>cosmétique</strong> provient d'amandons non torréfiés : plus claire, quasiment inodore.</p>
<p>Utiliser l'alimentaire sur la peau n'est pas dangereux, mais l'odeur persiste et le prix au litre est plus élevé.</p>

<h2>Ce que sa composition permet</h2>
<p>Riche en acides gras insaturés et en vitamine E, elle limite la perte en eau de la peau. C'est un bon émollient, ce qui est déjà beaucoup — mais ce n'est pas un traitement.</p>

<h2>Reconnaître une huile de qualité</h2>
<p>Pressée à froid, conditionnée en verre teinté, avec une origine identifiable. Une huile totalement inodore et très bon marché a souvent été coupée.</p>",
		),
		array(
			'titre'    => 'Ramadan : traverser le mois sans s\'épuiser',
			'image'    => 'blog/blog-06-ramadan.webp',
			'categorie' => 'Nutrition',
			'extrait'  => 'L\'organisation des repas et du sommeil pèse plus lourd que la durée du jeûne lui-même.',
			'contenu'  => "<p>La fatigue ressentie pendant le mois de jeûne vient rarement de la privation seule. Elle vient surtout de la déshydratation et du sommeil fragmenté.</p>

<h2>Le suhoor fait la journée</h2>
<p>Un repas d'avant l'aube composé uniquement de sucres rapides provoque une fringale en milieu de matinée. Céréales complètes, protéines et matières grasses de qualité tiennent nettement plus longtemps.</p>

<h2>Boire, mais autrement</h2>
<p>L'objectif est de répartir les apports entre la rupture du jeûne et le coucher, plutôt que d'ingérer un litre d'un coup. Le thé très sucré et le café en grande quantité accentuent la déshydratation.</p>

<h2>Le sommeil, angle mort</h2>
<p>C'est le facteur le plus souvent négligé. Une sieste courte en début d'après-midi compense une partie de la nuit écourtée et améliore nettement la vigilance.</p>

<p><em>Les personnes diabétiques, sous traitement au long cours, enceintes ou âgées doivent adapter leur pratique avec un médecin.</em></p>",
		),
		array(
			'titre'    => 'Mieux dormir : sept réglages qui changent tout',
			'image'    => 'blog/blog-07-sommeil.webp',
			'categorie' => 'Bien-être',
			'extrait'  => 'Pas de méthode miracle : des ajustements simples, appliqués avec régularité.',
			'contenu'  => "<p>L'insomnie occasionnelle touche presque tout le monde. Avant d'envisager un traitement, l'hygiène de sommeil règle une grande partie des cas.</p>

<h2>La régularité prime sur la durée</h2>
<p>Se lever à la même heure chaque jour, y compris le week-end, stabilise l'horloge interne. C'est le levier le plus efficace, et le plus difficile à tenir.</p>

<h2>La température de la chambre</h2>
<p>L'endormissement s'accompagne d'une baisse de la température corporelle. Une chambre à 18-19 °C facilite ce mécanisme ; une pièce surchauffée le contrarie.</p>

<h2>La lumière, le matin surtout</h2>
<p>On parle beaucoup des écrans le soir. On oublie que l'exposition à la lumière naturelle dans l'heure suivant le lever cale le rythme circadien bien plus efficacement.</p>

<h2>Le lit sert à dormir</h2>
<p>Travailler, manger ou regarder des séries au lit affaiblit l'association entre le lit et le sommeil. Si l'endormissement ne vient pas au bout de vingt minutes, mieux vaut se lever.</p>

<p>Un trouble du sommeil qui persiste au-delà d'un mois mérite un avis médical : il masque parfois une autre cause.</p>",
		),
		array(
			'titre'    => 'Diabète de type 2 : ce que l\'alimentation peut faire',
			'image'    => 'blog/blog-08-diabete.webp',
			'categorie' => 'Nutrition',
			'extrait'  => 'Une maladie chronique où les choix quotidiens pèsent autant que le traitement.',
			'contenu'  => "<p>Le diabète de type 2 se caractérise par une glycémie durablement élevée. L'alimentation ne remplace jamais un traitement prescrit, mais elle en conditionne l'efficacité.</p>

<h2>Index glycémique : utile, pas suffisant</h2>
<p>Il classe les aliments selon leur effet sur la glycémie. Sa limite est connue : il mesure un aliment isolé, alors qu'on mange des repas. Fibres, graisses et protéines ralentissent l'ensemble.</p>

<h2>L'ordre des aliments compte</h2>
<p>Commencer par les légumes et les protéines, terminer par les féculents : à composition identique, le pic glycémique est plus faible. C'est un ajustement gratuit et immédiat.</p>

<h2>L'activité physique agit vite</h2>
<p>Une marche de trente minutes après le repas améliore la captation du glucose par les muscles. Répétée, elle influence l'hémoglobine glyquée.</p>

<p><em>Tout changement alimentaire important doit être discuté avec l'équipe soignante, en particulier sous insuline ou sulfamides : le risque d'hypoglycémie est réel.</em></p>",
		),
		array(
			'titre'    => 'Le gingembre tient-il ses promesses ?',
			'image'    => 'topics/topic-01-gingembre.webp',
			'categorie' => 'Nutrition',
			'extrait'  => 'Nausées, digestion, douleurs articulaires : trois usages, trois niveaux de preuve très différents.',
			'contenu'  => "<p>Le gingembre est l'une des racines les plus étudiées. Les résultats sont solides sur un point, plus fragiles sur les autres.</p>

<h2>Les nausées : l'usage le mieux documenté</h2>
<p>Efficacité constatée contre les nausées de grossesse, du mal des transports et de certaines suites opératoires. C'est là que le niveau de preuve est le plus élevé.</p>

<h2>La digestion</h2>
<p>Il accélère légèrement la vidange gastrique, ce qui peut soulager une sensation de lourdeur. L'effet est réel mais modeste.</p>

<h2>Les douleurs articulaires</h2>
<p>Quelques études suggèrent un effet anti-inflammatoire léger. Les tailles d'échantillon restent trop faibles pour conclure.</p>

<h2>Précautions</h2>
<p>À forte dose, il peut interagir avec les anticoagulants. Signalez-le à votre médecin si vous suivez ce type de traitement.</p>",
		),
		array(
			'titre'    => 'Routine de soin : trois produits suffisent',
			'image'    => 'topics/topic-02-skincare.webp',
			'categorie' => 'Beauté',
			'extrait'  => 'Les routines à dix étapes vendent des produits. Votre peau en demande beaucoup moins.',
			'contenu'  => "<p>L'industrie cosmétique a un intérêt évident à multiplier les étapes. Les dermatologues, eux, recommandent une base courte et constante.</p>

<h2>Un nettoyant doux</h2>
<p>Matin et soir, sans savon décapant. Une peau qui tiraille après le nettoyage a été agressée : sa barrière protectrice mettra plusieurs heures à se reconstituer.</p>

<h2>Un hydratant adapté</h2>
<p>Même les peaux grasses en ont besoin. Une peau déshydratée produit souvent davantage de sébum en compensation.</p>

<h2>Une protection solaire</h2>
<p>C'est le seul produit dont l'effet anti-âge est démontré sans ambiguïté. Tous les jours, y compris en hiver et par temps couvert.</p>

<h2>Le reste est optionnel</h2>
<p>Sérums, acides, rétinoïdes : utiles pour des besoins précis, jamais indispensables. En ajouter plusieurs à la fois empêche de savoir lequel agit — ou lequel irrite.</p>",
		),
	);
}

/**
 * Importe une image du theme dans la mediatheque.
 *
 * @param string $relatif Chemin relatif dans assets/img/.
 * @param string $titre   Titre de l'article, sert de texte alternatif.
 * @return int Identifiant de la piece jointe, 0 en cas d'echec.
 */
function swiftboard_demo_blog_importer_image( $relatif, $titre ) {
	$source = trailingslashit( get_template_directory() ) . 'assets/img/' . $relatif;
	if ( ! file_exists( $source ) ) {
		return 0;
	}

	// Une image deja importee est reutilisee : relancer l'import ne doit pas
	// remplir la mediatheque de doublons.
	$existante = get_posts(
		array(
			'post_type'      => 'attachment',
			'posts_per_page' => 1,
			'post_status'    => 'inherit',
			'fields'         => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- import manuel, hors requete front.
			'meta_query'     => array(
				array(
					'key'   => '_swiftboard_demo_source',
					'value' => $relatif,
				),
			),
		)
	);
	if ( $existante ) {
		return (int) $existante[0];
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$upload = wp_upload_dir();
	if ( ! empty( $upload['error'] ) ) {
		return 0;
	}

	$nom         = wp_unique_filename( $upload['path'], basename( $relatif ) );
	$destination = trailingslashit( $upload['path'] ) . $nom;

	if ( ! copy( $source, $destination ) ) {
		return 0;
	}

	$type = wp_check_filetype( $destination, null );
	$id   = wp_insert_attachment(
		array(
			'post_mime_type' => $type['type'] ? $type['type'] : 'image/webp',
			'post_title'     => $titre,
			'post_content'   => '',
			'post_status'    => 'inherit',
		),
		$destination
	);

	if ( is_wp_error( $id ) || ! $id ) {
		return 0;
	}

	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $destination ) );
	update_post_meta( $id, '_wp_attachment_image_alt', $titre );
	update_post_meta( $id, '_swiftboard_demo_source', $relatif );

	return (int) $id;
}

/**
 * Publie les articles de demonstration.
 *
 * @return array{crees:int,ignores:int,images:int}
 */
function swiftboard_demo_blog_importer() {
	$crees   = 0;
	$ignores = 0;
	$images  = 0;

	// Les dates s'echelonnent vers le passe : un blog dont tous les articles
	// portent la meme date se voit immediatement.
	$decalage = 0;

	foreach ( swiftboard_demo_blog_articles() as $article ) {
		$existant = get_posts(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'title'          => $article['titre'],
			)
		);
		if ( $existant ) {
			++$ignores;
			continue;
		}

		$categorie_id = 0;
		if ( ! empty( $article['categorie'] ) ) {
			$terme = term_exists( $article['categorie'], 'category' );
			if ( ! $terme ) {
				$terme = wp_insert_term( $article['categorie'], 'category' );
			}
			if ( ! is_wp_error( $terme ) && isset( $terme['term_id'] ) ) {
				$categorie_id = (int) $terme['term_id'];
			}
		}

		$decalage += wp_rand( 2, 6 );
		$date      = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $decalage . ' days' ) );

		$post_id = wp_insert_post(
			array(
				'post_title'    => $article['titre'],
				'post_content'  => $article['contenu'],
				'post_excerpt'  => $article['extrait'],
				'post_status'   => 'publish',
				'post_type'     => 'post',
				'post_date'     => $date,
				'post_date_gmt' => $date,
				'post_category' => $categorie_id ? array( $categorie_id ) : array(),
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			continue;
		}

		update_post_meta( $post_id, '_swiftboard_demo_blog', 1 );

		$image_id = swiftboard_demo_blog_importer_image( $article['image'], $article['titre'] );
		if ( $image_id ) {
			set_post_thumbnail( $post_id, $image_id );
			++$images;
		}

		++$crees;
	}

	return array(
		'crees'   => $crees,
		'ignores' => $ignores,
		'images'  => $images,
	);
}

/**
 * Supprime les articles de demonstration.
 *
 * Ne touche QU'AUX contenus portant le marqueur _swiftboard_demo_blog :
 * un article redige par l'utilisateur ne peut pas etre emporte.
 *
 * @return int Nombre d'articles supprimes.
 */
function swiftboard_demo_blog_supprimer() {
	$ids = get_posts(
		array(
			'post_type'      => 'post',
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'fields'         => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- action manuelle en admin.
			'meta_key'       => '_swiftboard_demo_blog',
		)
	);
	$n = 0;
	foreach ( $ids as $id ) {
		if ( wp_delete_post( $id, true ) ) {
			++$n;
		}
	}
	return $n;
}

/**
 * Traite la demande d'import ou de suppression.
 *
 * @return void
 */
function swiftboard_demo_blog_action() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'swiftboard' ) );
	}
	check_admin_referer( 'swiftboard_demo_blog' );

	$action = isset( $_POST['sb_action'] ) ? sanitize_key( wp_unslash( $_POST['sb_action'] ) ) : '';

	if ( 'importer' === $action ) {
		$r = swiftboard_demo_blog_importer();
		set_transient(
			'swiftboard_demo_blog_avis',
			sprintf(
				/* translators: 1: articles crees, 2: images, 3: ignores. */
				__( '%1$d article(s) publié(s), %2$d image(s) importée(s), %3$d déjà présent(s).', 'swiftboard' ),
				$r['crees'],
				$r['images'],
				$r['ignores']
			),
			60
		);
	} elseif ( 'supprimer' === $action ) {
		$n = swiftboard_demo_blog_supprimer();
		set_transient(
			'swiftboard_demo_blog_avis',
			sprintf(
				/* translators: %d: nombre d'articles supprimes. */
				__( '%d article(s) de démonstration supprimé(s).', 'swiftboard' ),
				$n
			),
			60
		);
	}

	wp_safe_redirect( admin_url( 'admin.php?page=swiftboard-demos' ) );
	exit;
}
add_action( 'admin_post_swiftboard_demo_blog', 'swiftboard_demo_blog_action' );

/**
 * Encart d'import, affiche sous les cartes de demo.
 *
 * @return void
 */
function swiftboard_demo_blog_encart() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Mise en forme portee par une regle nommee plutot que par des attributs
	// style : c'est surchargeable, et le theme s'interdit le style inline
	// (regle R02 de l'audit, appliquee aussi au back-office).
	wp_add_inline_style(
		'common',
		'.sb-demo-blog{max-width:760px;margin-top:24px;padding:20px}'
		. '.sb-demo-blog h2{margin-top:0}'
		. '.sb-demo-blog-form{display:inline-block;margin-right:8px}'
	);

	$avis = get_transient( 'swiftboard_demo_blog_avis' );
	if ( $avis ) {
		delete_transient( 'swiftboard_demo_blog_avis' );
		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $avis ) );
	}

	$deja = count(
		get_posts(
			array(
				'post_type'      => 'post',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- ecran d'administration.
				'meta_key'       => '_swiftboard_demo_blog',
			)
		)
	);
	$total = count( swiftboard_demo_blog_articles() );
	?>
	<div class="sb-demo-blog card">
		<h2><?php esc_html_e( 'Articles de blog de démonstration', 'swiftboard' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: %d: nombre d'articles disponibles. */
				esc_html__( 'Publie %d articles illustrés, avec leurs images importées dans la médiathèque. Utile pour remplir la page Blog et juger du rendu réel.', 'swiftboard' ),
				(int) $total
			);
			?>
		</p>
		<p class="description">
			<?php esc_html_e( 'Vos articles existants ne sont pas modifiés. Un article déjà importé n’est jamais dupliqué.', 'swiftboard' ); ?>
		</p>

		<?php if ( $deja > 0 ) : ?>
			<p>
				<strong>
				<?php
				printf(
					/* translators: 1: articles importes, 2: total. */
					esc_html__( '%1$d article(s) de démonstration sur %2$d sont déjà publiés.', 'swiftboard' ),
					(int) $deja,
					(int) $total
				);
				?>
				</strong>
			</p>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sb-demo-blog-form">
			<?php wp_nonce_field( 'swiftboard_demo_blog' ); ?>
			<input type="hidden" name="action" value="swiftboard_demo_blog">
			<input type="hidden" name="sb_action" value="importer">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Publier les articles de démonstration', 'swiftboard' ); ?>
			</button>
		</form>

		<?php if ( $deja > 0 ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sb-demo-blog-form"
				onsubmit="return confirm('<?php echo esc_js( __( 'Supprimer les articles de démonstration ? Vos propres articles ne sont pas concernés.', 'swiftboard' ) ); ?>');">
				<?php wp_nonce_field( 'swiftboard_demo_blog' ); ?>
				<input type="hidden" name="action" value="swiftboard_demo_blog">
				<input type="hidden" name="sb_action" value="supprimer">
				<button type="submit" class="button"><?php esc_html_e( 'Les retirer', 'swiftboard' ); ?></button>
			</form>
		<?php endif; ?>
	</div>
	<?php
}
add_action( 'swiftboard_apres_demos', 'swiftboard_demo_blog_encart' );
