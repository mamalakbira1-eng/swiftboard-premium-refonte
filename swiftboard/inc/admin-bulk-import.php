<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared — SQL uses internal $wpdb variables (safe)
/**
 * SwiftBoard - Import en masse via Excel/CSV
 *
 * Permet à l'admin d'uploader un fichier Excel (.xlsx) ou CSV pour remplir
 * rapidement le forum avec du contenu scrapé (en changeant les noms et
 * reformulant les phrases).
 *
 * Format attendu (fichier CSV à 2 sections séparées par une ligne "---REPLIES---"):
 *
 * === Section TOPICS (lignes 1 à N avant ---REPLIES---) ===
 * Colonnes : forum, title, content, author, image_url, votes, date
 *
 * === Section REPLIES (lignes après ---REPLIES---) ===
 * Colonnes : topic_title, content, author, votes, reply_to, date
 *
 * Le module :
 *  - Crée automatiquement les forums manquants
 *  - Crée automatiquement les users fictifs (avec grade Rookie par défaut)
 *  - Crée les topics via bbp_insert_topic (déclenche les hooks)
 *  - Crée les replies via bbp_insert_reply
 *  - Simule les votes via swiftboard_cast_vote() avec IPs anonymisées différentes
 *  - Log détaillé de chaque action
 *  - Bouton "Annuler l'import" (supprime tout ce qui a été créé)
 *
 * Sécurité : capacité manage_options + nonce + maximum 500 lignes par import
 *
 * @package SwiftBoard
 * @since 3.2.0
 */
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared — SQL queries use internal $wpdb->prefix variables (safe)

// ============================================================================
// 1. MENU ADMIN
// ============================================================================
add_action(
	'admin_menu',
	function () {
		add_submenu_page(
			'swiftboard-dashboard',
			__( 'Import en masse', 'swiftboard' ),
			__( '📥 Import en masse', 'swiftboard' ),
			'manage_options',
			'swiftboard-bulk-import',
			'swiftboard_bulk_import_page'
		);
	}
);

// Intercepter le download template le plus tôt possible
// v5.3.6 : ?type=complet|membres|suite (3 modeles differents).
add_action(
	'admin_init',
	function () {
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'swiftboard-bulk-import' && isset( $_GET['download_template'] ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( 'Forbidden' );
			}
			swiftboard_download_template();
			exit;
		}
	}
);

// ============================================================================
// 2. PAGE ADMIN
/**
 * swiftboard_compter_publies().
 *
 * @param string $type Type de contenu.
 * @return int
 */
function swiftboard_compter_publies( $type ) {
	$comptes = wp_count_posts( $type );

	return isset( $comptes->publish ) ? (int) $comptes->publish : 0;
}

/**
 * @return array<string, mixed>
 */
function swiftboard_get_import_stats() {
	global $wpdb;
	return array(
		'forums'  => swiftboard_compter_publies( 'forum' ),
		'topics'  => swiftboard_compter_publies( 'topic' ),
		'replies' => swiftboard_compter_publies( 'reply' ),
		'users'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" ),
		'votes'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}swiftboard_votes" ),
	);
}



/**
 * Crée les sujets d'une section.
 *
 * @param array<int, array<string, mixed>> $rows     Lignes de la section « topics ».
 * @param array<string, mixed>             $imported Suivi des contenus créés, par référence.
 * @param array<int, array<string, mixed>> $log      Journal, par référence.
 * @return void
 */
function swiftboard_import_creer_topics( array $rows, array &$imported, array &$log ) {
	global $wpdb;

	foreach ( $rows as $i => $row ) {
		$forum_name  = trim( $row['forum'] ?? '' );
		$title       = trim( $row['title'] ?? '' );
		$content_t   = trim( $row['content'] ?? '' );
		$author_name = trim( $row['author'] ?? 'Anonyme' );
		$image_url   = trim( $row['image_url'] ?? '' );
		$votes       = (int) ( $row['votes'] ?? 0 );
		$vues        = trim( $row['vues'] ?? '' );
		$date        = trim( $row['date'] ?? '' );
		$grade       = trim( $row['grade'] ?? '' );

		if ( ! $title || ! $forum_name ) {
			$log[] = array(
				'time'    => current_time( 'mysql' ),
				'msg'     => '⚠️ Ligne ' . ( $i + 2 ) . ' ignorée (titre ou forum manquant)',
				'warning' => true,
			);
			continue;
		}

		// v5.3.5-bis — IDEMPOTENCE SUJETS : ré-uploader le même fichier ne
		// doit pas créer de doublon. Un sujet au titre exact identique (à
		// l'entité HTML près) existe déjà → on s'y rattache et on continue :
		// les réponses de la section ---REPLIES--- iront s'y ajouter (elles
		// aussi protégées par leur propre anti-doublon).
		$deja = swiftboard_trouver_par_titre( $title, 'topic' );
		if ( $deja ) {
			$imported['topics'][ $title ] = $deja->ID;
			// AJOURT CRITIQUE : signaler comme PRE-EXISTANT pour que le bouton
			// « Annuler l'import » ne supprime PAS un sujet qui n'a pas ete
			// cree par cet import.
			$imported['topics_preexistants'][] = $deja->ID;
			$log[]                             = array(
				'time'    => current_time( 'mysql' ),
				'msg'     => '♻️ Sujet déjà existant (#' . $deja->ID . ') : "' . substr( $title, 0, 50 ) . '" — non dupliqué',
				'success' => true,
			);
			continue;
		}

		$forum_id  = swiftboard_get_or_create_forum( $forum_name, $log, $imported );
		$author_id = swiftboard_get_or_create_user( $author_name, $log, $imported, $grade );

		$topic_data = array(
			'post_title'   => $title,
			'post_status'  => 'publish',
			'post_content' => $content_t,
			'post_author'  => $author_id,
			'post_parent'  => $forum_id,
		);
		if ( $date && strtotime( $date ) ) {
			// Une date future ferait passer WordPress en statut 'future' :
			// la page deviendrait invisible et Google la traiterait comme
			// absente. edit_date contourne ce controle.
			$topic_data['post_date']     = date( 'Y-m-d H:i:s', strtotime( $date ) );
			$topic_data['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', strtotime( $date ) );
			$topic_data['edit_date']     = true;
		}

		$topic_id = bbp_insert_topic( $topic_data, array( 'forum_id' => $forum_id ) );
		if ( ! $topic_id || is_wp_error( $topic_id ) ) {
			$log[] = array(
				'time'  => current_time( 'mysql' ),
				'msg'   => '❌ Échec création topic "' . substr( $title, 0, 40 ) . '..."',
				'error' => true,
			);
			continue;
		}

		// wp_update_post() conserverait 'future' pour une date a venir.
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $topic_id ) );
		clean_post_cache( $topic_id );

		if ( $image_url ) {
			// Les CSV de démo peuvent contenir une URL absolue issue d’un autre
			// staging (par exemple http://127.0.0.1:8088). Si le chemin cible un
			// asset du thème, on le réduit à un chemin relatif afin que l’origine
			// HTTPS courante soit toujours utilisée au rendu.
			$sb_image_path = wp_parse_url( $image_url, PHP_URL_PATH );
			if ( is_string( $sb_image_path ) && preg_match( '#/assets/img/(.+)$#', $sb_image_path, $sb_image_match ) ) {
				$image_url = 'assets/img/' . ltrim( $sb_image_match[1], '/' );
			}

			// URL externe (http/https)
			if ( filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
				update_post_meta( $topic_id, '_swiftboard_has_image', 1 );
				update_post_meta( $topic_id, '_swiftboard_image_url', esc_url_raw( $image_url ) );
			} else {
				// Chemin relatif → construire l'URL depuis le thème. Les images
				// de sujets de la démo vivent dans assets/img/sujets/ ; les CSV
				// historiques ne portent que le nom du fichier.
				if ( strpos( $image_url, 'assets/img/' ) === 0 ) {
					$full_url = SWIFTBOARD_URI . '/' . $image_url;
				} elseif ( strpos( $image_url, '/' ) === false ) {
					$full_url = SWIFTBOARD_URI . '/assets/img/sujets/' . ltrim( $image_url, '/' );
									} else {
						$full_url = SWIFTBOARD_URI . '/assets/img/' . ltrim( $image_url, '/' );
					}
					// Respecter le schéma réellement servi par l’environnement :
					// HTTP en QA locale, HTTPS dès que le staging possède son certificat.
					$full_url = set_url_scheme( $full_url );
					update_post_meta( $topic_id, '_swiftboard_has_image', 1 );
					update_post_meta( $topic_id, '_swiftboard_image_url', esc_url_raw( $full_url ) );

			}
		}
		if ( $votes > 0 ) {
			swiftboard_simulate_votes( $topic_id, $votes, $log, $imported );
		}

		$imported['topics'][ $title ] = $topic_id;

		// Store vues for post-processing (after replies are created)
		if ( $vues !== '' && is_numeric( $vues ) ) {
			$imported['topics_vues'][ $topic_id ] = (int) $vues;
		} else {
			$imported['topics_vues'][ $topic_id ] = 0; // 0 = auto-calculate later
		}

		$log[] = array(
			'time'    => current_time( 'mysql' ),
			'msg'     => '✅ Topic #' . $topic_id . ' créé : "' . substr( $title, 0, 50 ) . '" par ' . $author_name . ' (' . $votes . ' votes, ' . ( $vues !== '' ? $vues . ' vues' : 'vues=auto' ) . ')',
			'success' => true,
		);
	}
}

/**
 * Crée les réponses, en résolvant le threading.
 *
 * Deux passes : d'abord les réponses de premier niveau, puis celles qui
 * désignent un parent par nom d'auteur — ce parent doit exister avant.
 *
 * @param array<int, array<string, mixed>> $rows     Lignes de la section « replies ».
 * @param array<string, mixed>             $imported Suivi des contenus créés, par référence.
 * @param array<int, array<string, mixed>> $log      Journal, par référence.
 * @return void
 */
function swiftboard_import_creer_replies( array $rows, array &$imported, array &$log ) {
	$pending_replies = array();

	foreach ( $rows as $i => $row ) {
		$topic_title = trim( $row['topic_title'] ?? '' );
		$content_r   = trim( $row['content'] ?? '' );
		$reply_to    = trim( $row['reply_to'] ?? '' );

		if ( ! $topic_title || ! $content_r ) {
			$log[] = array(
				'time'    => current_time( 'mysql' ),
				'msg'     => '⚠️ Reply ligne ' . ( $i + 2 ) . ' ignorée (topic_title ou content manquant)',
				'warning' => true,
			);
			continue;
		}
		if ( $reply_to ) {
			$pending_replies[] = $row;
			continue;
		}
		swiftboard_create_reply_from_row( $row, $imported, $log );
	}

	// Le parent peut lui-meme etre une reponse differee : on repasse tant
	// qu'on progresse, avec une borne pour eviter une boucle infinie sur des
	// references circulaires.
	$max_passes = 5;
	while ( ! empty( $pending_replies ) && $max_passes > 0 ) {
		$still_pending = array();
		foreach ( $pending_replies as $row ) {
			$reply_to     = trim( $row['reply_to'] ?? '' );
			$found_parent = false;

			if ( isset( $imported['replies_by_topic_author'][ $row['topic_title'] ][ $reply_to ] ) ) {
				$author_replies = $imported['replies_by_topic_author'][ $row['topic_title'] ][ $reply_to ];
				if ( is_array( $author_replies ) && ! empty( $author_replies ) ) {
					// La derniere reponse de cet auteur est la plus proche
					// chronologiquement : c'est le parent le plus plausible.
					$row['_reply_to_id'] = end( $author_replies );
					$found_parent        = true;
				}
			}
			$found_parent
				? swiftboard_create_reply_from_row( $row, $imported, $log )
				: $still_pending[] = $row;
		}
		$pending_replies = $still_pending;
		--$max_passes;
	}

	// Parent introuvable apres 5 passes : on rattache au premier niveau
	// plutot que de perdre la contribution.
	foreach ( $pending_replies as $row ) {
		$log[] = array(
			'time'    => current_time( 'mysql' ),
			'msg'     => '⚠️ Reply de "' . ( $row['author'] ?? '?' ) . '" : parent "' . ( $row['reply_to'] ?? '?' ) . '" non trouvé, créé au niveau 1',
			'warning' => true,
		);
		unset( $row['reply_to'] );
		swiftboard_create_reply_from_row( $row, $imported, $log );
	}
}

/**
 * Crée les articles de blog depuis la section ---BLOG--- du CSV.
 *
 * @param array<int, array<string, mixed>> $rows     Lignes de la section « blog ».
 * @param array<string, mixed>             $imported Suivi, par référence.
 * @param array<int, array<string, mixed>> $log      Journal, par référence.
 * @return void
 */
function swiftboard_import_creer_blog( array $rows, array &$imported, array &$log ) {
	foreach ( $rows as $i => $row ) {
		$title    = trim( $row['blog_title'] ?? '' );
		$content  = trim( $row['content'] ?? '' );
		$category = trim( $row['category'] ?? 'Blog' );
		$image    = trim( $row['image'] ?? '' );
		$excerpt  = trim( $row['excerpt'] ?? '' );
		$date     = trim( $row['date'] ?? '' );
		$author   = trim( $row['author'] ?? 'admin' );

		if ( ! $title || ! $content ) {
			$log[] = array(
				'time'    => current_time( 'mysql' ),
				'msg'     => '⚠️ Blog ligne ' . ( $i + 2 ) . ' ignorée (titre ou contenu manquant)',
				'warning' => true,
			);
			continue;
		}

		// Idempotence : ne pas recréer un article existant
		$q_existing = new WP_Query( array(
			'post_type'              => 'post',
			'title'                  => $title,
			'post_status'            => array( 'publish', 'private', 'draft' ),
			'posts_per_page'         => 1,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
		) );
		$existing = $q_existing->posts ? $q_existing->posts[0] : null;
		if ( $existing ) {
			$log[] = array(
				'time'    => current_time( 'mysql' ),
				'msg'     => '♻️ Article déjà existant (#' . $existing->ID . ') : "' . substr( $title, 0, 50 ) . '" — non dupliqué',
				'success' => true,
			);
			continue;
		}

		// Résoudre l'auteur
		$author_id = 1;
		$user = get_user_by( 'login', $author );
		if ( $user ) {
			$author_id = $user->ID;
		} else {
			$user = get_user_by( 'slug', $author );
			if ( $user ) {
				$author_id = $user->ID;
			}
		}

		// Catégorie
		$cat_ids = array();
		if ( $category ) {
			$term = term_exists( $category, 'category' );
			if ( ! $term ) {
				$term = wp_insert_term( $category, 'category' );
			}
			if ( ! is_wp_error( $term ) ) {
				$cat_ids[] = is_array( $term ) ? $term['term_id'] : $term;
			}
		}

		// Données du post
		$post_data = array(
			'post_title'   => $title,
			'post_content' => $content,
			'post_excerpt' => $excerpt,
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'post_author'  => $author_id,
		);
		if ( ! empty( $cat_ids ) ) {
			$post_data['post_category'] = $cat_ids;
		}
		if ( $date && strtotime( $date ) ) {
			$post_data['post_date']     = date( 'Y-m-d H:i:s', strtotime( $date ) );
			$post_data['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', strtotime( $date ) );
			$post_data['edit_date']     = true;
		}

		$post_id = wp_insert_post( $post_data );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			$log[] = array(
				'time'  => current_time( 'mysql' ),
				'msg'   => '❌ Échec création article "' . substr( $title, 0, 40 ) . '..."',
				'error' => true,
			);
			continue;
		}

		// Image à la une : attachement direct depuis le dossier du thème
		if ( $image ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			if ( filter_var( $image, FILTER_VALIDATE_URL ) ) {
				// URL externe : télécharger via media_sideload
				$media_id = media_sideload_image( $image, $post_id, $title, 'id' );
				if ( ! is_wp_error( $media_id ) && $media_id ) {
					set_post_thumbnail( $post_id, $media_id );
					$log[] = array(
						'time'    => current_time( 'mysql' ),
						'msg'     => '🖼️ Image à la une définie pour "' . substr( $title, 0, 40 ) . '"',
						'success' => true,
					);
				} else {
					update_post_meta( $post_id, '_swiftboard_blog_image', esc_url_raw( $image ) );
				}
			} else {
				// Chemin relatif → fichier local dans le thème
				$image_path = SWIFTBOARD_DIR . '/assets/img/blog/' . ltrim( $image, '/' );

				if ( file_exists( $image_path ) ) {
					$upload_dir  = wp_upload_dir();
					$filename    = basename( $image_path );
					$dest_path   = $upload_dir['path'] . '/' . $filename;

					if ( ! file_exists( $dest_path ) ) {
						copy( $image_path, $dest_path );
					}

					$wp_filetype = wp_check_filetype( $filename, null );
					$attachment = array(
						'post_mime_type' => $wp_filetype['type'] ?: 'image/avif',
						'post_title'     => $title,
						'post_content'   => '',
						'post_status'    => 'inherit',
					);

					$attach_id = wp_insert_attachment( $attachment, $dest_path, $post_id );

					if ( ! is_wp_error( $attach_id ) ) {
						$attach_data = wp_generate_attachment_metadata( $attach_id, $dest_path );
						wp_update_attachment_metadata( $attach_id, $attach_data );
						set_post_thumbnail( $post_id, $attach_id );
						$log[] = array(
							'time'    => current_time( 'mysql' ),
							'msg'     => '🖼️ Image à la une définie pour "' . substr( $title, 0, 40 ) . '"',
							'success' => true,
						);
					}
				} else {
					$image_url = SWIFTBOARD_URI . '/assets/img/blog/' . ltrim( $image, '/' );
					update_post_meta( $post_id, '_swiftboard_blog_image', esc_url_raw( $image_url ) );
				}
			}
		}

		$imported['blog'][ $title ] = $post_id;

		$log[] = array(
			'time'    => current_time( 'mysql' ),
			'msg'     => '✅ Article #' . $post_id . ' créé : "' . substr( $title, 0, 50 ) . '" (' . $category . ')',
			'success' => true,
		);
	}
}

/**
 * Importe un fichier CSV de sujets et de réponses.
 *
 * EXI-ARCH-03 : orchestrateur. La fonction faisait 231 lignes ; la logique
 * vit désormais dans quatre sous-fonctions testables séparément.
 *
 * @param array<string, mixed> $file Entrée de $_FILES.
 * @return array<int, array<string, mixed>> Journal de l'import.
 */
function swiftboard_process_import( $file ) {
	$log = array();

	// Un import de 500 lignes cree des centaines de contenus : les limites
	// par defaut d'un mutualise (30 s) ne suffisent pas.
	@set_time_limit( 300 );
	@ini_set( 'max_execution_time', '300' );
	@ini_set( 'memory_limit', '256M' );

	$log[] = array(
		'time' => current_time( 'mysql' ),
		'msg'  => '🎬 Démarrage de l\'import',
	);

	$content = swiftboard_import_valider_fichier( $file, $log );
	if ( $content === false ) {
		return $log;
	}

	$sections = swiftboard_import_parser_sections( $content, $log );
	if ( $sections === false ) {
		return $log;
	}

	$imported = array(
		'forums'  => array(),
		'users'   => array(),   // UID des membres créés (annulables)
		'topics'  => array(),   // titre => ID
		'replies' => array(),
		'votes'   => 0,
	);

	// Sans ce filtre, chaque compte cree et chaque promotion declencherait un
	// e-mail : un import de 500 lignes ferait blacklister le serveur.
	add_filter( 'pre_wp_mail', '__return_false' );

	// v5.3.5 — EXI-IMPORT-02 : membres + grades d'abord (les sujets/réponses
	// postés ensuite par ces auteurs héritent du bon grade dès l'affichage).
	if ( ! empty( $sections['membres'] ) ) {
		swiftboard_import_creer_membres( $sections['membres'], $imported, $log );
	}
			swiftboard_import_creer_topics( $sections['topics'], $imported, $log );
		swiftboard_import_creer_replies( $sections['replies'], $imported, $log );

		// Les réponses modifient l’activité : recalculer le score hot après
		// l’import complet, et non seulement lors de la création du topic.
		if ( function_exists( 'swiftboard_refresh_hot_score' ) ) {
			foreach ( $imported['topics'] as $topic_id ) {
				swiftboard_refresh_hot_score( (int) $topic_id );
			}
		}

		// v7.6 — Articles de blog (section ---BLOG--- du CSV)
	if ( ! empty( $sections['blog'] ) ) {
		swiftboard_import_creer_blog( $sections['blog'], $imported, $log );
	}

	// v9.2 — Fix user registration dates to match earliest content
	swiftboard_fix_user_registration_dates( $imported );
	remove_filter( 'pre_wp_mail', '__return_false' );

	update_option( 'swiftboard_last_import_ids', $imported, false );

	// === POST-PROCESSING: Set view counts on topics ===
	if ( ! empty( $imported['topics_vues'] ) ) {
		$views_set = 0;
		foreach ( $imported['topics_vues'] as $topic_id => $vues ) {
			if ( $vues > 0 ) {
				// Explicit vues from CSV
				update_post_meta( $topic_id, '_bbp_voice_count', $vues );
				++$views_set;
			} else {
				// Auto-calculate: 9 × (topic votes + reply votes)
				$topic_votes = (int) get_post_meta( $topic_id, '_swiftboard_votes', true );

				// Sum reply votes for this topic
				$reply_votes = 0;
				$reply_ids   = get_posts(
					array(
						'post_type'      => function_exists( 'bbp_get_reply_post_type' ) ? bbp_get_reply_post_type() : 'reply',
						'post_parent'    => $topic_id,
						'posts_per_page' => -1,
						'fields'         => 'ids',
						'post_status'    => 'publish',
					)
				);
				foreach ( $reply_ids as $rid ) {
					$reply_votes += (int) get_post_meta( $rid, '_swiftboard_votes', true );
				}

				$total_votes = $topic_votes + $reply_votes;
				$auto_vues   = max( 50, $total_votes * 9 + wp_rand( 10, 100 ) );
				update_post_meta( $topic_id, '_bbp_voice_count', $auto_vues );
				++$views_set;
			}
		}
		$log[] = array(
			'time'    => current_time( 'mysql' ),
			'msg'     => '👁 ' . $views_set . ' sujets avec vues définies',
			'success' => true,
		);
	}

	$log[] = array(
		'time'    => current_time( 'mysql' ),
		'msg'     => '✅ Import terminé : ' . count( $imported['users'] ) . ' membre(s), ' . count( $imported['topics'] ) . ' topics, ' . count( $imported['replies'] ) . ' replies, ' . count( $imported['blog'] ?? [] ) . ' articles blog, ' . $imported['votes'] . ' votes',
		'success' => true,
	);

	// FIX CRITIQUE : Purger tous les caches pour que les nouveaux sujets apparaissent.
	// Note : $wpdb->query DELETE ne vide pas le cache objet WP (Redis/Memcached).
	// On utilise delete_transient pour les clés connues + le DELETE SQL pour
	// les clés dynamiques (sb_hot_*, sb_feed_*).
	delete_transient( 'swiftboard_hot_topics' );
	delete_transient( 'swiftboard_hot_topics_all' );
	delete_transient( 'swiftboard_hot_topics_24h' );
	delete_transient( 'swiftboard_hot_topics_7d' );
	delete_transient( 'swiftboard_hot_topics_30d' );

	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sb_hot_%' OR option_name LIKE '_transient_timeout_sb_hot_%'" );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sb_feed_%' OR option_name LIKE '_transient_timeout_sb_feed_%'" );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_swiftboard_%' OR option_name LIKE '_transient_timeout_swiftboard_%'" );

	// Purger le cache de pages SwiftBoard
	if ( function_exists( 'swiftboard_purge_cache' ) ) {
		swiftboard_purge_cache();
	}

	$log[] = array(
		'time'    => current_time( 'mysql' ),
		'msg'     => '🧹 Caches purgés (transients + page cache)',
		'success' => true,
	);

	return $log;
}



// ============================================================================
// 8. ANNULATION DE L'IMPORT
// ============================================================================
/**
 * @return array<string, mixed>
 */
function swiftboard_cancel_last_import() {
	$imported = get_option( 'swiftboard_last_import_ids', array() );
	if ( empty( $imported ) ) {
		return array(
			'success' => false,
			'message' => 'Aucun import à annuler.',
		);
	}

	$counts = array(
		'topics'  => 0,
		'replies' => 0,
		'users'   => 0,
		'forums'  => 0,
	);

	// Supprimer les replies
	if ( ! empty( $imported['replies'] ) ) {
		foreach ( $imported['replies'] as $rid ) {
			wp_delete_post( $rid, true );
			++$counts['replies'];
		}
	}

	// Supprimer les topics (v5.3.5-bis : JAMAIS ceux identifies comme
	// pre-existants a l'import — l'annulation ne doit pas detruire un sujet
	// qui existait avant, par ex. un vrai sujet du forum auquel on a juste
	// rattache des reponses).
	if ( ! empty( $imported['topics'] ) ) {
		$preexistants = array_map( 'intval', (array) ( $imported['topics_preexistants'] ?? array() ) );
		foreach ( $imported['topics'] as $title => $tid ) {
			if ( in_array( (int) $tid, $preexistants, true ) ) {
				continue;
			}
			wp_delete_post( $tid, true );
			++$counts['topics'];
		}
	}

	// Supprimer les users créés (sauf si admin)
	if ( ! empty( $imported['users'] ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( $imported['users'] as $uid ) {
			if ( $uid > 1 ) {
				wp_delete_user( $uid, null );
				++$counts['users'];
			}
		}
	}

	// Supprimer les forums créés
	if ( ! empty( $imported['forums'] ) ) {
		foreach ( $imported['forums'] as $fid ) {
			wp_delete_post( $fid, true );
			++$counts['forums'];
		}
	}

	// Supprimer les votes liés aux posts supprimés
	global $wpdb;
	$table     = swiftboard_table( 'votes' );
	$all_posts = array_merge(
		array_values( $imported['topics'] ),
		$imported['replies']
	);
	if ( ! empty( $all_posts ) ) {
		$placeholders = implode( ',', array_fill( 0, count( $all_posts ), '%d' ) );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE post_id IN ({$placeholders})",
				$all_posts
			)
		);
	}

	delete_option( 'swiftboard_last_import_ids' );
	delete_option( 'swiftboard_last_import_log' );

	return array(
		'success' => true,
		'message' => sprintf(
			'🧹 Import annulé : %d topics, %d replies, %d users, %d forums supprimés',
			$counts['topics'],
			$counts['replies'],
			$counts['users'],
			$counts['forums']
		),
	);
}

// ============================================================================
// POST-PROCESSING: Set user_registered to earliest content date
// ============================================================================
/**
 * After import, set each user's registration date to the date of their
 * earliest topic or reply. This makes "Inscrit le ..." match the content dates.
 *
 * @param array $imported The imported data tracking array.
 * @return void
 */
function swiftboard_fix_user_registration_dates( array $imported = array() ) {
	global $wpdb;

	// Fix ALL non-admin users that have published content
	// (not just the ones in $imported, which may be incomplete)
	$user_ids = $wpdb->get_col(
		"SELECT DISTINCT p.post_author FROM {$wpdb->posts} p
		 WHERE p.post_author > 1
		 AND p.post_type IN ('topic', 'reply', 'post')
		 AND p.post_status = 'publish'"
	);

	if ( empty( $user_ids ) ) {
		return;
	}

	foreach ( $user_ids as $uid ) {
		// Find the earliest post date for this user
		$earliest = $wpdb->get_var( $wpdb->prepare(
			"SELECT MIN(post_date) FROM {$wpdb->posts}
			 WHERE post_author = %d
			 AND post_type IN ('topic', 'reply', 'post')
			 AND post_status = 'publish'",
			$uid
		) );

		if ( $earliest ) {
			// Update user_registered directly in the database
			$wpdb->update(
				$wpdb->users,
				array( 'user_registered' => $earliest ),
				array( 'ID' => $uid )
			);
		}
	}
}
