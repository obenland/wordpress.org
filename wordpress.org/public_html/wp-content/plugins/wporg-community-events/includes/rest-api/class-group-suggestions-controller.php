<?php
/**
 * Group suggestions REST controller for Community Events.
 *
 * @package WordPressdotorg\Community_Events
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Community_Events;

defined( 'ABSPATH' ) || exit;

/**
 * Group suggestion routes.
 */
class Group_Suggestions_Controller extends Base_Controller {
	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/group-suggestions',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => __NAMESPACE__ . '\can_moderate_group_suggestions_route',
					'args'                => get_group_suggestions_collection_rest_args(),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'can_use_self_service_route' ),
					'args'                => get_group_suggestion_rest_args(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/group-suggestions/(?P<suggestion_id>[\d]+)',
			array(
				'args'   => array(
					'suggestion_id' => $this->get_group_suggestion_id_arg(),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => __NAMESPACE__ . '\can_read_group_suggestion_route',
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => __NAMESPACE__ . '\can_moderate_group_suggestions_route',
					'args'                => get_group_suggestion_update_rest_args(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Get the group suggestion item schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		return $this->get_group_suggestion_schema();
	}

	/**
	 * Get group suggestions for moderation.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		$query       = get_group_suggestion_collection_query(
			0,
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
	 * Create a group suggestion.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		$suggestion_id = create_group_suggestion( get_current_user_id(), get_group_suggestion_request_args( $request ) );

		if ( is_wp_error( $suggestion_id ) ) {
			return rest_convert_relationship_error_to_response( $suggestion_id );
		}

		$response = rest_ensure_response( $this->prepare_group_suggestion_item_response( $suggestion_id ) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Get a group suggestion.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		$suggestion_id = (int) $request['suggestion_id'];
		$suggestion    = get_post( $suggestion_id );

		if ( ! $suggestion || POST_TYPE_GROUP_SUGGESTION !== $suggestion->post_type ) {
			return new \WP_Error(
				'wporg_ce_invalid_group_suggestion',
				__( 'Invalid group suggestion.', 'wporg' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response( $this->prepare_group_suggestion_item_response( $suggestion_id ) );
	}

	/**
	 * Review a group suggestion.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		$suggestion_id = review_group_suggestion(
			(int) $request['suggestion_id'],
			get_current_user_id(),
			get_group_suggestion_update_request_args( $request )
		);

		if ( is_wp_error( $suggestion_id ) ) {
			return rest_convert_relationship_error_to_response( $suggestion_id );
		}

		return rest_ensure_response( $this->prepare_group_suggestion_item_response( $suggestion_id ) );
	}
}
