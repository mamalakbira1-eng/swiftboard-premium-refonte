<?php
if ( ! defined( 'ABSPATH' ))exit;

/**
 * SwiftBoard — Badges & Trophées personnalisables (Envato Gamification)
 *
 * @package SwiftBoard
 * @since 6.0.0
 */
add_action(
	'admin_menu',
	function () {
		add_submenu_page(
			'swiftboard-dashboard',
			__( 'Badges Custom', 'swiftboard' ),
			__( '🏆 Badges Custom', 'swiftboard' ),
			'manage_options',
			'swiftboard-badges',
			'swiftboard_badges_page_render'
		);
	}
);

function swiftboard_badges_page_render() {
	if ( ! current_user_can( 'manage_options' )) return;
	$badges = get_option( 'swiftboard_custom_badges', array() );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( '🏆 Gamification : Création de Badges Custom', 'swiftboard' ); ?></h1>
		<p><?php esc_html_e( 'Créez des trophées sur mesure (ex: Bug Hunter, Fondateur, Expert WP) à décerner à vos membres.', 'swiftboard' ); ?></p>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr><th>ID</th><th>Nom</th><th>Icône</th><th>Couleur</th><th>Description</th></tr>
			</thead>
			<tbody>
				<?php if ( empty( $badges ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'Aucun badge personnalisé créé. Vous pouvez en définir ici.', 'swiftboard' ); ?></td></tr>
					<?php
				else :
					foreach ( $badges as $b ) :
						?>
					<tr>
						<td><?php echo esc_html( $b['id'] ); ?></td>
						<td><?php echo esc_html( $b['name'] ); ?></td>
						<td><?php echo esc_html( $b['icon'] ); ?></td>
						<td><?php echo esc_html( $b['color'] ); ?></td>
						<td><?php echo esc_html( $b['description'] ); ?></td>
					</tr>
									<?php
				endforeach;
endif;
				?>
			</tbody>
		</table>
	</div>
	<?php
}

function swiftboard_get_user_badges( int $user_id ): array {
	$slugs = (array) get_user_meta( $user_id, 'swiftboard_user_badges', true );
	if (empty( $slugs )) return array();

	// Récupérer la config complète des badges depuis l'option
	$all_badges                                 = get_option( 'swiftboard_custom_badges', array() );
	if ( ! is_array( $all_badges )) $all_badges = array();

	// Indexer par slug pour lookup rapide
	$indexed = array();
	foreach ( $all_badges as $b ) {
		if ( is_array( $b ) && ! empty( $b['slug'] ) ) {
			$indexed[ $b['slug'] ] = $b;
		}
	}

	// Retourner les objets complets (pas juste les slugs)
	$result = array();
	foreach ( $slugs as $slug ) {
		if ( isset( $indexed[ $slug ] ) ) {
			$result[] = $indexed[ $slug ]; // {slug, name, icon, color}
		} else {
			// Fallback si le badge n'est pas trouvé dans la config
			$result[] = array(
				'slug'  => $slug,
				'name'  => $slug,
				'icon'  => '🏆',
				'color' => '#6b6b75',
			);
		}
	}
	return $result;
}
