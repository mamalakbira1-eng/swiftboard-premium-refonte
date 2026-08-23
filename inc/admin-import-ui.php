<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Ecran d'import en masse.
 *
 * EXI-ARCH-02/03 : inc/admin-bulk-import.php melangeait 868 lignes de rendu
 * HTML et de logique d'import. Ce fichier ne porte plus que l'interface ;
 * l'import lui-meme (validation, parsing, creation des contenus) reste dans
 * inc/admin-bulk-import.php, decoupe en sous-fonctions testables.
 *
 * L'ecran refait son propre controle de capacite : la capability declaree a
 * add_submenu_page() ne protege pas l'appel direct via admin.php?page=...
 * (EXI-SEC-BLOQ-07).
 *
 * @package SwiftBoard
 * @since 5.1.0
 */
// ============================================================================
/**
 * @return void
 */
function swiftboard_bulk_import_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'Accès refusé.', 'swiftboard' ) );
	}

	$import_log                                 = get_option( 'swiftboard_last_import_log', array() );
	if ( ! is_array( $import_log )) $import_log = array();

	// Traitement de l'upload
	if ( isset( $_FILES['import_file'] ) && check_admin_referer( 'swiftboard_bulk_import' ) ) {
		$import_log = swiftboard_process_import( $_FILES['import_file'] );
		update_option( 'swiftboard_last_import_log', $import_log, false );
	}

	// Annulation (suppression des données importées)
	if ( isset( $_POST['cancel_import'] ) && check_admin_referer( 'swiftboard_cancel_import' ) ) {
		$result = swiftboard_cancel_last_import();
		echo '<div class="notice notice-' . ( $result['success'] ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $result['message'] ) . '</p></div>';
	}

	// Téléchargement du template est géré par admin_init hook
	$stats = swiftboard_get_import_stats();
	?>
	<div class="wrap">
		<h1>📥 Import en masse</h1>

		<!-- BOUTONS IMPORT DÉMO 1 CLIC -->
		<div style="background: linear-gradient(135deg, #006cbd, #0090e0); color:#fff; padding:20px 24px; border-radius:12px; margin:16px 0; max-width:1000px;">
			<h2 style="color:#fff; margin:0 0 8px;">🚀 Importer une démo en 1 clic</h2>
			<p style="margin:0 0 16px; opacity:0.9;">
				Pas envie de préparer un fichier ? Importez la démo complète en 1 clic :
				forums + sujets + réponses + articles de blog + avatars ninja + grades militaires + images.
			</p>
			<div style="display:flex; gap:12px; flex-wrap:wrap;">
				<button type="button" id="sb-demo-import-fr" style="background:#fff; color:#006cbd; border:none; font-weight:700; padding:10px 24px; font-size:14px; border-radius:8px; cursor:pointer;">
					🇫🇷 Démo française (35 membres + 20 sujets + 8 articles)
				</button>
				<button type="button" id="sb-demo-import-ar" style="background:rgba(255,255,255,0.2); color:#fff; border:2px solid #fff; font-weight:700; padding:10px 24px; font-size:14px; border-radius:8px; cursor:pointer;">
					🇲🇦 استيراد النسخة العربية (35 عضو + 20 موضوع + 8 مقالات)
				</button>
			</div>
			<div id="sb-demo-import-status" style="margin-top:12px; display:none; background:rgba(255,255,255,0.15); padding:12px; border-radius:8px; font-weight:600;"></div>
		</div>
		<script>
		jQuery(function($){
			$('#sb-demo-import-fr, #sb-demo-import-ar').on('click', function(){
				var lang = $(this).attr('id') === 'sb-demo-import-fr' ? 'fr' : 'ar';
				var status = $('#sb-demo-import-status');
				status.show().html('<p style="margin:0;">⏳ Importation en cours... Cela peut prendre 30 secondes.</p>');
				$('#sb-demo-import-fr, #sb-demo-import-ar').prop('disabled', true);
				$.post(ajaxurl, {
					action: 'swiftboard_demo_import',
					lang: lang,
					_ajax_nonce: '<?php echo wp_create_nonce('swiftboard_demo_import'); ?>'
				}, function(resp){
					if (resp.success) {
						status.html('<p style="margin:0;">✅ ' + resp.data + '</p><p style="margin:4px 0 0; opacity:0.8;">Redirection...</p>');
						setTimeout(function(){ window.location.reload(); }, 2000);
					} else {
						status.html('<p style="margin:0; color:#fee2e2;">❌ ' + resp.data + '</p>');
						$('#sb-demo-import-fr, #sb-demo-import-ar').prop('disabled', false);
					}
				});
			});
		});
		</script>

		<p class="description">
			Ou uploadez votre propre fichier <strong>Excel (.xlsx)</strong> — lu nativement, plus besoin
			d'exporter en CSV — ou un CSV. Sections du fichier&nbsp;:
			<code>---MEMBRES---</code> (v5.3.5 — vos membres avec leur <strong>grade</strong> : rookie, member, pro, vip, moderator&nbsp;;
			un membre existant est simplement mis à jour, mot de passe jamais touché),
			les sujets, puis <code>---REPLIES---</code> (commentaires).
			<strong>Astuce&nbsp;: un commentaire dont le <code>topic_title</code> est le titre exact d'un sujet
			DÉJÀ existant est ajouté à la suite de ce sujet</strong>, sans recréer quoi que ce soit.
		</p>

		<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:16px;margin:16px 0;max-width:800px;">
			<h3 style="margin-top:0;">📊 Statistiques du forum</h3>
			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:12px;">
				<div><strong><?php echo (int) $stats['forums']; ?></strong> forums</div>
				<div><strong><?php echo (int) $stats['topics']; ?></strong> sujets</div>
				<div><strong><?php echo (int) $stats['replies']; ?></strong> réponses</div>
				<div><strong><?php echo (int) $stats['users']; ?></strong> utilisateurs</div>
				<div><strong><?php echo (int) $stats['votes']; ?></strong> votes</div>
			</div>
		</div>

		<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:1000px;margin:20px 0;">
			<!-- Upload -->
			<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;">
				<h2 style="margin-top:0;">📤 Uploader un fichier</h2>
				<form method="post" action="" enctype="multipart/form-data">
					<?php wp_nonce_field( 'swiftboard_bulk_import' ); ?>
					<p>
						<label for="import_file"><strong><?php esc_html_e( 'Fichier CSV ou XLSX :', 'swiftboard' ); ?></strong></label><br>
						<input type="file" name="import_file" id="import_file" accept=".csv,.xlsx,.txt" required
								style="margin-top:8px;width:100%;padding:8px;">
					</p>
					<p class="description">
						Formats acceptés : <code>.csv</code> (recommandé) ou <code>.xlsx</code>.<br>
						Maximum : 500 lignes par import (sécurité serveur).<br>
						Encodage : UTF-8 recommandé.
					</p>
					<p>
						<button type="submit" class="button button-primary" data-confirm="Lancer l'import ? Vérifiez que votre fichier est bien formaté.">
							🚀 Lancer l'import
						</button>
					</p>
				</form>
			</div>

			<!-- Template -->
			<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;">
				<h2 style="margin-top:0;">📋 Télécharger un template</h2>
				<p><?php esc_html_e( 'Trois modèles prêts à remplir dans Excel :', 'swiftboard' ); ?></p>
				<p>
					<a href="?page=swiftboard-bulk-import&download_template=1" class="button button-secondary">
						💾 Modèle complet
					</a>
					<a href="?page=swiftboard-bulk-import&download_template=1&type=membres" class="button button-secondary">
						👥 Membres + rangs + karma
					</a>
					<a href="?page=swiftboard-bulk-import&download_template=1&type=suite" class="button button-secondary">
						💬 Suite des commentaires (sujets existants)
					</a>
				</p>
				<p class="description">
					Remplissez-le dans Excel, puis uploadez directement le <strong>.xlsx</strong> (ou le CSV) : les deux sont lus nativement.
				</p>
				<h3><?php esc_html_e( 'Format attendu :', 'swiftboard' ); ?></h3>
				<pre style="background:#f6f7f8;padding:10px;border-radius:4px;font-size:11px;overflow-x:auto;">---MEMBRES---
identifiant,email,grade,mot_de_passe,karma
karim.benali,karim.benali@exemple.fr,vip,,1280

---TOPICS---
forum,title,content,author,grade,image_url,votes,date
Annonces & News,Bienvenue !,Bonjour à tous...,Marie,vip,https://...,15,2026-07-10
---REPLIES---
topic_title,content,author,grade,votes,reply_to,date
Bienvenue !,Merci pour ce forum !,Alice,member,3,,2026-07-10
Bienvenue !,+1 super design,Bob,,1,Alice,2026-07-10
TITRE EXACT D'UN SUJET EXISTANT,Ajouté à la suite du sujet déjà commencé,sophie.martin,member,2,,2026-08-01</pre>
				<p class="description">
					<strong>Rangs des membres</strong> : section <code>---MEMBRES---</code> (identifiant + email + grade + <strong>karma</strong>).<br>
					<strong>Karma</strong> : colonne <code>karma</code> = karma de départ crédible (un VIP à 0 karma, ça se voit). Bonus manuel ajustable après coup dans SwiftBoard → Grades, colonne « Karma (bonus) ».<br>
					<strong>Plancher de crédibilité 🛡️</strong> : rang sans karma (ou karma trop bas) → ajusté automatiquement au plancher du rang : membre <strong>7</strong>, pro <strong>538</strong>, modérateur <strong>2149</strong>, VIP <strong>7116</strong> — valeurs volontairement <em>non rondes</em>, légèrement au-dessus des seuils annoncés (5 / 500 / 2000 / 5000) pour un rendu naturel. Un karma fourni au-dessus du plancher est respecté ; pour un membre existant, on <em>complète</em> jusqu'au plancher sans écraser son karma réel.<br>
					<strong>Commentaires sur un sujet déjà commencé</strong> : dans <code>---REPLIES---</code>, mettez le titre exact du sujet existant dans <code>topic_title</code> — la réponse est ajoutée à sa suite (auteur, date, votes et réponses imbriquées pris en charge) — modèle dédié « 💬 Suite des commentaires » ci-dessus.<br>
					Grades acceptés : <code>rookie</code>, <code>member</code>, <code>pro</code>, <code>vip</code>, <code>moderator</code>.
				</p>
			</div>
		</div>

		<?php if ( ! empty( $import_log ) ) : ?>
		<h2>📜 Journal du dernier import</h2>
		<div style="background:#1e293b;color:#e2e8f0;border-radius:8px;padding:16px;font-family:monospace;font-size:12px;line-height:1.6;max-height:400px;overflow-y:auto;max-width:1000px;">
			<?php
			foreach ( $import_log as $entry ) :
				$color = '#e2e8f0';
				if (isset( $entry['error'] ) && $entry['error']) $color         = '#fca5a5';
				elseif (isset( $entry['success'] ) && $entry['success']) $color = '#86efac';
				elseif (isset( $entry['warning'] ) && $entry['warning']) $color = '#fcd34d';
				?>
			<div style="color:<?php echo esc_attr( $color ); ?>;">
				[<?php echo esc_html( $entry['time'] ); ?>] <?php echo esc_html( $entry['msg'] ); ?>
			</div>
			<?php endforeach; ?>
		</div>

		<form method="post" action="" style="margin-top:16px;">
			<?php wp_nonce_field( 'swiftboard_cancel_import' ); ?>
			<button type="submit" name="cancel_import" value="1" class="button button-link-delete"
					data-confirm="⚠️ Cela va SUPPRIMER tous les sujets, réponses et utilisateurs créés lors du dernier import. Continuer ?">
				🗑️ Annuler le dernier import
			</button>
		</form>
		<?php endif; ?>

		<div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:16px;margin-top:24px;max-width:1000px;">
			<h3 style="margin-top:0;color:#92400e;">⚠️ Bonnes pratiques</h3>
			<ul style="margin:0;padding-left:20px;color:#78350f;">
				<li><strong><?php esc_html_e( 'Reformulez', 'swiftboard' ); ?></strong> le contenu scrapé pour éviter les problèmes de droits d'auteur.</li>
				<li><strong><?php esc_html_e( 'Changez les noms', 'swiftboard' ); ?></strong> des auteurs ( utilisez des pseudos fictifs).</li>
				<li><strong><?php esc_html_e( 'Votes réalistes', 'swiftboard' ); ?></strong> : ne dépassez pas 50 votes par sujet (sinon ça paraît suspect).</li>
				<li><strong><?php esc_html_e( 'Dates étalées', 'swiftboard' ); ?></strong> : étalez les dates sur plusieurs jours/semaines pour faire naturel.</li>
				<li><?php esc_html_e( 'Testez d\'abord avec', 'swiftboard' ); ?><strong>5-10 lignes</strong> avant de faire un gros import.</li>
			</ul>
		</div>
	</div>
	<?php
}

// ============================================================================
// 3. STATISTIQUES
// ============================================================================
/**
 * Compte les contenus publies d'un type, meme si ce type n'existe pas.
 *
 * EXI-BBP-02 : sans bbPress, les types 'forum', 'topic' et 'reply' ne sont pas
 * enregistres. wp_count_posts() renvoie alors un stdClass VIDE, et l'acces
 * direct a ->publish emet « Undefined property » — trois Warnings a chaque
 * affichage de l'ecran d'import. Verifie en desactivant le plugin.
 *
 * @param string $type Type de contenu.
 * @return int
 */

