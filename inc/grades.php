<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Grades & Réputation (front-end)
 *
 * v4.6.1 : extrait de admin-settings-grades.php pour être chargé sur le front.
 * Les fonctions grades/réputation sont utilisées par :
 * - llm-readability.php (schema JSON-LD)
 * - reddit-layout.php (grade badge sur cards)
 * - nested-comments.php (grade badge sur replies)
 * - reddit-profile.php (grade badge sur profil)
 * - votes-social.php (rate limit par grade)
 *
 * @package SwiftBoard
 * @since 4.6.1
 */
// ============================================================================
// 1. DÉFINITION DES GRADES
// ============================================================================
/**
 * Definition des 5 grades.
 *
 * CONTRAINTE DE CONTRASTE (EXI-DES-01/04) :
 * 'color' est utilisee comme FOND de pastille avec du texte blanc dessus
 * (8 usages : grades.php:255, nested-comments, reddit-layout, reddit-profile,
 * admin-settings-grades). Le ratio #fff / color doit donc etre >= 4.5:1.
 * La pastille etant autoportante, ce ratio est identique en light et en dark :
 * un seul token par grade suffit.
 *
 * Ratios valides : rookie 4.83 | member 5.41 | pro 4.57 | moderator 5.02 | vip 4.91
 * Verifie par tools/check-contrast.php (echoue le build si un ratio passe sous 4.5).
 *
 * @return array<string, mixed>
 */
function swiftboard_get_grades() {
	return array(
		'rookie'    => array(
			'name'                => 'Recrue',
			'icon'                => '🪖',
			'color'               => '#6b7280',
			'min_score'           => 0,
			'can_create_topic'    => true,
			'can_reply'           => true,
			'can_vote'            => true,
			'can_upload'          => false,
			'can_create_subforum' => false,
			'daily_vote_limit'    => 5,
			'daily_upload_limit'  => 0,
			'rate_limit_seconds'  => 5,
		),
		'member'    => array(
			'name'                => 'Soldat',
			'icon'                => '🎖️',
			'color'               => '#006cbd',
			'min_score'           => 5,
			'can_create_topic'    => true,
			'can_reply'           => true,
			'can_vote'            => true,
			'can_upload'          => true,
			'can_create_subforum' => false,
			'daily_vote_limit'    => 50,
			'daily_upload_limit'  => 2,
			'rate_limit_seconds'  => 3,
		),
		'pro'       => array(
			'name'                => 'Sergent',
			'icon'                => '🏅',
			'color'               => '#3a8607',
			'min_score'           => 500, // v5.3.8 — EXI-KARMA-03 : echelle annoncee (50 -> 500)
			'can_create_topic'    => true,
			'can_reply'           => true,
			'can_vote'            => true,
			'can_upload'          => true,
			'can_create_subforum' => true,
			'daily_vote_limit'    => 200,
			'daily_upload_limit'  => 10,
			'rate_limit_seconds'  => 1,
		),
		'moderator' => array(
			'name'                => 'Commandant',
			'icon'                => '🥇',
			'color'               => '#b45309',
			// Attribution manuelle, JAMAIS auto-promu (le grade calcule
			// plafonne a pro). min_score = SEUIL ANNONCE de l'echelle
			// (v5.3.8, EXI-KARMA-03), distinct du PLANCHER D'IMPORT non rond
			// (2149, cf. swiftboard_import_karma_planchers). Aucun impact sur
			// maybe_promote_user (intouchable >= niveau 4) ni get_user_grade.
			'min_score'           => 2000,
			'can_create_topic'    => true,
			'can_reply'           => true,
			'can_vote'            => true,
			'can_upload'          => true,
			'can_create_subforum' => true,
			'daily_vote_limit'    => 0, // illimité
			'daily_upload_limit'  => 0,
			'rate_limit_seconds'  => 0,
		),
		'vip'       => array(
			'name'                => 'Général',
			'icon'                => '🎖️',
			'color'               => '#d1257e',
			// Meme logique que moderator : min_score = seuil annonce (5000) ;
			// plancher d'import non rond = 7116 (rendu naturel, EXI-KARMA-03).
			'min_score'           => 5000,
			'can_create_topic'    => true,
			'can_reply'           => true,
			'can_vote'            => true,
			'can_upload'          => true,
			'can_create_subforum' => true,
			'daily_vote_limit'    => 0,
			'daily_upload_limit'  => 0,
			'rate_limit_seconds'  => 0,
		),
	);
}

// ============================================================================
// 2. RÉCUPÉRER LE GRADE D'UN USER
// ============================================================================
/**
 * HTML de l'echelle des grades + rappel du calcul du karma.
 *
 * v5.3.8 — EXI-KARMA-03 : affichee dans les regles du forum (bloc « A propos »,
 * systematique) et sur le profil de chaque membre. SOURCE UNIQUE de ces
 * chiffres : swiftboard_get_grades()['min_score'] + les poids d'options.
 * Ne JAMAIS dupliquer les seuils en dur dans les templates.
 *
 * @return string HTML deja echappe.
 */
function swiftboard_get_karma_ladder_html() {
	$grades = swiftboard_get_grades();
	$order  = array( 'rookie', 'member', 'pro', 'moderator', 'vip' );
	$manual = array( 'moderator', 'vip' );
	$parts  = array();
	foreach ( $order as $k ) {
		if ( ! isset( $grades[ $k ] ) ) {
			continue;
		}
		$g     = $grades[ $k ];
		$min   = (int) $g['min_score'];
		$label = swiftboard_grade_insignia_svg( $k ) . ' ' . $g['name'] . ' ' . ( $min > 0
			/* translators: %d: seuil de karma annonce du grade. */
			? sprintf( __( 'dès %d', 'swiftboard' ), $min )
			: '0' );
		if ( in_array( $k, $manual, true ) ) {
			$label .= ' ' . __( '(manuel)', 'swiftboard' );
		}
		$parts[] = '<span class="sb-ladder-grade">' . esc_html( $label ) . '</span>';
	}
	$w_up    = (int) get_option( 'swiftboard_autopromote_weight_upvote', 1 );
	$w_reply = (int) get_option( 'swiftboard_autopromote_weight_reply', 1 );
	$html    = '<div class="sb-karma-ladder">';
	$html   .= '<div class="sb-ladder-grades">' . implode( '<span class="sb-ladder-sep" aria-hidden="true">·</span>', $parts ) . '</div>';
	$html   .= '<p class="sb-ladder-howto">' . esc_html(
		sprintf(
		/* translators: 1: karma par upvote recu, 2: karma par commentaire recu. */
			__( '▲ 1 upvote reçu = %1$d karma — sur un sujet OU un commentaire, même valeur · 💬 1 commentaire reçu sur vos sujets = %2$d karma · les partages ne comptent pas', 'swiftboard' ),
			$w_up,
			$w_reply
		)
	) . '</p>';
	$html .= '</div>';
	return $html;
}

/**
 * Migration v5.3.8 — nouvelle echelle annoncee : pro 50 -> 500.
 * N'ecrase qu'une valeur restee au defaut precedent ; un reglage admin
 * personnalise est preserve. One-shot via `swiftboard_karma_ladder_538`.
 */
add_action(
	'init',
	function () {
		if ( get_option( 'swiftboard_karma_ladder_538' ) ) {
			return;
		}
		// Valeur explicite restee a l'ancien defaut 50 ? On la monte a 500.
		// (Option jamais enregistree : le defaut 500 s'applique deja partout,
		// rien a migrer.)
		if ( (int) get_option( 'swiftboard_autopromote_threshold_pro', 50 ) === 50 ) {
			update_option( 'swiftboard_autopromote_threshold_pro', 500 );
		}
		update_option( 'swiftboard_karma_ladder_538', 1 );
	},
	5
);

/**
 * Grade d'un utilisateur.
 *
 * EXI-TEST-02 : le cache etait un `static $cache = []` de portee requete, sans
 * aucun moyen d'invalidation. Consequence : apres une promotion ou une
 * attribution manuelle de grade, la valeur restait perimee jusqu'a la fin de la
 * requete (badge/permissions incoherents dans la meme page).
 *
 * Migration vers l'API wp_cache_* : meme comportement par defaut (cache non
 * persistant de portee requete) mais invalidable, et directement compatible
 * avec un backend persistant type Redis (cf. EXI-SCALE-02).
 *
 * @param int $user_id ID utilisateur.
 * @return string Cle de grade (rookie|member|pro|moderator|vip).
 */
function swiftboard_get_user_grade( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return 'rookie';
	}

	$cached = wp_cache_get( 'sb_grade_' . $user_id, 'swiftboard' );
	if ( false !== $cached ) {
		return $cached;
	}

	// Vérifier grade manuel (moderator / vip)
	$manual_grade = get_user_meta( $user_id, 'swiftboard_grade', true );
	if ( $manual_grade && in_array( $manual_grade, array( 'moderator', 'vip' ), true ) ) {
		wp_cache_set( 'sb_grade_' . $user_id, $manual_grade, 'swiftboard', HOUR_IN_SECONDS );
		return $manual_grade;
	}

	// Sinon, calculer selon le score de réputation
	$reputation = swiftboard_get_user_reputation_score( $user_id );
	$score      = $reputation['score'] ?? 0;
	$grades     = swiftboard_get_grades();

	// Parcourir les grades par score décroissant (rookie → member → pro)
	$earned = 'rookie';
	foreach ( array( 'pro', 'member', 'rookie' ) as $g ) {
		if ( $score >= $grades[ $g ]['min_score'] ) {
			$earned = $g;
			break;
		}
	}

	// Override par grade manuel si défini (rookie/member/pro)
	if ( $manual_grade && isset( $grades[ $manual_grade ] ) ) {
		$earned = $manual_grade;
	}

	wp_cache_set( 'sb_grade_' . $user_id, $earned, 'swiftboard', HOUR_IN_SECONDS );
	return $earned;
}

/**
 * Invalide le grade en cache d'un utilisateur.
 *
 * EXI-TEST-02 : a appeler a CHAQUE point de mutation du grade, c'est-a-dire
 * chaque update_user_meta($uid, 'swiftboard_grade', ...).
 *
 * @param int $user_id ID utilisateur.
 * @return void
 */
function swiftboard_invalidate_grade_cache( $user_id ) {
	wp_cache_delete( 'sb_grade_' . (int) $user_id, 'swiftboard' );
}

// ============================================================================
// 3. PERMISSIONS D'UN USER
// ============================================================================
/**
 * swiftboard_user_can().
 *
 * @param int   $user_id    Identifiant de l'utilisateur.
 * @param mixed $capability À documenter.
 * @return bool
 */
function swiftboard_user_can( $user_id, $capability ) {
	$user_id = (int) $user_id;

	// Les capacités WordPress PRIMENT sur le grade.
	//
	// Le grade SwiftBoard est un système de réputation : il ouvre des droits à
	// mesure qu'un membre participe. Il ne doit jamais en RETIRER à qui les
	// détient déjà par son rôle.
	//
	// Sans cette porte, un administrateur fraîchement créé — donc sans
	// activité, donc au grade « rookie » — se voyait refuser `can_upload` et
	// recevait un HTTP 403 sur `POST /upload`, alors qu'il possède
	// `manage_options`. Un gestionnaire de site ne peut pas être bridé par un
	// score de réputation qu'il n'a pas eu le temps de construire.
	//
	// `manage_options` est le marqueur du gestionnaire ; `moderate_comments`
	// celui du modérateur, qui doit lui aussi pouvoir agir sur le contenu.
	if ( $user_id > 0
		&& ( user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'moderate_comments' ) ) ) {
		return true;
	}

	$grade      = swiftboard_get_user_grade( $user_id );
	$grades     = swiftboard_get_grades();
	$grade_info = $grades[ $grade ] ?? $grades['rookie'];
	return (bool) ( $grade_info[ $capability ] ?? false );
}

// ============================================================================
// 4. SCORE DE RÉPUTATION
// ============================================================================
/**
 * swiftboard_get_user_reputation_score().
 *
 * @param int $user_id Identifiant de l'utilisateur.
 * @return mixed
 */
function swiftboard_get_user_reputation_score( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return array(
			'score'        => 0,
			'upvotes'      => 0,
			'replies'      => 0,
			'weight_up'    => 0,
			'weight_reply' => 0,
		);
	}

	$cache_key = 'sb_reputation_' . $user_id;
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) && isset( $cached['score'] ) ) {
		return apply_filters( 'swiftboard_reputation_score', $cached, $user_id );
	}

	global $wpdb;

	$weight_up    = (int) get_option( 'swiftboard_autopromote_weight_upvote', 1 );
	$weight_reply = (int) get_option( 'swiftboard_autopromote_weight_reply', 1 );

	// Upvotes reçus sur mes posts
	$upvotes      = 0;
	$votes_table  = swiftboard_table( 'votes' );
	$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $votes_table ) ) === $votes_table;

	if ( $table_exists ) {
		$upvotes = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
             FROM {$votes_table} v
             INNER JOIN {$wpdb->posts} p ON p.ID = v.post_id
             WHERE p.post_author = %d
               AND v.vote_type = 'up'",
				$user_id
			)
		);
	}

	// Réponses reçues sur mes topics
	$replies = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*)
         FROM {$wpdb->posts} r
         INNER JOIN {$wpdb->posts} t ON t.ID = r.post_parent
         WHERE t.post_author = %d
           AND r.post_type = 'reply'
           AND r.post_status = 'publish'",
			$user_id
		)
	);

	$score = ( $upvotes * $weight_up ) + ( $replies * $weight_reply );

	// v5.3.6 — EXI-KARMA-01 : bonus manuel (meta `swiftboard_karma_bonus`).
	// Besoin client : « un VIP avec zéro karma, ça se voit que c'est fake ».
	// Un import de masse (ou l'admin) peut dorenavant crediter un compte
	// nouvellement cree d'un karma de depart credible. Le bonus s'AJOUTE au
	// score calcule ; les votes/reponses ulterieurs continuent d'empiler
	// normalement par-dessus.
	$bonus  = (int) get_user_meta( $user_id, 'swiftboard_karma_bonus', true );
	$score += $bonus;

	$result = array(
		'score'        => $score,
		'upvotes'      => $upvotes,
		'replies'      => $replies,
		'bonus'        => $bonus,
		'weight_up'    => $weight_up,
		'weight_reply' => $weight_reply,
	);

	set_transient( $cache_key, $result, 15 * MINUTE_IN_SECONDS );

	/**
	 * Filtre le score de réputation calculé.
	 *
	 * Permet aux tests et extensions de surcharger le score sans manipuler
	 * directement le cache transient ou la base de votes.
	 *
	 * @param array $result  Score et détails (score, upvotes, replies, bonus, etc.).
	 * @param int   $user_id ID de l'utilisateur.
	 */
	return apply_filters( 'swiftboard_reputation_score', $result, $user_id );
}

// ============================================================================
// 5. INVALIDATION DU CACHE RÉPUTATION
// ============================================================================
/**
 * swiftboard_invalidate_reputation_cache().
 *
 * @param int $user_id Identifiant de l'utilisateur.
 * @return void
 */
function swiftboard_invalidate_reputation_cache( $user_id ) {
	delete_transient( 'sb_reputation_' . $user_id );
	// EXI-TEST-02 : le grade est DERIVE du score de reputation
	// (cf. swiftboard_get_user_grade). Invalider la reputation sans invalider
	// le grade laisserait un grade calcule sur un score perime. Les deux
	// caches doivent donc etre purges ensemble.
	swiftboard_invalidate_grade_cache( $user_id );
}

// ============================================================================
// 6. NIVEAU DE GRADE (pour comparaisons)
// ============================================================================
/**
 * swiftboard_grade_level().
 *
 * @param string $grade Clé du grade.
 * @return mixed
 */
function swiftboard_grade_level( $grade ) {
	$levels = array(
		'rookie'    => 1,
		'member'    => 2,
		'pro'       => 3,
		'moderator' => 4,
		'vip'       => 5,
	);
	return $levels[ $grade ] ?? 1;
}

// ============================================================================
// 7. GRADE PAR DÉFAUT À L'INSCRIPTION
// ============================================================================
add_action(
	'user_register',
	function ( $user_id ) {
		$default_grade = get_option( 'swiftboard_default_grade', 'rookie' );
		update_user_meta( $user_id, 'swiftboard_grade', $default_grade );
		swiftboard_invalidate_grade_cache( $user_id ); // EXI-TEST-02
	}
);

// ==== Deplace depuis admin-settings-grades.php (EXI-BLOQ-02) ====
/**
 * swiftboard_get_user_permissions().
 *
 * @param int $user_id Identifiant de l'utilisateur.
 * @return mixed
 */
function swiftboard_get_user_permissions( $user_id ) {
	$grade_key = swiftboard_get_user_grade( $user_id );
	$grades    = swiftboard_get_grades();
	$grade     = $grades[ $grade_key ] ?? $grades['member'];

	// Permettre la surcharge par admin (réglages personnalisés par utilisateur)
	$custom = get_user_meta( $user_id, 'swiftboard_custom_permissions', true );
	if ( $custom && is_array( $custom ) ) {
		$grade = array_merge( $grade, $custom );
	}

	return $grade;
}

/**
 * swiftboard_display_grade_badge().
 *
 * @param int $user_id Identifiant de l'utilisateur.
 * @return void
 */
/**
 * Retourne le SVG de l'insigne militaire pour un grade.
 *
 * Insignes façon épaulettes militaires :
 *  - Recrue      : 1 chevron
 *  - Soldat      : 2 chevrons
 *  - Sergent     : 3 chevrons
 *  - Commandant  : 1 étoile
 *  - Général     : 2 étoiles
 *
 * @param string $grade_key Clé du grade (rookie|member|pro|moderator|vip).
 * @return string HTML SVG (inline, currentColor).
 */
function swiftboard_grade_insignia_svg( $grade_key ) {
	// Cette fonction retournait des EMOJIS malgre son nom. Consequence :
	// l'insigne dependait d'une police emoji installee sur la machine du
	// visiteur — absente sur de nombreux serveurs et navigateurs headless,
	// ou le badge s'affichait en carre vide. Un trace SVG inline herite de
	// currentColor, garde la meme taille partout et ne depend d'aucune police.
	$traces = array(
		// Chevron simple.
		'rookie'    => '<path d="M4 15l8-7 8 7"/>',
		// Double chevron.
		'member'    => '<path d="M4 13l8-7 8 7"/><path d="M4 18l8-7 8 7"/>',
		// Etoile pleine.
		'pro'       => '<path d="M12 3l2.6 5.6 6 .8-4.4 4.2 1.1 6L12 16.8 6.7 19.6l1.1-6L3.4 9.4l6-.8z" fill="currentColor" stroke="none"/>',
		// Couronne de moderation.
		'moderator' => '<path d="M4 8l3.5 3L12 5l4.5 6L20 8l-1.6 9H5.6z"/>',
		// Etoile a rayons.
		'vip'       => '<path d="M12 3l2.6 5.6 6 .8-4.4 4.2 1.1 6L12 16.8 6.7 19.6l1.1-6L3.4 9.4l6-.8z" fill="currentColor" stroke="none"/><path d="M12 1v2M12 21v2M3 12H1M23 12h-2"/>',
	);

	$trace = $traces[ $grade_key ] ?? $traces['rookie'];

	return '<svg class="sb-grade-i" width="12" height="12" viewBox="0 0 24 24" fill="none"'
		. ' stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"'
		. ' aria-hidden="true" focusable="false">' . $trace . '</svg>';
}

/**
 * swiftboard_display_grade_badge().
 *
 * Affiche un badge avec l'insigne militaire SVG + le nom du grade.
 *
 * @param int $user_id Identifiant de l'utilisateur.
 * @return void
 */
function swiftboard_display_grade_badge( $user_id ) {
	$grade_key = swiftboard_get_user_grade( $user_id );
	$grades    = swiftboard_get_grades();
	$grade     = $grades[ $grade_key ] ?? $grades['member'];

	echo '<span class="swiftboard-grade-badge" style="background:' . esc_attr( $grade['color'] ) . ';color:#fff;padding:2px 8px;border-radius:9999px;font-size:0.7rem;font-weight:700;letter-spacing:0.02em;margin-left:4px;display:inline-flex;align-items:center;gap:3px;">' .
		swiftboard_grade_insignia_svg( $grade_key ) . ' ' . esc_html( $grade['name'] ) . '</span>';
}
