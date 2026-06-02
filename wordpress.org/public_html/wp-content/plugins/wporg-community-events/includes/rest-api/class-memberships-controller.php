<?php
/**
 * Memberships REST controller for Community Events.
 *
 * @package WordPressdotorg\Community_Events
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Community_Events;

defined( 'ABSPATH' ) || exit;

/**
 * Current-user group membership routes.
 */
class Memberships_Controller extends Base_Controller {
	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/groups/(?P<group_id>[\d]+)/membership',
			array(
				'args'   => array(
					'group_id' => $this->get_group_id_arg(),
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
					'args'                => get_membership_rest_args(),
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
	 * Get the membership item schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		return $this->get_membership_schema();
	}

	/**
	 * Get the current user's membership.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		$group_id      = (int) $request['group_id'];
		$membership_id = get_group_membership_id( $group_id, get_current_user_id() );

		if ( ! $membership_id ) {
			return new \WP_Error(
				'wporg_ce_membership_not_found',
				__( 'Membership not found.', 'wporg' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response( $this->prepare_membership_item_response( $membership_id ) );
	}

	/**
	 * Create or update the current user's membership.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		$submitted_params = array_merge(
			$request->get_body_params(),
			is_array( $request->get_json_params() ) ? $request->get_json_params() : array()
		);
		$args             = array();

		if ( array_key_exists( 'visibility', $submitted_params ) ) {
			$args['visibility'] = $request['visibility'];
		}

		if ( array_key_exists( 'notification_preferences', $submitted_params ) ) {
			$args['notification_preferences'] = $request['notification_preferences'];
		}

		$membership_id = join_group(
			(int) $request['group_id'],
			get_current_user_id(),
			$args
		);

		if ( is_wp_error( $membership_id ) ) {
			return rest_convert_relationship_error_to_response( $membership_id );
		}

		return rest_ensure_response( $this->prepare_membership_item_response( $membership_id ) );
	}

	/**
	 * Delete the current user's membership.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ) {
		$membership_id = get_group_membership_id( (int) $request['group_id'], get_current_user_id() );

		if ( ! $membership_id ) {
			return new \WP_Error(
				'wporg_ce_membership_not_found',
				__( 'Membership not found.', 'wporg' ),
				array( 'status' => 404 )
			);
		}

		$membership_id = join_group(
			(int) $request['group_id'],
			get_current_user_id(),
			array(
				'status' => MEMBERSHIP_STATUS_LEFT,
			)
		);

		if ( is_wp_error( $membership_id ) ) {
			return rest_convert_relationship_error_to_response( $membership_id );
		}

		return rest_ensure_response( $this->prepare_membership_item_response( $membership_id ) );
	}
}
