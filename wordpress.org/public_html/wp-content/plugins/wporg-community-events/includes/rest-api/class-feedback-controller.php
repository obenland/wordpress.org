<?php
/**
 * Event feedback REST controller for Community Events.
 *
 * @package WordPressdotorg\Community_Events
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Community_Events;

defined( 'ABSPATH' ) || exit;

/**
 * Event feedback routes.
 */
class Feedback_Controller extends Base_Controller {
	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/events/(?P<event_id>[\d]+)/feedback',
			array(
				'args'   => array(
					'event_id' => $this->get_event_id_arg(),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'allow_public_access' ),
					'args'                => get_event_feedback_collection_rest_args(),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'can_use_self_service_route' ),
					'args'                => get_event_feedback_create_rest_args(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Get the event feedback item schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		return $this->get_feedback_schema();
	}

	/**
	 * Get event feedback.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_items( $request ) {
		$event_id = (int) $request['event_id'];
		$event    = get_post( $event_id );

		if ( ! is_public_event_post( $event ) ) {
			return new \WP_Error(
				'wporg_ce_invalid_relationship_target',
				__( 'Invalid community object.', 'wporg' ),
				array( 'status' => 404 )
			);
		}

		$query    = get_public_event_feedback_query(
			$event_id,
			(int) $request['page'],
			(int) $request['per_page']
		);
		$feedback = array_map(
			array( $this, 'prepare_feedback_item_response' ),
			array_map( 'intval', $query->posts )
		);
		$response = rest_ensure_response( $feedback );

		$response->header( 'X-WP-Total', (int) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (int) $query->max_num_pages );

		return $response;
	}

	/**
	 * Create event feedback for the current user.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		$feedback_id = submit_event_feedback(
			(int) $request['event_id'],
			get_current_user_id(),
			array(
				'rating' => (int) $request['rating'],
				'review' => (string) $request['review'],
			)
		);

		if ( is_wp_error( $feedback_id ) ) {
			return rest_convert_relationship_error_to_response( $feedback_id );
		}

		$response = rest_ensure_response( $this->prepare_feedback_item_response( $feedback_id ) );
		$response->set_status( 201 );

		return $response;
	}
}
