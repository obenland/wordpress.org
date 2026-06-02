<?php
/**
 * Current-user REST controller for Community Events.
 *
 * @package WordPressdotorg\Community_Events
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Community_Events;

defined( 'ABSPATH' ) || exit;

/**
 * Current-user dashboard routes.
 */
class Current_User_Controller extends Base_Controller {
	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/me/memberships',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_memberships' ),
					'permission_callback' => array( $this, 'can_use_self_service_route' ),
					'args'                => get_current_user_memberships_collection_rest_args(),
				),
				'schema' => array( $this, 'get_public_current_user_membership_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/me/rsvps',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_rsvps' ),
					'permission_callback' => array( $this, 'can_use_self_service_route' ),
					'args'                => get_current_user_rsvps_collection_rest_args(),
				),
				'schema' => array( $this, 'get_public_current_user_rsvp_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/me/group-suggestions',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_group_suggestions' ),
					'permission_callback' => array( $this, 'can_use_self_service_route' ),
					'args'                => get_current_user_group_suggestions_collection_rest_args(),
				),
				'schema' => array( $this, 'get_public_group_suggestion_schema' ),
			)
		);
	}

	/**
	 * Get current-user memberships.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_memberships( \WP_REST_Request $request ): \WP_REST_Response {
		$membership_ids = get_current_user_membership_ids(
			get_current_user_id(),
			(string) $request['role'],
			(string) $request['status'],
			(int) $request['per_page']
		);
		$memberships    = array_map(
			array( $this, 'prepare_membership_response' ),
			$membership_ids
		);

		$response = rest_ensure_response( $memberships );

		$response->header( 'X-WP-Total', count( $memberships ) );
		$response->header( 'X-WP-TotalPages', 1 );

		return $response;
	}

	/**
	 * Get current-user RSVPs.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_rsvps( \WP_REST_Request $request ): \WP_REST_Response {
		$rsvp_ids = get_current_user_rsvp_ids(
			get_current_user_id(),
			(string) $request['status'],
			(string) $request['timeframe'],
			(int) $request['per_page']
		);
		$rsvps    = array_map(
			array( $this, 'prepare_rsvp_response' ),
			$rsvp_ids
		);

		$response = rest_ensure_response( $rsvps );

		$response->header( 'X-WP-Total', count( $rsvps ) );
		$response->header( 'X-WP-TotalPages', 1 );

		return $response;
	}

	/**
	 * Get current-user group suggestions.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_group_suggestions( \WP_REST_Request $request ): \WP_REST_Response {
		$query       = get_group_suggestion_collection_query(
			get_current_user_id(),
			(string) $request['review_status'],
			(int) $request['page'],
			(int) $request['per_page']
		);
		$suggestions = array_map(
			array( $this, 'prepare_group_suggestion_item_response' ),
			array_map( 'intval', $query->posts )
		);
		$response    = rest_ensure_response( $suggestions );

		$response->header( 'X-WP-Total', (int) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (int) $query->max_num_pages );

		return $response;
	}

	/**
	 * Prepare membership data for the current-user dashboard.
	 *
	 * @param int $membership_id Membership post ID.
	 *
	 * @return array
	 */
	private function prepare_membership_response( int $membership_id ): array {
		$response = $this->prepare_membership_item_response( $membership_id );
		$group_id = (int) $response['group_id'];
		$group    = get_post( $group_id );

		$response['group'] = is_public_group_post( $group ) ? $this->prepare_group_item_response( $group_id ) : array();

		return $response;
	}

	/**
	 * Prepare RSVP data for the current-user dashboard.
	 *
	 * @param int $rsvp_id RSVP post ID.
	 *
	 * @return array
	 */
	private function prepare_rsvp_response( int $rsvp_id ): array {
		$response = $this->prepare_rsvp_item_response( $rsvp_id );
		$event_id = (int) $response['event_id'];

		$response['event'] = $this->prepare_event_item_response( $event_id );

		return $response;
	}
}
