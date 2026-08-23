<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — pagination admin simple.
 * CDC-PROD-FERME-06
 */
/**
 * @param int $total Total items.
 * @param int $per_page Per page.
 * @param int $current Current page (1-based).
 * @return void Echo safe HTML.
 */
function swiftboard_admin_render_pagination( $total, $per_page, $current ) {
	$total    = max( 0, (int) $total );
	$per_page = max( 1, (int) $per_page );
	$current  = max( 1, (int) $current );
	$pages    = (int) max( 1, (int) ceil( $total / max( 1, $per_page ) ) );
	if ( $current > $pages ) {
		$current = $pages;
	}

	echo '<div class="tablenav"><div class="tablenav-pages">';
	echo '<span class="displaying-num">' . esc_html(
		sprintf(
			/* translators: 1: total items */
			_n( '%s élément', '%s éléments', $total, 'swiftboard' ),
			number_format_i18n( $total )
		)
	) . '</span> ';

	if ( $pages <= 1 ) {
		echo '</div></div>';
		return;
	}

	$base = remove_query_arg( 'paged' );
	if ( $current > 1 ) {
		$url = esc_url( add_query_arg( 'paged', $current - 1, $base ) );
		echo '<a class="button" href="' . $url . '">&laquo; ' . esc_html__( 'Précédent', 'swiftboard' ) . '</a> ';
	}
	echo '<span class="paging-input">' . esc_html(
		sprintf(
			/* translators: 1: current page 2: total pages */
			__( 'Page %1$s / %2$s', 'swiftboard' ),
			number_format_i18n( $current ),
			number_format_i18n( $pages )
		)
	) . '</span> ';
	if ( $current < $pages ) {
		$url = esc_url( add_query_arg( 'paged', $current + 1, $base ) );
		echo '<a class="button" href="' . $url . '">' . esc_html__( 'Suivant', 'swiftboard' ) . ' &raquo;</a>';
	}
	echo '</div></div>';
}

/**
 * @param int $default_per Default per page.
 * @param int $max_per Max per page.
 * @return array{page:int, per:int, offset:int}
 */
function swiftboard_admin_pagination_args( $default_per = 50, $max_per = 100 ) {
	$page = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
	$per  = max( 1, min( (int) $max_per, (int) ( $_GET['per_page'] ?? $default_per ) ) );
	return array(
		'page'   => $page,
		'per'    => $per,
		'offset' => ( $page - 1 ) * $per,
	);
}
