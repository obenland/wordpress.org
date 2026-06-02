<?php
/**
 * Groups REST controller for Community Events.
 *
 * @package WordPressdotorg\Community_Events
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Community_Events;

defined( 'ABSPATH' ) || exit;

/**
 * Group routes.
 */
class Groups_Controller extends Base_Controller {
	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/groups',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'allow_public_access' ),
					'args'                => get_groups_collection_rest_args(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/groups/(?P<group_id>[\d]+)',
			array(
				'args'   => array(
					'group_id' => $this->get_group_id_arg(),
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
					'args'                => get_group_update_rest_args(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/groups/(?P<group_id>[\d]+)/events',
			array(
				'args'   => array(
					'group_id' => $this->get_group_id_arg(),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_events' ),
					'permission_callback' => array( $this, 'allow_public_access' ),
					'args'                => get_group_events_collection_rest_args(),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_event' ),
					'permission_callback' => array( $this, 'can_use_self_service_route' ),
					'args'                => get_group_event_rest_args(),
				),
				'schema' => array( $this, 'get_public_event_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/groups/(?P<group_id>[\d]+)/organizers',
			array(
				'args'   => array(
					'group_id' => $this->get_group_id_arg(),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_organizers' ),
					'permission_callback' => array( $this, 'allow_public_access' ),
					'args'                => get_group_organizers_collection_rest_args(),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_organizer' ),
					'permission_callback' => array( $this, 'can_use_self_service_route' ),
					'args'                => get_group_organizer_management_rest_args( true ),
				),
				'schema' => array( $this, 'get_public_organizer_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/groups/(?P<group_id>[\d]+)/organizers/(?P<membership_id>[\d]+)',
			array(
				'args'   => array(
					'group_id'      => $this->get_group_id_arg(),
					'membership_id' => array(
						'description' => 'Membership relationship ID.',
						'type'        => 'integer',
						'minimum'     => 1,
						'required'    => true,
					),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_organizer' ),
					'permission_callback' => array( $this, 'can_use_self_service_route' ),
					'args'                => get_group_organizer_management_rest_args(),
				),
				'schema' => array( $this, 'get_public_organizer_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/groups/(?P<group_id>[\d]+)/members',
			array(
				'args'   => array(
					'group_id' => $this->get_group_id_arg(),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_members' ),
					'permission_callback' => array( $this, 'allow_public_access' ),
					'args'                => get_group_members_collection_rest_args(),
				),
				'schema' => array( $this, 'get_public_member_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/groups/(?P<group_id>[\d]+)/calendar\.ics',
			array(
				'args' => array(
					'group_id' => $this->get_group_id_arg(),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_calendar' ),
					'permission_callback' => array( $this, 'allow_public_access' ),
				),
			)
		);
	}

	/**
	 * Get the group item schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		return $this->get_group_schema();
	}

	/**
	 * Get groups.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		$query_args = array(
			'fields'                 => 'ids',
			'post_type'              => POST_TYPE_GROUP,
			'post_status'            => 'publish',
			'posts_per_page'         => (int) $request['per_page'],
			'paged'                  => (int) $request['page'],
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		);
		$search     = trim( (string) $request['search'] );
		$tax_query  = get_group_collection_tax_query( $request );

		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}

		if ( $tax_query ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Directory filters intentionally use registered taxonomies.
			$query_args['tax_query'] = $tax_query;
		}

		$query  = new \WP_Query( $query_args );
		$groups = array_map(
			array( $this, 'prepare_group_item_response' ),
			array_map( 'intval', $query->posts )
		);

		$response = rest_ensure_response( $groups );

		$response->header( 'X-WP-Total', (int) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (int) $query->max_num_pages );

		return $response;
	}

	/**
	 * Get a group.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		$group_id = (int) $request['group_id'];
		$group    = get_post( $group_id );

		if ( ! $group || POST_TYPE_GROUP !== $group->post_type || 'publish' !== $group->post_status ) {
			return new \WP_Error(
				'wporg_ce_invalid_relationship_target',
				__( 'Invalid community object.', 'wporg' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response( $this->prepare_group_item_response( $group_id ) );
	}

	/**
	 * Update a group profile.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		$group_id = update_group_profile(
			(int) $request['group_id'],
			get_current_user_id(),
			get_group_update_request_args( $request )
		);

		if ( is_wp_error( $group_id ) ) {
			return rest_convert_relationship_error_to_response( $group_id );
		}

		return rest_ensure_response( $this->prepare_group_item_response( $group_id ) );
	}

	/**
	 * Get group events.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_events( \WP_REST_Request $request ) {
		$group_id = (int) $request['group_id'];
		$group    = get_post( $group_id );

		if ( ! is_public_group_post( $group ) ) {
			return new \WP_Error(
				'wporg_ce_invalid_relationship_target',
				__( 'Invalid community object.', 'wporg' ),
				array( 'status' => 404 )
			);
		}

		$query    = get_public_event_collection_query(
			$group_id,
			(string) $request['timeframe'],
			(int) $request['page'],
			(int) $request['per_page'],
			get_event_collection_filter_args( $request )
		);
		$events   = array_map(
			array( $this, 'prepare_event_item_response' ),
			array_map( 'intval', $query->posts )
		);
		$response = rest_ensure_response( $events );

		$response->header( 'X-WP-Total', (int) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (int) $query->max_num_pages );

		return $response;
	}

	/**
	 * Create a group event.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_event( \WP_REST_Request $request ) {
		$event_id = create_group_event(
			(int) $request['group_id'],
			get_current_user_id(),
			get_group_event_request_args( $request )
		);

		if ( is_wp_error( $event_id ) ) {
			return rest_convert_relationship_error_to_response( $event_id );
		}

		return rest_ensure_response( $this->prepare_event_item_response( $event_id ) );
	}

	/**
	 * Get group organizers.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_organizers( \WP_REST_Request $request ) {
		$group_id = (int) $request['group_id'];
		$group    = get_post( $group_id );

		if ( ! is_public_group_post( $group ) ) {
			return new \WP_Error(
				'wporg_ce_invalid_relationship_target',
				__( 'Invalid community object.', 'wporg' ),
				array( 'status' => 404 )
			);
		}

		$membership_ids = get_public_group_organizer_membership_ids(
			$group_id,
			(string) $request['role'],
			(int) $request['per_page']
		);
		$organizers     = array_map(
			array( $this, 'prepare_group_organizer_response' ),
			$membership_ids
		);

		$response = rest_ensure_response( $organizers );

		$response->header( 'X-WP-Total', count( $organizers ) );
		$response->header( 'X-WP-TotalPages', 1 );

		return $response;
	}

	/**
	 * Add a group organizer or host.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_organizer( \WP_REST_Request $request ) {
		$membership_id = manage_group_organizer(
			(int) $request['group_id'],
			get_current_user_id(),
			(int) $request['user_id'],
			get_group_organizer_management_request_args( $request )
		);

		if ( is_wp_error( $membership_id ) ) {
			return rest_convert_relationship_error_to_response( $membership_id );
		}

		$response = rest_ensure_response( $this->prepare_group_organizer_response( $membership_id ) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Update a group organizer or host.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_organizer( \WP_REST_Request $request ) {
		$group_id       = (int) $request['group_id'];
		$membership_id  = (int) $request['membership_id'];
		$membership     = get_post( $membership_id );
		$target_user_id = (int) get_post_meta( $membership_id, 'wporg_ce_user_id', true );

		if (
			! $membership ||
			POST_TYPE_MEMBERSHIP !== $membership->post_type ||
			(int) get_post_meta( $membership_id, 'wporg_ce_group_id', true ) !== $group_id
		) {
			return new \WP_Error(
				'wporg_ce_invalid_relationship_target',
				__( 'Invalid community object.', 'wporg' ),
				array( 'status' => 404 )
			);
		}

		$membership_id = manage_group_organizer(
			$group_id,
			get_current_user_id(),
			$target_user_id,
			get_group_organizer_management_request_args( $request )
		);

		if ( is_wp_error( $membership_id ) ) {
			return rest_convert_relationship_error_to_response( $membership_id );
		}

		return rest_ensure_response( $this->prepare_group_organizer_response( $membership_id ) );
	}

	/**
	 * Get group members.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_members( \WP_REST_Request $request ) {
		$group_id = (int) $request['group_id'];
		$group    = get_post( $group_id );

		if ( ! is_public_group_post( $group ) ) {
			return new \WP_Error(
				'wporg_ce_invalid_relationship_target',
				__( 'Invalid community object.', 'wporg' ),
				array( 'status' => 404 )
			);
		}

		$membership_ids = get_public_group_member_membership_ids(
			$group_id,
			(string) $request['role'],
			(int) $request['per_page']
		);
		$members        = array_map(
			array( $this, 'prepare_group_organizer_response' ),
			$membership_ids
		);

		$response = rest_ensure_response( $members );

		$response->header( 'X-WP-Total', count( $members ) );
		$response->header( 'X-WP-TotalPages', 1 );

		return $response;
	}

	/**
	 * Get a group event calendar export.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_calendar( \WP_REST_Request $request ) {
		$group_id = (int) $request['group_id'];
		$group    = get_post( $group_id );

		if ( ! is_public_group_post( $group ) ) {
			return new \WP_Error(
				'wporg_ce_invalid_relationship_target',
				__( 'Invalid community object.', 'wporg' ),
				array( 'status' => 404 )
			);
		}

		$query = get_public_event_collection_query( $group_id, 'upcoming', 1, 100 );
		$name  = sprintf(
			/* translators: %s: community group name. */
			__( '%s Events', 'wporg' ),
			get_the_title( $group )
		);
		$description = '' !== $group->post_excerpt ? $group->post_excerpt : $group->post_content;

		return prepare_calendar_rest_response(
			get_events_calendar(
				array_map( 'intval', $query->posts ),
				$name,
				$description
			),
			get_calendar_filename( $group_id, 'group' )
		);
	}

	/**
	 * Prepare a public organizer record for REST responses.
	 *
	 * @param int $membership_id Membership post ID.
	 *
	 * @return array
	 */
	private function prepare_group_organizer_response( int $membership_id ): array {
		$user_id = (int) get_post_meta( $membership_id, 'wporg_ce_user_id', true );

		return array(
			'id'            => $membership_id,
			'membership_id' => $membership_id,
			'user_id'       => $user_id,
			'user'          => prepare_user_rest_response( $user_id ),
			'role'          => get_post_meta( $membership_id, 'wporg_ce_role', true ),
			'joined_at_utc' => get_post_meta( $membership_id, 'wporg_ce_joined_at_utc', true ),
			'_links'        => $this->get_group_membership_links( $membership_id ),
		);
	}
}
