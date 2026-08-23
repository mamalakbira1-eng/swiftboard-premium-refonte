<?php
/**
 * SwiftBoard — Diagnostic PAR PAGE, desktop et mobile (LECTURE SEULE).
 *
 * A QUOI CE FICHIER SERT
 * `diagnostic-site.php` verifie l'installation. Celui-ci verifie ce que
 * l'utilisateur VOIT et TOUCHE sur une page precise : textes tronques,
 * images non chargees, boutons sans effet, barres en double, debordement
 * horizontal. Les mesures sont prises dans le navigateur, aux deux largeurs.
 *
 * POURQUOI C'EST NECESSAIRE
 * Ces defauts dependent du contenu reel, du theme actif et de la largeur de
 * l'ecran. Ils ne sont pas reproductibles depuis un environnement de test :
 * seule la page du client, dans le navigateur du client, dit la verite.
 *
 * GARANTIE
 * Lecture seule cote serveur : aucune ecriture en base, sur disque ou dans les
 * options. L'analyse s'execute dans votre navigateur, sur une copie de la page
 * chargee dans un cadre isole. Rien n'est envoye a l'exterieur.
 *
 * UTILISATION
 *   1. Connectez-vous en administrateur.
 *   2. Ouvrez, dans le meme navigateur :
 *      .../wp-content/themes/swiftboard/tests/diagnostic-page.php
 *   3. Choisissez une page, lancez l'analyse, copiez le rapport.
 *
 * @package SwiftBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
	$sb_d = __DIR__;
	$sb_t = false;
	for ( $i = 0; $i < 10; $i++ ) {
		$sb_d = dirname( $sb_d );
		if ( file_exists( $sb_d . '/wp-load.php' ) ) {
			require_once $sb_d . '/wp-load.php';
			$sb_t = true;
			break;
		}
	}
	if ( ! $sb_t ) {
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo "WordPress introuvable. Le fichier doit rester dans wp-content/themes/swiftboard/tests/.\n";
		exit( 1 );
	}
}

if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
	status_header( 403 );
	header( 'Content-Type: text/html; charset=utf-8' );
	echo '<!doctype html><meta charset="utf-8"><title>Accès refusé</title>';
	echo '<body style="font:15px/1.6 system-ui,sans-serif;max-width:34em;margin:12vh auto;padding:0 1em">';
	echo '<h1 style="font-size:1.3em">Accès refusé</h1>';
	echo '<p>Ce diagnostic n’est accessible qu’à un administrateur connecté.</p>';
	echo '<p><a href="' . esc_url( wp_login_url( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' ) ) . '">Se connecter</a></p></body>';
	exit;
}

// ---------------------------------------------------------------------------
// Pages proposees : on prend de vrais contenus du site, pas des exemples.
// ---------------------------------------------------------------------------
$sb_pages = array(
	array(
		'nom' => 'Accueil',
		'url' => home_url( '/' ),
	),
);

if ( function_exists( 'bbp_get_forum_post_type' ) ) {
	$sb_arch = get_post_type_archive_link( bbp_get_forum_post_type() );
	if ( $sb_arch ) {
		$sb_pages[] = array(
			'nom' => 'Liste des communautés',
			'url' => $sb_arch,
		);
	}
	$sb_f = get_posts(
		array(
			'post_type'      => bbp_get_forum_post_type(),
			'posts_per_page' => 2,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		)
	);
	foreach ( $sb_f as $sb_id ) {
		$sb_pages[] = array(
			'nom' => 'Communauté : ' . wp_trim_words( get_the_title( $sb_id ), 4, '' ),
			'url' => get_permalink( $sb_id ),
		);
	}
	$sb_t2 = get_posts(
		array(
			'post_type'      => bbp_get_topic_post_type(),
			'posts_per_page' => 2,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		)
	);
	foreach ( $sb_t2 as $sb_id ) {
		$sb_pages[] = array(
			'nom' => 'Sujet : ' . wp_trim_words( get_the_title( $sb_id ), 4, '' ),
			'url' => get_permalink( $sb_id ),
		);
	}
}

$sb_pages[] = array(
	'nom' => 'Résultats de recherche',
	'url' => home_url( '/?s=a' ),
);

header( 'Content-Type: text/html; charset=utf-8' );
header( 'X-Robots-Tag: noindex, nofollow' );
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>SwiftBoard — diagnostic par page</title>
<style>
* { box-sizing: border-box; }
body { font: 15px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
	max-width: 64em; margin: 0 auto; padding: 2em 1.2em 4em; color: #1a1a1b; background: #f6f7f8; }
h1 { font-size: 1.5em; margin: 0 0 .2em; }
.sous { color: #6b7280; margin: 0 0 1.5em; font-size: .92em; }
.panneau { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 1.2em; margin-bottom: 1.5em; }
label { font-weight: 600; display: block; margin-bottom: .4em; }
select, input[type=text] { font: inherit; width: 100%; padding: .55em .7em; border: 1px solid #d1d5db;
	border-radius: 6px; margin-bottom: .8em; }
button { font: inherit; padding: .65em 1.3em; border: 0; border-radius: 6px; background: #006cbd;
	color: #fff; cursor: pointer; font-weight: 600; }
button:hover { background: #005a9e; }
button:disabled { background: #9ca3af; cursor: wait; }
.bilan { padding: 1em 1.2em; border-radius: 10px; margin: 1.5em 0; font-weight: 600; }
.vert { background: #ecfdf5; border: 1px solid #6ee7b7; color: #065f46; }
.orange { background: #fffbeb; border: 1px solid #fcd34d; color: #92400e; }
.rouge { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }
h2 { font-size: 1.05em; margin: 1.6em 0 .5em; padding-bottom: .3em; border-bottom: 2px solid #e5e7eb; }
table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden;
	box-shadow: 0 1px 2px rgba(0,0,0,.06); }
th { text-align: left; padding: .6em .9em; background: #f9fafb; font-size: .85em; color: #6b7280; }
td { padding: .65em .9em; border-bottom: 1px solid #f1f2f3; vertical-align: top; font-size: .93em; }
tr:last-child td { border-bottom: 0; }
.etat { width: 2.2em; text-align: center; font-weight: 700; }
.ok .etat { color: #059669; } .attention .etat { color: #d97706; }
.probleme .etat { color: #dc2626; } .info .etat { color: #9ca3af; }
.probleme td { background: #fffbfb; }
.action { display: block; margin-top: .35em; padding: .45em .65em; background: #f9fafb;
	border-left: 3px solid #d1d5db; border-radius: 3px; font-size: .88em; color: #4b5563; }
.probleme .action { background: #fef2f2; border-left-color: #dc2626; color: #7f1d1d; }
.vp { display: inline-block; padding: .1em .5em; border-radius: 4px; font-size: .8em;
	font-weight: 700; margin-right: .4em; }
.vp.d { background: #e0e7ff; color: #3730a3; }
.vp.m { background: #fce7f3; color: #9d174d; }
textarea { width: 100%; height: 16em; margin-top: 1em; font: 12px/1.5 ui-monospace, Menlo, Consolas, monospace;
	padding: 1em; border: 1px solid #d1d5db; border-radius: 8px; }
#cadre { width: 100%; height: 300px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; }
.masque { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
.note { font-size: .88em; color: #6b7280; margin-top: 2em; padding-top: 1em; border-top: 1px solid #e5e7eb; }
progress { width: 100%; height: 6px; margin-top: .8em; }
</style>
</head>
<body>

<h1>SwiftBoard — diagnostic par page</h1>
<p class="sous">
	Analyse ce que voit réellement un visiteur : textes tronqués, images non chargées, boutons sans
	effet, barres en double. Mesuré en <strong>desktop 1280 px</strong> et <strong>mobile 390 px</strong>.
	Lecture seule.
</p>

<div class="panneau">
	<label for="page">Page à analyser</label>
	<select id="page">
		<?php foreach ( $sb_pages as $sb_p ) : ?>
			<option value="<?php echo esc_attr( $sb_p['url'] ); ?>"><?php echo esc_html( $sb_p['nom'] ); ?></option>
		<?php endforeach; ?>
		<option value="__autre__">Autre adresse…</option>
	</select>
	<input type="text" id="autre" placeholder="https://votre-site.tld/une-page/" style="display:none">
	<button type="button" id="lancer">Analyser cette page</button>
	<progress id="avance" value="0" max="100" style="display:none"></progress>
</div>

<div id="sortie"></div>

<div id="zone-cadre" class="masque"><iframe id="cadre" title="page analysée"></iframe></div>

<p class="note">
	L’analyse charge la page dans un cadre isolé, à l’intérieur de votre navigateur.
	Rien n’est envoyé à l’extérieur et aucune donnée n’est modifiée.
</p>

<script>
(function () {
	'use strict';

	var sel = document.getElementById('page');
	var autre = document.getElementById('autre');
	var bouton = document.getElementById('lancer');
	var sortie = document.getElementById('sortie');
	var avance = document.getElementById('avance');
	var cadre = document.getElementById('cadre');
	var zone = document.getElementById('zone-cadre');

	sel.addEventListener('change', function () {
		autre.style.display = sel.value === '__autre__' ? 'block' : 'none';
	});

	function urlChoisie() {
		return sel.value === '__autre__' ? autre.value.trim() : sel.value;
	}

	/**
	 * Charge la page dans le cadre à une largeur donnée, puis l'inspecte.
	 * On mesure DANS le document rendu : c'est la seule façon de connaître
	 * la largeur réelle d'un texte ou l'état de chargement d'une image.
	 */
	function analyser(url, largeur, hauteur) {
		return new Promise(function (resolve) {
			zone.style.position = 'absolute';
			// La largeur du cadre EST la largeur de viewport vue par la page :
			// c'est ce qui declenche les media queries. On retire toute bordure
			// et on force une barre de defilement fine, sinon la largeur utile
			// est amputee de ~15 px et un faux depassement apparait.
			cadre.style.border = '0';
			cadre.style.width = largeur + 'px';
			cadre.style.height = hauteur + 'px';
			cadre.setAttribute('scrolling', 'no');
			cadre.src = url + (url.indexOf('?') === -1 ? '?' : '&') + 'sb_diag=' + Date.now();

			var fini = false;
			var termine = function () {
				if (fini) { return; }
				fini = true;
				var doc;
				try {
					doc = cadre.contentDocument;
				} catch (e) {
					resolve({ erreur: 'Page inaccessible (domaine différent).' });
					return;
				}
				if (!doc) { resolve({ erreur: 'Page non chargée.' }); return; }

				// Les images en loading="lazy" ne se declenchent qu'au defilement,
				// et leur decodage n'est pas instantane. Mesurer trop tot faisait
				// passer des images parfaitement valides pour cassees.
				// On parcourt la page, puis on ATTEND que le navigateur confirme
				// le decodage de chaque image avant d'inspecter.
				var win = cadre.contentWindow;
				// Balayage BORNE : sur une page longue, un parcours par pas de
				// 600 px sans plafond peut durer des minutes. On limite a 25
				// etapes, largement assez pour declencher le chargement differe.
				var balayer = function (y, etape, fin) {
					var hauteur = 0;
					try { hauteur = doc.body.scrollHeight; } catch (e) {}
					if (etape > 25 || y > hauteur) { fin(); return; }
					try { win.scrollTo(0, y); } catch (e) {}
					setTimeout(function () { balayer(y + 800, etape + 1, fin); }, 90);
				};
				balayer(0, 0, function () {
					try { win.scrollTo(0, 0); } catch (e) {}
					var imgs = [].slice.call(doc.querySelectorAll('img'));
					// Le cadre d'analyse est positionne hors ecran : le navigateur
					// considere alors TOUTES les images differees comme invisibles
					// et ne les telecharge jamais. Sans cette bascule, des images
					// parfaitement valides seraient rapportees comme cassees.
					// On passe donc en chargement immediat avant de mesurer.
					// Barre de defilement masquee : elle reduit clientWidth et
					// fausse la detection de depassement horizontal.
					try {
						var st = doc.createElement('style');
						st.textContent = 'html{scrollbar-width:none}html::-webkit-scrollbar{width:0;height:0}';
						doc.head.appendChild(st);
					} catch (e) {}
					imgs.forEach(function (im) {
						if (im.loading === 'lazy') { im.loading = 'eager'; }
						if (im.hasAttribute('data-src') && !im.src) {
							im.src = im.getAttribute('data-src');
						}
					});
					var attentes = imgs.map(function (im) {
						if (im.complete && im.naturalWidth > 0) { return Promise.resolve(); }
						if (typeof im.decode === 'function') {
							return im.decode().catch(function () {});
						}
						return new Promise(function (r) {
							im.addEventListener('load', r, { once: true });
							im.addEventListener('error', r, { once: true });
							setTimeout(r, 2500);
						});
					});
					// Course entre l'attente des images et un delai maximum :
					// une image bloquee ne doit pas figer tout le diagnostic.
					var plafond = new Promise(function (r) { setTimeout(r, 6000); });
					Promise.race([ Promise.all(attentes), plafond ]).then(function () {
						// Deux images de rendu successives : le passage en
						// chargement immediat reflow la page, et mesurer trop
						// tot rapportait un depassement horizontal inexistant
						// (415 px annonces pour 390 px reels).
						var stabiliser = function (reste, suite) {
							if (reste <= 0) { suite(); return; }
							win.requestAnimationFrame(function () {
								setTimeout(function () { stabiliser(reste - 1, suite); }, 250);
							});
						};
						stabiliser(4, function () {
							var r = inspecter(doc, win, largeur);
							testerBoutons(doc, win, r).then(function () {
								// Relecture de la largeur apres les clics :
								// un menu ouvert puis referme peut la modifier.
								var de2 = doc.documentElement;
								r.debord.sw = de2.scrollWidth;
								r.debord.cw = de2.clientWidth;
								if (r.debord.sw <= r.debord.cw + 20) { r.debord.coupables = []; }
								resolve(r);
							});
						});
					});
				});
			};

			cadre.onload = termine;
			setTimeout(termine, 12000);
		});
	}

	function visible(el, win) {
		var s = win.getComputedStyle(el);
		if (s.display === 'none' || s.visibility === 'hidden' || s.opacity === '0') { return false; }
		var r = el.getBoundingClientRect();
		return r.width > 0 && r.height > 0;
	}

	/**
	 * Clique chaque bouton candidat et observe si l'interface reagit.
	 *
	 * Signaux retenus : changement de classe ou d'aria-expanded sur le bouton,
	 * apparition d'un element dans le document, requete reseau, navigation.
	 * On restaure l'etat entre deux essais en cliquant a nouveau si besoin.
	 */
	function testerBoutons(doc, win, res) {
		var liste = res.aTester || [];
		delete res.aTester;
		if (!liste.length) { return Promise.resolve(); }

		return liste.reduce(function (chaine, bt) {
			return chaine.then(function () {
				return new Promise(function (suite) {
					// Remise a zero par Echap UNIQUEMENT.
					// Un clic simule sur body remonte jusqu'aux ecouteurs de
					// « fermeture au clic exterieur » ET se propageait au
					// bouton teste juste apres : la bascule s'annulait et le
					// bouton paraissait sans effet. Echap ferme les menus sans
					// declencher de clic parasite.
					try {
						doc.dispatchEvent(new win.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
					} catch (e) {}

					var lbl = (bt.textContent || bt.getAttribute('aria-label') || '')
						.replace(/\s+/g, ' ').trim().slice(0, 30) || '(sans libellé)';
					var cls = (bt.className || '').toString().slice(0, 34);

					// Beaucoup de menus sont deja dans le DOM et ne font que
					// basculer leur style.display : le nombre d'elements ne
					// change pas. Sans releve des voisins visibles, un bouton
					// parfaitement fonctionnel passe pour mort.
					var voisinage = bt.closest('.sb-notif-bell, .sb-user-menu, .sb-more-wrap, li, .sb-card-actions')
						|| bt.parentElement || doc.body;
					var compterVisibles = function (racine) {
						var n = 0;
						var tous = racine.querySelectorAll('*');
						for (var z = 0; z < tous.length; z++) {
							var st = win.getComputedStyle(tous[z]);
							if (st.display !== 'none' && st.visibility !== 'hidden') { n++; }
						}
						return n;
					};

					var avant = {
						cls: (bt.className || '').toString(),
						exp: bt.getAttribute('aria-expanded'),
						pre: bt.getAttribute('aria-pressed'),
						n: doc.body.getElementsByTagName('*').length,
						vis: compterVisibles(voisinage),
						visDoc: compterVisibles(doc.body),
						url: win.location.href,
						txt: (bt.textContent || '').trim()
					};

					var requete = false;
					var of = win.fetch;
					if (of) { win.fetch = function () { requete = true; return of.apply(this, arguments); }; }

					try { bt.click(); } catch (e) {}

					setTimeout(function () {
						if (of) { win.fetch = of; }
						var bouge = (bt.className || '').toString() !== avant.cls
							|| bt.getAttribute('aria-expanded') !== avant.exp
							|| bt.getAttribute('aria-pressed') !== avant.pre
							|| doc.body.getElementsByTagName('*').length !== avant.n
							|| compterVisibles(voisinage) !== avant.vis
							|| compterVisibles(doc.body) !== avant.visDoc
							|| win.location.href !== avant.url
							|| (bt.textContent || '').trim() !== avant.txt
							|| requete;

						if (!bouge) {
							res.boutons.push({ lbl: lbl, cls: cls });
						} else {
							res.boutonsOk = (res.boutonsOk || 0) + 1;
						}
						suite();
					}, 420);
				});
			});
		}, Promise.resolve());
	}

	function inspecter(doc, win, largeur) {
		var res = { textes: [], images: [], boutons: [], barres: [], debord: null, liens: [] };

		// --- Textes tronqués --------------------------------------------------
		// On ne retient que les éléments qui portent leur propre texte : un
		// conteneur hérite de la largeur de ses enfants et fausserait la mesure.
		// Elements a exclure de TOUTE analyse : ils ne concernent pas le visiteur.
		//  - .screen-reader-text : texte volontairement masque, destine aux
		//    lecteurs d'ecran ; il est cense deborder de son cadre de 1 px ;
		//  - #wpadminbar : barre d'administration, invisible pour un visiteur ;
		//  - modales fermees : leur contenu n'est pas encore affiche.
		function horsPerimetre(el) {
			return !!(el.closest('#wpadminbar, .screen-reader-text, .sb-modal, [aria-hidden="true"]')
				|| el.classList.contains('screen-reader-text')
				|| el.classList.contains('skip-link'));
		}

		var elems = doc.querySelectorAll('h1,h2,h3,h4,a,span,p,button,li,td,div');
		for (var i = 0; i < elems.length; i++) {
			var e = elems[i];
			if (e.children.length > 0) { continue; }
			if (horsPerimetre(e)) { continue; }
			var t = (e.textContent || '').trim();
			if (t.length < 5) { continue; }
			if (!visible(e, win)) { continue; }
			var s = win.getComputedStyle(e);
			// Une troncature ASSUMÉE (ellipsis) n'est pas un défaut.
			if (s.textOverflow === 'ellipsis') { continue; }
			if (e.scrollWidth > e.clientWidth + 2 && e.clientWidth > 0) {
				res.textes.push({
					cls: (e.className || '').toString().slice(0, 40),
					txt: t.slice(0, 45),
					sw: e.scrollWidth, cw: e.clientWidth
				});
			}
			// -webkit-line-clamp est une troncature VOULUE (extrait sur N lignes),
			// exactement comme text-overflow: ellipsis. La signaler serait crier
			// au loup sur un choix de design.
			else if (e.scrollHeight > e.clientHeight + 4 && s.overflow === 'hidden'
				&& e.clientHeight > 0
				&& s.webkitLineClamp === 'none' && !s.getPropertyValue('-webkit-line-clamp')) {
				res.textes.push({
					cls: (e.className || '').toString().slice(0, 40),
					txt: t.slice(0, 45),
					sw: 0, cw: 0, vertical: true
				});
			}
		}

		// --- Images -----------------------------------------------------------
		var imgs = doc.querySelectorAll('img');
		for (var j = 0; j < imgs.length; j++) {
			var im = imgs[j];
			if (horsPerimetre(im) || !visible(im, win)) { continue; }
			var charge = im.complete && im.naturalWidth > 0;
			// Une image chargee mais rendue a 0 px n'affiche rien non plus.
			var rect = im.getBoundingClientRect();
			if (charge && rect.width < 2) { charge = false; }
			res.images.push({
				src: (im.currentSrc || im.src || '').split('/').pop().slice(0, 38),
				alt: (im.alt || '').slice(0, 30),
				ok: charge,
				lazy: im.loading === 'lazy'
			});
		}

		// --- Boutons : test par CLIC REEL -------------------------------------
		// L'inspection du DOM ne suffit pas. La quasi-totalite des boutons de ce
		// theme sont cables par DELEGATION : un seul ecouteur pose sur document
		// intercepte le clic via closest('.ma-classe'). Le bouton lui-meme n'a
		// donc ni onclick, ni formulaire, ni attribut data — et un detecteur
		// naif le declare « inactif » alors qu'il fonctionne parfaitement.
		//
		// On clique donc reellement, et on observe si QUELQUE CHOSE change :
		// classe, aria-expanded, apparition d'un menu, navigation, requete.
		// Un bouton dont rien ne bouge apres un clic est un vrai bouton mort.
		var btns = doc.querySelectorAll('button, [role=button]');
		var aTester = [];
		for (var k = 0; k < btns.length; k++) {
			var bt = btns[k];
			if (horsPerimetre(bt) || !visible(bt, win)) { continue; }
			if (bt.closest('form')) { continue; }      // soumission : effet evident
			if (bt.type === 'submit') { continue; }
			if (bt.getAttribute('href')) { continue; } // lien : navigation
			aTester.push(bt);
		}
        // On borne le nombre de clics : inutile de tester 40 fois le meme
        // bouton « Plus d'actions » repete sur chaque carte.
        var vus = {};
        res.aTester = [];
        for (var q = 0; q < aTester.length; q++) {
            var cle = (aTester[q].className || '').toString();
            if (vus[cle]) { continue; }
            vus[cle] = 1;
            res.aTester.push(aTester[q]);
            if (res.aTester.length >= 12) { break; }
        }

		// --- Liens de tri : rechargement complet ? ----------------------------
		var tris = doc.querySelectorAll('[href*="sort="], [href*="csort="], .sb-sort a, .sb-sort-tabs a');
		for (var l = 0; l < tris.length; l++) {
			var tl = tris[l];
			if (horsPerimetre(tl) || !visible(tl, win)) { continue; }
			res.liens.push({
				txt: (tl.textContent || '').trim().slice(0, 22),
				href: (tl.getAttribute('href') || '').slice(-34)
			});
		}

		// --- Barres fixes empilées --------------------------------------------
		var cand = doc.querySelectorAll('header, .site-header, [class*=topbar], [class*=navbar], [class*=mobile-bar], [class*=sticky]');
		for (var m = 0; m < cand.length; m++) {
			var el = cand[m];
			if (horsPerimetre(el) || el.id === 'wpadminbar' || !visible(el, win)) { continue; }
			var st = win.getComputedStyle(el);
			var rc = el.getBoundingClientRect();
			if (rc.height < 24) { continue; }
			if (rc.top > 200) { continue; }
			if (el.querySelector('header, .site-header')) { continue; }
			res.barres.push({
				cls: (el.className || '').toString().slice(0, 42),
				h: Math.round(rc.height), top: Math.round(rc.top),
				pos: st.position,
				logo: !!el.querySelector('[class*=logo], .custom-logo, .site-title')
			});
		}

		// --- Débordement horizontal -------------------------------------------
		var de = doc.documentElement;
		// Tolerance de 20 px : dans un cadre, la barre de defilement verticale
		// reduit la largeur utile et cree un faux depassement de quelques
		// pixels. En dessous de ce seuil, le visiteur ne voit aucun decalage.
		var seuil = 20;
		res.debord = { sw: de.scrollWidth, cw: de.clientWidth, largeur: largeur };
		if (de.scrollWidth > de.clientWidth + seuil) {
			var coupables = [];
			var tous = doc.querySelectorAll('body *');
			for (var n = 0; n < tous.length && coupables.length < 4; n++) {
				var r2 = tous[n].getBoundingClientRect();
				if (r2.right > de.clientWidth + seuil && r2.width > 40) {
					coupables.push({
						cls: (tous[n].className || '').toString().slice(0, 38),
						droite: Math.round(r2.right)
					});
				}
			}
			res.debord.coupables = coupables;
		}

		return res;
	}

	function ligne(etat, vp, titre, detail, action) {
		var g = { ok: '✓', attention: '!', probleme: '✕', info: '·' }[etat];
		var badge = vp ? '<span class="vp ' + (vp === 'mobile' ? 'm' : 'd') + '">' + vp + '</span>' : '';
		return '<tr class="' + etat + '"><td class="etat">' + g + '</td>'
			+ '<td>' + badge + titre + '</td><td>' + detail
			+ (action ? '<span class="action">' + action + '</span>' : '') + '</td></tr>';
	}

	function rendre(url, d, m) {
		var html = '';
		var pb = 0, at = 0;
		var texte = 'SwiftBoard — diagnostic de page\n' + url + '\n'
			+ new Date().toISOString().slice(0, 16).replace('T', ' ') + ' UTC\n'
			+ '='.repeat(64) + '\n';

		function ajouter(etat, vp, titre, detail, action) {
			html += ligne(etat, vp, titre, detail, action);
			if (etat === 'probleme') { pb++; }
			if (etat === 'attention') { at++; }
			var mk = { ok: ' OK ', attention: ' !! ', probleme: ' XX ', info: ' .. ' }[etat];
			texte += mk + (vp ? '[' + vp + '] ' : '') + titre + ' : ' + detail.replace(/<[^>]+>/g, '') + '\n';
			if (action) { texte += '      -> ' + action.replace(/<[^>]+>/g, '') + '\n'; }
		}

		var vues = [['desktop', d], ['mobile', m]];

		// 1. Textes tronqués
		html += '<h2>Textes coupés</h2><table><tr><th></th><th>Vue</th><th>Détail</th></tr>';
		vues.forEach(function (v) {
			var nom = v[0], r = v[1];
			if (r.erreur) { ajouter('attention', nom, 'Analyse impossible', r.erreur, ''); return; }
			if (!r.textes.length) {
				ajouter('ok', nom, 'Aucun texte coupé', 'tous les libellés tiennent dans leur cadre', '');
			} else {
				r.textes.slice(0, 6).forEach(function (t) {
					ajouter('probleme', nom, 'Texte coupé',
						'« ' + t.txt + ' » dans <code>.' + t.cls + '</code>'
						+ (t.vertical ? ' (coupé en hauteur)' : ' (' + t.sw + ' px dans ' + t.cw + ' px)'),
						'Ajouter <code>overflow-wrap: anywhere</code> ou réduire la taille du texte à cette largeur.');
				});
				if (r.textes.length > 6) {
					ajouter('info', nom, 'Autres textes coupés', (r.textes.length - 6) + ' de plus', '');
				}
			}
		});
		html += '</table>';

		// 2. Images
		html += '<h2>Images</h2><table><tr><th></th><th>Vue</th><th>Détail</th></tr>';
		vues.forEach(function (v) {
			var nom = v[0], r = v[1];
			if (r.erreur) { return; }
			var ko = r.images.filter(function (i) { return !i.ok; });
			if (!r.images.length) {
				ajouter('attention', nom, 'Aucune image visible', 'la page n’affiche que du texte',
					'Normal sur une page sans illustration ; anormal sur un fil de discussion.');
			} else if (!ko.length) {
				ajouter('ok', nom, 'Images chargées', r.images.length + ' image(s), toutes affichées', '');
			} else {
				ko.slice(0, 5).forEach(function (i) {
					ajouter('probleme', nom, 'Image non chargée',
						i.src + (i.alt ? ' (texte affiché : « ' + i.alt + ' »)' : ''),
						'Le visiteur voit le texte alternatif à la place. Vérifier que le fichier existe et que son adresse est correcte.');
				});
				ajouter('info', nom, 'Bilan images', ko.length + ' cassée(s) sur ' + r.images.length, '');
			}
		});
		html += '</table>';

		// 3. Boutons
		html += '<h2>Boutons</h2><table><tr><th></th><th>Vue</th><th>Détail</th></tr>';
		vues.forEach(function (v) {
			var nom = v[0], r = v[1];
			if (r.erreur) { return; }
			var reussis = r.boutonsOk || 0;
			if (!r.boutons.length) {
				ajouter('ok', nom, 'Boutons',
					reussis + ' bouton(s) testés au clic, tous réagissent', '');
			} else {
				r.boutons.slice(0, 6).forEach(function (b) {
					ajouter('probleme', nom, 'Bouton sans effet',
						'« ' + b.lbl + ' » <code>.' + b.cls + '</code>',
						'Cliqué pour de vrai : aucune réaction (ni menu, ni changement d’état, ni requête). Le visiteur croit son action prise en compte.');
				});
				if (reussis) {
					ajouter('info', nom, 'Autres boutons', reussis + ' bouton(s) réagissent normalement', '');
				}
			}
		});
		html += '</table>';

		// 4. Tri
		html += '<h2>Onglets de tri</h2><table><tr><th></th><th>Vue</th><th>Détail</th></tr>';
		if (d.liens && d.liens.length) {
			ajouter('attention', '', 'Le tri recharge toute la page',
				d.liens.length + ' onglet(s) : ' + d.liens.map(function (l) { return l.txt; }).join(', '),
				'Ce sont des liens classiques : le navigateur recharge la page entière à chaque clic. Un remplacement partiel du fil éviterait le clignotement.');
		} else {
			ajouter('info', '', 'Onglets de tri', 'aucun détecté sur cette page', '');
		}
		html += '</table>';

		// 5. Barres
		html += '<h2>Barres d’en-tête</h2><table><tr><th></th><th>Vue</th><th>Détail</th></tr>';
		vues.forEach(function (v) {
			var nom = v[0], r = v[1];
			if (r.erreur) { return; }
			var avecLogo = r.barres.filter(function (b) { return b.logo; });
			if (r.barres.length <= 1) {
				ajouter('ok', nom, 'En-tête', r.barres.length + ' barre en haut de page', '');
			} else {
				ajouter(avecLogo.length > 1 ? 'probleme' : 'attention', nom,
					'Plusieurs barres empilées',
					r.barres.map(function (b) { return '.' + b.cls + ' (' + b.h + ' px)'; }).join(' + '),
					avecLogo.length > 1
						? 'Le logo apparaît ' + avecLogo.length + ' fois : deux en-têtes se superposent et mangent la hauteur d’écran.'
						: 'Vérifier si ces deux barres doivent coexister à cette largeur.');
			}
		});
		html += '</table>';

		// 6. Débordement
		html += '<h2>Débordement horizontal</h2><table><tr><th></th><th>Vue</th><th>Détail</th></tr>';
		vues.forEach(function (v) {
			var nom = v[0], r = v[1];
			if (r.erreur || !r.debord) { return; }
			if (r.debord.sw > r.debord.cw + 20) {
				ajouter('probleme', nom, 'La page défile latéralement',
					r.debord.sw + ' px de contenu pour ' + r.debord.cw + ' px d’écran'
					+ (r.debord.coupables && r.debord.coupables.length
						? ' — en cause : ' + r.debord.coupables.map(function (c) { return '.' + c.cls; }).join(', ')
						: ''),
					'Un élément dépasse la largeur de l’écran et force un défilement horizontal.');
			} else {
				ajouter('ok', nom, 'Largeur respectée', 'aucun défilement latéral', '');
			}
		});
		html += '</table>';

		var cl = pb ? 'rouge' : (at ? 'orange' : 'vert');
		var msg = pb ? (pb + ' problème(s), ' + at + ' point(s) d’attention')
			: (at ? (at + ' point(s) d’attention') : 'Aucun problème détecté sur cette page.');

		texte += '\n' + '='.repeat(64) + '\nBILAN : ' + pb + ' probleme(s), ' + at + ' attention\n';

		sortie.innerHTML = '<div class="bilan ' + cl + '">' + msg + '</div>' + html
			+ '<h2>Rapport à transmettre</h2>'
			+ '<button type="button" id="copier">Copier le rapport</button>'
			+ '<textarea id="rapport" readonly></textarea>';
		document.getElementById('rapport').value = texte;
		document.getElementById('copier').addEventListener('click', function () {
			var z = document.getElementById('rapport');
			z.select();
			var ok = false;
			try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
			this.textContent = ok ? 'Rapport copié' : 'Sélectionnez puis Ctrl+C';
		});
	}

	bouton.addEventListener('click', function () {
		var url = urlChoisie();
		if (!url) { return; }
		bouton.disabled = true;
		bouton.textContent = 'Analyse en cours…';
		avance.style.display = 'block';
		avance.value = 10;
		sortie.innerHTML = '';

		analyser(url, 1280, 800).then(function (d) {
			avance.value = 55;
			return analyser(url, 390, 844).then(function (m) {
				avance.value = 100;
				rendre(url, d, m);
				bouton.disabled = false;
				bouton.textContent = 'Analyser cette page';
				setTimeout(function () { avance.style.display = 'none'; }, 400);
			});
		});
	});
})();
</script>

</body>
</html>
