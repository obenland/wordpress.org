<?php
/**
 * Events REST controller for Community Events.
 *
 * @package WordPressdotorg\Community_Events
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Community_Events;

defined( 'ABSPATH' ) || exit;

/**
 * Event routes.
 */
class Events_Controller extends Base_Controller {
	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/events',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'allow_public_access' ),
					'args'                => get_events_collection_rest_args(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/events/(?P<event_id>[\d]+)',
			array(
				'args'   => array(
					'event_id' => $this->get_event_id_arg(),
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
					'args'                => get_group_event_update_rest_args(),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'can_use_self_service_route' ),
					'args'                => get_event_cancellation_rest_args(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/events/(?P<event_id>[\d]+)/attendees',
			array(
				'args'   => array(
					'event_id' => $this->get_event_id_arg(),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_attendees' ),
					'permission_callback' => array( $this, 'allow_public_access' ),
					'args'                => get_event_attendees_collection_rest_args(),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_attendee' ),
					'permission_callback' => array( $this, 'can_use_self_service_route' ),
					'args'                => get_event_attendee_management_rest_args( true ),
				),
				'schema' => array( $this, 'get_public_attendee_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/events/(?P<event_id>[\d]+)/attendees/(?P<rsvp_id>[\d]+)',
			array(
				'args'   => array(
					'event_id' => $this->get_event_id_arg(),
					'rsvp_id'  => array(
						'description' => 'RSVP relationship ID.',
						'type'        => 'integer',
						'minimum'     => 1,
						'required'    => true,
					),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_attendee' ),
					'permission_callback' => array( $this, 'can_use_self_service_route' ),
					'args'                => get_event_attendee_management_rest_args(),
				),
				'schema' => array( $this, 'get_public_attendee_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/events/(?P<event_id>[\d]+)/copies',
			array(
				'args'   => array(
					'event_id' => $this->get_event_id_arg(),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_copy' ),
					'permission_callback' => array( $this, 'can_use_self_service_route' ),
					'args'                => get_event_copy_rest_args(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/events/(?P<event_id>[\d]+)/messages',
			array(
				'args'   => array(
					'event_id' => $this->get_event_id_arg(),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_message' ),
					'permission_callback' => array( $this, 'can_use_self_service_route' ),
					'args'                => get_event_attendee_message_rest_args(),
				),
				'schema' => array( $this, 'get_public_message_result_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/events/(?P<event_id>[\d]+)/attendees\.csv',
			array(
				'args' => array(
					'event_id' => $this->get_event_id_arg(),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_attendees_export' ),
					'permission_callback' => array( $this, 'can_use_self_service_route' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/events/(?P<event_id>[\d]+)/calendar\.ics',
			array(
				'args' => array(
					'event_id' => $this->get_event_id_arg(),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_calendar' ),
					'permission_callback' => array( $this, 'allow_public_access' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/events/(?P<event_id>[\d]+)/cancellation',
			array(
				'args'   => array(
					'event_id' => $this->get_event_id_arg(),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'cancel_item' ),
					'permission_callback' => array( $this, 'can_use_self_service_route' ),
					'args'                => get_event_cancellation_rest_args(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Get events.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_items( $request ) {
		$group_id = (int) $request['group_id'];

		if ( $group_id && ! is_public_group_post( get_post( $group_id ) ) ) {
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
	 * Get the event item schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		return $this->get_event_schema();
	}

	/**
	 * Get an event.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		$event_id = (int) $request['event_id'];
		$event    = get_post( $event_id );

		if ( ! is_public_event_post( $event ) ) {
			return new \WP_Error(
				'wporg_ce_invalid_relationship_target',
				__( 'Invalid community object.', 'wporg' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response( $this->prepare_event_item_response( $event_id ) );
	}

	/**
	 * Update an event.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		$event_id = update_group_event(
			(int) $request['event_id'],
			get_current_user_id(),
			get_group_event_update_request_args( $request )
		);

		if ( is_wp_error( $event_id ) ) {
			return rest_convert_relationship_error_to_response( $event_id );
		}

		return rest_ensure_response( $this->prepare_event_item_response( $event_id ) );
	}

	/**
	 * Cancel an event through DELETE semantics.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ) {
		return $this->cancel_item( $request );
	}

	/**
	 * Copy an event to a new date.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_copy( \WP_REST_Request $request ) {
		$event_id = copy_group_event(
			(int) $request['event_id'],
			get_current_user_id(),
			get_event_copy_request_args( $request )
		);

		if ( is_wp_error( $event_id ) ) {
			return rest_convert_relationship_error_to_response( $event_id );
		}

		return rest_ensure_response( $this->prepare_event_item_response( $event_id ) );
	}

	/**
	 * Get event attendees.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_attendees( \WP_REST_Request $request ) {
		$event_id = (int) $request['event_id'];
		$event    = get_post( $event_id );

		if ( ! is_public_event_post( $event ) ) {
			return new \WP_Error(
				'wporg_ce_invalid_relationship_target',
				__( 'Invalid community object.', 'wporg' ),
				array( 'status' => 404 )
			);
		}

		$manager_check   = validate_group_event_manager( $event_id, get_current_user_id() );
		$include_private = ! is_wp_error( $manager_check );
		$rsvp_ids        = get_event_attendee_rsvp_ids(
			$event_id,
			(string) $request['status'],
			(int) $request['per_page'],
			$include_private
		);
		$attendees       = array_map(
			array( $this, 'prepare_attendee_response' ),
			$rsvp_ids
		);

		$response = rest_ensure_response( $attendees );

		$response->header( 'X-WP-Total', count( $attendees ) );
		$response->header( 'X-WP-TotalPages', 1 );

		return $response;
	}

	/**
	 * Get an event calendar export.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_calendar( \WP_REST_Request $request ) {
		$event_id = (int) $request['event_id'];
		$event    = get_post( $event_id );

		if ( ! is_public_event_post( $event ) ) {
			return new \WP_Error(
				'wporg_ce_invalid_relationship_target',
				__( 'Invalid community object.', 'wporg' ),
				array( 'status' => 404 )
			);
		}

		if ( ! get_event_calendar_lines( $event_id ) ) {
			return new \WP_Error(
				'wporg_ce_event_calendar_unavailable',
				__( 'This event does not have a valid start time.', 'wporg' ),
				array( 'status' => 400 )
			);
		}

		return prepare_calendar_rest_response(
			get_events_calendar( array( $event_id ), get_the_title( $event ), $event->post_excerpt ),
			get_calendar_filename( $event_id, 'event' )
		);
	}

	/**
	 * Get a manager-only attendee CSV export.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_attendees_export( \WP_REST_Request $request ) {
		$event_id   = (int) $request['event_id'];
		$validation = validate_group_event_manager( $event_id, get_current_user_id() );

		if ( is_wp_error( $validation ) ) {
			return rest_convert_relationship_error_to_response( $validation );
		}

		return prepare_csv_rest_response(
			$this->get_attendees_export_csv( $event_id ),
			$this->get_attendees_export_filename( $event_id )
		);
	}

	/**
	 * Create an event attendee.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_attendee( \WP_REST_Request $request ) {
		$rsvp_id = manage_event_attendee(
			(int) $request['event_id'],
			get_current_user_id(),
			(int) $request['user_id'],
			get_event_attendee_management_request_args( $request )
		);

		if ( is_wp_error( $rsvp_id ) ) {
			return rest_convert_relationship_error_to_response( $rsvp_id );
		}

		$response = rest_ensure_response( $this->prepare_attendee_response( $rsvp_id ) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Update an event attendee.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_attendee( \WP_REST_Request $request ) {
		$rsvp_id  = (int) $request['rsvp_id'];
		$event_id = (int) $request['event_id'];
		$rsvp     = get_post( $rsvp_id );

		if (
			! $rsvp ||
			POST_TYPE_RSVP !== $rsvp->post_type ||
			(int) get_post_meta( $rsvp_id, 'wporg_ce_event_id', true ) !== $event_id
		) {
			return new \WP_Error(
				'wporg_ce_invalid_relationship_target',
				__( 'Invalid community object.', 'wporg' ),
				array( 'status' => 404 )
			);
		}

		$rsvp_id = manage_event_attendee(
			$event_id,
			get_current_user_id(),
			(int) get_post_meta( $rsvp_id, 'wporg_ce_user_id', true ),
			get_event_attendee_management_request_args( $request )
		);

		if ( is_wp_error( $rsvp_id ) ) {
			return rest_convert_relationship_error_to_response( $rsvp_id );
		}

		return rest_ensure_response( $this->prepare_attendee_response( $rsvp_id ) );
	}

	/**
	 * Send a message to event attendees.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_message( \WP_REST_Request $request ) {
		$result = send_event_attendee_message(
			(int) $request['event_id'],
			get_current_user_id(),
			array(
				'message' => (string) $request['message'],
				'status'  => (string) $request['status'],
				'subject' => (string) $request['subject'],
			)
		);

		if ( is_wp_error( $result ) ) {
			return rest_convert_relationship_error_to_response( $result );
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Cancel an event.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function cancel_item( \WP_REST_Request $request ) {
		$event_id = cancel_group_event(
			(int) $request['event_id'],
			get_current_user_id(),
			array(
				'reason' => $request['reason'] ?? '',
			)
		);

		if ( is_wp_error( $event_id ) ) {
			return rest_convert_relationship_error_to_response( $event_id );
		}

		return rest_ensure_response( $this->prepare_event_item_response( $event_id ) );
	}

	/**
	 * Prepare attendee data for public REST responses.
	 *
	 * @param int $rsvp_id RSVP post ID.
	 *
	 * @return array
	 */
	private function prepare_attendee_response( int $rsvp_id ): array {
		$user_id = (int) get_post_meta( $rsvp_id, 'wporg_ce_user_id', true );

		return array(
			'id'                            => $rsvp_id,
			'rsvp_id'                       => $rsvp_id,
			'user_id'                       => $user_id,
			'user'                          => prepare_user_rest_response( $user_id ),
			'status'                        => get_post_meta( $rsvp_id, 'wporg_ce_status', true ),
			'attendance_status'             => get_post_meta( $rsvp_id, 'wporg_ce_attendance_status', true ),
			'waitlist_position'             => (int) get_post_meta( $rsvp_id, 'wporg_ce_waitlist_position', true ),
			'guest_count'                   => (int) get_post_meta( $rsvp_id, 'wporg_ce_guest_count', true ),
			'answers'                       => can_user_view_rsvp_answers( $rsvp_id, get_current_user_id() ) ? get_event_rsvp_answers( $rsvp_id ) : array(),
			'attended_at_utc'               => get_post_meta( $rsvp_id, 'wporg_ce_attended_at_utc', true ),
			'attendance_updated_by_user_id' => (int) get_post_meta( $rsvp_id, 'wporg_ce_attendance_by', true ),
			'attendance_updated_at_utc'     => get_post_meta( $rsvp_id, 'wporg_ce_attendance_at_utc', true ),
			'created_at_utc'                => get_post_meta( $rsvp_id, 'wporg_ce_created_at_utc', true ),
			'_links'                        => $this->get_attendee_links( $rsvp_id ),
		);
	}

	/**
	 * Build CSV content for an attendee export.
	 *
	 * @param int $event_id Event post ID.
	 *
	 * @return string
	 */
	private function get_attendees_export_csv( int $event_id ): string {
		$csv       = new \SplTempFileObject();
		$questions = get_event_rsvp_questions( $event_id );
		$headers   = array(
			'RSVP ID',
			'User ID',
			'Name',
			'Profile URL',
			'RSVP Status',
			'Attendance Status',
			'Guest Count',
			'Waitlist Position',
			'Visibility',
			'Created At UTC',
		);

		foreach ( $questions as $question ) {
			$headers[] = 'Question: ' . (string) ( $question['label'] ?? '' );
		}

		$this->write_attendees_export_csv_row( $csv, $headers );

		foreach ( $this->get_attendees_export_rsvp_ids( $event_id ) as $rsvp_id ) {
			$user_id = (int) get_post_meta( $rsvp_id, 'wporg_ce_user_id', true );
			$user    = prepare_user_rest_response( $user_id );
			$answers = get_event_rsvp_answers( $rsvp_id );
			$row     = array(
				$rsvp_id,
				$user_id,
				(string) ( $user['name'] ?? '' ),
				(string) ( $user['profile_url'] ?? '' ),
				get_post_meta( $rsvp_id, 'wporg_ce_status', true ),
				get_post_meta( $rsvp_id, 'wporg_ce_attendance_status', true ),
				(int) get_post_meta( $rsvp_id, 'wporg_ce_guest_count', true ),
				(int) get_post_meta( $rsvp_id, 'wporg_ce_waitlist_position', true ),
				get_post_meta( $rsvp_id, 'wporg_ce_visibility', true ),
				get_post_meta( $rsvp_id, 'wporg_ce_created_at_utc', true ),
			);

			foreach ( $questions as $question ) {
				$question_id = sanitize_key( (string) ( $question['id'] ?? '' ) );
				$row[]       = (string) ( $answers[ $question_id ] ?? '' );
			}

			$this->write_attendees_export_csv_row( $csv, $row );
		}

		$csv->rewind();
		$content = '';

		while ( ! $csv->eof() ) {
			$content .= (string) $csv->fgets();
		}

		return $content;
	}

	/**
	 * Write one attendee export row to a CSV stream.
	 *
	 * @param \SplFileObject $csv CSV stream.
	 * @param array          $row CSV row fields.
	 */
	private function write_attendees_export_csv_row( \SplFileObject $csv, array $row ): void {
		$csv->fputcsv( $row, ',', '"', '' );
	}

	/**
	 * Get active RSVP IDs for an attendee export.
	 *
	 * @param int $event_id Event post ID.
	 *
	 * @return int[]
	 */
	private function get_attendees_export_rsvp_ids( int $event_id ): array {
		$rsvp_ids = array_merge(
			get_event_attendee_rsvp_ids( $event_id, RSVP_STATUS_ATTENDING, 0, true ),
			get_event_attendee_rsvp_ids( $event_id, RSVP_STATUS_WAITLISTED, 0, true )
		);

		sort( $rsvp_ids, SORT_NUMERIC );

		return $rsvp_ids;
	}

	/**
	 * Get an attendee export filename.
	 *
	 * @param int $event_id Event post ID.
	 *
	 * @return string
	 */
	private function get_attendees_export_filename( int $event_id ): string {
		$post = get_post( $event_id );
		$slug = $post && '' !== $post->post_name ? $post->post_name : "event-{$event_id}";

		return sanitize_file_name( sanitize_title( $slug ) . '-attendees.csv' );
	}
}
