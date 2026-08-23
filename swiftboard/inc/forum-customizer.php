<?php
if ( ! defined( 'ABSPATH' )) exit;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped — admin context with intentional HTML
/**
 * SwiftBoard - Personnalisation par forum (style subreddit)
 *
 * Permet à chaque forum bbPress d'avoir sa propre identité visuelle :
 *  - Couleur d'accent (utilisé dans hero, badges, liens)
 *  - Image de couverture (hero gradient)
 *  - Icône / logo du forum
 *  - Description étendue (Markdown)
 *  - Règles spécifiques
 *
 * Configuration : méta-box dans l'admin bbPress sur la page d'édition du forum.
 *
 * Affichage : injecté en haut de chaque page de forum via
 * bbp_template_before_forums_index et bbp_template_before_single_forum.
 *
 * @package SwiftBoard
 * @since 3.5.0
 */
// ============================================================================
// 1. MÉTA-BOX DANS L'ADMIN bbPress
// ============================================================================
add_action(
	'add_meta_boxes',
	function () {
		add_meta_box(
			'swiftboard_forum_customizer',
			__( '🎨 Personnalisation SwiftBoard', 'swiftboard' ),
			'swiftboard_forum_customizer_metabox',
			'forum',
			'normal',
			'high'
		);
	}
);

/**
 * swiftboard_forum_customizer_metabox().
 *
 * @param mixed $post À documenter.
 * @return void
 */
function swiftboard_forum_customizer_metabox( $post ) {
	wp_nonce_field( 'swiftboard_forum_customizer_save', 'swiftboard_forum_customizer_nonce' );

	$accent_color = get_post_meta( $post->ID, '_swiftboard_forum_color', true ) ?: '#006cbd';
	$cover_url    = get_post_meta( $post->ID, '_swiftboard_forum_cover', true );
	$icon         = get_post_meta( $post->ID, '_swiftboard_forum_icon', true );
	$description  = get_post_meta( $post->ID, '_swiftboard_forum_description', true );
	$rules        = get_post_meta( $post->ID, '_swiftboard_forum_rules', true );
	?>
	<table class="form-table">
		<tr>
			<th><label for="sb_forum_color"><?php esc_html_e( 'Couleur d\'accent', 'swiftboard' ); ?></label></th>
			<td>
				<input type="color" id="sb_forum_color" name="sb_forum_color"
						value="<?php echo esc_attr( $accent_color ); ?>"
						style="width:60px;height:40px;vertical-align:middle;">
				<input type="text" name="sb_forum_color_text"
						value="<?php echo esc_attr( $accent_color ); ?>"
						style="width:100px;margin-left:8px;" placeholder="#006cbd">
				<p class="description"><?php esc_html_e( 'Couleur principale du forum (badges, hero, liens actifs).', 'swiftboard' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="sb_forum_cover"><?php esc_html_e( 'Image de couverture', 'swiftboard' ); ?></label></th>
			<td>
				<input type="text" id="sb_forum_cover" name="sb_forum_cover"
						value="<?php echo esc_attr( $cover_url ); ?>"
						class="regular-text" placeholder="https://…">
				<button type="button" class="button" id="sb_forum_cover_upload">
					Choisir une image
				</button>
				<?php if ( $cover_url ) : ?>
				<div style="margin-top:8px;">
					<img src="<?php echo esc_url( $cover_url ); ?>" alt="" style="max-width:300px;border-radius:8px;">
				</div>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'Image de bannière en haut du forum (recommandé : 1200×200).', 'swiftboard' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="sb_forum_icon"><?php esc_html_e( 'Icône du forum', 'swiftboard' ); ?></label></th>
			<td>
				<input type="text" id="sb_forum_icon" name="sb_forum_icon"
						value="<?php echo esc_attr( $icon ); ?>"
						class="regular-text" placeholder="🚀 ou URL d'image">
				<p class="description"><?php esc_html_e( 'Emoji ou URL d\'image carrée (64×64 recommandé).', 'swiftboard' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="sb_forum_description"><?php esc_html_e( 'Description étendue', 'swiftboard' ); ?></label></th>
			<td>
				<textarea id="sb_forum_description" name="sb_forum_description" rows="4"
							class="large-text" placeholder="Décrivez votre forum en quelques phrases…"><?php echo esc_textarea( $description ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Affichée dans la carte "À propos" du forum.', 'swiftboard' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="sb_forum_rules"><?php esc_html_e( 'Règles du forum', 'swiftboard' ); ?></label></th>
			<td>
				<textarea id="sb_forum_rules" name="sb_forum_rules" rows="6"
							class="large-text" placeholder="Une règle par ligne…"><?php echo esc_textarea( $rules ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Une règle par ligne. Numérotation automatique.', 'swiftboard' ); ?></p>
			</td>
		</tr>
	</table>
	<script>
	jQuery(document).ready(function($) {
		// Sync color picker ↔ text input
		$('#sb_forum_color').on('input', function() {
			$('#sb_forum_color_text').val(this.value);
		});
		$('#sb_forum_color_text').on('input', function() {
			$('#sb_forum_color').val(this.value);
		});

		// Media uploader pour la cover
		$('#sb_forum_cover_upload').on('click', function(e) {
			e.preventDefault();
			var frame = wp.media({
				title: 'Choisir une image de couverture',
				button: { text: 'Utiliser cette image' },
				multiple: false
			});
			frame.on('select', function() {
				var attachment = frame.state().get('selection').first().toJSON();
				$('#sb_forum_cover').val(attachment.url);
			});
			frame.open();
		});
	});
	</script>
	<?php
}

// ============================================================================
// 2. SAUVEGARDER LES MÉTAS
// ============================================================================
add_action(
	'save_post_forum',
	function ( $post_id, $post, $update ) {
		if ( ! isset( $_POST['swiftboard_forum_customizer_nonce'] )) return;
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['swiftboard_forum_customizer_nonce'] ) ), 'swiftboard_forum_customizer_save' )) return;
		if (defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE) return;
		if ( ! current_user_can( 'edit_post', $post_id )) return;

		if ( isset( $_POST['sb_forum_color_text'] ) ) {
			$color                = sanitize_hex_color( wp_unslash( $_POST['sb_forum_color_text'] ) );
			if ( ! $color) $color = '#006cbd';
			update_post_meta( $post_id, '_swiftboard_forum_color', $color );
		}
		if ( isset( $_POST['sb_forum_cover'] ) ) {
			update_post_meta( $post_id, '_swiftboard_forum_cover', esc_url_raw( wp_unslash( $_POST['sb_forum_cover'] ) ) );
		}
		if ( isset( $_POST['sb_forum_icon'] ) ) {
			update_post_meta( $post_id, '_swiftboard_forum_icon', sanitize_text_field( wp_unslash( $_POST['sb_forum_icon'] ) ) );
		}
		if ( isset( $_POST['sb_forum_description'] ) ) {
			update_post_meta( $post_id, '_swiftboard_forum_description', sanitize_textarea_field( wp_unslash( $_POST['sb_forum_description'] ) ) );
		}
		if ( isset( $_POST['sb_forum_rules'] ) ) {
			update_post_meta( $post_id, '_swiftboard_forum_rules', sanitize_textarea_field( wp_unslash( $_POST['sb_forum_rules'] ) ) );
		}
	},
	10,
	3
);

// ============================================================================
// 3. HELPERS — Récupérer la config d'un forum
// ============================================================================
/**
 * swiftboard_get_forum_custom().
 *
 * @param int $forum_id Identifiant du forum.
 * @return array<string, mixed>
 */
function swiftboard_get_forum_custom( $forum_id ) {
	return array(
		'color'       => get_post_meta( $forum_id, '_swiftboard_forum_color', true ) ?: '#006cbd',
		'cover'       => get_post_meta( $forum_id, '_swiftboard_forum_cover', true ) ?: '',
		'icon'        => get_post_meta( $forum_id, '_swiftboard_forum_icon', true ) ?: '',
		'description' => get_post_meta( $forum_id, '_swiftboard_forum_description', true ) ?: '',
		'rules'       => get_post_meta( $forum_id, '_swiftboard_forum_rules', true ) ?: '',
	);
}

// ============================================================================
// 4. RENDU — Hero du forum
// ============================================================================
/**
 * Affiche le hero personnalisé du forum en haut de la page.
 *
 * @param int $forum_id Identifiant du forum. Optionnel.
 * @return void
 */
function swiftboard_render_forum_hero( $forum_id = 0 ) {
	$forum_id = $forum_id ?: ( function_exists( 'bbp_get_forum_id' ) ? bbp_get_forum_id() : 0 );
	if ( ! $forum_id) return;

	$custom      = swiftboard_get_forum_custom( $forum_id );
	$forum_title = function_exists( 'bbp_get_forum_title' ) ? bbp_get_forum_title( $forum_id ) : get_the_title( $forum_id );
	$forum_url   = function_exists( 'bbp_get_forum_permalink' ) ? bbp_get_forum_permalink( $forum_id ) : get_permalink( $forum_id );

	// Stats
	$topics_count = function_exists( 'bbp_get_forum_topic_count' ) ? bbp_get_forum_topic_count( $forum_id, true, true ) : 0;
	$posts_count  = function_exists( 'bbp_get_forum_post_count' ) ? bbp_get_forum_post_count( $forum_id, true, true ) : 0;

	// Background du hero
	if ( $custom['cover'] ) {
		$bg = "background: linear-gradient(180deg, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0.5) 100%), url('" . esc_url( $custom['cover'] ) . "') center/cover;";
	} else {
		$bg = 'background: linear-gradient(135deg, ' . esc_attr( $custom['color'] ) . ', ' . esc_attr( swiftboard_lighten_color( $custom['color'], 20 ) ) . ');';
	}
	?>
    <div class="sb-forum-hero" style="<?php echo $bg; /* phpcs:ignore */ ?>">
		<div class="sb-forum-hero-content">
			<div class="sb-forum-hero-icon" style="background:<?php echo esc_attr( $custom['color'] ); ?>;">
				<?php
				$icon = $custom['icon'];
				if ( $icon ) {
					if ( filter_var( $icon, FILTER_VALIDATE_URL ) ) {
						echo '<img src="' . esc_url( $icon ) . '" alt="" width="48" height="48">';
					} else {
						echo esc_html( $icon );
					}
				} else {
					echo esc_html( strtoupper( substr( $forum_title, 0, 1 ) ) );
				}
				?>
			</div>
			<div class="sb-forum-hero-info">
				<h1 class="sb-forum-hero-title">r/<?php echo esc_html( $forum_title ); ?></h1>
				<div class="sb-forum-hero-stats">
					<span><strong><?php echo esc_html( swiftboard_format_count( $topics_count ) ); ?></strong> sujets</span>
					<span>·</span>
					<span><strong><?php echo esc_html( swiftboard_format_count( $posts_count ) ); ?></strong> messages</span>
				</div>
			</div>
			<?php
			// Le lien d'abonnement natif bbPress (nonce + URL de retour) est rendu
			// ici pour les membres : assets/js/forum-subscribe.js le declenche au
			// clic sur le bouton du hero. Sans lui, le bouton n'aurait rien a
			// appeler et redeviendrait decoratif (defaut R10).
			if ( function_exists( 'swiftboard_render_join_button' ) ) {
				swiftboard_render_join_button( $forum_id );
			}

			$sb_est_abonne = is_user_logged_in()
				&& function_exists( 'bbp_is_user_subscribed_to_forum' )
				&& bbp_is_user_subscribed_to_forum( get_current_user_id(), $forum_id );
			?>
			<button
				class="sb-forum-hero-subscribe<?php echo $sb_est_abonne ? ' subscribed' : ''; ?>"
				type="button"
				data-forum-id="<?php echo esc_attr( (string) $forum_id ); ?>"
				<?php if ( ! is_user_logged_in() ) : ?>
				data-login-url="<?php echo esc_url( wp_login_url( get_permalink( $forum_id ) ) ); ?>"
				<?php endif; ?>
				aria-pressed="<?php echo $sb_est_abonne ? 'true' : 'false'; ?>"
			>
				<?php
				// Icone en SVG inline et non en caractere : sur un serveur sans
				// police emoji, le glyphe se rend en carre (defaut R03 deja vecu).
				if ( $sb_est_abonne ) {
					echo '<svg class="sb-join-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg> ';
					echo esc_html__( 'Abonné', 'swiftboard' );
				} else {
					echo '<svg class="sb-join-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M12 5v14M5 12h14"/></svg> ';
					echo esc_html__( "S'abonner", 'swiftboard' );
				}
				?>
			</button>
		</div>
	</div>
	<?php
	// Bloc « A propos / Regles / Grades & karma » : delegue a
	// swiftboard_render_forum_about() — appelee aussi directement par
	// bbpress/content-single-forum.php (voir note EXI-KARMA-03 plus bas).
	swiftboard_render_forum_about( $forum_id );
}

/**
 * Bloc « A propos » d'un forum : description, regles et echelle des grades.
 *
 * v5.3.8 — EXI-KARMA-03 : extrait de swiftboard_render_forum_hero(). Le hook
 * historique bbp_template_before_single_forum n'etait JAMAIS declenche par
 * l'override bbpress/content-single-forum.php du theme (hero + regles =
 * code mort) ; l'y activer tel quel doublait l'en-tete (ce hero + le
 * subreddit-head du template). Le template appelle desormais CETTE fonction
 * directement : une seule en-tete, puis ce bloc. L'echelle des grades fait
 * partie des regles affichees → le bloc est rendu SYSTEMATIQUEMENT, meme
 * sans description ni regles personnalisees.
 *
 * @param int $forum_id ID du forum. Optionnel.
 * @return void
 */
function swiftboard_render_forum_about( $forum_id = 0 ) {
	$forum_id = $forum_id ?: ( function_exists( 'bbp_get_forum_id' ) ? bbp_get_forum_id() : 0 );
	if ( ! $forum_id) return;

	$custom = swiftboard_get_forum_custom( $forum_id );
	?>
	<div class="sb-forum-about">
		<?php if ( $custom['description'] ) : ?>
		<div class="sb-forum-about-section">
			<h3>À propos</h3>
			<p><?php echo esc_html( $custom['description'] ); ?></p>
		</div>
		<?php endif; ?>
		<?php
		if ( $custom['rules'] ) :
			$rules = array_filter( array_map( 'trim', explode( "\n", $custom['rules'] ) ) );
			if ( ! empty( $rules ) ) :
				?>
		<div class="sb-forum-about-section">
			<h3>📜 Règles</h3>
			<ol class="sb-forum-rules">
				<?php foreach ( $rules as $rule ) : ?>
				<li><?php echo esc_html( $rule ); ?></li>
				<?php endforeach; ?>
			</ol>
		</div>
				<?php
		endif;
endif;
		?>
		<div class="sb-forum-about-section sb-forum-ladder-section">
			<h3>🏆 <?php esc_html_e( 'Grades & karma', 'swiftboard' ); ?></h3>
			<?php echo function_exists( 'swiftboard_get_karma_ladder_html' ) ? swiftboard_get_karma_ladder_html() : ''; /* phpcs:ignore — HTML echappe dans le helper */ ?>
		</div>
	</div>
	<?php
	// La couleur d'accent n'est PAS emise ici.
	//
	// Cette fonction est appelee tard dans le rendu du gabarit du forum, bien
	// APRES wp_head : le `add_action('wp_head', ...)` qui se
	// trouvait a cet endroit n'avait aucune chance d'etre declenche. Le
	// correctif « v4.6.2 fix W3C » avait deplace le probleme (plus de <style>
	// dans le <body>) sans le resoudre : la variable --sb-forum-accent
	// n'apparaissait tout simplement plus dans la page.
	//
	// Elle est desormais emise par swiftboard_forum_accent_css(), accrochee a
	// wp_head en amont du rendu (voir plus bas).
}

/**
 * Emet la couleur d'accent du forum courant dans le <head>.
 *
 * Accrochee directement a wp_head : la valeur doit etre connue AVANT le rendu
 * du gabarit, et la regle doit rester dans le <head> pour la conformite W3C.
 *
 * @return void
 */
function swiftboard_forum_accent_css() {
	if ( ! function_exists( 'bbp_get_forum_id' ) ) {
		return;
	}

	// is_singular('forum') plutot que bbp_is_single_forum() : ce hook se
	// declenche avant que bbPress ait fini d'etablir son contexte.
	$forum_id = is_singular( 'forum' ) ? get_queried_object_id() : 0;
	if ( ! $forum_id ) {
		return;
	}

	$custom = swiftboard_get_forum_custom( $forum_id );
	if ( empty( $custom['color'] ) ) {
		return;
	}

	// Palette bornee a une couleur hexadecimale : la valeur vient d'une metabox
	// d'administration, elle ne doit jamais pouvoir s'echapper du style.
	if ( ! preg_match( '/^#[0-9a-fA-F]{3,8}$/', $custom['color'] ) ) {
		return;
	}

	echo '<style id="sb-forum-accent">:root{--sb-forum-accent:'
		. esc_attr( $custom['color'] ) . ';}</style>' . "\n";
}
add_action( 'wp_head', 'swiftboard_forum_accent_css', 20 );

add_action( 'bbp_template_before_single_forum', 'swiftboard_render_forum_hero', 5 );
add_action(
	'bbp_template_before_forums_index',
	function () {
		// Sur l'index global, on n'affiche pas de hero (forum_id 0)
	},
	5
);

// ============================================================================
// 5. HELPER — Éclaircir une couleur hex
// ============================================================================
/**
 * swiftboard_lighten_color().
 *
 * @param string $hex     Couleur au format hexadécimal.
 * @param int    $percent Pourcentage appliqué.
 * @return mixed
 */
function swiftboard_lighten_color( $hex, $percent ) {
	$hex = ltrim( $hex, '#' );
	if (strlen( $hex ) !== 6) return $hex;
	$r = hexdec( substr( $hex, 0, 2 ) );
	$g = hexdec( substr( $hex, 2, 2 ) );
	$b = hexdec( substr( $hex, 4, 2 ) );
	$r = min( 255, $r + round( 255 * $percent / 100 ) );
	$g = min( 255, $g + round( 255 * $percent / 100 ) );
	$b = min( 255, $b + round( 255 * $percent / 100 ) );
	return sprintf( '#%02x%02x%02x', $r, $g, $b );
}

// ============================================================================
// 6. CSS
// ============================================================================
// CSS moved to assets/css/main.css (dead closure removed)

// ============================================================================
// 7. JS — Bouton "S'abonner"
// ============================================================================
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_script(
			'swiftboard-forum-subscribe',
			SWIFTBOARD_URI . '/assets/js/forum-subscribe.js',
			array( 'swiftboard-main' ),
			defined( 'SWIFTBOARD_VERSION' ) ? SWIFTBOARD_VERSION : null,
			true
		);
	},
	30
);

