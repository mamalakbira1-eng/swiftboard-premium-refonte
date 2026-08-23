<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Importateur de démos NATIF (sans plugin).
 *
 * Page admin "🎨 Démos" avec cartes visuelles et bouton Import en 1 clic.
 * Parse les fichiers XML WXR (WordPress eXtended RSS) directement en PHP,
 * crée forums/topics/replies/users, applique avatars + grades + RTL.
 *
 * 9 démos : 3 thèmes (Communauté, Gaming, SaaS) × 3 langues (FR, EN, AR).
 *
 * @package SwiftBoard
 * @since 8.6.0
 */

// ============================================================================
// 1. MENU ADMIN
// ============================================================================
add_action('admin_menu', function() {
	add_submenu_page(
		'swiftboard-dashboard',
		__('Démos', 'swiftboard'),
		__('🎨 Démos', 'swiftboard'),
		'manage_options',
		'swiftboard-demos',
		'swiftboard_demos_page'
	);
}, 11);

// ============================================================================
// 2. PAGE ADMIN — Cartes de démos
// ============================================================================
function swiftboard_demos_page() {
	if (!current_user_can('manage_options')) {
		wp_die( esc_html__( 'Accès refusé.', 'swiftboard' ) );
	}

	$demos = swiftboard_get_demo_list();
	$stats = swiftboard_get_import_stats();
	?>
	<div class="wrap">
		<h1>🎨 <?php esc_html_e('Import de démos SwiftBoard', 'swiftboard'); ?></h1>
		<p class="description">
			<?php esc_html_e('Cliquez sur une démo pour l\'installer en 1 clic. Forums, sujets, réponses, avatars ninja et grades militaires sont importés automatiquement.', 'swiftboard'); ?>
		</p>

		<?php if ($stats['topics'] > 0 || $stats['forums'] > 0): ?>
		<div class="notice notice-warning inline">
			<p>⚠️ <?php esc_html_e('Votre forum contient déjà du contenu. L\'import ajoutera du contenu à l\'existant. Pour repartir de zéro, supprimez d\'abord les sujets existants.', 'swiftboard'); ?></p>
		</div>
		<?php endif; ?>

		<div id="sb-demo-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;margin:20px 0;max-width:1200px;">
			<?php foreach ($demos as $id => $demo): ?>
			<div class="sb-demo-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;transition:box-shadow 0.2s;">
				<div style="height:160px;background:<?php echo esc_attr($demo['color']); ?>;display:flex;align-items:center;justify-content:center;font-size:48px;">
					<?php echo esc_html($demo['icon']); ?>
				</div>
				<div style="padding:16px;">
					<h3 style="margin:0 0 4px;font-size:16px;"><?php echo esc_html($demo['name']); ?></h3>
					<p style="color:#6b7280;font-size:13px;margin:0 0 12px;"><?php echo esc_html($demo['desc']); ?></p>
					<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
						<span style="background:#f0f2f5;padding:2px 10px;border-radius:9999px;font-size:11px;font-weight:600;"><?php echo esc_html($demo['forums']); ?> forums</span>
						<span style="background:#f0f2f5;padding:2px 10px;border-radius:9999px;font-size:11px;font-weight:600;"><?php echo esc_html($demo['topics']); ?> sujets</span>
						<span style="background:#f0f2f5;padding:2px 10px;border-radius:9999px;font-size:11px;font-weight:600;"><?php echo esc_html($demo['replies']); ?> réponses</span>
						<span style="background:#f0f2f5;padding:2px 10px;border-radius:9999px;font-size:11px;font-weight:600;"><?php echo esc_html($demo['users']); ?> membres</span>
					</div>
					<button type="button" class="button button-primary sb-demo-import-btn"
						data-demo-id="<?php echo esc_attr($id); ?>"
						data-demo-name="<?php echo esc_attr($demo['name']); ?>"
						data-is-ar="<?php echo esc_attr($demo['is_ar'] ? '1' : '0'); ?>"
						style="width:100%;text-align:center;justify-content:center;">
						⚡ <?php esc_html_e('Importer cette démo', 'swiftboard'); ?>
					</button>
				</div>
			</div>
			<?php endforeach; ?>
		</div>

		<div id="sb-demo-import-progress" style="display:none;max-width:600px;margin:20px 0;">
			<div style="background:#f0f2f5;border-radius:8px;padding:20px;">
				<h3 id="sb-demo-progress-title" style="margin:0 0 8px;"></h3>
				<div id="sb-demo-progress-log" style="font-family:monospace;font-size:12px;color:#4b5563;max-height:200px;overflow-y:auto;margin-top:8px;"></div>
				<div id="sb-demo-progress-done" style="display:none;margin-top:12px;">
					<div class="notice notice-success inline"><p id="sb-demo-progress-result"></p></div>
					<a href="<?php echo esc_url(home_url('/')); ?>" target="_blank" class="button button-primary" style="margin-top:8px;">
						<?php esc_html_e('Voir le site →', 'swiftboard'); ?>
					</a>
				</div>
			</div>
		</div>
	</div>

	<style>
	.sb-demo-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
	.sb-demo-import-btn:disabled { opacity:0.5; cursor:not-allowed; }
	</style>

	<script>
	jQuery(function($){
		$('.sb-demo-import-btn').on('click', function(){
			var btn = $(this);
			var demoId = btn.data('demo-id');
			var demoName = btn.data('demo-name');
			var isAr = btn.data('is-ar');
			var progress = $('#sb-demo-import-progress');
			var log = $('#sb-demo-progress-log');
			var done = $('#sb-demo-progress-done');

			if (!confirm('Importer la démo "' + demoName + '" ? Cela ajoutera du contenu à votre forum.')) return;

			$('.sb-demo-import-btn').prop('disabled', true);
			progress.show();
			done.hide();
			log.html('<p>⏳ Importation en cours... Veuillez patienter (~30 secondes).</p>');
			$('#sb-demo-progress-title').text('📥 ' + demoName);

			$.post(ajaxurl, {
				action: 'swiftboard_native_demo_import',
				demo_id: demoId,
				is_ar: isAr,
				_ajax_nonce: '<?php echo esc_attr( wp_create_nonce( 'swiftboard_native_demo' ) ); ?>'
			}, function(resp){
				if (resp.success) {
					log.html('<p>✅ Import terminé !</p>');
					$('#sb-demo-progress-result').html(resp.data);
					done.show();
					$('.sb-demo-import-btn').prop('disabled', false);
				} else {
					log.html('<p style="color:#dc2626;">❌ ' + resp.data + '</p>');
					$('.sb-demo-import-btn').prop('disabled', false);
				}
			}).fail(function(){
				log.html('<p style="color:#dc2626;">❌ Erreur de connexion. Réessayez.</p>');
				$('.sb-demo-import-btn').prop('disabled', false);
			});
		});
	});
	</script>
	<?php

	/**
	 * Point d'accroche : permet d'ajouter des encarts sous les cartes de demo
	 * sans modifier cette fonction. Utilise par inc/demo-blog.php.
	 */
	do_action( 'swiftboard_apres_demos' );
}

// ============================================================================
// 3. LISTE DES DÉMOS
// ============================================================================
function swiftboard_get_demo_list() {
	$dir = SWIFTBOARD_DIR . '/demo-data';
	return array(
		'communaute-fr' => array(
			'name'    => '🇫🇷 Communauté (Français)',
			'desc'    => 'Forum communautaire en français — 20 forums, 50 sujets, 80 réponses, 80 membres',
			'icon'    => '🏥',
			'color'   => '#006cbd',
			'xml'     => $dir . '/demo-communaute/content-fr.xml',
			'csv'     => $dir . '/demo-communaute/membres-fr.csv',
			'is_ar'   => false,
			'locale'  => 'fr_FR',
			'forums'  => 20, 'topics' => 50, 'replies' => 80, 'users' => 80,
		),
		'communaute-en' => array(
			'name'    => '🇬🇧 Community (English)',
			'desc'    => 'Community forum in English — 20 forums, 50 topics, 80 replies',
			'icon'    => '🏥',
			'color'   => '#006cbd',
			'xml'     => $dir . '/demo-communaute/content-en.xml',
			'csv'     => $dir . '/demo-communaute/membres-en.csv',
			'is_ar'   => false,
			'locale'  => '',
			'forums'  => 20, 'topics' => 50, 'replies' => 80, 'users' => 20,
		),
		'communaute-ar' => array(
			'name'    => '🇲🇦 المجتمع (العربية)',
			'desc'    => 'منتدى مجتمعي بالعربية — 20 منتدى، 50 موضوع، 80 رد — واجهة عربية كاملة RTL',
			'icon'    => '🏥',
			'color'   => '#006cbd',
			'xml'     => $dir . '/demo-communaute/content-ar.xml',
			'csv'     => $dir . '/demo-communaute/membres-ar.csv',
			'is_ar'   => true,
			'locale'  => 'ar',
			'forums'  => 20, 'topics' => 50, 'replies' => 80, 'users' => 20,
		),
	);
}

// ============================================================================
// 4. AJAX — IMPORT NATIF (sans plugin)
// ============================================================================
add_action('wp_ajax_swiftboard_native_demo_import', function() {
	global $wpdb;
	check_ajax_referer('swiftboard_native_demo', '_ajax_nonce');
	if (!current_user_can('manage_options')) {
		wp_send_json_error('Accès refusé');
	}

	@set_time_limit(300);
	@ini_set('memory_limit', '256M');

	$demo_id = sanitize_text_field($_POST['demo_id'] ?? '');
	$is_ar = ($_POST['is_ar'] ?? '0') === '1';

	$demos = swiftboard_get_demo_list();
	if (!isset($demos[$demo_id])) {
		wp_send_json_error('Démo introuvable: ' . $demo_id);
	}

	$demo = $demos[$demo_id];
	$xml_path = $demo['xml'];
	$csv_path = $demo['csv'];

	if (!file_exists($xml_path) || !is_readable($xml_path)) {
		wp_send_json_error('Fichier XML introuvable ou illisible.');
	}
	if (!file_exists($csv_path) || !is_readable($csv_path)) {
		wp_send_json_error('Fichier CSV des membres introuvable ou illisible.');
	}

	// Désactiver les emails
	add_filter('pre_wp_mail', '__return_false');

	// 1. Parser et importer le XML
	$result = swiftboard_native_import_xml($xml_path);

	// Vérifier que l'import XML a réussi (pas un XML corrompu).
	if ( $result === false ) {
		wp_send_json_error( 'Erreur : le fichier XML est corrompu ou introuvable.' );
	}

		// 2. Appliquer membres (avatars + grades + karma)
		swiftboard_native_apply_membres($csv_path);
	// Fix user registration dates to match earliest content
	swiftboard_native_fix_registration_dates();
	swiftboard_native_fix_reply_counts();

	// 3. Si démo arabe → RTL + langue arabe
	if ($is_ar) {
		update_option('WPLANG', 'ar');
		update_option('swiftboard_force_rtl', '1');
	} else {
		delete_option('swiftboard_force_rtl');
		// Forcer la locale de la démo (FR = boutons en français, EN = défaut WP)
		$locale = $demo['locale'] ?? '';
		if ($locale === 'fr_FR') {
			update_option('WPLANG', 'fr_FR');
		} else {
			update_option('WPLANG', '');
		}
	}

	// 4. Créer la table votes via la fonction canonique (schéma complet avec
	// voter_hash, post_type, etc.). admin_init ne se déclenche pas en AJAX,
	// donc on appelle swiftboard_create_votes_table() directement.
	// L'ancien CREATE TABLE IF NOT EXISTS créait un schéma incomplet (sans
	// voter_hash) qui cassait les votes anonymes après l'import.
	if ( function_exists( 'swiftboard_create_votes_table' ) ) {
		swiftboard_create_votes_table();
	} else {
		// Fallback : créer manuellement si le module votes n'est pas chargé.
		$votes_table = swiftboard_table( 'votes' );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $votes_table ) ) !== $votes_table ) {
			$charset_collate = $wpdb->get_charset_collate();
				// Les identifiants et le charset viennent du noyau WordPress ; aucune entrée utilisateur.
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "CREATE TABLE IF NOT EXISTS $votes_table (
					id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				post_id BIGINT(20) UNSIGNED NOT NULL,
				post_type VARCHAR(20) NOT NULL DEFAULT 'topic',
				post_author BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
				vote_type VARCHAR(10) NOT NULL DEFAULT 'up',
				user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
				voter_ip VARCHAR(100) NOT NULL DEFAULT '',
				voter_hash VARCHAR(64) NOT NULL DEFAULT '',
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY uniq_voter (post_id, user_id, voter_hash),
				KEY idx_post (post_id),
				KEY idx_author (post_author),
				KEY idx_type (vote_type),
				KEY idx_user (user_id)
				) {$charset_collate};" );
				// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	// 5. Purger les caches
	// delete_transient() vide le cache objet WP (Redis/Memcached) + la DB.
	delete_transient( 'swiftboard_hot_topics' );
	delete_transient( 'swiftboard_hot_topics_all' );
	delete_transient( 'swiftboard_hot_topics_24h' );
	delete_transient( 'swiftboard_hot_topics_7d' );
	delete_transient( 'swiftboard_hot_topics_30d' );

	// Purger aussi les clés dynamiques (schema topic, reputation par user).
	$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sb_hot_%'");
	$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_swiftboard_%'");
	if (function_exists('swiftboard_purge_cache')) {
		swiftboard_purge_cache();
	}
	flush_rewrite_rules();

	remove_filter('pre_wp_mail', '__return_false');

	// Compter les résultats
	$forums = wp_count_posts('forum')->publish;
	$topics = wp_count_posts('topic')->publish;
	$replies = wp_count_posts('reply')->publish;
	$users = count(get_users(array('fields' => 'ID'))) - 1;

	$lang_msg = $is_ar ? ' — 🇲🇦 Interface en arabe (RTL)' : '';

	wp_send_json_success(sprintf(
		'✅ Démo "%s" importée : %d forums, %d sujets, %d réponses, %d membres%s',
		$demo['name'], $forums, $topics, $replies, $users, $lang_msg
	));
});

// ============================================================================
// 5. PARSER XML WXR (WordPress eXtended RSS) — natif, sans dépendance
// ============================================================================
function swiftboard_native_import_xml($xml_path) {
	global $wpdb;

		$content = file_get_contents($xml_path);
		if (!$content) return false;

		// Le fichier est local et ne doit jamais résoudre d’entités externes.
		$xml = @simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
	if (!$xml) return false;

	$namespaces = $xml->getDocNamespaces();
	$wp_ns = 'http://wordpress.org/export/1.2/';
	$dc_ns = 'http://purl.org/dc/elements/1.1/';
	$content_ns = 'http://purl.org/rss/1.0/modules/content/';

	// Map des anciens IDs vers nouveaux IDs
	$id_map = array();
	$author_map = array();

	// 1. Créer les auteurs
	$xml->registerXPathNamespace('w', $wp_ns);
	foreach ($xml->xpath('//w:author') as $author) {
		$auth_children = $author->children($wp_ns);
		$login = (string) $auth_children->author_login;
		$email = (string) $auth_children->author_email;
		$display_name = (string) $auth_children->author_display_name;

		// Vérifier si existe
		$existing = get_user_by('login', $login);
		if ($existing) {
			$author_map[$login] = $existing->ID;
			// Mettre à jour le display_name
			if ($display_name) {
				wp_update_user(array('ID' => $existing->ID, 'display_name' => $display_name, 'nickname' => $display_name));
			}
			continue;
		}

		$user_id = wp_create_user($login, wp_generate_password(24, true), $email);
		if (!is_wp_error($user_id)) {
			wp_update_user(array('ID' => $user_id, 'display_name' => $display_name, 'nickname' => $display_name, 'role' => 'subscriber'));
			if (function_exists('bbp_set_user_role')) {
				bbp_set_user_role($user_id, 'bbp_participant');
			}
			$author_map[$login] = $user_id;
		}
	}

	// 2. Collecter tous les items
	$items = array();
	foreach ($xml->channel->item as $item) {
		$wp = $item->children($wp_ns);
		$dc = $item->children($dc_ns);
		$cnt = $item->children($content_ns);

		$old_id = (int) $wp->post_id;
		$post_type = (string) $wp->post_type;
		$post_parent = (int) $wp->post_parent;
		$title = (string) $item->title;
		$content_encoded = (string) $cnt->encoded;
		$creator = (string) $dc->creator;
		$date = (string) $wp->post_date;
		$status = (string) $wp->post_status ?: 'publish';
		$menu_order = (int) $wp->menu_order;

		// Metas — postmeta is in wp namespace
		$metas = array();
		foreach ($wp->postmeta as $meta) {
			$meta_children = $meta->children($wp_ns);
			$metas[(string) $meta_children->meta_key] = (string) $meta_children->meta_value;
		}

		$items[] = array(
			'old_id' => $old_id,
			'type' => $post_type,
			'parent' => $post_parent,
			'title' => $title,
			'content' => $content_encoded,
			'author' => $creator,
			'date' => $date,
			'status' => $status,
			'menu_order' => $menu_order,
			'metas' => $metas,
		);
	}

	// 3. Trier : forums d'abord, puis topics, puis replies
	// Les parents doivent exister avant les enfants
	$type_order = array('forum' => 1, 'topic' => 2, 'reply' => 3, 'post' => 4, 'page' => 5);
	usort($items, function($a, $b) use ($type_order) {
		$ta = $type_order[$a['type']] ?? 9;
		$tb = $type_order[$b['type']] ?? 9;
		if ($ta !== $tb) return $ta - $tb;
		return $a['old_id'] - $b['old_id'];
	});

	// 4. Créer les posts
	$created = 0;
	foreach ($items as $item) {
		$new_parent = isset($id_map[$item['parent']]) ? $id_map[$item['parent']] : 0;
		$author_id = isset($author_map[$item['author']]) ? $author_map[$item['author']] : 1;

		// Vérifier d’abord l’identifiant XML source : cette clé est stable et
		// empêche les doublons de forums, sujets et réponses lors d’un second import.
		$existing_by_source = get_posts(array(
			'post_type'      => $item['type'],
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_key'       => '_swiftboard_old_id',
			'meta_value'     => (string) $item['old_id'],
			'fields'         => 'ids',
		));
		if (!empty($existing_by_source)) {
			$id_map[$item['old_id']] = (int) $existing_by_source[0];
			continue;
		}

		// Vérifier idempotence par titre + type
		// Pour les replies : pas de title unique, on vérifie par post_parent + contenu.
		if ($item['type'] === 'reply' && $item['parent']) {
			$new_parent = isset($id_map[$item['parent']]) ? $id_map[$item['parent']] : 0;
			if ($new_parent) {
				$existing_reply = get_posts(array(
					'post_type'   => 'reply',
					'post_status' => 'any',
					'post_parent' => $new_parent,
					'meta_key'    => '_swiftboard_old_id',
					'meta_value'  => $item['old_id'],
					'posts_per_page' => 1,
					'fields'      => 'ids',
				));
				if (!empty($existing_reply)) {
					$id_map[$item['old_id']] = $existing_reply[0];
					continue;
				}
			}
		} elseif ($item['type'] !== 'reply' && $item['title']) {
			$existing_post = function_exists('swiftboard_trouver_par_titre')
				? swiftboard_trouver_par_titre($item['title'], $item['type'])
				: null;
				if ($existing_post) {
					$id_map[$item['old_id']] = $existing_post->ID;
					update_post_meta($existing_post->ID, '_swiftboard_old_id', (int) $item['old_id']);
					continue;
				}
		}

		$post_data = array(
			'post_title' => $item['title'],
			'post_content' => $item['content'],
			'post_status' => $item['status'],
			'post_type' => $item['type'],
			'post_author' => $author_id,
			'post_parent' => $new_parent,
			'menu_order' => $item['menu_order'],
		);

		if ($item['date'] && strtotime($item['date'])) {
			$post_data['post_date'] = date('Y-m-d H:i:s', strtotime($item['date']));
			$post_data['post_date_gmt'] = gmdate('Y-m-d H:i:s', strtotime($item['date']));
			$post_data['edit_date'] = true;
		}

		// Pour les forums, utiliser bbp_insert_forum
		if ($item['type'] === 'forum' && function_exists('bbp_insert_forum')) {
			$forum_id = bbp_insert_forum($post_data);
			if ($forum_id) {
				$id_map[$item['old_id']] = $forum_id;
				// Mettre à jour le post_parent (bbp_insert_forum peut l'ignorer)
				if ($new_parent) {
					$wpdb->update($wpdb->posts, array('post_parent' => $new_parent), array('ID' => $forum_id));
				}
				$created++;
			}
		} elseif ($item['type'] === 'topic' && function_exists('bbp_insert_topic')) {
			$topic_id = bbp_insert_topic($post_data, array('forum_id' => $new_parent));
			if ($topic_id) {
				$id_map[$item['old_id']] = $topic_id;
				$wpdb->update($wpdb->posts, array('post_parent' => $new_parent), array('ID' => $topic_id));
				// Forcer publish
				$wpdb->update($wpdb->posts, array('post_status' => 'publish'), array('ID' => $topic_id));
				$created++;
			}
		} elseif ($item['type'] === 'reply' && function_exists('bbp_insert_reply')) {
			$reply_id = bbp_insert_reply($post_data, array(
				'topic_id' => $new_parent,
				'forum_id' => get_post_meta($new_parent, '_bbp_forum_id', true) ?: wp_get_post_parent_id($new_parent),
			));
			if ($reply_id) {
				$id_map[$item['old_id']] = $reply_id;
				$wpdb->update($wpdb->posts, array('post_status' => 'publish'), array('ID' => $reply_id));
				$created++;
			}
		} else {
			// Posts, pages, etc.
			$post_id = wp_insert_post($post_data);
			if ($post_id && !is_wp_error($post_id)) {
				$id_map[$item['old_id']] = $post_id;
				$created++;
			}
		}

		// Appliquer les metas
		if (isset($id_map[$item['old_id']])) {
			$pid = $id_map[$item['old_id']];
				foreach ($item['metas'] as $key => $value) {
					// Ignorer les metas internes WordPress
					if (in_array($key, array('_edit_lock', '_edit_last'), true)) continue;
					update_post_meta($pid, $key, $value);
				}
				// Identifiant source stable : empêche les doublons de réponses lors d’un second import.
				update_post_meta($pid, '_swiftboard_old_id', (int) $item['old_id']);

			// Forcer les metas bbPress essentielles
			if ($item['type'] === 'topic') {
				$forum_id = get_post_meta($pid, '_bbp_forum_id', true);
				if (!$forum_id) {
					update_post_meta($pid, '_bbp_forum_id', $new_parent);
				}
				if (function_exists('bbp_update_topic_reply_count')) {
					bbp_update_topic_reply_count($pid);
				}
			}
			if ($item['type'] === 'reply') {
				$topic_id = $new_parent;
				$forum_id = get_post_meta($topic_id, '_bbp_forum_id', true);
				update_post_meta($pid, '_bbp_topic_id', $topic_id);
				update_post_meta($pid, '_bbp_forum_id', $forum_id);
				update_post_meta($pid, '_bbp_reply_to', 0);
			}
		}
	}

	return array('created' => $created, 'id_map' => $id_map);
}

// ============================================================================
// 6. APPLIQUER MEMBRES (avatars + grades + karma) depuis CSV
function swiftboard_native_apply_membres($csv_path) {
	$content = file_get_contents($csv_path);
	if (!$content) return;
	if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
		$content = substr($content, 3);
	}

	$lines = array_map('str_getcsv', explode("\n", $content));
	$headers = null;

	foreach ($lines as $row) {
		// Skip section markers like ---MEMBRES---
		if (count($row) === 1 && strpos(trim($row[0]), '---') === 0) {
			continue;
		}
		// Skip empty lines
		if (count($row) === 1 && trim($row[0]) === '') {
			continue;
		}
		if (!$headers) {
			$headers = array_map('strtolower', array_map('trim', $row));
			continue;
		}
		if (count($row) < 2) continue;
		// Pad or trim to match headers
		$row = array_pad($row, count($headers), '');
		$row = array_slice($row, 0, count($headers));
		$data = array_combine($headers, $row);

		$login = trim($data['identifiant'] ?? '');
		$grade = trim($data['grade'] ?? 'rookie');
		$avatar = trim($data['avatar'] ?? '');
		$karma = (int) ($data['karma'] ?? 0);
		$display_name = trim($data['nom_arabe'] ?? $data['display_name'] ?? '');

		if (!$login) continue;

		$user = get_user_by('login', $login);
		if (!$user) continue;

		// Grade
		$grades_valides = array('rookie', 'member', 'pro', 'moderator', 'vip');
		if (!in_array($grade, $grades_valides, true)) $grade = 'rookie';
		update_user_meta($user->ID, 'swiftboard_grade', $grade);

		// Avatar : depuis CSV ou auto-assigne (1-15 base sur user_id)
		if (is_numeric($avatar) && (int)$avatar >= 1 && (int)$avatar <= 15) {
			$num = (int) $avatar;
			update_user_meta($user->ID, 'swiftboard_avatar_id', $num);
			update_user_meta($user->ID, 'swiftboard_avatar', $num);
		} else {
			$auto_avatar = ((int) $user->ID % 15) + 1;
			update_user_meta($user->ID, 'swiftboard_avatar_id', $auto_avatar);
			update_user_meta($user->ID, 'swiftboard_avatar', $auto_avatar);
		}

		// Karma
		if ($karma > 0) {
			update_user_meta($user->ID, 'swiftboard_karma_bonus', $karma);
		}

		// Display name arabe
		if ($display_name) {
			wp_update_user(array('ID' => $user->ID, 'display_name' => $display_name, 'nickname' => $display_name));
		}

		// Invalider cache grade
		if (function_exists('swiftboard_invalidate_grade_cache')) {
			swiftboard_invalidate_grade_cache($user->ID);
		}
		if (function_exists('swiftboard_invalidate_reputation_cache')) {
			swiftboard_invalidate_reputation_cache($user->ID);
		}
	}
}

/**
 * Fix user registration dates to match earliest content date.
 * Called after XML import to set user_registered = date of first topic/reply.
 */
function swiftboard_native_fix_registration_dates() {
	global $wpdb;

	// Get all users except admin
	$users = get_users(array('exclude' => array(1), 'fields' => 'ID'));
	if (empty($users)) return;

	foreach ($users as $uid) {
		$earliest = $wpdb->get_var($wpdb->prepare(
			"SELECT MIN(post_date) FROM {$wpdb->posts}
			 WHERE post_author = %d
			 AND post_type IN ('topic', 'reply', 'post')
			 AND post_status = 'publish'",
			$uid
		));

		if ($earliest) {
			$wpdb->update(
				$wpdb->users,
				array('user_registered' => $earliest),
				array('ID' => $uid)
			);
		}
	}
}

/**
 * Recalculate all topic reply counts after import.
 * Fixes mismatches caused by multiple bbp_update_topic_reply_count calls.
 */
function swiftboard_native_fix_reply_counts() {
    $topics = get_posts(array('post_type' => 'topic', 'numberposts' => -1, 'post_status' => 'any'));
    foreach ($topics as $t) {
        if (function_exists('bbp_update_topic_reply_count')) {
            bbp_update_topic_reply_count($t->ID);
        }
    }
}
