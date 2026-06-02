<?php
/**
 * WordPress.org profile helpers for Community Events.
 *
 * @package WordPressdotorg\Community_Events
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Community_Events;

defined( 'ABSPATH' ) || exit;

/**
 * Prepare a public WordPress.org user identity for REST responses.
 *
 * @param int $user_id WordPress.org user ID.
 *
 * @return array
 */
function prepare_user_rest_response( int $user_id ): array {
	$user = get_user_by( 'id', $user_id );

	if ( ! $user ) {
		return array();
	}

	return array(
		'id'          => (int) $user->ID,
		'slug'        => (string) $user->user_nicename,
		'name'        => get_user_display_name( $user ),
		'profile_url' => get_user_profile_url( $user ),
		'avatar_url'  => get_avatar_url(
			$user->ID,
			array(
				'size' => 96,
			)
		),
	);
}

/**
 * Build a WordPress.org profile URL.
 *
 * @param \WP_User $user User object.
 *
 * @return string
 */
function get_user_profile_url( \WP_User $user ): string {
	$slug = $user->user_nicename ? $user->user_nicename : $user->user_login;

	return sprintf( 'https://profiles.wordpress.org/%s/', rawurlencode( $slug ) );
}

/**
 * Get a safe display name for a user.
 *
 * @param \WP_User $user User object.
 *
 * @return string
 */
function get_user_display_name( \WP_User $user ): string {
	if ( $user->display_name ) {
		return (string) $user->display_name;
	}

	return (string) $user->user_login;
}
