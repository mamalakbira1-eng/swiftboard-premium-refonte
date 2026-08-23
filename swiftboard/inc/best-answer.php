<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SwiftBoard — Système de "Meilleure Réponse" (✔ Résolu / Best Answer pour Q&A)
 *
 * @package SwiftBoard
 * @since 6.0.0
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'swiftboard/v1',
			'/topic/(?P<id>\d+)/solve',
			array(
				'methods'             => 'POST',
				'callback'            => 'swiftboard_rest_mark_best_answer',
				'permission_callback' => 'swiftboard_can_solve_topic_callback',
				'args'                => array(
					'reply_id' => array(
						'required' => true,
						'type'     => 'integer',
					),
				),
			)
		);
	}
);

function swiftboard_can_solve_topic_callback( WP_REST_Request $request ): bool {
	if ( ! is_user_logged_in() ) {
		return false;
	}
	$topic_id = (int) $request->get_param( 'id' );
	$topic    = get_post( $topic_id );
	if ( ! $topic || $topic->post_type !== 'topic' ) {
		return false;
	}
	$user_id = get_current_user_id();
	return ( $user_id === (int) $topic->post_author ) || current_user_can( 'moderate_comments' );
}

function swiftboard_rest_mark_best_answer( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$topic_id = (int) $request->get_param( 'id' );
	$reply_id = max( 0, (int) $request->get_param( 'reply_id' ) );

	if ( $reply_id > 0 ) {
		$reply = get_post( $reply_id );
		if ( ! $reply || $reply->post_type !== 'reply' || (int) $reply->post_parent !== $topic_id ) {
			return new WP_Error( 'invalid_reply', __( 'Cette réponse n\'appartient pas à ce sujet.', 'swiftboard' ), array( 'status' => 400 ) );
		}
		update_post_meta( $topic_id, '_swiftboard_best_answer_id', $reply_id );
		update_post_meta( $topic_id, '_swiftboard_is_solved', 1 );
		$action = 'solved';
	} else {
		delete_post_meta( $topic_id, '_swiftboard_best_answer_id' );
		delete_post_meta( $topic_id, '_swiftboard_is_solved' );
		$action = 'unsolved';
	}

	if ( function_exists( 'clean_post_cache' ) ) {
		clean_post_cache( $topic_id );
	}

	return rest_ensure_response(
		array(
			'success'        => true,
			'action'         => $action,
			'topic_id'       => $topic_id,
			'best_answer_id' => $reply_id,
			'is_solved'      => ( $reply_id > 0 ),
		)
	);
}

/**
 * Récupère l'ID de la meilleure réponse d'un sujet.
 */
function swiftboard_get_best_answer_id( int $topic_id ): int {
	return (int) get_post_meta( $topic_id, '_swiftboard_best_answer_id', true );
}

/**
 * V2 restauration - JS pour bouton ✔ Résolu
 * Branché sur REST /topic/{id}/solve
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! is_singular( 'topic' ) && ! is_singular( 'forum' ) && ! function_exists( 'is_bbpress' ) ) {
			return;
		}
		wp_enqueue_script(
			'swiftboard-best-answer',
			SWIFTBOARD_URI . '/assets/js/best-answer.js',
			array( 'swiftboard-main' ),
			defined( 'SWIFTBOARD_VERSION' ) ? SWIFTBOARD_VERSION : null,
			true
		);
	},
	30
);

// CSS pour best answer
add_action(
	'wp_head',
	function () {
		echo '<style>.sb-best-answer{border-left:3px solid #10B981 !important;background:#F0FDF4;}.sb-best-badge{animation:sb-pulse 1s ease}.sb-action-solve.active{color:#10B981;background:rgba(16,185,129,0.12);}@keyframes sb-pulse{0%{transform:scale(1)}50%{transform:scale(1.05)}100%{transform:scale(1)}}</style>';
	},
	20
);

/**
 * Helper pour afficher la meilleure réponse épinglée sous le post d'origine
 */
function swiftboard_render_pinned_best_answer( $topic_id ) {
	$best_id = swiftboard_get_best_answer_id( $topic_id );
	if ( ! $best_id ) {
		return;
	}
	$reply = get_post( $best_id );
	if ( ! $reply ) {
		return;
	}
	echo '<div class="sb-pinned-best" style="background:#F0FDF4;border:2px solid #10B981;border-radius:12px;padding:16px;margin:16px 0;">';
	echo '<div style="font-weight:700;color:#065F46;margin-bottom:8px;">✔ ' . esc_html__( 'Meilleure réponse - Résolu', 'swiftboard' ) . '</div>';
	echo '<div style="font-size:14px;color:#064E3B;">' . wp_kses_post( wp_trim_words( $reply->post_content, 50 ) ) . '</div>';
	echo '<a href="#reply-' . esc_attr( (string) $best_id ) . '" style="font-size:12px;color:#10B981;font-weight:600;">' . esc_html__( 'Voir la réponse complète', 'swiftboard' ) . '</a>';
	echo '</div>';
}
