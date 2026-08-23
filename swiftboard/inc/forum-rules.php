<?php
/**
 * SwiftBoard — Regles de communaute editables par forum.
 *
 * La colonne « A propos » d'une page de forum affiche une liste de regles,
 * comme sur Reddit. Sans interface de saisie ce bloc resterait vide a vie :
 * on ajoute donc une metabox sur l'ecran d'edition du forum.
 *
 * @package SwiftBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enregistre la metabox sur le type de contenu forum.
 *
 * @return void
 */
function swiftboard_forum_rules_metabox() {
	if ( ! function_exists( 'bbp_get_forum_post_type' ) ) {
		return;
	}

	add_meta_box(
		'swiftboard_forum_rules',
		__( 'Règles de la communauté', 'swiftboard' ),
		'swiftboard_forum_rules_render',
		bbp_get_forum_post_type(),
		'normal',
		'default'
	);
}
add_action( 'add_meta_boxes', 'swiftboard_forum_rules_metabox' );

/**
 * Affiche le champ de saisie.
 *
 * @param WP_Post $post Forum en cours d'edition.
 * @return void
 */
function swiftboard_forum_rules_render( $post ) {
	wp_nonce_field( 'swiftboard_forum_rules_save', 'swiftboard_forum_rules_nonce' );
	$valeur = (string) get_post_meta( $post->ID, '_swiftboard_forum_rules', true );
	?>
	<p class="description">
		<?php esc_html_e( 'Une règle par ligne. Elles apparaissent numérotées dans la colonne « À propos » de la communauté.', 'swiftboard' ); ?>
	</p>
	<textarea name="swiftboard_forum_rules" rows="6" class="widefat" style="font-family:inherit;"><?php echo esc_textarea( $valeur ); ?></textarea>
	<?php
}

/**
 * Enregistre les regles saisies.
 *
 * @param int $post_id Forum enregistre.
 * @return void
 */
function swiftboard_forum_rules_save( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	$nonce = isset( $_POST['swiftboard_forum_rules_nonce'] )
		? sanitize_text_field( wp_unslash( $_POST['swiftboard_forum_rules_nonce'] ) )
		: '';

	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'swiftboard_forum_rules_save' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( ! isset( $_POST['swiftboard_forum_rules'] ) ) {
		delete_post_meta( $post_id, '_swiftboard_forum_rules' );
		return;
	}

	// Chaque ligne est du texte brut : on nettoie ligne par ligne pour
	// conserver les retours a la ligne, que sanitize_textarea_field()
	// preserve mais que sanitize_text_field() ecraserait.
	$brut   = sanitize_textarea_field( wp_unslash( $_POST['swiftboard_forum_rules'] ) );
	$lignes = array_filter( array_map( 'trim', explode( "\n", $brut ) ) );

	if ( empty( $lignes ) ) {
		delete_post_meta( $post_id, '_swiftboard_forum_rules' );
		return;
	}

	update_post_meta( $post_id, '_swiftboard_forum_rules', implode( "\n", $lignes ) );
}
add_action( 'save_post', 'swiftboard_forum_rules_save' );
