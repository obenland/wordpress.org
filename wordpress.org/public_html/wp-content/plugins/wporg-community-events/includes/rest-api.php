<?php
/**
 * REST API actions for Community Events.
 *
 * @package WordPressdotorg\Community_Events
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Community_Events;

defined( 'ABSPATH' ) || exit;

const REST_NAMESPACE = 'wporg-community-events/v1';

require_once __DIR__ . '/rest-api/class-base-controller.php';
require_once __DIR__ . '/rest-api/class-current-user-controller.php';
require_once __DIR__ . '/rest-api/class-events-controller.php';
require_once __DIR__ . '/rest-api/class-feedback-controller.php';
require_once __DIR__ . '/rest-api/class-group-suggestions-controller.php';
require_once __DIR__ . '/rest-api/class-groups-controller.php';
require_once __DIR__ . '/rest-api/class-memberships-controller.php';
require_once __DIR__ . '/rest-api/class-rsvps-controller.php';
require_once __DIR__ . '/rest-api/class-venues-controller.php';

add_filter( 'rest_pre_serve_request', __NAMESPACE__ . '\serve_calendar_rest_response', 10, 4 );
add_filter( 'rest_pre_serve_request', __NAMESPACE__ . '\serve_csv_rest_response', 10, 4 );

/**
 * Register REST action routes.
 */
function register_rest_routes(): void {
	$controllers = array(
		new Current_User_Controller(),
		new Events_Controller(),
		new Feedback_Controller(),
		new Group_Suggestions_Controller(),
		new Groups_Controller(),
		new Memberships_Controller(),
		new RSVPs_Controller(),
		new Venues_Controller(),
	);

	foreach ( $controllers as $controller ) {
		$controller->register_routes();
	}
}

/**
 * Serve iCalendar REST responses without JSON encoding.
 *
 * @param bool              $served  Whether the request has already been served.
 * @param \WP_HTTP_Response $result  REST response object.
 * @param \WP_REST_Request  $request Request object.
 * @param \WP_REST_Server   $server  REST server object.
 *
 * @return bool
 */
function serve_calendar_rest_response( bool $served, \WP_HTTP_Response $result, \WP_REST_Request $request, \WP_REST_Server $server ): bool {
	unset( $request, $server );

	$headers = $result->get_headers();

	if ( empty( $headers['X-WPorg-Community-Events-Calendar'] ) ) {
		return $served;
	}

	if ( ! headers_sent() ) {
		header( 'Content-Type: text/calendar; charset=utf-8', true );

		if ( ! empty( $headers['Content-Disposition'] ) ) {
			header( 'Content-Disposition: ' . $headers['Content-Disposition'], true );
		}
	}

	echo (string) $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- iCalendar content is escaped during generation.

	return true;
}

/**
 * Serve CSV REST responses without JSON encoding.
 *
 * @param bool              $served  Whether the request has already been served.
 * @param \WP_HTTP_Response $result  REST response object.
 * @param \WP_REST_Request  $request Request object.
 * @param \WP_REST_Server   $server  REST server object.
 *
 * @return bool
 */
function serve_csv_rest_response( bool $served, \WP_HTTP_Response $result, \WP_REST_Request $request, \WP_REST_Server $server ): bool {
	unset( $request, $server );

	$headers = $result->get_headers();

	if ( empty( $headers['X-WPorg-Community-Events-CSV'] ) ) {
		return $served;
	}

	if ( ! headers_sent() ) {
		header( 'Content-Type: text/csv; charset=utf-8', true );

		if ( ! empty( $headers['Content-Disposition'] ) ) {
			header( 'Content-Disposition: ' . $headers['Content-Disposition'], true );
		}
	}

	echo (string) $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV content is escaped while generated.

	return true;
}

/**
 * Create a reusable community venue.
 *
 * @param int   $user_id WordPress.org user ID.
 * @param array $args    Venue data.
 *
 * @return int|\WP_Error
 */
function create_community_venue( int $user_id, array $args ) {
	$title = trim( (string) ( $args['title'] ?? '' ) );

	if ( '' === $title ) {
		return new \WP_Error( 'wporg_ce_venue_title_required', __( 'Venue title is required.', 'wporg' ) );
	}

	$venue_id = wp_insert_post(
		wp_slash(
			array(
				'post_author'  => $user_id,
				'post_content' => (string) ( $args['description'] ?? '' ),
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_type'    => POST_TYPE_VENUE,
			)
		),
		true
	);

	if ( is_wp_error( $venue_id ) ) {
		return $venue_id;
	}

	update_community_venue_meta( (int) $venue_id, $args );
	set_venue_terms( (int) $venue_id, $args );

	return $venue_id;
}

/**
 * Update a reusable community venue.
 *
 * @param int   $venue_id Venue post ID.
 * @param array $args     Venue data.
 *
 * @return int|\WP_Error
 */
function update_community_venue( int $venue_id, array $args ) {
	$post_update = array( 'ID' => $venue_id );

	if ( array_key_exists( 'title', $args ) ) {
		$title = trim( (string) $args['title'] );

		if ( '' === $title ) {
			return new \WP_Error( 'wporg_ce_venue_title_required', __( 'Venue title is required.', 'wporg' ) );
		}

		$post_update['post_title'] = $title;
	}

	if ( array_key_exists( 'description', $args ) ) {
		$post_update['post_content'] = (string) $args['description'];
	}

	if ( 1 < count( $post_update ) ) {
		$updated_id = wp_update_post( wp_slash( $post_update ), true );

		if ( is_wp_error( $updated_id ) ) {
			return $updated_id;
		}
	}

	update_community_venue_meta( $venue_id, $args );
	set_venue_terms( $venue_id, $args );

	return $venue_id;
}

/**
 * Update venue metadata from request-style data.
 *
 * @param int   $venue_id Venue post ID.
 * @param array $args     Venue data.
 *
 * @return void
 */
function update_community_venue_meta( int $venue_id, array $args ): void {
	$meta_map = array(
		'address'             => 'wporg_ce_address',
		'city'                => 'wporg_ce_city',
		'region'              => 'wporg_ce_region',
		'postal_code'         => 'wporg_ce_postal_code',
		'latitude'            => 'wporg_ce_latitude',
		'longitude'           => 'wporg_ce_longitude',
		'accessibility_notes' => 'wporg_ce_accessibility_notes',
		'online_url'          => 'wporg_ce_online_url',
	);
	$meta     = array();

	foreach ( $meta_map as $request_key => $meta_key ) {
		if ( ! array_key_exists( $request_key, $args ) ) {
			continue;
		}

		$meta[ $meta_key ] = $args[ $request_key ];
	}

	if ( $meta ) {
		update_relationship_meta( $venue_id, $meta );
	}
}

/**
 * Set taxonomy terms for a venue from request-style data.
 *
 * @param int   $venue_id Venue post ID.
 * @param array $args     Venue data.
 *
 * @return void
 */
function set_venue_terms( int $venue_id, array $args ): void {
	if ( ! array_key_exists( 'countries', $args ) ) {
		return;
	}

	$countries = array_filter( array_map( 'sanitize_title', (array) $args['countries'] ) );

	wp_set_object_terms( $venue_id, $countries, TAXONOMY_COUNTRY, false );
}

/**
 * Validate that a user can manage a venue.
 *
 * @param int $venue_id Venue post ID.
 * @param int $user_id  WordPress.org user ID.
 *
 * @return true|\WP_Error
 */
function validate_venue_manager( int $venue_id, int $user_id ) {
	$venue = get_post( $venue_id );
	$user  = get_user_by( 'id', $user_id );

	if ( ! $venue || POST_TYPE_VENUE !== $venue->post_type || 'publish' !== $venue->post_status ) {
		return new \WP_Error( 'wporg_ce_invalid_relationship_target', __( 'Invalid community object.', 'wporg' ) );
	}

	if ( ! $user ) {
		return new \WP_Error( 'wporg_ce_invalid_relationship_user', __( 'Invalid community member.', 'wporg' ) );
	}

	if ( (int) $venue->post_author === $user_id || user_can_moderate_group_organizers( $user_id ) ) {
		return true;
	}

	return new \WP_Error( 'wporg_ce_cannot_manage_venue', __( 'You cannot manage this venue.', 'wporg' ) );
}

/**
 * Determine whether a post is a publicly visible group.
 *
 * @param \WP_Post|null $post Post object.
 *
 * @return bool
 */
function is_public_group_post( ?\WP_Post $post ): bool {
	return $post && POST_TYPE_GROUP === $post->post_type && 'publish' === $post->post_status;
}

/**
 * Determine whether a post is a publicly visible event.
 *
 * @param \WP_Post|null $post Post object.
 *
 * @return bool
 */
function is_public_event_post( ?\WP_Post $post ): bool {
	if ( ! $post || POST_TYPE_EVENT !== $post->post_type || 'publish' !== $post->post_status ) {
		return false;
	}

	return is_public_group_post( get_post( get_event_group_id( $post ) ) );
}

/**
 * Check whether the current user can use self-service relationship routes.
 *
 * @return true|\WP_Error
 */
function can_use_self_service_relationship_route() {
	if ( is_user_logged_in() && current_user_can( 'read' ) ) {
		return true;
	}

	return new \WP_Error(
		'wporg_ce_rest_not_logged_in',
		__( 'You must be logged in to participate in community events.', 'wporg' ),
		array( 'status' => 401 )
	);
}

/**
 * Check whether the current user can moderate group suggestions.
 *
 * @return true|\WP_Error
 */
function can_moderate_group_suggestions_route() {
	if ( current_user_can_moderate_group_suggestions() ) {
		return true;
	}

	return new \WP_Error(
		'wporg_ce_cannot_moderate_group_suggestions',
		__( 'You cannot review group suggestions.', 'wporg' ),
		array( 'status' => 403 )
	);
}

/**
 * Check whether the current user can read a group suggestion.
 *
 * @param \WP_REST_Request $request Request object.
 *
 * @return true|\WP_Error
 */
function can_read_group_suggestion_route( \WP_REST_Request $request ) {
	if ( current_user_can_moderate_group_suggestions() ) {
		return true;
	}

	if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
		return new \WP_Error(
			'wporg_ce_rest_not_logged_in',
			__( 'You must be logged in to participate in community events.', 'wporg' ),
			array( 'status' => 401 )
		);
	}

	$suggestion = get_post( (int) $request['suggestion_id'] );

	if ( $suggestion && POST_TYPE_GROUP_SUGGESTION === $suggestion->post_type && get_current_user_id() === (int) $suggestion->post_author ) {
		return true;
	}

	return new \WP_Error(
		'wporg_ce_cannot_read_group_suggestion',
		__( 'You cannot view this group suggestion.', 'wporg' ),
		array( 'status' => 403 )
	);
}

/**
 * Get a group suggestion collection query.
 *
 * @param int    $author_id     Optional author user ID.
 * @param string $review_status Review status filter.
 * @param int    $page          Results page.
 * @param int    $per_page      Maximum suggestions to return.
 *
 * @return \WP_Query
 */
function get_group_suggestion_collection_query( int $author_id, string $review_status, int $page, int $per_page ): \WP_Query {
	$query_args = array(
		'fields'                 => 'ids',
		'orderby'                => 'date',
		'order'                  => 'DESC',
		'paged'                  => max( 1, $page ),
		'post_status'            => array( 'pending', 'publish', 'draft' ),
		'post_type'              => POST_TYPE_GROUP_SUGGESTION,
		'posts_per_page'         => max( 1, $per_page ),
		'update_post_meta_cache' => true,
		'update_post_term_cache' => true,
	);

	if ( $author_id ) {
		$query_args['author'] = $author_id;
	}

	if ( 'all' !== $review_status ) {
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Moderation queue filters by registered review status meta.
		$query_args['meta_query'] = array(
			array(
				'key'   => 'wporg_ce_review_status',
				'value' => $review_status,
			),
		);
	}

	return new \WP_Query( $query_args );
}

/**
 * Get a paginated public event collection query.
 *
 * @param int    $group_id  Optional group post ID. Zero returns events across all groups.
 * @param string $timeframe Event timeframe.
 * @param int    $page      Results page.
 * @param int    $per_page  Maximum number of events.
 * @param array  $filters   Event collection filters.
 *
 * @return \WP_Query
 */
function get_public_event_collection_query( int $group_id, string $timeframe, int $page, int $per_page, array $filters = array() ): \WP_Query {
	global $wpdb;

	$query_args = array(
		'fields'                 => 'ids',
		'meta_key'               => 'wporg_ce_start_utc', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Event directory sorting uses registered event start meta.
		'orderby'                => 'meta_value',
		'order'                  => 'ASC',
		'paged'                  => max( 1, $page ),
		'post_type'              => POST_TYPE_EVENT,
		'post_status'            => 'publish',
		'posts_per_page'         => max( 1, $per_page ),
		'suppress_filters'       => false,
		'update_post_meta_cache' => true,
		'update_post_term_cache' => false,
	);

	if ( $group_id ) {
		$query_args['post_parent'] = $group_id;
	} else {
		$query_args['wporg_ce_require_public_parent_group'] = true;
	}

	// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Event directory filtering uses registered event start meta.
	$query_args['meta_query'] = get_event_collection_timeframe_meta_query( $timeframe );
	$tax_query                = get_event_collection_tax_query( $filters );

	if ( $tax_query ) {
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Event directory filters intentionally use registered taxonomies.
		$query_args['tax_query'] = $tax_query;
	}

	$public_parent_group_filter = static function ( array $clauses, \WP_Query $query ) use ( $wpdb ): array {
		if ( ! $query->get( 'wporg_ce_require_public_parent_group' ) ) {
			return $clauses;
		}

		$clauses['join']  .= " INNER JOIN {$wpdb->posts} wporg_ce_parent_group ON {$wpdb->posts}.post_parent = wporg_ce_parent_group.ID";
		$clauses['where'] .= $wpdb->prepare(
			' AND wporg_ce_parent_group.post_type = %s AND wporg_ce_parent_group.post_status = %s',
			POST_TYPE_GROUP,
			'publish'
		);

		return $clauses;
	};

	add_filter( 'posts_clauses', $public_parent_group_filter, 10, 2 );

	try {
		return new \WP_Query( $query_args );
	} finally {
		remove_filter( 'posts_clauses', $public_parent_group_filter, 10 );
	}
}

/**
 * Build event start meta filters for an event collection.
 *
 * @param string $timeframe Event timeframe.
 *
 * @return array
 */
function get_event_collection_timeframe_meta_query( string $timeframe ): array {
	if ( 'all' === $timeframe ) {
		return array(
			array(
				'key'     => 'wporg_ce_start_utc',
				'compare' => 'EXISTS',
			),
		);
	}

	return array(
		array(
			'key'     => 'wporg_ce_start_utc',
			'value'   => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'compare' => 'past' === $timeframe ? '<' : '>=',
			'type'    => 'CHAR',
		),
	);
}

/**
 * Extract event collection filters from a REST request.
 *
 * @param \WP_REST_Request $request Request object.
 *
 * @return array
 */
function get_event_collection_filter_args( \WP_REST_Request $request ): array {
	$filters = array();

	foreach ( get_event_collection_taxonomy_filter_map() as $request_key => $taxonomy ) {
		$filters[ $request_key ] = (string) $request[ $request_key ];
	}

	return $filters;
}

/**
 * Build a taxonomy query for public event directory filters.
 *
 * @param array $filters Event collection filters.
 *
 * @return array
 */
function get_event_collection_tax_query( array $filters ): array {
	$tax_query = array();

	foreach ( get_event_collection_taxonomy_filter_map() as $request_key => $taxonomy ) {
		$slug = trim( (string) ( $filters[ $request_key ] ?? '' ) );

		if ( '' === $slug ) {
			continue;
		}

		$tax_query[] = array(
			'taxonomy' => $taxonomy,
			'field'    => 'slug',
			'terms'    => array( $slug ),
		);
	}

	if ( 1 < count( $tax_query ) ) {
		$tax_query['relation'] = 'AND';
	}

	return $tax_query;
}

/**
 * Map event collection filter keys to taxonomies.
 *
 * @return array
 */
function get_event_collection_taxonomy_filter_map(): array {
	return array(
		'country'      => TAXONOMY_COUNTRY,
		'event_format' => TAXONOMY_EVENT_FORMAT,
		'event_type'   => TAXONOMY_EVENT_TYPE,
		'language'     => TAXONOMY_LANGUAGE,
		'topic'        => TAXONOMY_TOPIC,
	);
}

/**
 * Determine whether an event matches a timeframe.
 *
 * @param int    $event_id  Event post ID.
 * @param string $timeframe Event timeframe.
 *
 * @return bool
 */
function event_matches_timeframe( int $event_id, string $timeframe ): bool {
	if ( 'all' === $timeframe ) {
		return true;
	}

	$timestamp = event_start_timestamp( $event_id );

	if ( ! $timestamp ) {
		return false;
	}

	$now = time();

	if ( 'past' === $timeframe ) {
		return $timestamp < $now;
	}

	return $timestamp >= $now;
}

/**
 * Compare event IDs by start time.
 *
 * @param int $first_event_id  First event post ID.
 * @param int $second_event_id Second event post ID.
 *
 * @return int
 */
function compare_event_ids_by_start_time( int $first_event_id, int $second_event_id ): int {
	$first_time  = event_start_timestamp( $first_event_id );
	$second_time = event_start_timestamp( $second_event_id );

	return $first_time <=> $second_time;
}

/**
 * Get an event start timestamp.
 *
 * @param int $event_id Event post ID.
 *
 * @return int
 */
function event_start_timestamp( int $event_id ): int {
	$start = get_post_meta( $event_id, 'wporg_ce_start_utc', true );

	if ( ! is_string( $start ) || '' === $start ) {
		return 0;
	}

	$timestamp = strtotime( $start );

	if ( false === $timestamp ) {
		return 0;
	}

	return $timestamp;
}

/**
 * Prepare a calendar REST response.
 *
 * @param string $calendar iCalendar content.
 * @param string $filename Download filename.
 *
 * @return \WP_REST_Response
 */
function prepare_calendar_rest_response( string $calendar, string $filename ): \WP_REST_Response {
	$response = rest_ensure_response( $calendar );
	$filename = sanitize_file_name( $filename );

	if ( '' === $filename ) {
		$filename = 'calendar.ics';
	}

	$response->header( 'Content-Type', 'text/calendar; charset=utf-8' );
	$response->header( 'Content-Disposition', sprintf( 'inline; filename="%s"', str_replace( '"', '', $filename ) ) );
	$response->header( 'X-WPorg-Community-Events-Calendar', '1' );

	return $response;
}

/**
 * Prepare a CSV REST response.
 *
 * @param string $csv      CSV content.
 * @param string $filename Download filename.
 *
 * @return \WP_REST_Response
 */
function prepare_csv_rest_response( string $csv, string $filename ): \WP_REST_Response {
	$response = rest_ensure_response( $csv );
	$filename = sanitize_file_name( $filename );

	if ( '' === $filename ) {
		$filename = 'export.csv';
	}

	$response->header( 'Content-Type', 'text/csv; charset=utf-8' );
	$response->header( 'Content-Disposition', sprintf( 'attachment; filename="%s"', str_replace( '"', '', $filename ) ) );
	$response->header( 'X-WPorg-Community-Events-CSV', '1' );

	return $response;
}

/**
 * Build an iCalendar document for event IDs.
 *
 * @param int[]  $event_ids   Event post IDs.
 * @param string $name        Calendar name.
 * @param string $description Calendar description.
 *
 * @return string
 */
function get_events_calendar( array $event_ids, string $name, string $description = '' ): string {
	$lines = array(
		'BEGIN:VCALENDAR',
		'VERSION:2.0',
		'PRODID:-//WordPress.org//Community Events//EN',
		'CALSCALE:GREGORIAN',
		'METHOD:PUBLISH',
		'X-WR-CALNAME:' . escape_calendar_text( $name ),
	);

	if ( '' !== trim( $description ) ) {
		$lines[] = 'X-WR-CALDESC:' . escape_calendar_text( $description );
	}

	foreach ( $event_ids as $event_id ) {
		$lines = array_merge( $lines, get_event_calendar_lines( (int) $event_id ) );
	}

	$lines[] = 'END:VCALENDAR';

	return get_calendar_content( $lines );
}

/**
 * Build iCalendar lines for one event.
 *
 * @param int $event_id Event post ID.
 *
 * @return string[]
 */
function get_event_calendar_lines( int $event_id ): array {
	$event = get_post( $event_id );

	if ( ! is_public_event_post( $event ) ) {
		return array();
	}

	$start = get_calendar_timestamp( (string) get_post_meta( $event_id, 'wporg_ce_start_utc', true ) );

	if ( '' === $start ) {
		return array();
	}

	$end         = get_calendar_timestamp( (string) get_post_meta( $event_id, 'wporg_ce_end_utc', true ) );
	$created     = get_calendar_timestamp( $event->post_date_gmt );
	$description = get_event_calendar_description( $event_id );
	$modified    = get_calendar_timestamp( $event->post_modified_gmt );
	$categories  = get_event_calendar_categories( $event_id );
	$location    = get_event_calendar_location( $event_id );
	$lines       = array(
		'BEGIN:VEVENT',
		'UID:wporg-community-event-' . $event_id . '@wordpress.org',
		'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
		'DTSTART:' . $start,
	);

	if ( '' !== $end ) {
		$lines[] = 'DTEND:' . $end;
	}

	if ( '' !== $created ) {
		$lines[] = 'CREATED:' . $created;
	}

	if ( '' !== $modified ) {
		$lines[] = 'LAST-MODIFIED:' . $modified;
	}

	$lines[] = 'SUMMARY:' . escape_calendar_text( get_the_title( $event ) );

	if ( '' !== $description ) {
		$lines[] = 'DESCRIPTION:' . escape_calendar_text( $description );
	}

	if ( '' !== $location ) {
		$lines[] = 'LOCATION:' . escape_calendar_text( $location );
	}

	$lines[] = 'URL:' . esc_url_raw( (string) get_permalink( $event ) );
	$lines[] = event_is_canceled( $event_id ) ? 'STATUS:CANCELLED' : 'STATUS:CONFIRMED';

	if ( $categories ) {
		$lines[] = 'CATEGORIES:' . implode( ',', array_map( __NAMESPACE__ . '\escape_calendar_text', $categories ) );
	}

	$lines[] = 'END:VEVENT';

	return $lines;
}

/**
 * Get a timestamp formatted for iCalendar.
 *
 * @param string $date Date string.
 *
 * @return string
 */
function get_calendar_timestamp( string $date ): string {
	$date = trim( $date );

	if ( '' === $date || '0000-00-00 00:00:00' === $date ) {
		return '';
	}

	if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date ) ) {
		$date .= ' UTC';
	}

	$timestamp = strtotime( $date );

	if ( false === $timestamp ) {
		return '';
	}

	return gmdate( 'Ymd\THis\Z', $timestamp );
}

/**
 * Get an event description for calendar clients.
 *
 * @param int $event_id Event post ID.
 *
 * @return string
 */
function get_event_calendar_description( int $event_id ): string {
	$event       = get_post( $event_id );
	$description = '';
	$online_url  = (string) get_post_meta( $event_id, 'wporg_ce_online_url', true );

	if ( $event ) {
		$description = '' !== $event->post_content ? $event->post_content : $event->post_excerpt;
	}

	if ( '' !== $online_url ) {
		$description = trim(
			$description . "\n\n" . sprintf(
				/* translators: %s: online event URL. */
				__( 'Online event: %s', 'wporg' ),
				$online_url
			)
		);
	}

	return wp_strip_all_tags( $description );
}

/**
 * Get an event location for calendar clients.
 *
 * @param int $event_id Event post ID.
 *
 * @return string
 */
function get_event_calendar_location( int $event_id ): string {
	$venue_id = (int) get_post_meta( $event_id, 'wporg_ce_venue_id', true );

	if ( $venue_id && 'publish' === get_post_status( $venue_id ) ) {
		return implode(
			', ',
			array_filter(
				array(
					get_the_title( $venue_id ),
					get_post_meta( $venue_id, 'wporg_ce_address', true ),
					trim(
						implode(
							' ',
							array_filter(
								array(
									get_post_meta( $venue_id, 'wporg_ce_postal_code', true ),
									get_post_meta( $venue_id, 'wporg_ce_city', true ),
								)
							)
						)
					),
					get_post_meta( $venue_id, 'wporg_ce_region', true ),
				)
			)
		);
	}

	if ( '' !== get_post_meta( $event_id, 'wporg_ce_online_url', true ) ) {
		return __( 'Online', 'wporg' );
	}

	return '';
}

/**
 * Get event category labels for calendar clients.
 *
 * @param int $event_id Event post ID.
 *
 * @return string[]
 */
function get_event_calendar_categories( int $event_id ): array {
	$categories = array();

	foreach ( array( TAXONOMY_EVENT_TYPE, TAXONOMY_EVENT_FORMAT, TAXONOMY_TOPIC ) as $taxonomy ) {
		$terms = get_the_terms( $event_id, $taxonomy );

		if ( ! $terms || is_wp_error( $terms ) ) {
			continue;
		}

		foreach ( $terms as $term ) {
			$categories[] = $term->name;
		}
	}

	return array_values( array_unique( $categories ) );
}

/**
 * Escape plain text for iCalendar text values.
 *
 * @param string $text Text value.
 *
 * @return string
 */
function escape_calendar_text( string $text ): string {
	$charset = get_bloginfo( 'charset' );

	if ( '' === $charset ) {
		$charset = 'UTF-8';
	}

	$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, $charset );

	return str_replace(
		array( '\\', "\r\n", "\r", "\n", ';', ',' ),
		array( '\\\\', '\n', '\n', '\n', '\;', '\,' ),
		$text
	);
}

/**
 * Join iCalendar lines with CRLF endings.
 *
 * @param string[] $lines iCalendar lines.
 *
 * @return string
 */
function get_calendar_content( array $lines ): string {
	return implode(
		"\r\n",
		array_filter(
			$lines,
			static function ( string $line ): bool {
				return '' !== $line;
			}
		)
	) . "\r\n";
}

/**
 * Get a calendar filename for a post.
 *
 * @param int    $post_id Post ID.
 * @param string $prefix  Fallback filename prefix.
 *
 * @return string
 */
function get_calendar_filename( int $post_id, string $prefix ): string {
	$post = get_post( $post_id );
	$slug = $post && '' !== $post->post_name ? $post->post_name : "{$prefix}-{$post_id}";

	return sanitize_file_name( sanitize_title( $slug ) . '.ics' );
}

/**
 * Build a taxonomy query for public group directory filters.
 *
 * @param \WP_REST_Request $request Request object.
 *
 * @return array
 */
function get_group_collection_tax_query( \WP_REST_Request $request ): array {
	$taxonomies = array(
		'country'    => TAXONOMY_COUNTRY,
		'group_type' => TAXONOMY_GROUP_TYPE,
		'language'   => TAXONOMY_LANGUAGE,
		'topic'      => TAXONOMY_TOPIC,
	);
	$tax_query  = array();

	foreach ( $taxonomies as $request_key => $taxonomy ) {
		$slug = (string) $request[ $request_key ];

		if ( '' === $slug ) {
			continue;
		}

		$tax_query[] = array(
			'taxonomy' => $taxonomy,
			'field'    => 'slug',
			'terms'    => array( $slug ),
		);
	}

	if ( 1 < count( $tax_query ) ) {
		$tax_query['relation'] = 'AND';
	}

	return $tax_query;
}

/**
 * Get public organizer membership IDs for a group.
 *
 * @param int    $group_id Group post ID.
 * @param string $role     Role filter.
 * @param int    $per_page Maximum number of organizers.
 *
 * @return int[]
 */
function get_public_group_organizer_membership_ids( int $group_id, string $role, int $per_page ): array {
	$query = new \WP_Query(
		array(
			'fields'                 => 'ids',
			'post_type'              => POST_TYPE_MEMBERSHIP,
			'post_status'            => array( 'publish', 'private', 'pending', 'draft' ),
			'post_parent'            => $group_id,
			'posts_per_page'         => -1,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		)
	);

	$membership_ids = array_filter(
		array_map( 'intval', $query->posts ),
		static function ( int $membership_id ) use ( $role ): bool {
			return membership_is_public_group_organizer( $membership_id, $role );
		}
	);

	return array_slice( $membership_ids, 0, $per_page );
}

/**
 * Get public active group member membership IDs.
 *
 * @param int    $group_id Group post ID.
 * @param string $role     Role filter.
 * @param int    $per_page Maximum number of members.
 *
 * @return int[]
 */
function get_public_group_member_membership_ids( int $group_id, string $role, int $per_page ): array {
	$query = new \WP_Query(
		array(
			'fields'                 => 'ids',
			'post_type'              => POST_TYPE_MEMBERSHIP,
			'post_status'            => array( 'publish', 'private', 'pending', 'draft' ),
			'post_parent'            => $group_id,
			'posts_per_page'         => -1,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		)
	);

	$membership_ids = array_filter(
		array_map( 'intval', $query->posts ),
		static function ( int $membership_id ) use ( $role ): bool {
			return membership_is_public_group_member( $membership_id, $role );
		}
	);

	return array_slice( $membership_ids, 0, $per_page );
}

/**
 * Determine whether a membership can be shown in public member lists.
 *
 * @param int    $membership_id Membership post ID.
 * @param string $requested_role Role filter.
 *
 * @return bool
 */
function membership_is_public_group_member( int $membership_id, string $requested_role ): bool {
	$role = get_post_meta( $membership_id, 'wporg_ce_role', true );

	if ( 'all' !== $requested_role && $role !== $requested_role ) {
		return false;
	}

	if ( MEMBERSHIP_STATUS_ACTIVE !== get_post_meta( $membership_id, 'wporg_ce_status', true ) ) {
		return false;
	}

	return RELATIONSHIP_VISIBILITY_PRIVATE !== get_post_meta( $membership_id, 'wporg_ce_visibility', true );
}

/**
 * Determine whether a membership can be shown in public organizer lists.
 *
 * @param int    $membership_id Membership post ID.
 * @param string $requested_role Role filter.
 *
 * @return bool
 */
function membership_is_public_group_organizer( int $membership_id, string $requested_role ): bool {
	$role = get_post_meta( $membership_id, 'wporg_ce_role', true );

	if ( ! in_array( $role, array( MEMBERSHIP_ROLE_ORGANIZER, MEMBERSHIP_ROLE_HOST ), true ) ) {
		return false;
	}

	if ( 'all' !== $requested_role && $role !== $requested_role ) {
		return false;
	}

	if ( MEMBERSHIP_STATUS_ACTIVE !== get_post_meta( $membership_id, 'wporg_ce_status', true ) ) {
		return false;
	}

	return RELATIONSHIP_VISIBILITY_PRIVATE !== get_post_meta( $membership_id, 'wporg_ce_visibility', true );
}

/**
 * Get membership IDs for the current user dashboard.
 *
 * @param int    $user_id  User ID.
 * @param string $role     Role filter.
 * @param string $status   Status filter.
 * @param int    $per_page Maximum number of memberships.
 *
 * @return int[]
 */
function get_current_user_membership_ids( int $user_id, string $role, string $status, int $per_page ): array {
	$query = new \WP_Query(
		array(
			'fields'                 => 'ids',
			'post_type'              => POST_TYPE_MEMBERSHIP,
			'post_status'            => array( 'publish', 'private', 'pending', 'draft' ),
			'author'                 => $user_id,
			'posts_per_page'         => -1,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		)
	);

	$membership_ids = array_filter(
		array_map( 'intval', $query->posts ),
		static function ( int $membership_id ) use ( $role, $status ): bool {
			return membership_matches_current_user_collection_filters( $membership_id, $role, $status );
		}
	);

	return array_slice( $membership_ids, 0, $per_page );
}

/**
 * Determine whether a membership matches current-user dashboard filters.
 *
 * @param int    $membership_id Membership post ID.
 * @param string $requested_role Role filter.
 * @param string $requested_status Status filter.
 *
 * @return bool
 */
function membership_matches_current_user_collection_filters( int $membership_id, string $requested_role, string $requested_status ): bool {
	$role   = get_post_meta( $membership_id, 'wporg_ce_role', true );
	$status = get_post_meta( $membership_id, 'wporg_ce_status', true );

	if ( 'all' !== $requested_role && $role !== $requested_role ) {
		return false;
	}

	return 'all' === $requested_status || $status === $requested_status;
}

/**
 * Get RSVP IDs for the current user dashboard.
 *
 * @param int    $user_id   User ID.
 * @param string $status    RSVP status filter.
 * @param string $timeframe Event timeframe.
 * @param int    $per_page  Maximum number of RSVPs.
 *
 * @return int[]
 */
function get_current_user_rsvp_ids( int $user_id, string $status, string $timeframe, int $per_page ): array {
	$query = new \WP_Query(
		array(
			'fields'                 => 'ids',
			'post_type'              => POST_TYPE_RSVP,
			'post_status'            => array( 'publish', 'private', 'pending', 'draft' ),
			'author'                 => $user_id,
			'posts_per_page'         => -1,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		)
	);

	$rsvp_ids = array_filter(
		array_map( 'intval', $query->posts ),
		static function ( int $rsvp_id ) use ( $status, $timeframe ): bool {
			return rsvp_matches_current_user_collection_filters( $rsvp_id, $status, $timeframe );
		}
	);

	usort( $rsvp_ids, __NAMESPACE__ . '\compare_rsvp_ids_by_event_start_time' );

	return array_slice( $rsvp_ids, 0, $per_page );
}

/**
 * Determine whether an RSVP matches current-user dashboard filters.
 *
 * @param int    $rsvp_id RSVP post ID.
 * @param string $requested_status RSVP status filter.
 * @param string $timeframe Event timeframe.
 *
 * @return bool
 */
function rsvp_matches_current_user_collection_filters( int $rsvp_id, string $requested_status, string $timeframe ): bool {
	$status = get_post_meta( $rsvp_id, 'wporg_ce_status', true );

	if ( 'active' === $requested_status && ! in_array( $status, array( RSVP_STATUS_ATTENDING, RSVP_STATUS_WAITLISTED ), true ) ) {
		return false;
	}

	if ( ! in_array( $requested_status, array( 'active', 'all' ), true ) && $status !== $requested_status ) {
		return false;
	}

	$event_id = (int) get_post_meta( $rsvp_id, 'wporg_ce_event_id', true );
	$event    = get_post( $event_id );

	return $event && POST_TYPE_EVENT === $event->post_type && 'publish' === $event->post_status && event_matches_timeframe( $event_id, $timeframe );
}

/**
 * Compare RSVP IDs by related event start time.
 *
 * @param int $first_rsvp_id  First RSVP post ID.
 * @param int $second_rsvp_id Second RSVP post ID.
 *
 * @return int
 */
function compare_rsvp_ids_by_event_start_time( int $first_rsvp_id, int $second_rsvp_id ): int {
	$first_event_id  = (int) get_post_meta( $first_rsvp_id, 'wporg_ce_event_id', true );
	$second_event_id = (int) get_post_meta( $second_rsvp_id, 'wporg_ce_event_id', true );
	$comparison      = compare_event_ids_by_start_time( $first_event_id, $second_event_id );

	if ( 0 !== $comparison ) {
		return $comparison;
	}

	return $first_rsvp_id <=> $second_rsvp_id;
}

/**
 * Convert relationship-layer validation errors to REST responses.
 *
 * @param \WP_Error $error Relationship-layer error.
 *
 * @return \WP_Error
 */
function rest_convert_relationship_error_to_response( \WP_Error $error ): \WP_Error {
	$status = 404;

	if ( 'wporg_ce_invalid_relationship_user' === $error->get_error_code() ) {
		$status = 401;
	} elseif (
		in_array(
			$error->get_error_code(),
			array(
				'wporg_ce_not_group_member',
				'wporg_ce_cannot_create_event',
				'wporg_ce_cannot_manage_group_profile',
				'wporg_ce_cannot_manage_group_organizers',
				'wporg_ce_cannot_moderate_event',
				'wporg_ce_cannot_feedback_own_event',
				'wporg_ce_cannot_manage_event',
				'wporg_ce_cannot_moderate_group_suggestions',
				'wporg_ce_cannot_read_group_suggestion',
				'wporg_ce_event_feedback_attendee_required',
			),
			true
		)
	) {
		$status = 403;
	} elseif (
		in_array(
			$error->get_error_code(),
			array(
				'wporg_ce_event_canceled',
				'wporg_ce_event_datetime_invalid',
				'wporg_ce_event_end_before_start',
				'wporg_ce_event_feedback_not_open',
				'wporg_ce_event_feedback_rating_invalid',
				'wporg_ce_event_feedback_review_required',
				'wporg_ce_event_host_required',
				'wporg_ce_event_message_body_required',
				'wporg_ce_event_message_no_recipients',
				'wporg_ce_event_message_subject_required',
				'wporg_ce_event_not_cancelable',
				'wporg_ce_event_title_required',
				'wporg_ce_event_start_required',
				'wporg_ce_group_member_required',
				'wporg_ce_group_suggestion_location_required',
				'wporg_ce_group_suggestion_title_required',
				'wporg_ce_invalid_event_attendee',
				'wporg_ce_invalid_event_host',
				'wporg_ce_invalid_group_suggestion_status',
				'wporg_ce_rsvp_answer_required',
				'wporg_ce_rsvp_closed',
				'wporg_ce_venue_title_required',
			),
			true
		)
	) {
		$status = 400;
	} elseif ( 'wporg_ce_event_feedback_exists' === $error->get_error_code() ) {
		$status = 409;
	} elseif (
		in_array(
			$error->get_error_code(),
			array(
				'wporg_ce_cannot_create_venue',
				'wporg_ce_cannot_manage_venue',
			),
			true
		)
	) {
		$status = 403;
	}

	$error->add_data( array( 'status' => $status ) );

	return $error;
}

/**
 * Request schema for membership writes.
 *
 * @return array
 */
function get_membership_rest_args(): array {
	$notification_preferences_arg = get_notification_preferences_schema();

	unset( $notification_preferences_arg['default'] );

	foreach ( array_keys( $notification_preferences_arg['properties'] ) as $preference_key ) {
		unset( $notification_preferences_arg['properties'][ $preference_key ]['default'] );
	}

	$notification_preferences_arg['description'] = 'Group notification preferences for the current user.';
	$notification_preferences_arg['required']    = false;

	return array(
		'visibility'               => get_visibility_rest_arg(),
		'notification_preferences' => $notification_preferences_arg,
	);
}

/**
 * Request schema for current-user memberships.
 *
 * @return array
 */
function get_current_user_memberships_collection_rest_args(): array {
	return array(
		'role'     => array(
			'description' => 'Membership role to return.',
			'type'        => 'string',
			'enum'        => array_merge( array( 'all' ), get_membership_roles() ),
			'default'     => 'all',
		),
		'status'   => array(
			'description' => 'Membership status to return.',
			'type'        => 'string',
			'enum'        => array_merge( array( 'all' ), get_membership_statuses() ),
			'default'     => MEMBERSHIP_STATUS_ACTIVE,
		),
		'per_page' => array(
			'description' => 'Maximum number of memberships to return.',
			'type'        => 'integer',
			'minimum'     => 1,
			'maximum'     => 100,
			'default'     => 20,
		),
	);
}

/**
 * Request schema for current-user RSVPs.
 *
 * @return array
 */
function get_current_user_rsvps_collection_rest_args(): array {
	return array(
		'status'    => array(
			'description' => 'RSVP status to return.',
			'type'        => 'string',
			'enum'        => array( 'active', 'all', RSVP_STATUS_ATTENDING, RSVP_STATUS_WAITLISTED, RSVP_STATUS_NOT_ATTENDING ),
			'default'     => 'active',
		),
		'timeframe' => array(
			'description' => 'Event timeframe.',
			'type'        => 'string',
			'enum'        => array( 'upcoming', 'past', 'all' ),
			'default'     => 'upcoming',
		),
		'per_page'  => array(
			'description' => 'Maximum number of RSVPs to return.',
			'type'        => 'integer',
			'minimum'     => 1,
			'maximum'     => 100,
			'default'     => 20,
		),
	);
}

/**
 * Request schema for current-user group suggestions.
 *
 * @return array
 */
function get_current_user_group_suggestions_collection_rest_args(): array {
	return get_group_suggestions_collection_rest_args();
}

/**
 * Request schema for group suggestion collections.
 *
 * @return array
 */
function get_group_suggestions_collection_rest_args(): array {
	return array(
		'review_status' => array(
			'description' => 'Group suggestion review status.',
			'type'        => 'string',
			'enum'        => array_merge( array( 'all' ), get_group_suggestion_review_statuses() ),
			'default'     => 'all',
		),
		'page'          => array(
			'description' => 'Results page.',
			'type'        => 'integer',
			'minimum'     => 1,
			'default'     => 1,
		),
		'per_page'      => array(
			'description' => 'Maximum number of group suggestions to return.',
			'type'        => 'integer',
			'minimum'     => 1,
			'maximum'     => 100,
			'default'     => 20,
		),
	);
}

/**
 * Request schema for creating a group suggestion.
 *
 * @return array
 */
function get_group_suggestion_rest_args(): array {
	return array_merge(
		get_group_suggestion_content_rest_args(),
		get_group_suggestion_taxonomy_rest_args()
	);
}

/**
 * Request schema for updating a group suggestion review.
 *
 * @return array
 */
function get_group_suggestion_update_rest_args(): array {
	return array(
		'duplicate_group_id' => array(
			'description' => 'Existing group ID when this suggestion duplicates another group.',
			'type'        => 'integer',
			'minimum'     => 0,
			'default'     => 0,
		),
		'review_note'        => array(
			'description' => 'Community Team review note.',
			'type'        => 'string',
			'default'     => '',
		),
		'review_status'      => array(
			'description' => 'Group suggestion review status.',
			'type'        => 'string',
			'enum'        => get_group_suggestion_review_statuses(),
			'required'    => true,
		),
	);
}

/**
 * Request schema for group suggestion content fields.
 *
 * @return array
 */
function get_group_suggestion_content_rest_args(): array {
	return array(
		'title'          => array(
			'description' => 'Suggested group name.',
			'type'        => 'string',
			'minLength'   => 1,
			'required'    => true,
		),
		'description'    => array(
			'description' => 'Suggested group description or rationale.',
			'type'        => 'string',
			'default'     => '',
		),
		'excerpt'        => array(
			'description' => 'Short suggested group summary.',
			'type'        => 'string',
			'default'     => '',
		),
		'location_label' => array(
			'description' => 'Human-readable suggested group location.',
			'type'        => 'string',
			'minLength'   => 1,
			'required'    => true,
		),
		'website_url'    => array(
			'description' => 'Third-party suggested group website URL.',
			'type'        => 'string',
			'format'      => 'uri',
			'default'     => '',
		),
		'city'           => array(
			'description' => 'Suggested group city.',
			'type'        => 'string',
			'default'     => '',
		),
		'region'         => array(
			'description' => 'Suggested group region.',
			'type'        => 'string',
			'default'     => '',
		),
		'timezone'       => array(
			'description' => 'Suggested group timezone.',
			'type'        => 'string',
			'default'     => '',
		),
	);
}

/**
 * Request schema for group suggestion taxonomy fields.
 *
 * @return array
 */
function get_group_suggestion_taxonomy_rest_args(): array {
	$args = array();

	foreach ( get_group_suggestion_taxonomy_request_map() as $request_key => $taxonomy ) {
		$args[ $request_key ] = array(
			'description' => "Term slugs for {$taxonomy}.",
			'type'        => 'array',
			'default'     => array(),
			'items'       => array(
				'type' => 'string',
			),
		);
	}

	return $args;
}

/**
 * Request schema for group profile updates.
 *
 * @return array
 */
function get_group_update_rest_args(): array {
	return array(
		'website_url' => array(
			'description' => 'Third-party group website URL.',
			'type'        => 'string',
			'format'      => 'uri',
		),
	);
}

/**
 * Request schema for public group directory results.
 *
 * @return array
 */
function get_groups_collection_rest_args(): array {
	return array(
		'country'    => array(
			'description' => 'Country term slug.',
			'type'        => 'string',
			'default'     => '',
		),
		'group_type' => array(
			'description' => 'Group type term slug.',
			'type'        => 'string',
			'default'     => '',
		),
		'language'   => array(
			'description' => 'Language term slug.',
			'type'        => 'string',
			'default'     => '',
		),
		'topic'      => array(
			'description' => 'Topic term slug.',
			'type'        => 'string',
			'default'     => '',
		),
		'search'     => array(
			'description' => 'Search term.',
			'type'        => 'string',
			'default'     => '',
		),
		'page'       => array(
			'description' => 'Results page.',
			'type'        => 'integer',
			'minimum'     => 1,
			'default'     => 1,
		),
		'per_page'   => array(
			'description' => 'Maximum number of groups to return.',
			'type'        => 'integer',
			'minimum'     => 1,
			'maximum'     => 100,
			'default'     => 20,
		),
	);
}

/**
 * Request schema for public venue directory results.
 *
 * @return array
 */
function get_venues_collection_rest_args(): array {
	return array(
		'city'     => array(
			'description' => 'Venue city.',
			'type'        => 'string',
			'default'     => '',
		),
		'country'  => array(
			'description' => 'Country term slug.',
			'type'        => 'string',
			'default'     => '',
		),
		'search'   => array(
			'description' => 'Search term.',
			'type'        => 'string',
			'default'     => '',
		),
		'page'     => array(
			'description' => 'Results page.',
			'type'        => 'integer',
			'minimum'     => 1,
			'default'     => 1,
		),
		'per_page' => array(
			'description' => 'Maximum number of venues to return.',
			'type'        => 'integer',
			'minimum'     => 1,
			'maximum'     => 100,
			'default'     => 20,
		),
	);
}

/**
 * Request schema for venue writes.
 *
 * @param bool $require_group_id Whether a group ID is required for permission checks.
 *
 * @return array
 */
function get_venue_rest_args( bool $require_group_id ): array {
	$args = array(
		'title'               => array(
			'description' => 'Venue title.',
			'type'        => 'string',
			'minLength'   => 1,
			'required'    => $require_group_id,
		),
		'description'         => array(
			'description' => 'Venue description.',
			'type'        => 'string',
			'default'     => '',
		),
		'address'             => array(
			'description' => 'Street address.',
			'type'        => 'string',
			'default'     => '',
		),
		'city'                => array(
			'description' => 'Venue city.',
			'type'        => 'string',
			'default'     => '',
		),
		'region'              => array(
			'description' => 'Venue region.',
			'type'        => 'string',
			'default'     => '',
		),
		'postal_code'         => array(
			'description' => 'Postal code.',
			'type'        => 'string',
			'default'     => '',
		),
		'latitude'            => array(
			'description' => 'Venue latitude.',
			'type'        => 'number',
			'default'     => 0,
		),
		'longitude'           => array(
			'description' => 'Venue longitude.',
			'type'        => 'number',
			'default'     => 0,
		),
		'accessibility_notes' => array(
			'description' => 'Accessibility notes.',
			'type'        => 'string',
			'default'     => '',
		),
		'online_url'          => array(
			'description' => 'Online venue URL.',
			'type'        => 'string',
			'format'      => 'uri',
			'default'     => '',
		),
		'countries'           => array(
			'description' => 'Country term slugs.',
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
			'default'     => array(),
		),
	);

	if ( $require_group_id ) {
		$args['group_id'] = array(
			'description' => 'Community group ID used for venue creation permissions.',
			'type'        => 'integer',
			'minimum'     => 1,
			'required'    => true,
		);
	}

	if ( ! $require_group_id ) {
		foreach ( array_keys( $args ) as $key ) {
			unset( $args[ $key ]['default'], $args[ $key ]['required'] );
		}
	}

	return $args;
}

/**
 * Request schema for public group organizers.
 *
 * @return array
 */
function get_group_organizers_collection_rest_args(): array {
	return array(
		'role'     => array(
			'description' => 'Organizer role to return.',
			'type'        => 'string',
			'enum'        => array( 'all', MEMBERSHIP_ROLE_ORGANIZER, MEMBERSHIP_ROLE_HOST ),
			'default'     => 'all',
		),
		'per_page' => array(
			'description' => 'Maximum number of organizers to return.',
			'type'        => 'integer',
			'minimum'     => 1,
			'maximum'     => 100,
			'default'     => 20,
		),
	);
}

/**
 * Request schema for public group members.
 *
 * @return array
 */
function get_group_members_collection_rest_args(): array {
	return array(
		'role'     => array(
			'description' => 'Membership role to return.',
			'type'        => 'string',
			'enum'        => array_merge( array( 'all' ), get_membership_roles() ),
			'default'     => 'all',
		),
		'per_page' => array(
			'description' => 'Maximum number of members to return.',
			'type'        => 'integer',
			'minimum'     => 1,
			'maximum'     => 100,
			'default'     => 20,
		),
	);
}

/**
 * Request schema for group organizer management.
 *
 * @param bool $require_user_id Whether the target user ID is required.
 *
 * @return array
 */
function get_group_organizer_management_rest_args( bool $require_user_id = false ): array {
	$args = array(
		'user_id'    => array(
			'description' => 'Target group member user ID.',
			'type'        => 'integer',
			'minimum'     => 1,
			'required'    => $require_user_id,
		),
		'role'       => array(
			'description' => 'Membership role.',
			'type'        => 'string',
			'enum'        => get_membership_roles(),
			'required'    => $require_user_id,
		),
		'visibility' => get_visibility_rest_arg(),
	);

	unset( $args['visibility']['default'] );

	return $args;
}

/**
 * Request schema for public event directory results.
 *
 * @return array
 */
function get_events_collection_rest_args(): array {
	return array_merge(
		array(
			'group_id' => array(
				'description' => 'Optional group ID.',
				'type'        => 'integer',
				'minimum'     => 0,
				'default'     => 0,
			),
		),
		get_group_events_collection_rest_args()
	);
}

/**
 * Request schema for group event collections.
 *
 * @return array
 */
function get_group_events_collection_rest_args(): array {
	return array_merge(
		array(
			'timeframe' => array(
				'description' => 'Event timeframe.',
				'type'        => 'string',
				'enum'        => array( 'upcoming', 'past', 'all' ),
				'default'     => 'upcoming',
			),
			'page'      => array(
				'description' => 'Results page.',
				'type'        => 'integer',
				'minimum'     => 1,
				'default'     => 1,
			),
			'per_page'  => array(
				'description' => 'Maximum number of events to return.',
				'type'        => 'integer',
				'minimum'     => 1,
				'maximum'     => 100,
				'default'     => 20,
			),
		),
		get_event_collection_taxonomy_rest_args()
	);
}

/**
 * Request schema for event collection taxonomy filters.
 *
 * @return array
 */
function get_event_collection_taxonomy_rest_args(): array {
	$args = array();

	foreach ( get_event_collection_taxonomy_filter_map() as $request_key => $taxonomy ) {
		$args[ $request_key ] = array(
			'description' => "Term slug for {$taxonomy}.",
			'type'        => 'string',
			'default'     => '',
		);
	}

	return $args;
}

/**
 * Request schema for public event attendees.
 *
 * @return array
 */
function get_event_attendees_collection_rest_args(): array {
	return array(
		'status'   => array(
			'description' => 'Attendee RSVP status.',
			'type'        => 'string',
			'enum'        => array( RSVP_STATUS_ATTENDING, RSVP_STATUS_WAITLISTED ),
			'default'     => RSVP_STATUS_ATTENDING,
		),
		'per_page' => array(
			'description' => 'Maximum number of attendees to return.',
			'type'        => 'integer',
			'minimum'     => 1,
			'maximum'     => 100,
			'default'     => 20,
		),
	);
}

/**
 * Request schema for public event feedback collections.
 *
 * @return array
 */
function get_event_feedback_collection_rest_args(): array {
	return array(
		'page'     => array(
			'description' => 'Results page.',
			'type'        => 'integer',
			'minimum'     => 1,
			'default'     => 1,
		),
		'per_page' => array(
			'description' => 'Maximum number of feedback records to return.',
			'type'        => 'integer',
			'minimum'     => 1,
			'maximum'     => 100,
			'default'     => 20,
		),
	);
}

/**
 * Request schema for event feedback submissions.
 *
 * @return array
 */
function get_event_feedback_create_rest_args(): array {
	return array(
		'rating' => array(
			'description' => 'Event rating from 1 to 5.',
			'type'        => 'integer',
			'minimum'     => 1,
			'maximum'     => 5,
			'required'    => true,
		),
		'review' => array(
			'description' => 'Optional event feedback note.',
			'type'        => 'string',
			'default'     => '',
		),
	);
}

/**
 * Request schema for organizer attendee messages.
 *
 * @return array
 */
function get_event_attendee_message_rest_args(): array {
	return array(
		'subject' => array(
			'description' => 'Attendee message subject.',
			'type'        => 'string',
			'minLength'   => 1,
			'required'    => true,
		),
		'message' => array(
			'description' => 'Attendee message body.',
			'type'        => 'string',
			'minLength'   => 1,
			'required'    => true,
		),
		'status'  => array(
			'description' => 'Recipient RSVP status filter.',
			'type'        => 'string',
			'enum'        => array( 'all', RSVP_STATUS_ATTENDING, RSVP_STATUS_WAITLISTED ),
			'default'     => 'all',
		),
	);
}

/**
 * Request schema for managed attendee writes.
 *
 * @param bool $require_user_id Whether the attendee user ID is required.
 *
 * @return array
 */
function get_event_attendee_management_rest_args( bool $require_user_id = false ): array {
	$args = array(
		'user_id'           => array(
			'description' => 'Attendee user ID.',
			'type'        => 'integer',
			'minimum'     => 1,
			'required'    => $require_user_id,
		),
		'status'            => array(
			'description' => 'RSVP status.',
			'type'        => 'string',
			'enum'        => get_rsvp_statuses(),
		),
		'attendance_status' => array(
			'description' => 'Attendance tracking status.',
			'type'        => 'string',
			'enum'        => get_attendance_statuses(),
		),
		'guest_count'       => array(
			'description' => 'Additional guest count.',
			'type'        => 'integer',
			'minimum'     => 0,
		),
		'answers'           => get_event_rsvp_answers_rest_arg(),
		'visibility'        => get_visibility_rest_arg(),
	);

	unset( $args['visibility']['default'] );

	if ( $require_user_id ) {
		$args['status']['default']      = RSVP_STATUS_ATTENDING;
		$args['guest_count']['default'] = 0;
		$args['visibility']['default']  = RELATIONSHIP_VISIBILITY_PUBLIC;
	}

	return $args;
}

/**
 * Request schema for event updates.
 *
 * @return array
 */
function get_group_event_update_rest_args(): array {
	return array_merge(
		array(
			'title'          => array(
				'description' => 'Event title.',
				'type'        => 'string',
				'minLength'   => 1,
			),
			'description'    => array(
				'description' => 'Event description.',
				'type'        => 'string',
			),
			'excerpt'        => array(
				'description' => 'Short event summary.',
				'type'        => 'string',
			),
			'venue_id'       => array(
				'description' => 'Venue ID.',
				'type'        => 'integer',
				'minimum'     => 0,
			),
			'host_user_ids'  => get_event_host_user_ids_rest_arg(),
			'start_utc'      => array(
				'description' => 'Event start time in UTC.',
				'type'        => 'string',
			),
			'end_utc'        => array(
				'description' => 'Event end time in UTC.',
				'type'        => 'string',
			),
			'timezone'       => array(
				'description' => 'Event timezone.',
				'type'        => 'string',
			),
			'capacity'       => array(
				'description' => 'Event RSVP capacity.',
				'type'        => 'integer',
				'minimum'     => 0,
			),
			'online_url'     => array(
				'description' => 'Online event URL.',
				'type'        => 'string',
				'format'      => 'uri',
			),
			'rsvp_policy'    => array(
				'description' => 'RSVP policy.',
				'type'        => 'string',
				'enum'        => array( 'open', 'closed' ),
			),
			'rsvp_questions' => get_event_rsvp_questions_rest_arg(),
		),
		get_event_taxonomy_rest_args( false )
	);
}

/**
 * Request schema for copying an event.
 *
 * @return array
 */
function get_event_copy_rest_args(): array {
	$args = get_group_event_update_rest_args();

	$args['start_utc']['required'] = true;

	return $args;
}

/**
 * Request schema for event cancellation.
 *
 * @return array
 */
function get_event_cancellation_rest_args(): array {
	return array(
		'reason' => array(
			'description' => 'Optional cancellation reason.',
			'type'        => 'string',
			'default'     => '',
		),
	);
}

/**
 * Extract supplied group suggestion creation fields from a REST request.
 *
 * @param \WP_REST_Request $request Request object.
 *
 * @return array
 */
function get_group_suggestion_request_args( \WP_REST_Request $request ): array {
	$args = array();

	foreach ( array_keys( get_group_suggestion_rest_args() ) as $key ) {
		if ( $request->has_param( $key ) ) {
			$args[ $key ] = $request[ $key ];
		}
	}

	return $args;
}

/**
 * Extract supplied group profile fields from a REST request.
 *
 * @param \WP_REST_Request $request Request object.
 *
 * @return array
 */
function get_group_update_request_args( \WP_REST_Request $request ): array {
	$args = array();

	foreach ( array_keys( get_group_update_rest_args() ) as $key ) {
		if ( $request->has_param( $key ) ) {
			$args[ $key ] = $request[ $key ];
		}
	}

	return $args;
}

/**
 * Extract supplied group suggestion update fields from a REST request.
 *
 * @param \WP_REST_Request $request Request object.
 *
 * @return array
 */
function get_group_suggestion_update_request_args( \WP_REST_Request $request ): array {
	$args = array();

	foreach ( array_keys( get_group_suggestion_update_rest_args() ) as $key ) {
		if ( $request->has_param( $key ) ) {
			$args[ $key ] = $request[ $key ];
		}
	}

	return $args;
}

/**
 * Extract supplied venue fields from a REST request.
 *
 * @param \WP_REST_Request $request Request object.
 *
 * @return array
 */
function get_venue_request_args( \WP_REST_Request $request ): array {
	$args = array();

	foreach ( array_keys( get_venue_rest_args( false ) ) as $key ) {
		if ( $request->has_param( $key ) ) {
			$args[ $key ] = $request[ $key ];
		}
	}

	return $args;
}

/**
 * Request schema for event creation.
 *
 * @return array
 */
function get_group_event_rest_args(): array {
	return array_merge(
		array(
			'title'          => array(
				'description' => 'Event title.',
				'type'        => 'string',
				'minLength'   => 1,
				'required'    => true,
			),
			'description'    => array(
				'description' => 'Event description.',
				'type'        => 'string',
				'default'     => '',
			),
			'excerpt'        => array(
				'description' => 'Short event summary.',
				'type'        => 'string',
				'default'     => '',
			),
			'venue_id'       => array(
				'description' => 'Venue ID.',
				'type'        => 'integer',
				'minimum'     => 0,
				'default'     => 0,
			),
			'host_user_ids'  => get_event_host_user_ids_rest_arg(),
			'start_utc'      => array(
				'description' => 'Event start time in UTC.',
				'type'        => 'string',
				'required'    => true,
			),
			'end_utc'        => array(
				'description' => 'Event end time in UTC.',
				'type'        => 'string',
				'default'     => '',
			),
			'timezone'       => array(
				'description' => 'Event timezone.',
				'type'        => 'string',
				'default'     => '',
			),
			'capacity'       => array(
				'description' => 'Event RSVP capacity.',
				'type'        => 'integer',
				'minimum'     => 0,
				'default'     => 0,
			),
			'online_url'     => array(
				'description' => 'Online event URL.',
				'type'        => 'string',
				'format'      => 'uri',
				'default'     => '',
			),
			'rsvp_policy'    => array(
				'description' => 'RSVP policy.',
				'type'        => 'string',
				'enum'        => array( 'open', 'closed' ),
				'default'     => 'open',
			),
			'rsvp_questions' => get_event_rsvp_questions_rest_arg( true ),
		),
		get_event_taxonomy_rest_args()
	);
}

/**
 * Request schema for event RSVP questions.
 *
 * @param bool $include_default Whether to include the default value.
 *
 * @return array
 */
function get_event_rsvp_questions_rest_arg( bool $include_default = false ): array {
	$schema                = get_event_rsvp_questions_schema();
	$schema['description'] = 'Question definitions attendees answer when RSVPing.';

	if ( ! $include_default ) {
		unset( $schema['default'] );
	}

	return $schema;
}

/**
 * Request schema for RSVP answers.
 *
 * @param bool $include_default Whether to include the schema default.
 *
 * @return array
 */
function get_event_rsvp_answers_rest_arg( bool $include_default = false ): array {
	$schema                = get_event_rsvp_answers_schema();
	$schema['description'] = 'Answers to event RSVP questions keyed by question ID.';

	if ( ! $include_default ) {
		unset( $schema['default'] );
	}

	return $schema;
}

/**
 * Request schema for event host IDs.
 *
 * @return array
 */
function get_event_host_user_ids_rest_arg(): array {
	return array(
		'description' => 'Event host user IDs.',
		'type'        => 'array',
		'items'       => array(
			'type' => 'integer',
		),
	);
}

/**
 * Request schema for event taxonomy fields.
 *
 * @param bool $include_default Whether to include create-route defaults.
 *
 * @return array
 */
function get_event_taxonomy_rest_args( bool $include_default = true ): array {
	$args = array();

	foreach ( get_event_taxonomy_request_map() as $request_key => $taxonomy ) {
		$args[ $request_key ] = array(
			'description' => "Term slugs for {$taxonomy}.",
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
		);

		if ( $include_default ) {
			$args[ $request_key ]['default'] = array();
		}
	}

	return $args;
}

/**
 * Extract supplied event creation fields from a REST request.
 *
 * @param \WP_REST_Request $request Request object.
 *
 * @return array
 */
function get_group_event_request_args( \WP_REST_Request $request ): array {
	$args = array();

	foreach ( array_keys( get_group_event_rest_args() ) as $key ) {
		if ( $request->has_param( $key ) ) {
			$args[ $key ] = $request[ $key ];
		}
	}

	return $args;
}

/**
 * Extract supplied attendee management fields from a REST request.
 *
 * @param \WP_REST_Request $request Request object.
 *
 * @return array
 */
function get_event_attendee_management_request_args( \WP_REST_Request $request ): array {
	$args = array();

	foreach ( array_keys( get_event_attendee_management_rest_args() ) as $key ) {
		if ( 'user_id' === $key ) {
			continue;
		}

		if ( $request->has_param( $key ) ) {
			$args[ $key ] = $request[ $key ];
		}
	}

	return $args;
}

/**
 * Extract supplied organizer management fields from a REST request.
 *
 * @param \WP_REST_Request $request Request object.
 *
 * @return array
 */
function get_group_organizer_management_request_args( \WP_REST_Request $request ): array {
	$args = array();

	foreach ( array_keys( get_group_organizer_management_rest_args() ) as $key ) {
		if ( 'user_id' === $key ) {
			continue;
		}

		if ( $request->has_param( $key ) ) {
			$args[ $key ] = $request[ $key ];
		}
	}

	return $args;
}

/**
 * Extract only supplied event update fields from a REST request.
 *
 * @param \WP_REST_Request $request Request object.
 *
 * @return array
 */
function get_group_event_update_request_args( \WP_REST_Request $request ): array {
	$args = array();

	foreach ( array_keys( get_group_event_update_rest_args() ) as $key ) {
		if ( $request->has_param( $key ) ) {
			$args[ $key ] = $request[ $key ];
		}
	}

	return $args;
}

/**
 * Extract supplied event copy fields from a REST request.
 *
 * @param \WP_REST_Request $request Request object.
 *
 * @return array
 */
function get_event_copy_request_args( \WP_REST_Request $request ): array {
	$args = array();

	foreach ( array_keys( get_event_copy_rest_args() ) as $key ) {
		if ( $request->has_param( $key ) ) {
			$args[ $key ] = $request[ $key ];
		}
	}

	return $args;
}

/**
 * Request schema for RSVP writes.
 *
 * @return array
 */
function get_rsvp_rest_args(): array {
	return array(
		'status'      => array(
			'description' => 'Requested RSVP status.',
			'type'        => 'string',
			'enum'        => array( RSVP_STATUS_ATTENDING, RSVP_STATUS_NOT_ATTENDING ),
			'default'     => RSVP_STATUS_ATTENDING,
		),
		'guest_count' => array(
			'description' => 'Number of additional guests.',
			'type'        => 'integer',
			'minimum'     => 0,
			'default'     => 0,
		),
		'answers'     => get_event_rsvp_answers_rest_arg(),
		'visibility'  => get_visibility_rest_arg(),
	);
}

/**
 * Shared request schema for relationship visibility.
 *
 * @return array
 */
function get_visibility_rest_arg(): array {
	return array(
		'description' => 'Relationship visibility.',
		'type'        => 'string',
		'enum'        => get_relationship_visibilities(),
		'default'     => RELATIONSHIP_VISIBILITY_PUBLIC,
	);
}
