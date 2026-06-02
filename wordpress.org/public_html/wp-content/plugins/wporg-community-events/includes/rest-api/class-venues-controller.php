<?php
/**
 * Venues REST controller for Community Events.
 *
 * @package WordPressdotorg\Community_Events
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Community_Events;

defined( 'ABSPATH' ) || exit;

/**
 * Venue routes.
 */
class Venues_Controller extends Base_Controller {
	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/venues',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'allow_public_access' ),
					'args'                => get_venues_collection_rest_args(),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'can_use_self_service_route' ),
					'args'                => get_venue_rest_args( true ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/venues/(?P<venue_id>[\d]+)',
			array(
				'args'   => array(
					'venue_id' => $this->get_venue_id_arg(),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'allow_public_access' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'can_use_self_service_route' ),
					'args'                => get_venue_rest_args( false ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Get the venue item schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		return $this->get_venue_schema();
	}

	/**
	 * Get venues.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		$query_args = array(
			'fields'                 => 'ids',
			'post_type'              => POST_TYPE_VENUE,
			'post_status'            => 'publish',
			'posts_per_page'         => (int) $request['per_page'],
			'paged'                  => (int) $request['page'],
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		);
		$search     = trim( (string) $request['search'] );
		$city       = trim( (string) $request['city'] );
		$country    = trim( (string) $request['country'] );

		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}

		if ( '' !== $city ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Venue directory city filtering uses registered venue meta.
			$query_args['meta_query'] = array(
				array(
					'key'   => 'wporg_ce_city',
					'value' => $city,
				),
			);
		}

		if ( '' !== $country ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Venue directory filters intentionally use registered taxonomies.
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => TAXONOMY_COUNTRY,
					'field'    => 'slug',
					'terms'    => array( $country ),
				),
			);
		}

		$query  = new \WP_Query( $query_args );
		$venues = array_map(
			array( $this, 'prepare_venue_item_response' ),
			array_map( 'intval', $query->posts )
		);

		$response = rest_ensure_response( $venues );

		$response->header( 'X-WP-Total', (int) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (int) $query->max_num_pages );

		return $response;
	}

	/**
	 * Get a venue.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		$venue_id = (int) $request['venue_id'];
		$venue    = get_post( $venue_id );

		if ( ! $venue || POST_TYPE_VENUE !== $venue->post_type || 'publish' !== $venue->post_status ) {
			return new \WP_Error(
				'wporg_ce_invalid_relationship_target',
				__( 'Invalid community object.', 'wporg' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response( $this->prepare_venue_item_response( $venue_id ) );
	}

	/**
	 * Create a venue.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		$group_id   = (int) $request['group_id'];
		$validation = validate_user_relationship_target( POST_TYPE_GROUP, $group_id, get_current_user_id() );

		if ( is_wp_error( $validation ) ) {
			return rest_convert_relationship_error_to_response( $validation );
		}

		if ( ! can_user_publish_group_events( $group_id, get_current_user_id() ) ) {
			return rest_convert_relationship_error_to_response(
				new \WP_Error( 'wporg_ce_cannot_create_venue', __( 'You cannot create venues for this group.', 'wporg' ) )
			);
		}

		$args     = get_venue_request_args( $request );
		$venue_id = create_community_venue( get_current_user_id(), $args );

		if ( is_wp_error( $venue_id ) ) {
			return rest_convert_relationship_error_to_response( $venue_id );
		}

		return rest_ensure_response( $this->prepare_venue_item_response( $venue_id ) );
	}

	/**
	 * Update a venue.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		$venue_id   = (int) $request['venue_id'];
		$validation = validate_venue_manager( $venue_id, get_current_user_id() );

		if ( is_wp_error( $validation ) ) {
			return rest_convert_relationship_error_to_response( $validation );
		}

		$updated_id = update_community_venue( $venue_id, get_venue_request_args( $request ) );

		if ( is_wp_error( $updated_id ) ) {
			return rest_convert_relationship_error_to_response( $updated_id );
		}

		return rest_ensure_response( $this->prepare_venue_item_response( $updated_id ) );
	}
}
