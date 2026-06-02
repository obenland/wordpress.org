<?php
/**
 * RSVPs REST controller for Community Events.
 *
 * @package WordPressdotorg\Community_Events
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Community_Events;

defined( 'ABSPATH' ) || exit;

/**
 * Current-user RSVP routes.
 */
class RSVPs_Controller extends Base_Controller {
	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/events/(?P<event_id>[\d]+)/rsvp',
			array(
				'args'   => array(
					'event_id' => $this->get_event_id_arg(),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'can_use_self_service_route' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'can_use_self_service_route' ),
					'args'                => get_rsvp_rest_args(),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'can_use_self_service_route' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Get the RSVP item schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		return $this->get_rsvp_schema();
	}

	/**
	 * Get the current user's RSVP.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		$event_id = (int) $request['event_id'];
		$rsvp_id  = get_event_rsvp_id( $event_id, get_current_user_id() );

		if ( ! $rsvp_id ) {
			return new \WP_Error(
				'wporg_ce_rsvp_not_found',
				__( 'RSVP not found.', 'wporg' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response( $this->prepare_rsvp_item_response( $rsvp_id ) );
	}

	/**
	 * Create or update the current user's RSVP.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		$args = array(
			'status'      => $request['status'] ?? RSVP_STATUS_ATTENDING,
			'guest_count' => $request['guest_count'] ?? 0,
			'visibility'  => $request['visibility'],
		);

		if ( $request->has_param( 'answers' ) ) {
			$args['answers'] = $request['answers'];
		}

		$rsvp_id = rsvp_to_event(
			(int) $request['event_id'],
			get_current_user_id(),
			$args
		);

		if ( is_wp_error( $rsvp_id ) ) {
			return rest_convert_relationship_error_to_response( $rsvp_id );
		}

		return rest_ensure_response( $this->prepare_rsvp_item_response( $rsvp_id ) );
	}

	/**
	 * Delete the current user's RSVP.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ) {
		$rsvp_id = get_event_rsvp_id( (int) $request['event_id'], get_current_user_id() );

		if ( ! $rsvp_id ) {
			return new \WP_Error(
				'wporg_ce_rsvp_not_found',
				__( 'RSVP not found.', 'wporg' ),
				array( 'status' => 404 )
			);
		}

		$rsvp_id = rsvp_to_event(
			(int) $request['event_id'],
			get_current_user_id(),
			array(
				'status' => RSVP_STATUS_NOT_ATTENDING,
			)
		);

		if ( is_wp_error( $rsvp_id ) ) {
			return rest_convert_relationship_error_to_response( $rsvp_id );
		}

		return rest_ensure_response( $this->prepare_rsvp_item_response( $rsvp_id ) );
	}
}
