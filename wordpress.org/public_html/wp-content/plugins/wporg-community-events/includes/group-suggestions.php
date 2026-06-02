<?php
/**
 * Group suggestion helpers.
 *
 * @package WordPressdotorg\Community_Events
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Community_Events;

defined( 'ABSPATH' ) || exit;

const GROUP_SUGGESTION_STATUS_PENDING    = 'pending';
const GROUP_SUGGESTION_STATUS_NEEDS_INFO = 'needs_info';
const GROUP_SUGGESTION_STATUS_APPROVED   = 'approved';
const GROUP_SUGGESTION_STATUS_DECLINED   = 'declined';

/**
 * Create a group suggestion.
 *
 * @param int   $user_id WordPress.org user ID.
 * @param array $args    Suggestion data.
 *
 * @return int|\WP_Error
 */
function create_group_suggestion( int $user_id, array $args ) {
	$user = get_user_by( 'id', $user_id );

	if ( ! $user ) {
		return new \WP_Error( 'wporg_ce_invalid_relationship_user', __( 'Invalid community member.', 'wporg' ) );
	}

	$title          = trim( (string) ( $args['title'] ?? '' ) );
	$location_label = trim( (string) ( $args['location_label'] ?? '' ) );

	if ( '' === $title ) {
		return new \WP_Error( 'wporg_ce_group_suggestion_title_required', __( 'Group name is required.', 'wporg' ) );
	}

	if ( '' === $location_label ) {
		return new \WP_Error( 'wporg_ce_group_suggestion_location_required', __( 'Group location is required.', 'wporg' ) );
	}

	$suggestion_id = wp_insert_post(
		wp_slash(
			array(
				'post_author'  => $user_id,
				'post_content' => (string) ( $args['description'] ?? '' ),
				'post_excerpt' => (string) ( $args['excerpt'] ?? '' ),
				'post_status'  => 'pending',
				'post_title'   => $title,
				'post_type'    => POST_TYPE_GROUP_SUGGESTION,
			)
		),
		true
	);

	if ( is_wp_error( $suggestion_id ) ) {
		return $suggestion_id;
	}

	update_relationship_meta(
		$suggestion_id,
		array(
			'wporg_ce_city'           => trim( (string) ( $args['city'] ?? '' ) ),
			'wporg_ce_location_label' => $location_label,
			'wporg_ce_region'         => trim( (string) ( $args['region'] ?? '' ) ),
			'wporg_ce_review_status'  => GROUP_SUGGESTION_STATUS_PENDING,
			'wporg_ce_timezone'       => trim( (string) ( $args['timezone'] ?? '' ) ),
			'wporg_ce_website_url'    => esc_url_raw( (string) ( $args['website_url'] ?? '' ) ),
		)
	);

	set_group_suggestion_terms( (int) $suggestion_id, $args );

	return (int) $suggestion_id;
}

/**
 * Update a group suggestion review.
 *
 * @param int   $suggestion_id Suggestion post ID.
 * @param int   $reviewer_id   WordPress.org user ID.
 * @param array $args          Review data.
 *
 * @return int|\WP_Error
 */
function review_group_suggestion( int $suggestion_id, int $reviewer_id, array $args ) {
	$suggestion = get_post( $suggestion_id );
	$reviewer   = get_user_by( 'id', $reviewer_id );

	if ( ! $suggestion || POST_TYPE_GROUP_SUGGESTION !== $suggestion->post_type ) {
		return new \WP_Error( 'wporg_ce_invalid_group_suggestion', __( 'Invalid group suggestion.', 'wporg' ) );
	}

	if ( ! $reviewer ) {
		return new \WP_Error( 'wporg_ce_invalid_relationship_user', __( 'Invalid community member.', 'wporg' ) );
	}

	$review_status = (string) ( $args['review_status'] ?? get_post_meta( $suggestion_id, 'wporg_ce_review_status', true ) );
	$review_status = get_allowed_group_suggestion_review_status( $review_status );

	if ( '' === $review_status ) {
		return new \WP_Error( 'wporg_ce_invalid_group_suggestion_status', __( 'Invalid group suggestion status.', 'wporg' ) );
	}

	$created_group_id = (int) get_post_meta( $suggestion_id, 'wporg_ce_created_group_id', true );

	if ( GROUP_SUGGESTION_STATUS_APPROVED === $review_status && ! $created_group_id ) {
		$created_group_id = create_group_from_suggestion( $suggestion_id, $reviewer_id );

		if ( is_wp_error( $created_group_id ) ) {
			return $created_group_id;
		}
	}

	$post_status = get_group_suggestion_post_status( $review_status );
	$updated_id  = wp_update_post(
		wp_slash(
			array(
				'ID'          => $suggestion_id,
				'post_status' => $post_status,
			)
		),
		true
	);

	if ( is_wp_error( $updated_id ) ) {
		return $updated_id;
	}

	$meta = array(
		'wporg_ce_duplicate_group_id'  => max( 0, (int) ( $args['duplicate_group_id'] ?? get_post_meta( $suggestion_id, 'wporg_ce_duplicate_group_id', true ) ) ),
		'wporg_ce_review_note'         => (string) ( $args['review_note'] ?? get_post_meta( $suggestion_id, 'wporg_ce_review_note', true ) ),
		'wporg_ce_review_status'       => $review_status,
		'wporg_ce_reviewed_at_utc'     => current_time( 'mysql', true ),
		'wporg_ce_reviewed_by_user_id' => $reviewer_id,
	);

	if ( $created_group_id ) {
		$meta['wporg_ce_created_group_id'] = (int) $created_group_id;
	}

	update_relationship_meta( $suggestion_id, $meta );

	return $suggestion_id;
}

/**
 * Create an official group from an approved suggestion.
 *
 * @param int $suggestion_id Suggestion post ID.
 * @param int $reviewer_id   Reviewer user ID.
 *
 * @return int|\WP_Error
 */
function create_group_from_suggestion( int $suggestion_id, int $reviewer_id ) {
	$suggestion = get_post( $suggestion_id );

	if ( ! $suggestion || POST_TYPE_GROUP_SUGGESTION !== $suggestion->post_type ) {
		return new \WP_Error( 'wporg_ce_invalid_group_suggestion', __( 'Invalid group suggestion.', 'wporg' ) );
	}

	$group_id = wp_insert_post(
		wp_slash(
			array(
				'post_author'  => $reviewer_id,
				'post_content' => $suggestion->post_content,
				'post_excerpt' => $suggestion->post_excerpt,
				'post_status'  => 'publish',
				'post_title'   => $suggestion->post_title,
				'post_type'    => POST_TYPE_GROUP,
			)
		),
		true
	);

	if ( is_wp_error( $group_id ) ) {
		return $group_id;
	}

	update_relationship_meta(
		$group_id,
		array(
			'wporg_ce_city'            => get_post_meta( $suggestion_id, 'wporg_ce_city', true ),
			'wporg_ce_event_count'     => 0,
			'wporg_ce_location_label'  => get_post_meta( $suggestion_id, 'wporg_ce_location_label', true ),
			'wporg_ce_member_count'    => 0,
			'wporg_ce_official_status' => 'official',
			'wporg_ce_region'          => get_post_meta( $suggestion_id, 'wporg_ce_region', true ),
			'wporg_ce_timezone'        => get_post_meta( $suggestion_id, 'wporg_ce_timezone', true ),
			'wporg_ce_website_url'     => get_post_meta( $suggestion_id, 'wporg_ce_website_url', true ),
		)
	);
	copy_group_suggestion_terms_to_group( $suggestion_id, (int) $group_id );

	return (int) $group_id;
}

/**
 * Set taxonomy terms for a group suggestion.
 *
 * @param int   $suggestion_id Suggestion post ID.
 * @param array $args          Suggestion data.
 *
 * @return void
 */
function set_group_suggestion_terms( int $suggestion_id, array $args ): void {
	foreach ( get_group_suggestion_taxonomy_request_map() as $request_key => $taxonomy ) {
		if ( empty( $args[ $request_key ] ) ) {
			continue;
		}

		$terms = array_map( 'sanitize_title', (array) $args[ $request_key ] );
		$terms = array_filter( $terms );

		if ( $terms ) {
			wp_set_object_terms( $suggestion_id, $terms, $taxonomy, false );
		}
	}
}

/**
 * Copy suggestion taxonomy terms to a created group.
 *
 * @param int $suggestion_id Suggestion post ID.
 * @param int $group_id      Group post ID.
 *
 * @return void
 */
function copy_group_suggestion_terms_to_group( int $suggestion_id, int $group_id ): void {
	foreach ( get_group_suggestion_taxonomy_request_map() as $taxonomy ) {
		$terms = wp_get_object_terms(
			$suggestion_id,
			$taxonomy,
			array(
				'fields' => 'ids',
			)
		);

		if ( ! is_wp_error( $terms ) && $terms ) {
			wp_set_object_terms( $group_id, array_map( 'intval', $terms ), $taxonomy, false );
		}
	}
}

/**
 * Map REST request keys to group suggestion taxonomies.
 *
 * @return array
 */
function get_group_suggestion_taxonomy_request_map(): array {
	return array(
		'countries'   => TAXONOMY_COUNTRY,
		'group_types' => TAXONOMY_GROUP_TYPE,
		'languages'   => TAXONOMY_LANGUAGE,
		'topics'      => TAXONOMY_TOPIC,
	);
}

/**
 * Get allowed group suggestion review statuses.
 *
 * @return string[]
 */
function get_group_suggestion_review_statuses(): array {
	return array(
		GROUP_SUGGESTION_STATUS_PENDING,
		GROUP_SUGGESTION_STATUS_NEEDS_INFO,
		GROUP_SUGGESTION_STATUS_APPROVED,
		GROUP_SUGGESTION_STATUS_DECLINED,
	);
}

/**
 * Normalize a group suggestion review status.
 *
 * @param string $status Requested status.
 *
 * @return string
 */
function get_allowed_group_suggestion_review_status( string $status ): string {
	return in_array( $status, get_group_suggestion_review_statuses(), true ) ? $status : '';
}

/**
 * Map review status to post status.
 *
 * @param string $review_status Review status.
 *
 * @return string
 */
function get_group_suggestion_post_status( string $review_status ): string {
	if ( GROUP_SUGGESTION_STATUS_APPROVED === $review_status ) {
		return 'publish';
	}

	if ( GROUP_SUGGESTION_STATUS_DECLINED === $review_status ) {
		return 'draft';
	}

	return 'pending';
}

/**
 * Check whether the current user can review group suggestions.
 *
 * @return bool
 */
function current_user_can_moderate_group_suggestions(): bool {
	return (bool) apply_filters(
		'wporg_ce_can_moderate_group_suggestions',
		current_user_can( 'manage_options' ),
		get_current_user_id()
	);
}
