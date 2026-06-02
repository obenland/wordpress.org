<?php
/**
 * Event comment policy for Community Events.
 *
 * @package WordPressdotorg\Community_Events
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Community_Events;

defined( 'ABSPATH' ) || exit;

add_filter( 'wp_insert_post_data', __NAMESPACE__ . '\open_event_comments_by_default', 10, 4 );
add_filter( 'pre_comment_approved', __NAMESPACE__ . '\require_logged_in_event_comment_author', 10, 2 );

/**
 * Keep newly created events discussion-ready by default.
 *
 * @param array $data                 Sanitized post data.
 * @param array $postarr              Sanitized post array.
 * @param array $unsanitized_postarr  Original post array.
 * @param bool  $update               Whether the post is being updated.
 *
 * @return array
 */
function open_event_comments_by_default( array $data, array $postarr, array $unsanitized_postarr, bool $update ): array {
	unset( $postarr );

	if ( $update || POST_TYPE_EVENT !== ( $data['post_type'] ?? '' ) ) {
		return $data;
	}

	if ( array_key_exists( 'comment_status', $unsanitized_postarr ) && '' !== (string) $unsanitized_postarr['comment_status'] ) {
		return $data;
	}

	$data['comment_status'] = 'open';

	return $data;
}

/**
 * Require a logged-in WordPress.org user for event comments.
 *
 * @param int|string|bool $approved    Comment approval status.
 * @param array           $commentdata Comment data.
 *
 * @return int|string|bool|\WP_Error
 */
function require_logged_in_event_comment_author( $approved, array $commentdata ) {
	$post_id = (int) ( $commentdata['comment_post_ID'] ?? 0 );

	if ( $post_id <= 0 || POST_TYPE_EVENT !== get_post_type( $post_id ) || is_user_logged_in() ) {
		return $approved;
	}

	return new \WP_Error(
		'wporg_ce_event_comment_login_required',
		__( 'You must be logged in with a WordPress.org account to comment on events.', 'wporg' ),
		array( 'status' => 403 )
	);
}
