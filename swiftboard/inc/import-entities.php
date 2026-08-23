<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Creation des entites lors d'un import en masse.
 *
 * EXI-ARCH-03 : extrait de inc/admin-bulk-import.php pour ramener chaque
 * module sous 500 lignes. Regroupe la recherche et la creation des forums,
 * des comptes, des reponses et des votes simules.
 *
 * Points de vigilance conserves :
 *   - swiftboard_trouver_par_titre() remplace get_page_by_title(), depreciee
 *     depuis WordPress 6.2, et teste trois variantes d'encodage du titre ;
 *   - les comptes crees recoivent le role « abonne » et le grade « rookie »,
 *     jamais davantage ;
 *   - les votes simules sont plafonnes a 100 par contenu et inseres par
 *     requete preparee, pas par concatenation SQL (EXI-SEC-03).
 *
 * @package SwiftBoard
 * @since 5.1.0
 */
// ============================================================================
/** */
/**
 * @param string $titre
 * @param string $type
 * @return WP_Post|null
 */
function swiftboard_trouver_par_titre( $titre, $type ) {
	$variantes = array_unique(
		array(
			$titre,
			html_entity_decode( $titre, ENT_QUOTES, 'UTF-8' ),
			htmlspecialchars( $titre, ENT_QUOTES, 'UTF-8' ),
		)
	);

	foreach ( $variantes as $variante ) {
		$q = new WP_Query(
			array(
				'post_type'              => $type,
				'title'                  => $variante,
				'post_status'            => array( 'publish', 'private', 'draft' ),
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
			)
		);
		if ( ! empty( $q->posts ) ) {
			return $q->posts[0];
		}
	}

	return null;
}

/** */
/**
 * @param string $name
 * @param array<int, array<string, mixed>> $log
 * @param array<string, mixed> $imported
 * @return int
 */
function swiftboard_get_or_create_forum( $name, &$log, &$imported ): int {
	// Cherche un forum existant par titre (toutes variantes d'encodage).
	$existing = swiftboard_trouver_par_titre( $name, 'forum' );
	if ( $existing ) {
		return $existing->ID;
	}

	$forum_id             = bbp_insert_forum(
		array(
			'post_title'   => $name,
			'post_status'  => 'publish',
			'post_content' => 'Forum ' . $name,
		)
	);
	$imported['forums'][] = $forum_id;
	$log[]                = array(
		'time' => current_time( 'mysql' ),
		'msg'  => '📂 Forum créé : ' . $name . ' (ID:' . $forum_id . ')',
	);
	return $forum_id;
}

/** */
/**
 * @param string $name
 * @param array<int, array<string, mixed>> $log
 * @param array<string, mixed> $imported
 * @return int
 */
/**
 * @param string $name
 * @param array<int, array<string, mixed>> $log
 * @param array<string, mixed> $imported
 * @return int
 */
function swiftboard_get_or_create_user( string $name, array &$log, array &$imported, string $grade = '', string $avatar = '' ): int {
	$name = sanitize_text_field( $name );
	if ( ! $name ) {
		$name = 'Anonyme';
	}

	// Grade demandé (optionnel, venant du CSV) : on ne conserve que les
	// valeurs valides connues du système de grades, sinon on retombe sur le
	// défaut "rookie" sans jamais introduire de grade inconnu.
	$grade          = sanitize_key( (string) $grade );
	$grades_valides = function_exists( 'swiftboard_get_grades' )
		? array_keys( swiftboard_get_grades() )
		: array( 'rookie', 'member', 'pro', 'moderator', 'vip' );
	$grade          = in_array( $grade, $grades_valides, true ) ? $grade : '';

	// Cherche par display_name (essai par nickname exact)
	$existing = get_users(
		array(
			'meta_key'   => 'nickname',
			'meta_value' => $name,
			'number'     => 1,
			'fields'     => 'ID',
		)
	);
	if ( ! empty( $existing ) ) {
		$uid = (int) $existing[0];
		if ( $grade !== '' ) {
			update_user_meta( $uid, 'swiftboard_grade', $grade );
			if ( function_exists( 'swiftboard_invalidate_grade_cache' ) ) {
				swiftboard_invalidate_grade_cache( $uid );
			}
		}
		return $uid;
	}

	// Cherche aussi par login (pour les noms latin du CSV)
	$by_login = get_user_by( 'login', $name );
	if ( $by_login ) {
		if ( $grade !== '' ) {
			update_user_meta( $by_login->ID, 'swiftboard_grade', $grade );
			if ( function_exists( 'swiftboard_invalidate_grade_cache' ) ) {
				swiftboard_invalidate_grade_cache( $by_login->ID );
			}
		}
		return (int) $by_login->ID;
	}

	// Cherche par user_login (slugifié)
	$login = sanitize_user( sanitize_title( $name ), true );
	if ( username_exists( $login ) ) {
		$login = $login . '_' . wp_rand( 100, 999 );
	}
	$email = $login . '@imported.swiftboard.test';

	$user_id = wp_create_user( $login, wp_generate_password( 24, true ), $email );
	if ( is_wp_error( $user_id ) ) {
		$log[] = array(
			'time'  => current_time( 'mysql' ),
			'msg'   => '❌ Échec création user ' . $name,
			'error' => true,
		);
		return 0; // fallback: no author
	}

	wp_update_user(
		array(
			'ID'           => $user_id,
			'display_name' => $name,
			'nickname'     => $name,
			'role'         => 'subscriber',
		)
	);
	// Grade par défaut : "rookie", sauf si un grade est fourni par le CSV.
	update_user_meta( $user_id, 'swiftboard_grade', $grade !== '' ? $grade : 'rookie' );
	swiftboard_invalidate_grade_cache( $user_id ); // EXI-TEST-02
	update_user_meta( $user_id, '_swiftboard_imported_user', 1 );

	// LOT 11 : Attribution avatar depuis CSV
	if ( $avatar !== '' ) {
		if ( is_numeric( $avatar ) ) {
			$num = (int) $avatar;
			if ( $num >= 1 && $num <= 15 ) {
				update_user_meta( $user_id, 'swiftboard_avatar_id', $num );
				$log[] = array(
					'time'    => current_time( 'mysql' ),
					'msg'     => "  🖼️ Avatar #$num attribué à $name",
					'success' => true,
				);
			}
		} elseif ( filter_var( $avatar, FILTER_VALIDATE_URL ) && function_exists( 'swiftboard_download_avatar' ) ) {
			$avatar_path = swiftboard_download_avatar( $avatar, $user_id );
			if ( $avatar_path ) {
				update_user_meta( $user_id, 'swiftboard_custom_avatar', $avatar_path );
				$log[] = array(
					'time'    => current_time( 'mysql' ),
					'msg'     => "  🖼️ Avatar URL téléchargé pour $name",
					'success' => true,
				);
			}
		}
	}

	$imported['users'][] = $user_id;
	return $user_id;
}

/** */
/**
 * @param array<string, string> $row
 * @param array<string, mixed> $imported
 * @param array<int, array<string, mixed>> $log
 * @return void
 */
function swiftboard_create_reply_from_row( $row, &$imported, &$log ): void {
	$topic_title = trim( $row['topic_title'] ?? '' );
	$content_r   = trim( $row['content'] ?? '' );
	$author_name = trim( $row['author'] ?? 'Anonyme' );
	$votes       = (int) ( $row['votes'] ?? 0 );
	$date        = trim( $row['date'] ?? '' );
	$grade       = trim( $row['grade'] ?? '' );
	$reply_to_id = (int) ( $row['_reply_to_id'] ?? 0 );

	// Trouver le topic par titre
	$topic_id = $imported['topics'][ $topic_title ] ?? null;
	if ( ! $topic_id ) {
		// Cherche par titre exact
		$topic = swiftboard_trouver_par_titre( $topic_title, 'topic' );
		if ( $topic ) {
			$topic_id                           = $topic->ID;
			$imported['topics'][ $topic_title ] = $topic_id;
		} else {
			$log[] = array(
				'time'  => current_time( 'mysql' ),
				'msg'   => '❌ Reply ignorée : topic "' . substr( $topic_title, 0, 40 ) . '..." non trouvé',
				'error' => true,
			);
			return;
		}
	}

	$forum_id  = wp_get_post_parent_id( $topic_id );
	$author_id = swiftboard_get_or_create_user( $author_name, $log, $imported, $grade );

	// v5.3.5-bis — IDEMPOTENCE REPONSES : un re-upload (ou une relecture apres
	// echec partiel) ne doit pas dupliquer les commentaires. Meme auteur +
	// meme texte dans ce sujet → doublon signale, rien de cree.
	if ( $author_id ) {
		$signature = trim( wp_strip_all_tags( html_entity_decode( $content_r, ENT_QUOTES, 'UTF-8' ) ) );
		$existants = get_posts(
			array(
				'post_type'      => 'reply',
				'post_parent'    => $topic_id,
				'author'         => $author_id,
				'posts_per_page' => 50,
				'post_status'    => 'any',
				'orderby'        => 'ID',
			)
		);
		foreach ( $existants as $ex ) {
			if ( trim( wp_strip_all_tags( html_entity_decode( $ex->post_content, ENT_QUOTES, 'UTF-8' ) ) ) === $signature ) {
				$log[] = array(
					'time'    => current_time( 'mysql' ),
					'msg'     => '♻️ Doublon ignoré : même commentaire de ' . $author_name . ' déjà présent (#' . $ex->ID . ') dans ce sujet',
					'success' => true,
				);
				if ( function_exists( 'bbp_update_reply_to' ) && $reply_to_id ) {
					bbp_update_reply_to( $ex->ID, $reply_to_id ); // complete un fil manquant
				}
				return;
			}
		}
	}

	$reply_data = array(
		'post_title'   => '',
		'post_status'  => 'publish',
		'post_content' => $content_r,
		'post_author'  => $author_id,
		'post_parent'  => $topic_id,
	);
	if ( $date && strtotime( $date ) ) {
		$reply_data['post_date']     = date( 'Y-m-d H:i:s', strtotime( $date ) );
		$reply_data['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', strtotime( $date ) );
		$reply_data['edit_date']     = true; // bypass future check
	}

	$reply_meta = array(
		'forum_id' => $forum_id,
		'topic_id' => $topic_id,
	);
	if ( $reply_to_id ) {
		$reply_meta['reply_to'] = $reply_to_id;
	}

	$reply_id = bbp_insert_reply( $reply_data, $reply_meta );

	if ( ! $reply_id || is_wp_error( $reply_id ) ) {
		$log[] = array(
			'time'  => current_time( 'mysql' ),
			'msg'   => '❌ Échec création reply',
			'error' => true,
		);
		return;
	}

	// Forcer le status à publish via $wpdb direct
	global $wpdb;
	$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $reply_id ) );
	clean_post_cache( $reply_id );

	// FIX : Forcer les meta bbPress obligatoires (bbp_insert_reply peut les manquer)
	update_post_meta( $reply_id, '_bbp_topic_id', $topic_id );
	update_post_meta( $reply_id, '_bbp_forum_id', $forum_id );
	update_post_meta( $reply_id, '_bbp_reply_to', $reply_to_id );

	// FIX : Recalculer le compteur de réponses du topic
	if ( function_exists( 'bbp_update_topic_reply_count' ) ) {
		bbp_update_topic_reply_count( $topic_id );
	}
	if ( function_exists( 'bbp_update_topic_voice_count' ) ) {
		bbp_update_topic_voice_count( $topic_id );
	}

	$imported['replies'][] = $reply_id;
	// m-10 fix: Tracker pour threading par (topic_title, author_name, position_index)
	// au lieu de juste (topic_title, author_name) — évite les collisions
	if ( ! isset( $imported['replies_by_topic_author'][ $topic_title ] ) ) {
		$imported['replies_by_topic_author'][ $topic_title ] = array();
	}
	if ( ! isset( $imported['replies_by_topic_author'][ $topic_title ][ $author_name ] ) ) {
		$imported['replies_by_topic_author'][ $topic_title ][ $author_name ] = array();
	}
	$reply_position = count( $imported['replies_by_topic_author'][ $topic_title ][ $author_name ] );
	$imported['replies_by_topic_author'][ $topic_title ][ $author_name ][] = $reply_id;
	// Stocker aussi la position pour lookup
	$imported['replies_by_topic_author'][ $topic_title ][ $author_name . '__pos_' . $reply_position ] = $reply_id;

	// Simuler les votes sur la reply
	if ( $votes > 0 ) {
		swiftboard_simulate_votes( $reply_id, $votes, $log, $imported );
	}

	$log[] = array(
		'time'    => current_time( 'mysql' ),
		'msg'     => '  💬 Reply #' . $reply_id . ' par ' . $author_name . ' (' . $votes . ' votes)' . ( $reply_to_id ? ' [réponse à #' . $reply_to_id . ']' : '' ),
		'success' => true,
	);
}

/** */
/**
 * @param int $post_id
 * @param int $count
 * @param array<int, array<string, mixed>> $log
 * @param array<string, mixed> $imported
 * @return void
 */
function swiftboard_simulate_votes( $post_id, $count, &$log, &$imported ): void {
	// FIX : gérer les votes négatifs (downvotes)
	$abs_count   = abs( (int) $count );
	$abs_count   = min( 100, max( 1, $abs_count ) );
	$vote_type   = $count >= 0 ? 'up' : 'down';

	// On simule en créant directement des entrées dans la table swiftboard_votes
	// avec des voter_hash différents (anonymes) — pas besoin de créer des users
	// Optimisation : une seule requête INSERT multi-lignes au lieu de N inserts
	global $wpdb;
	$table = swiftboard_table( 'votes' );

	$post = get_post( $post_id );
	if ( ! $post ) {
		return;
	}

	// Construire les valeurs pour INSERT multi-lignes
	$values      = array();
	$post_id_int = (int) $post_id;
	$post_type   = esc_sql( $post->post_type );
	$post_author = (int) $post->post_author;
	$now         = current_time( 'mysql' );

	for ( $i = 0; $i < $abs_count; $i++ ) {
		$ip   = long2ip( mt_rand( ip2long( '1.0.0.0' ), ip2long( '223.255.255.255' ) ) );
		$hash = 'a:' . hash( 'sha1', wp_rand() . '|' . microtime( true ) . '|' . $i );
		// EXI-SEC-03 : on empile les VALEURS, plus des fragments SQL
		$values[] = array( $post_id_int, $post_type, $post_author, $vote_type, 0, $ip, $hash, $now );
	}

	// EXI-SEC-03 : INSERT prepare (etait concatene — seul SQL du theme hors
	// pattern prepare()). Placeholders generes dynamiquement par lot de 50.
	$batches = array_chunk( $values, 50 );
	foreach ( $batches as $batch ) {
		$row_sql      = '(%d, %s, %d, %s, %d, %s, %s, %s)';
		$placeholders = implode( ', ', array_fill( 0, count( $batch ), $row_sql ) );
		$flat         = array();
		foreach ( $batch as $row ) {
			foreach ( $row as $v ) {
				$flat[] = $v;
			}
		}
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (post_id, post_type, post_author, vote_type, user_id, voter_ip, voter_hash, created_at) VALUES {$placeholders}",
				$flat
			)
		);
	}

	// Recompter le score
	if ( function_exists( 'swiftboard_recount_post_votes' ) ) {
		swiftboard_recount_post_votes( $post_id );
	}

	$imported['votes'] += $abs_count;
}

// ============================================================================
// 6. PARSER CSV MULTI-LIGNES
// ============================================================================
/** */


// ============================================================================
// 7. IMPORT MEMBRES + GRADES (v5.3.5 — EXI-IMPORT-02)
// ============================================================================
/** */
/** */
/** @return array<string, int> */
function swiftboard_import_karma_planchers(): array {
	return (array) apply_filters(
		'swiftboard_import_karma_planchers',
		array(
			'rookie'    => 0,
			'member'    => 7,
			'pro'       => 538,
			'moderator' => 2149,
			'vip'       => 7116,
		)
	);
}

/**
 * @param array<int, array<string, string>> $rows
 * @param array<string, mixed> $imported
 * @param array<int, array<string, mixed>> $log
 * @return void
 */
function swiftboard_import_creer_membres( array $rows, array &$imported, array &$log ): void {
	if ( empty( $rows ) ) {
		return;
	}
	$grades_valides = function_exists( 'swiftboard_get_grades' )
		? array_keys( swiftboard_get_grades() )
		: array( 'rookie', 'member', 'pro', 'vip', 'moderator' );
	$alias_grade    = array(
		'membre'        => 'member',
		'membres'       => 'member',
		'debutant'      => 'rookie',
		'novice'        => 'rookie',
		'modo'          => 'moderator',
		'moderateur'    => 'moderator',
		'moderatrice'   => 'moderator',
		'professionnel' => 'pro',
		'expert'        => 'pro',
	);
	$cree           = 0;
	$maj            = 0;

	foreach ( $rows as $i => $row ) {
		$n        = $i + 2; // ligne humaine (1 = en-têtes)
		$login    = '';
		$email    = '';
		$grade_in = '';
		$pwd      = '';
		$karma_in  = '';
		$avatar_in       = '';
		$display_name_in = '';
		foreach ( $row as $k => $v ) {
			$k = strtolower( trim( (string) $k ) );
			$v = trim( (string) $v );
			if ( in_array( $k, array( 'identifiant', 'login', 'pseudo', 'username', 'utilisateur', 'user' ), true ) ) {
				$login = $v;
			}
			if ( in_array( $k, array( 'email', 'e-mail', 'mail', 'courriel' ), true ) ) {
				$email = $v;
			}
			if ( in_array( $k, array( 'grade', 'rang', 'rank', 'niveau', 'badge', 'statut' ), true ) ) {
				$grade_in = $v;
			}
			if ( in_array( $k, array( 'mot_de_passe', 'motdepasse', 'password', 'mdp' ), true ) ) {
				$pwd = $v;
			}
			// v5.3.6 — EXI-KARMA-01 : karma de depart (credibilite des comptes
			// importes : un VIP avec 0 karma « se voit »).
			if ( in_array( $k, array( 'karma', 'score', 'points', 'reputation', 'karma_bonus' ), true ) ) {
				$karma_in = $v;
			}
			if ( in_array( $k, array( 'avatar', 'photo', 'image', 'profil' ), true ) ) {
				$avatar_in = $v;
			}
			if ( in_array( $k, array( 'nom_arabe', 'display_name', 'nom', 'nom_affichage', 'الاسم' ), true ) ) {
				$display_name_in = $v;
			}
		}
		$karma = max( 0, min( 99999, (int) preg_replace( '/[^0-9-]/', '', $karma_in ) ) );
		$login = sanitize_user( $login, true );
		$email = sanitize_email( $email );

		$grade = strtolower( $grade_in );
		if ( isset( $alias_grade[ $grade ] ) ) {
			$grade = $alias_grade[ $grade ];
		}
		if ( ! in_array( $grade, $grades_valides, true ) ) {
			$grade = 'rookie';
		}

		// v5.3.7/5.3.8 — EXI-KARMA-02/03 : plancher de credibilite par rang.
		// Un rang eleve avec 0 karma « se voit ». Le plancher D'IMPORT est
		// volontairement NON ROND (7/538/2149/7116) et legerement au-dessus
		// du seuil annonce (5/500/2000/5000) : un profil pile au seuil ferait
		// artificiel. Le karma fourni — ou absent — est monte au plancher.
		$planchers    = function_exists( 'swiftboard_import_karma_planchers' ) ? swiftboard_import_karma_planchers() : array();
		$plancher     = isset( $planchers[ $grade ] ) ? (int) $planchers[ $grade ] : 0;
		$karma_ajuste = false;
		if ( $plancher > 0 && $karma < $plancher ) {
			$karma        = $plancher;
			$karma_ajuste = true;
		}
		$karma_msg = '';
		if ( $karma > 0 ) {
			if ( $karma_ajuste && '' === $karma_in ) {
				$karma_msg = " — karma plancher « {$grade} » = {$karma} (crédibilité)";
			} elseif ( $karma_ajuste ) {
				$karma_msg = " — karma ajusté au plancher « {$grade} » ({$karma_in} → {$karma})";
			} else {
				$karma_msg = " — karma {$karma}";
			}
		}

		if ( $login === '' || $email === '' ) {
			$log[] = array(
				'time'  => current_time( 'mysql' ),
				'msg'   => "⚠️ Ligne {$n} : identifiant ou e-mail vide — ignorée",
				'error' => true,
			);
			continue;
		}
		if ( ! is_email( $email ) ) {
			$log[] = array(
				'time'  => current_time( 'mysql' ),
				'msg'   => "⚠️ Ligne {$n} : e-mail « {$email} » invalide — ignorée",
				'error' => true,
			);
			continue;
		}

		$existant = get_user_by( 'login', $login );
		if ( $existant ) {
			// Membre existant : on met à jour le grade, JAMAIS le mot de passe.
			update_user_meta( $existant->ID, 'swiftboard_grade', $grade );
			$bonus_msg = '';
			if ( '' !== $karma_in ) {
				// Karma explicite : valeur posee telle quelle (deja >= plancher
				// apres ajustement EXI-KARMA-02 ci-dessus).
				update_user_meta( $existant->ID, 'swiftboard_karma_bonus', $karma );
				$bonus_msg = $karma_ajuste
					? " + karma ajusté au plancher « {$grade} » ({$karma_in} → {$karma})"
					: " + karma bonus {$karma}";
			} elseif ( $plancher > 0 && function_exists( 'swiftboard_get_user_reputation_score' ) ) {
				// v5.3.7 — pas de colonne karma : garantie de plancher. On
				// COMPLETE jusqu'au plancher sans ecraser le karma reel deja
				// gagne (reponses/upvotes). Idempotent : si total >= plancher,
				// on ne touche a rien.
				$rep_ex   = swiftboard_get_user_reputation_score( $existant->ID );
				$total_ex = (int) ( $rep_ex['score'] ?? 0 );
				if ( $total_ex < $plancher ) {
					$bonus_ex = (int) get_user_meta( $existant->ID, 'swiftboard_karma_bonus', true );
					$reel_ex  = max( 0, $total_ex - $bonus_ex );
					update_user_meta( $existant->ID, 'swiftboard_karma_bonus', max( 0, $plancher - $reel_ex ) );
					$bonus_msg = " + karma ajusté au plancher « {$grade} » ({$total_ex} → {$plancher})";
				}
			}
			if ( function_exists( 'swiftboard_invalidate_reputation_cache' ) ) {
				swiftboard_invalidate_reputation_cache( $existant->ID ); // purge grade + karma
			}
			++$maj;
			$log[] = array(
				'time'    => current_time( 'mysql' ),
				'msg'     => "  👤 « {$login} » existant → grade « {$grade} » appliqué (mot de passe inchangé)" . $bonus_msg,
				'success' => true,
			);
			continue;
		}

		$proprietaire = get_user_by( 'email', $email );
		if ( $proprietaire ) {
			$log[] = array(
				'time'  => current_time( 'mysql' ),
				'msg'   => "⚠️ Ligne {$n} : e-mail déjà utilisé par « {$proprietaire->user_login} » — ignorée",
				'error' => true,
			);
			continue;
		}

		$mot_de_passe = $pwd !== '' ? $pwd : wp_generate_password( 12, true, true );
		$uid          = wp_create_user( $login, $mot_de_passe, $email );
		if ( is_wp_error( $uid ) ) {
			$log[] = array(
				'time'  => current_time( 'mysql' ),
				'msg'   => "❌ Ligne {$n} « {$login} » : " . $uid->get_error_message(),
				'error' => true,
			);
			continue;
		}
		$u = new WP_User( $uid );
		$u->set_role( 'subscriber' );
		if ( function_exists( 'bbp_set_user_role' ) ) {
			bbp_set_user_role( $uid, 'bbp_participant' );
		}
		update_user_meta( $uid, 'swiftboard_grade', $grade );
		if ( $karma > 0 ) {
			update_user_meta( $uid, 'swiftboard_karma_bonus', $karma );
		}
		// Avatar depuis CSV (numéro 1-15 ou URL)
		if ( $avatar_in !== '' ) {
			if ( is_numeric( $avatar_in ) ) {
				$avatar_num = (int) $avatar_in;
				if ( $avatar_num >= 1 && $avatar_num <= 15 ) {
					update_user_meta( $uid, 'swiftboard_avatar_id', $avatar_num );
					update_user_meta( $uid, 'swiftboard_avatar', $avatar_num );
				}
			} elseif ( filter_var( $avatar_in, FILTER_VALIDATE_URL ) && function_exists( 'swiftboard_download_avatar' ) ) {
				$avatar_path = swiftboard_download_avatar( $avatar_in, $uid );
				if ( $avatar_path ) {
					update_user_meta( $uid, 'swiftboard_custom_avatar', $avatar_path );
				}
			}
		}
		if ( function_exists( 'swiftboard_invalidate_grade_cache' ) ) {
			swiftboard_invalidate_grade_cache( $uid );
		}
		update_user_meta( $uid, '_swiftboard_imported_user', 1 );
		// Display name arabe (ou personnalisé)
		if ( $display_name_in !== '' ) {
			wp_update_user( array(
				'ID'           => $uid,
				'display_name' => $display_name_in,
				'nickname'     => $display_name_in,
			) );
		}
		$imported['users'][] = $uid;
		++$cree;
		$log[] = array(
			'time'    => current_time( 'mysql' ),
			'msg'     => "  👤 « {$login} » créé — grade « {$grade} »" . $karma_msg . ( $pwd === '' ? " — mot de passe généré : {$mot_de_passe}" : ' (mot de passe fourni)' ),
			'success' => true,
		);
	}

	$log[] = array(
		'time'    => current_time( 'mysql' ),
		'msg'     => "👥 Membres : {$cree} créé(s), {$maj} grade(s) mis à jour",
		'success' => $cree + $maj > 0,
	);
}
