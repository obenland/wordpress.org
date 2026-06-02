<?php
/**
 * Import mapping helpers for Community Events.
 *
 * @package WordPressdotorg\Community_Events
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Community_Events;

defined( 'ABSPATH' ) || exit;

const IMPORT_STATUS_PENDING  = 'pending';
const IMPORT_STATUS_IMPORTED = 'imported';
const IMPORT_STATUS_SKIPPED  = 'skipped';
const IMPORT_STATUS_FAILED   = 'failed';

/**
 * Get allowed source import statuses.
 *
 * @return string[]
 */
function get_import_statuses(): array {
	return array(
		IMPORT_STATUS_PENDING,
		IMPORT_STATUS_IMPORTED,
		IMPORT_STATUS_SKIPPED,
		IMPORT_STATUS_FAILED,
	);
}

/**
 * Create or update a source-to-WordPress import mapping.
 *
 * Import mappings are private CPT records so migration scripts can safely
 * reconcile source IDs without custom tables.
 *
 * @param string $source      Source system key, such as "meetup".
 * @param string $source_id   Source object ID.
 * @param string $target_type Local target type, usually a Community Events post type.
 * @param int    $target_id   Local target object ID.
 * @param array  $args        Optional import data.
 *
 * @return int|\WP_Error
 */
function upsert_import_record( string $source, string $source_id, string $target_type, int $target_id, array $args = array() ) {
	$source      = sanitize_key( $source );
	$source_id   = trim( $source_id );
	$target_type = sanitize_key( $target_type );
	$validation  = validate_import_record_target( $source, $source_id, $target_type, $target_id );

	if ( is_wp_error( $validation ) ) {
		return $validation;
	}

	$import_id     = get_import_record_id( $source, $source_id, $target_type );
	$import_status = array_key_exists( 'import_status', $args )
		? get_allowed_value( $args['import_status'], get_import_statuses(), IMPORT_STATUS_IMPORTED )
		: IMPORT_STATUS_IMPORTED;
	$imported_at   = trim( (string) ( $args['imported_at'] ?? '' ) );
	$source_url    = isset( $args['source_url'] ) ? esc_url_raw( (string) $args['source_url'] ) : '';

	if ( '' === $imported_at ) {
		$imported_at = current_time( 'mysql', true );
	}

	$post = array(
		'post_name'   => sanitize_title( "import-{$source}-{$target_type}-{$source_id}" ),
		'post_parent' => should_import_record_use_post_parent( $target_type, $target_id ) ? $target_id : 0,
		'post_status' => 'publish',
		'post_title'  => "{$source}: {$source_id}",
		'post_type'   => POST_TYPE_IMPORT,
	);

	if ( $import_id ) {
		$post['ID'] = $import_id;
	}

	$import_id = wp_insert_post( wp_slash( $post ), true );

	if ( is_wp_error( $import_id ) ) {
		return $import_id;
	}

	update_relationship_meta(
		(int) $import_id,
		array(
			'wporg_ce_import_status' => $import_status,
			'wporg_ce_imported_at'   => $imported_at,
			'wporg_ce_source'        => $source,
			'wporg_ce_source_id'     => $source_id,
			'wporg_ce_source_url'    => $source_url,
			'wporg_ce_target_id'     => $target_id,
			'wporg_ce_target_type'   => $target_type,
		)
	);

	return (int) $import_id;
}

/**
 * Find an import record by source ID and target type.
 *
 * @param string $source      Source system key.
 * @param string $source_id   Source object ID.
 * @param string $target_type Local target type.
 *
 * @return int Import post ID, or 0 when no mapping exists.
 */
function get_import_record_id( string $source, string $source_id, string $target_type ): int {
	$source      = sanitize_key( $source );
	$source_id   = trim( $source_id );
	$target_type = sanitize_key( $target_type );

	if ( '' === $source || '' === $source_id || '' === $target_type ) {
		return 0;
	}

	$query = new \WP_Query(
		array(
			'fields'                 => 'ids',
			'post_type'              => POST_TYPE_IMPORT,
			'post_status'            => array( 'publish', 'private', 'pending', 'draft' ),
			'posts_per_page'         => 1,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Import reconciliation intentionally resolves source IDs from registered import meta.
			'meta_query'             => array(
				'relation' => 'AND',
				array(
					'key'   => 'wporg_ce_source',
					'value' => $source,
				),
				array(
					'key'   => 'wporg_ce_source_id',
					'value' => $source_id,
				),
				array(
					'key'   => 'wporg_ce_target_type',
					'value' => $target_type,
				),
			),
		)
	);

	return (int) ( $query->posts[0] ?? 0 );
}

/**
 * Get a mapped local target ID for a source object.
 *
 * @param string $source      Source system key.
 * @param string $source_id   Source object ID.
 * @param string $target_type Local target type.
 *
 * @return int Local target object ID, or 0 when no mapping exists.
 */
function get_import_target_id( string $source, string $source_id, string $target_type ): int {
	$import_id = get_import_record_id( $source, $source_id, $target_type );

	if ( ! $import_id ) {
		return 0;
	}

	return (int) get_post_meta( $import_id, 'wporg_ce_target_id', true );
}

/**
 * Validate import mapping identity and target data.
 *
 * @param string $source      Source system key.
 * @param string $source_id   Source object ID.
 * @param string $target_type Local target type.
 * @param int    $target_id   Local target object ID.
 *
 * @return true|\WP_Error
 */
function validate_import_record_target( string $source, string $source_id, string $target_type, int $target_id ) {
	if ( '' === $source || '' === $source_id ) {
		return new \WP_Error( 'wporg_ce_import_source_required', __( 'Import source and source ID are required.', 'wporg' ) );
	}

	if ( '' === $target_type || $target_id <= 0 ) {
		return new \WP_Error( 'wporg_ce_import_target_required', __( 'Import target type and target ID are required.', 'wporg' ) );
	}

	if ( 'user' === $target_type ) {
		if ( get_user_by( 'id', $target_id ) ) {
			return true;
		}

		return new \WP_Error( 'wporg_ce_invalid_import_target', __( 'Invalid import target.', 'wporg' ) );
	}

	$target = get_post( $target_id );

	if ( ! $target || $target_type !== $target->post_type ) {
		return new \WP_Error( 'wporg_ce_invalid_import_target', __( 'Invalid import target.', 'wporg' ) );
	}

	return true;
}

/**
 * Determine whether an import record can use post_parent for the target.
 *
 * @param string $target_type Local target type.
 * @param int    $target_id   Local target object ID.
 *
 * @return bool True when the target is a post of the requested type.
 */
function should_import_record_use_post_parent( string $target_type, int $target_id ): bool {
	$target = get_post( $target_id );

	return $target && $target_type === $target->post_type;
}
