<?php
/**
 * Tests for Community Events REST actions.
 *
 * @package WordPressdotorg\Community_Events
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Community_Events\Tests;

use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

use const WordPressdotorg\Community_Events\ATTENDANCE_STATUS_CHECKED_IN;
use const WordPressdotorg\Community_Events\ATTENDANCE_STATUS_NO_SHOW;
use const WordPressdotorg\Community_Events\ATTENDANCE_STATUS_NOT_CHECKED_IN;
use const WordPressdotorg\Community_Events\ATTENDANCE_STATUS_NOT_COMING;
use const WordPressdotorg\Community_Events\EVENT_APPROVAL_STATUS_CANCELED;
use const WordPressdotorg\Community_Events\EVENT_APPROVAL_STATUS_APPROVED;
use const WordPressdotorg\Community_Events\GROUP_SUGGESTION_STATUS_APPROVED;
use const WordPressdotorg\Community_Events\GROUP_SUGGESTION_STATUS_DECLINED;
use const WordPressdotorg\Community_Events\GROUP_SUGGESTION_STATUS_PENDING;
use const WordPressdotorg\Community_Events\MEMBERSHIP_ROLE_HOST;
use const WordPressdotorg\Community_Events\MEMBERSHIP_ROLE_ORGANIZER;
use const WordPressdotorg\Community_Events\MEMBERSHIP_ROLE_MEMBER;
use const WordPressdotorg\Community_Events\MEMBERSHIP_STATUS_ACTIVE;
use const WordPressdotorg\Community_Events\MEMBERSHIP_STATUS_LEFT;
use const WordPressdotorg\Community_Events\MEMBERSHIP_STATUS_PENDING;
use const WordPressdotorg\Community_Events\NOTIFICATION_EVENT_CANCELLATIONS;
use const WordPressdotorg\Community_Events\NOTIFICATION_EVENT_UPDATES;
use const WordPressdotorg\Community_Events\NOTIFICATION_NEW_EVENTS;
use const WordPressdotorg\Community_Events\POST_TYPE_EVENT;
use const WordPressdotorg\Community_Events\POST_TYPE_FEEDBACK;
use const WordPressdotorg\Community_Events\POST_TYPE_GROUP;
use const WordPressdotorg\Community_Events\POST_TYPE_GROUP_SUGGESTION;
use const WordPressdotorg\Community_Events\POST_TYPE_VENUE;
use const WordPressdotorg\Community_Events\RELATIONSHIP_VISIBILITY_PRIVATE;
use const WordPressdotorg\Community_Events\REST_NAMESPACE;
use const WordPressdotorg\Community_Events\RSVP_STATUS_ATTENDING;
use const WordPressdotorg\Community_Events\RSVP_STATUS_NOT_ATTENDING;
use const WordPressdotorg\Community_Events\RSVP_STATUS_WAITLISTED;
use const WordPressdotorg\Community_Events\TAXONOMY_COUNTRY;
use const WordPressdotorg\Community_Events\TAXONOMY_EVENT_FORMAT;
use const WordPressdotorg\Community_Events\TAXONOMY_EVENT_TYPE;
use const WordPressdotorg\Community_Events\TAXONOMY_GROUP_TYPE;
use const WordPressdotorg\Community_Events\TAXONOMY_LANGUAGE;
use const WordPressdotorg\Community_Events\TAXONOMY_TOPIC;

/**
 * Tests for REST membership and RSVP actions.
 */
class Rest_API_Test extends WP_UnitTestCase {
	/**
	 * Set up REST routing and the plugin data model for each isolated test.
	 */
	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;

		$wp_rest_server = new WP_REST_Server();

		\WordPressdotorg\Community_Events\register_post_types();
		\WordPressdotorg\Community_Events\register_taxonomies();
		\WordPressdotorg\Community_Events\register_meta_fields();

		do_action( 'rest_api_init' );
	}

	/**
	 * Reset the REST server after each test.
	 */
	public function tear_down(): void {
		global $wp_rest_server;

		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * REST routes should expose item schemas for future core-data entities.
	 */
	public function test_rest_routes_expose_response_schemas(): void {
		$this->assert_route_schema_properties(
			'/groups',
			'wporg_community_event_group',
			array(
				'id'          => 'integer',
				'_links'      => 'object',
				'link'        => 'string',
				'title'       => 'string',
				'taxonomies'  => 'object',
				'website_url' => 'string',
			)
		);
		$this->assert_route_schema_properties(
			'/groups/(?P<group_id>[\d]+)',
			'wporg_community_event_group',
			array(
				'id'           => 'integer',
				'_links'       => 'object',
				'member_count' => 'integer',
				'taxonomies'   => 'object',
				'website_url'  => 'string',
			)
		);
		$this->assert_route_schema_properties(
			'/groups/(?P<group_id>[\d]+)/events',
			'wporg_community_event_event',
			array(
				'id'                   => 'integer',
				'_links'               => 'object',
				'group_id'             => 'integer',
				'event_counts'         => 'object',
				'host_user_ids'        => 'array',
				'hosts'                => 'array',
				'copied_from_event_id' => 'integer',
				'feedback_summary'     => 'object',
				'link'                 => 'string',
				'rsvp_questions'       => 'array',
				'taxonomies'           => 'object',
			)
		);
		$this->assert_route_schema_properties(
			'/events',
			'wporg_community_event_event',
			array(
				'id'                   => 'integer',
				'_links'               => 'object',
				'group_id'             => 'integer',
				'event_counts'         => 'object',
				'host_user_ids'        => 'array',
				'hosts'                => 'array',
				'copied_from_event_id' => 'integer',
				'feedback_summary'     => 'object',
				'link'                 => 'string',
				'rsvp_questions'       => 'array',
				'taxonomies'           => 'object',
			)
		);
		$this->assert_route_schema_properties(
			'/groups/(?P<group_id>[\d]+)/organizers',
			'wporg_community_event_organizer',
			array(
				'id'            => 'integer',
				'_links'        => 'object',
				'membership_id' => 'integer',
				'user'          => 'object',
				'role'          => 'string',
			)
		);
		$this->assert_route_schema_properties(
			'/groups/(?P<group_id>[\d]+)/organizers/(?P<membership_id>[\d]+)',
			'wporg_community_event_organizer',
			array(
				'id'            => 'integer',
				'_links'        => 'object',
				'membership_id' => 'integer',
				'user'          => 'object',
				'role'          => 'string',
			)
		);
		$this->assert_route_schema_properties(
			'/groups/(?P<group_id>[\d]+)/members',
			'wporg_community_event_member',
			array(
				'id'            => 'integer',
				'_links'        => 'object',
				'membership_id' => 'integer',
				'user'          => 'object',
				'role'          => 'string',
			)
		);
		$this->assert_route_schema_properties(
			'/groups/(?P<group_id>[\d]+)/membership',
			'wporg_community_event_membership',
			array(
				'id'                       => 'integer',
				'_links'                   => 'object',
				'group_id'                 => 'integer',
				'notification_preferences' => 'object',
			)
		);
		$this->assert_route_schema_properties(
			'/events/(?P<event_id>[\d]+)',
			'wporg_community_event_event',
			array(
				'id'                   => 'integer',
				'_links'               => 'object',
				'host'                 => 'object',
				'host_user_ids'        => 'array',
				'hosts'                => 'array',
				'link'                 => 'string',
				'venue'                => 'object',
				'canceled_by_user_id'  => 'integer',
				'cancellation_reason'  => 'string',
				'copied_from_event_id' => 'integer',
				'feedback_summary'     => 'object',
				'rsvp_questions'       => 'array',
				'taxonomies'           => 'object',
			)
		);
		$this->assert_route_schema_properties(
			'/events/(?P<event_id>[\d]+)/copies',
			'wporg_community_event_event',
			array(
				'id'                   => 'integer',
				'_links'               => 'object',
				'copied_from_event_id' => 'integer',
				'group_id'             => 'integer',
				'host_user_ids'        => 'array',
				'link'                 => 'string',
				'rsvp_questions'       => 'array',
				'taxonomies'           => 'object',
			)
		);
		$this->assert_route_schema_properties(
			'/venues',
			'wporg_community_event_venue',
			array(
				'id'         => 'integer',
				'_links'     => 'object',
				'link'       => 'string',
				'title'      => 'string',
				'taxonomies' => 'object',
			)
		);
		$this->assert_route_schema_properties(
			'/venues/(?P<venue_id>[\d]+)',
			'wporg_community_event_venue',
			array(
				'id'                  => 'integer',
				'_links'              => 'object',
				'accessibility_notes' => 'string',
				'taxonomies'          => 'object',
			)
		);
		$this->assert_route_schema_properties(
			'/events/(?P<event_id>[\d]+)/attendees',
			'wporg_community_event_attendee',
			array(
				'id'                => 'integer',
				'_links'            => 'object',
				'rsvp_id'           => 'integer',
				'user'              => 'object',
				'status'            => 'string',
				'answers'           => 'object',
				'attendance_status' => 'string',
				'attended_at_utc'   => 'string',
			)
		);
		$this->assert_route_schema_properties(
			'/events/(?P<event_id>[\d]+)/messages',
			'wporg_community_event_message_result',
			array(
				'recipient_count' => 'integer',
				'sent_count'      => 'integer',
			)
		);
		$this->assert_route_schema_properties(
			'/events/(?P<event_id>[\d]+)/feedback',
			'wporg_community_event_feedback',
			array(
				'id'             => 'integer',
				'_links'         => 'object',
				'event_id'       => 'integer',
				'group_id'       => 'integer',
				'user'           => 'object',
				'rating'         => 'integer',
				'review'         => 'string',
				'created_at_utc' => 'string',
			)
		);
		$this->assert_route_schema_properties(
			'/events/(?P<event_id>[\d]+)/rsvp',
			'wporg_community_event_rsvp',
			array(
				'id'                            => 'integer',
				'_links'                        => 'object',
				'event_id'                      => 'integer',
				'answers'                       => 'object',
				'attendance_status'             => 'string',
				'attendance_updated_by_user_id' => 'integer',
				'waitlist_position'             => 'integer',
			)
		);
		$this->assert_route_schema_properties(
			'/me/memberships',
			'wporg_community_event_current_user_membership',
			array(
				'id'                       => 'integer',
				'_links'                   => 'object',
				'group'                    => 'object',
				'notification_preferences' => 'object',
				'user'                     => 'object',
			)
		);
		$this->assert_route_schema_properties(
			'/me/rsvps',
			'wporg_community_event_current_user_rsvp',
			array(
				'id'     => 'integer',
				'_links' => 'object',
				'event'  => 'object',
				'user'   => 'object',
			)
		);
		$this->assert_route_schema_properties(
			'/group-suggestions',
			'wporg_community_event_group_suggestion',
			array(
				'id'            => 'integer',
				'_links'        => 'object',
				'review_status' => 'string',
				'submitter'     => 'object',
				'taxonomies'    => 'object',
				'website_url'   => 'string',
			)
		);
		$this->assert_route_schema_properties(
			'/group-suggestions/(?P<suggestion_id>[\d]+)',
			'wporg_community_event_group_suggestion',
			array(
				'id'               => 'integer',
				'_links'           => 'object',
				'created_group_id' => 'integer',
				'review_status'    => 'string',
				'reviewed_by'      => 'object',
				'website_url'      => 'string',
			)
		);
		$this->assert_route_schema_properties(
			'/me/group-suggestions',
			'wporg_community_event_group_suggestion',
			array(
				'id'            => 'integer',
				'_links'        => 'object',
				'review_status' => 'string',
				'submitter'     => 'object',
				'taxonomies'    => 'object',
				'website_url'   => 'string',
			)
		);
	}

	/**
	 * Entity-like REST responses should expose stable links for future core-data registration.
	 */
	public function test_public_entity_responses_expose_rest_links(): void {
		$group_id = $this->create_group(
			array(
				'post_name' => 'wordpress-zurich',
			)
		);
		$venue_id = $this->create_venue(
			array(
				'post_name' => 'zurich-community-space',
			)
		);
		$event_id = $this->create_event(
			$group_id,
			0,
			array(
				'meta_input' => array(
					'wporg_ce_venue_id' => $venue_id,
				),
				'post_name'  => 'zurich-community-meetup',
			)
		);

		$group_data = rest_do_request(
			new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/groups/{$group_id}" )
		)->get_data();
		$event_data = rest_do_request(
			new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/events/{$event_id}" )
		)->get_data();
		$venue_data = rest_do_request(
			new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/venues/{$venue_id}" )
		)->get_data();

		$this->assert_rest_link( $group_data, 'self', rest_url( REST_NAMESPACE . "/groups/{$group_id}" ) );
		$this->assert_rest_link( $group_data, 'collection', rest_url( REST_NAMESPACE . '/groups' ) );
		$this->assert_rest_link( $group_data, 'wporg:events', rest_url( REST_NAMESPACE . "/groups/{$group_id}/events" ) );

		$this->assertSame( 'zurich-community-meetup', $event_data['slug'] );
		$this->assertSame( $event_data['link'], $event_data['url'] );
		$this->assert_rest_link( $event_data, 'self', rest_url( REST_NAMESPACE . "/events/{$event_id}" ) );
		$this->assert_rest_link( $event_data, 'collection', rest_url( REST_NAMESPACE . '/events' ) );
		$this->assert_rest_link( $event_data, 'wporg:group', rest_url( REST_NAMESPACE . "/groups/{$group_id}" ) );
		$this->assert_rest_link( $event_data, 'wporg:group-events', rest_url( REST_NAMESPACE . "/groups/{$group_id}/events" ) );
		$this->assert_rest_link( $event_data, 'wporg:venue', rest_url( REST_NAMESPACE . "/venues/{$venue_id}" ) );
		$this->assert_rest_link( $event_data, 'wporg:calendar', rest_url( REST_NAMESPACE . "/events/{$event_id}/calendar.ics" ) );

		$this->assert_rest_link( $venue_data, 'self', rest_url( REST_NAMESPACE . "/venues/{$venue_id}" ) );
		$this->assert_rest_link( $venue_data, 'collection', rest_url( REST_NAMESPACE . '/venues' ) );
	}

	/**
	 * Calendar routes should be registered as public REST endpoints.
	 */
	public function test_calendar_routes_are_registered(): void {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( "/{$this->route_namespace()}/events/(?P<event_id>[\d]+)/calendar\.ics", $routes );
		$this->assertArrayHasKey( "/{$this->route_namespace()}/events/(?P<event_id>[\d]+)/attendees\.csv", $routes );
		$this->assertArrayHasKey( "/{$this->route_namespace()}/groups/(?P<group_id>[\d]+)/calendar\.ics", $routes );
	}

	/**
	 * Membership actions should require a logged-in WordPress.org user.
	 */
	public function test_membership_endpoint_requires_logged_in_user(): void {
		$group_id = $this->create_group();
		$request  = new WP_REST_Request( WP_REST_Server::CREATABLE, "/{$this->route_namespace()}/groups/{$group_id}/membership" );
		$response = rest_do_request( $request );

		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( 'wporg_ce_rest_not_logged_in', $response->as_error()->get_error_code() );
	}

	/**
	 * A logged-in user should be able to join, read, and leave a group.
	 */
	public function test_membership_endpoint_creates_reads_and_leaves_membership(): void {
		$user_id  = self::factory()->user->create(
			array(
				'display_name'  => 'Community Member',
				'user_login'    => 'community-member',
				'user_nicename' => 'community-member',
			)
		);
		$group_id = $this->create_group();

		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( WP_REST_Server::CREATABLE, "/{$this->route_namespace()}/groups/{$group_id}/membership" );
		$request->set_body_params(
			array(
				'notification_preferences' => array(
					NOTIFICATION_NEW_EVENTS => false,
				),
				'role'                     => 'host',
				'visibility'               => RELATIONSHIP_VISIBILITY_PRIVATE,
			)
		);

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $group_id, $data['group_id'] );
		$this->assertSame( $user_id, $data['user_id'] );
		$this->assertSame( 'community-member', $data['user']['slug'] );
		$this->assertSame( 'Community Member', $data['user']['name'] );
		$this->assertSame( 'https://profiles.wordpress.org/community-member/', $data['user']['profile_url'] );
		$this->assertStringContainsString( 'avatar', $data['user']['avatar_url'] );
		$this->assertSame( MEMBERSHIP_ROLE_MEMBER, $data['role'] );
		$this->assertSame( MEMBERSHIP_STATUS_ACTIVE, $data['status'] );
		$this->assertSame( RELATIONSHIP_VISIBILITY_PRIVATE, $data['visibility'] );
		$this->assertSame(
			array(
				NOTIFICATION_NEW_EVENTS          => false,
				NOTIFICATION_EVENT_UPDATES       => true,
				NOTIFICATION_EVENT_CANCELLATIONS => true,
			),
			$data['notification_preferences']
		);

		$get_response = rest_do_request(
			new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/groups/{$group_id}/membership" )
		);
		$get_data     = $get_response->get_data();

		$this->assertSame( 200, $get_response->get_status() );
		$this->assertSame( $data['id'], $get_data['id'] );

		$update_request = new WP_REST_Request( WP_REST_Server::CREATABLE, "/{$this->route_namespace()}/groups/{$group_id}/membership" );
		$update_request->set_body_params(
			array(
				'notification_preferences' => array(
					NOTIFICATION_EVENT_UPDATES => false,
				),
			)
		);

		$update_response = rest_do_request( $update_request );
		$update_data     = $update_response->get_data();

		$this->assertSame( 200, $update_response->get_status() );
		$this->assertSame( RELATIONSHIP_VISIBILITY_PRIVATE, $update_data['visibility'] );
		$this->assertSame(
			array(
				NOTIFICATION_NEW_EVENTS          => false,
				NOTIFICATION_EVENT_UPDATES       => false,
				NOTIFICATION_EVENT_CANCELLATIONS => true,
			),
			$update_data['notification_preferences']
		);

		$delete_response = rest_do_request(
			new WP_REST_Request( WP_REST_Server::DELETABLE, "/{$this->route_namespace()}/groups/{$group_id}/membership" )
		);
		$delete_data     = $delete_response->get_data();

		$this->assertSame( 200, $delete_response->get_status() );
		$this->assertSame( $data['id'], $delete_data['id'] );
		$this->assertSame( MEMBERSHIP_STATUS_LEFT, $delete_data['status'] );
		$this->assertSame( $update_data['notification_preferences'], $delete_data['notification_preferences'] );
	}

	/**
	 * Current-user dashboard endpoints should require a logged-in WordPress.org user.
	 */
	public function test_current_user_dashboard_endpoints_require_logged_in_user(): void {
		foreach ( array( 'memberships', 'rsvps', 'group-suggestions' ) as $endpoint ) {
			$response = rest_do_request(
				new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/me/{$endpoint}" )
			);

			$this->assertSame( 401, $response->get_status() );
			$this->assertSame( 'wporg_ce_rest_not_logged_in', $response->as_error()->get_error_code() );
		}
	}

	/**
	 * Group suggestions should require a logged-in WordPress.org user.
	 */
	public function test_group_suggestion_endpoint_requires_logged_in_user(): void {
		$response = $this->post_group_suggestion_request();

		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( 'wporg_ce_rest_not_logged_in', $response->as_error()->get_error_code() );
	}

	/**
	 * Logged-in users should be able to create and read their own group suggestions.
	 */
	public function test_logged_in_user_can_create_and_read_group_suggestion(): void {
		self::factory()->term->create(
			array(
				'name'     => 'Switzerland',
				'slug'     => 'switzerland',
				'taxonomy' => TAXONOMY_COUNTRY,
			)
		);

		$user_id = self::factory()->user->create(
			array(
				'display_name'  => 'Group Suggestor',
				'user_login'    => 'group-suggestor',
				'user_nicename' => 'group-suggestor',
			)
		);

		wp_set_current_user( $user_id );

		$response = $this->post_group_suggestion_request(
			array(
				'countries'      => array( 'switzerland' ),
				'description'    => 'A monthly local group for WordPress contributors.',
				'excerpt'        => 'Monthly WordPress contributor gatherings.',
				'location_label' => 'Zurich, Switzerland',
				'title'          => 'WordPress Zurich Contributors',
				'timezone'       => 'Europe/Zurich',
				'website_url'    => 'https://zurich.example.com/',
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( $user_id, $data['submitter_user_id'] );
		$this->assertSame( 'group-suggestor', $data['submitter']['slug'] );
		$this->assertSame( 'WordPress Zurich Contributors', $data['title'] );
		$this->assertSame( 'pending', $data['status'] );
		$this->assertSame( GROUP_SUGGESTION_STATUS_PENDING, $data['review_status'] );
		$this->assertSame( 'Zurich, Switzerland', $data['location_label'] );
		$this->assertSame( 'Europe/Zurich', $data['timezone'] );
		$this->assertSame( 'https://zurich.example.com/', $data['website_url'] );
		$this->assertSame( 'switzerland', $data['taxonomies']['countries'][0]['slug'] );

		$get_response = rest_do_request(
			new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/group-suggestions/{$data['id']}" )
		);
		$get_data     = $get_response->get_data();

		$this->assertSame( 200, $get_response->get_status() );
		$this->assertSame( $data['id'], $get_data['id'] );
	}

	/**
	 * Current-user group suggestion collections should only expose that user's suggestions.
	 */
	public function test_current_user_group_suggestions_endpoint_returns_own_suggestions(): void {
		$user_id       = self::factory()->user->create();
		$other_user_id = self::factory()->user->create();
		$suggestion_id = $this->create_group_suggestion( $user_id );

		$this->create_group_suggestion(
			$other_user_id,
			array(
				'title' => 'WordPress Basel',
			)
		);

		wp_set_current_user( $user_id );

		$response = $this->get_current_user_group_suggestions_request();
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 1, $response->get_headers()['X-WP-Total'] );
		$this->assertCount( 1, $data );
		$this->assertSame( $suggestion_id, $data[0]['id'] );
		$this->assertSame( $user_id, $data[0]['submitter_user_id'] );
	}

	/**
	 * Group suggestion moderation routes should require Community Team privileges.
	 */
	public function test_group_suggestion_moderation_requires_moderator(): void {
		$owner_id      = self::factory()->user->create();
		$other_user_id = self::factory()->user->create();
		$suggestion_id = $this->create_group_suggestion( $owner_id );

		wp_set_current_user( $other_user_id );

		$get_response   = rest_do_request(
			new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/group-suggestions/{$suggestion_id}" )
		);
		$list_response  = $this->get_group_suggestions_request();
		$patch_response = $this->patch_group_suggestion_request(
			$suggestion_id,
			array(
				'review_status' => GROUP_SUGGESTION_STATUS_APPROVED,
			)
		);

		$this->assertSame( 403, $get_response->get_status() );
		$this->assertSame( 'wporg_ce_cannot_read_group_suggestion', $get_response->as_error()->get_error_code() );
		$this->assertSame( 403, $list_response->get_status() );
		$this->assertSame( 'wporg_ce_cannot_moderate_group_suggestions', $list_response->as_error()->get_error_code() );
		$this->assertSame( 403, $patch_response->get_status() );
		$this->assertSame( 'wporg_ce_cannot_moderate_group_suggestions', $patch_response->as_error()->get_error_code() );
	}

	/**
	 * Moderators should be able to approve suggestions into official groups.
	 */
	public function test_moderator_can_approve_group_suggestion_and_create_group(): void {
		self::factory()->term->create(
			array(
				'name'     => 'Switzerland',
				'slug'     => 'switzerland',
				'taxonomy' => TAXONOMY_COUNTRY,
			)
		);

		$submitter_id  = self::factory()->user->create();
		$moderator_id  = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		$suggestion_id = $this->create_group_suggestion(
			$submitter_id,
			array(
				'countries'      => array( 'switzerland' ),
				'description'    => 'A local community group.',
				'excerpt'        => 'Local WordPress events.',
				'location_label' => 'Zurich, Switzerland',
				'region'         => 'ZH',
				'title'          => 'WordPress Zurich',
				'timezone'       => 'Europe/Zurich',
				'website_url'    => 'https://wpzurich.example.com/',
			)
		);
		$pending_id    = $this->create_group_suggestion(
			$submitter_id,
			array(
				'title' => 'WordPress Basel',
			)
		);

		wp_set_current_user( $moderator_id );

		$response = $this->patch_group_suggestion_request(
			$suggestion_id,
			array(
				'review_note'   => 'Approved for launch.',
				'review_status' => GROUP_SUGGESTION_STATUS_APPROVED,
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( GROUP_SUGGESTION_STATUS_APPROVED, $data['review_status'] );
		$this->assertSame( 'publish', $data['status'] );
		$this->assertSame( $moderator_id, $data['reviewed_by_user_id'] );
		$this->assertSame( 'Approved for launch.', $data['review_note'] );
		$this->assertNotEmpty( $data['reviewed_at_utc'] );
		$this->assertGreaterThan( 0, $data['created_group_id'] );

		$group_id = (int) $data['created_group_id'];

		$this->assertSame( POST_TYPE_GROUP, get_post_type( $group_id ) );
		$this->assertSame( 'publish', get_post_status( $group_id ) );
		$this->assertSame( 'WordPress Zurich', get_the_title( $group_id ) );
		$this->assertSame( 'Zurich, Switzerland', get_post_meta( $group_id, 'wporg_ce_location_label', true ) );
		$this->assertSame( 'official', get_post_meta( $group_id, 'wporg_ce_official_status', true ) );
		$this->assertSame( 'https://wpzurich.example.com/', get_post_meta( $group_id, 'wporg_ce_website_url', true ) );
		$this->assertContains( 'switzerland', wp_get_object_terms( $group_id, TAXONOMY_COUNTRY, array( 'fields' => 'slugs' ) ) );

		$pending_response = $this->get_group_suggestions_request(
			array(
				'review_status' => GROUP_SUGGESTION_STATUS_PENDING,
			)
		);
		$pending_data     = $pending_response->get_data();

		$this->assertSame( 200, $pending_response->get_status() );
		$this->assertSame( 1, $pending_response->get_headers()['X-WP-Total'] );
		$this->assertSame( $pending_id, $pending_data[0]['id'] );
	}

	/**
	 * Moderators should be able to decline group suggestions without creating groups.
	 */
	public function test_moderator_can_decline_group_suggestion(): void {
		$submitter_id  = self::factory()->user->create();
		$moderator_id  = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		$duplicate_id  = $this->create_group();
		$suggestion_id = $this->create_group_suggestion( $submitter_id );

		wp_set_current_user( $moderator_id );

		$response = $this->patch_group_suggestion_request(
			$suggestion_id,
			array(
				'duplicate_group_id' => $duplicate_id,
				'review_note'        => 'This is already covered by an existing group.',
				'review_status'      => GROUP_SUGGESTION_STATUS_DECLINED,
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( GROUP_SUGGESTION_STATUS_DECLINED, $data['review_status'] );
		$this->assertSame( 'draft', $data['status'] );
		$this->assertSame( 0, $data['created_group_id'] );
		$this->assertSame( $duplicate_id, $data['duplicate_group_id'] );
		$this->assertSame( 'draft', get_post_status( $suggestion_id ) );
	}

	/**
	 * Current-user membership collections should expose the user's own groups.
	 */
	public function test_current_user_memberships_endpoint_returns_filtered_memberships(): void {
		$user_id         = self::factory()->user->create(
			array(
				'display_name'  => 'Dashboard Member',
				'user_login'    => 'dashboard-member',
				'user_nicename' => 'dashboard-member',
			)
		);
		$other_user_id   = self::factory()->user->create();
		$member_group    = $this->create_group(
			array(
				'post_title' => 'WordPress Zurich',
			)
		);
		$organizer_group = $this->create_group(
			array(
				'post_title' => 'WordPress Basel',
			)
		);
		$left_group      = $this->create_group(
			array(
				'post_title' => 'WordPress Bern',
			)
		);
		$pending_group   = $this->create_group(
			array(
				'post_title' => 'WordPress Lausanne',
			)
		);

		\WordPressdotorg\Community_Events\join_group(
			$member_group,
			$user_id,
			array(
				'visibility' => RELATIONSHIP_VISIBILITY_PRIVATE,
			)
		);
		\WordPressdotorg\Community_Events\join_group(
			$organizer_group,
			$user_id,
			array(
				'role' => MEMBERSHIP_ROLE_ORGANIZER,
			)
		);
		\WordPressdotorg\Community_Events\join_group(
			$left_group,
			$user_id,
			array(
				'status' => MEMBERSHIP_STATUS_LEFT,
			)
		);
		\WordPressdotorg\Community_Events\join_group(
			$pending_group,
			$user_id,
			array(
				'status' => MEMBERSHIP_STATUS_PENDING,
			)
		);
		\WordPressdotorg\Community_Events\join_group( $member_group, $other_user_id );

		wp_set_current_user( $user_id );

		$response = rest_do_request(
			new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/me/memberships" )
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 2, $response->get_headers()['X-WP-Total'] );
		$this->assertCount( 2, $data );
		$this->assertSame( $member_group, $data[0]['group_id'] );
		$this->assertSame( $user_id, $data[0]['user_id'] );
		$this->assertSame( RELATIONSHIP_VISIBILITY_PRIVATE, $data[0]['visibility'] );
		$this->assertSame( 'WordPress Zurich', $data[0]['group']['title'] );
		$this->assertSame( 'dashboard-member', $data[0]['user']['slug'] );
		$this->assertSame( $organizer_group, $data[1]['group_id'] );
		$this->assertSame( MEMBERSHIP_ROLE_ORGANIZER, $data[1]['role'] );

		$organizer_request = new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/me/memberships" );
		$organizer_request->set_query_params(
			array(
				'role' => MEMBERSHIP_ROLE_ORGANIZER,
			)
		);
		$organizer_response = rest_do_request( $organizer_request );
		$organizer_data     = $organizer_response->get_data();

		$this->assertSame( 200, $organizer_response->get_status() );
		$this->assertSame( 1, $organizer_response->get_headers()['X-WP-Total'] );
		$this->assertCount( 1, $organizer_data );
		$this->assertSame( $organizer_group, $organizer_data[0]['group_id'] );
	}

	/**
	 * Group event creation should require an active organizer or host role.
	 */
	public function test_group_event_endpoint_requires_group_organizer_or_host(): void {
		$user_id  = self::factory()->user->create();
		$group_id = $this->create_group();

		\WordPressdotorg\Community_Events\join_group( $group_id, $user_id );
		wp_set_current_user( $user_id );

		$response = $this->post_group_event_request( $group_id );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'wporg_ce_cannot_create_event', $response->as_error()->get_error_code() );
	}

	/**
	 * Active organizers should be able to publish group events directly.
	 */
	public function test_group_organizer_can_publish_event_directly(): void {
		$user_id   = self::factory()->user->create(
			array(
				'display_name'  => 'Event Host',
				'user_login'    => 'event-host',
				'user_nicename' => 'event-host',
			)
		);
		$cohost_id = self::factory()->user->create(
			array(
				'display_name'  => 'Event Co-Host',
				'user_login'    => 'event-cohost',
				'user_nicename' => 'event-cohost',
			)
		);
		$group_id  = $this->create_group();
		$venue_id  = $this->create_venue(
			array(
				'post_title' => 'Accessibility Lab',
			)
		);
		self::factory()->term->create(
			array(
				'name'     => 'Workshop',
				'slug'     => 'workshop',
				'taxonomy' => TAXONOMY_EVENT_TYPE,
			)
		);
		self::factory()->term->create(
			array(
				'name'     => 'Hybrid',
				'slug'     => 'hybrid',
				'taxonomy' => TAXONOMY_EVENT_FORMAT,
			)
		);

		\WordPressdotorg\Community_Events\join_group(
			$group_id,
			$user_id,
			array(
				'role' => MEMBERSHIP_ROLE_ORGANIZER,
			)
		);
		\WordPressdotorg\Community_Events\join_group( $group_id, $cohost_id );
		wp_set_current_user( $user_id );

		$response = $this->post_group_event_request(
			$group_id,
			array(
				'title'          => 'Accessibility Table',
				'description'    => 'A contributor-focused meetup.',
				'capacity'       => 24,
				'end_utc'        => '2026-06-02T20:00:00Z',
				'online_url'     => 'https://wordpress.tv/live/accessibility-table/',
				'timezone'       => 'Europe/Zurich',
				'venue_id'       => $venue_id,
				'host_user_ids'  => array( $user_id, $cohost_id ),
				'rsvp_questions' => array(
					array(
						'id'       => 'access_needs',
						'label'    => 'Do you have accessibility needs?',
						'type'     => 'textarea',
						'required' => false,
					),
					array(
						'id'       => 'contribution_area',
						'label'    => 'Which area are you contributing to?',
						'type'     => 'select',
						'required' => true,
						'choices'  => array( 'Core', 'Docs' ),
					),
				),
				'event_types'    => array( 'workshop' ),
				'event_formats'  => array( 'hybrid' ),
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $group_id, $data['group_id'] );
		$this->assertSame( $user_id, $data['host_user_id'] );
		$this->assertSame( array( $user_id, $cohost_id ), $data['host_user_ids'] );
		$this->assertSame( 'event-host', $data['host']['slug'] );
		$this->assertSame( 'Event Host', $data['host']['name'] );
		$this->assertSame( 'https://profiles.wordpress.org/event-host/', $data['host']['profile_url'] );
		$this->assertSame( 'event-host', $data['hosts'][0]['slug'] );
		$this->assertSame( 'event-cohost', $data['hosts'][1]['slug'] );
		$this->assertSame( 'Accessibility Table', $data['title'] );
		$this->assertSame( 'publish', $data['status'] );
		$this->assertSame( EVENT_APPROVAL_STATUS_APPROVED, $data['approval_status'] );
		$this->assertSame( '2026-06-02T18:00:00Z', $data['start_utc'] );
		$this->assertSame( '2026-06-02T20:00:00Z', $data['end_utc'] );
		$this->assertSame( $venue_id, $data['venue_id'] );
		$this->assertSame( 'Accessibility Lab', $data['venue']['title'] );
		$this->assertSame( 24, $data['capacity'] );
		$this->assertSame( 'https://wordpress.tv/live/accessibility-table/', $data['online_url'] );
		$this->assertSame( 'open', $data['rsvp_policy'] );
		$this->assertSame( 'access_needs', $data['rsvp_questions'][0]['id'] );
		$this->assertSame( 'textarea', $data['rsvp_questions'][0]['type'] );
		$this->assertFalse( $data['rsvp_questions'][0]['required'] );
		$this->assertSame( 'contribution_area', $data['rsvp_questions'][1]['id'] );
		$this->assertSame( array( 'Core', 'Docs' ), $data['rsvp_questions'][1]['choices'] );
		$this->assertSame( 'workshop', $data['taxonomies']['event_types'][0]['slug'] );
		$this->assertSame( 'hybrid', $data['taxonomies']['event_formats'][0]['slug'] );
		$this->assertSame( array( $user_id, $cohost_id ), get_post_meta( $data['id'], 'wporg_ce_host_user_ids', true ) );
		$this->assertTrue( comments_open( $data['id'] ) );
	}

	/**
	 * Group event creation should reject hosts who are not active group members.
	 */
	public function test_group_event_endpoint_rejects_nonmember_event_hosts(): void {
		$organizer_id = self::factory()->user->create();
		$outsider_id  = self::factory()->user->create();
		$group_id     = $this->create_group();

		\WordPressdotorg\Community_Events\join_group(
			$group_id,
			$organizer_id,
			array(
				'role' => MEMBERSHIP_ROLE_ORGANIZER,
			)
		);
		wp_set_current_user( $organizer_id );

		$response = $this->post_group_event_request(
			$group_id,
			array(
				'host_user_ids' => array( $organizer_id, $outsider_id ),
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'wporg_ce_invalid_event_host', $response->as_error()->get_error_code() );
	}

	/**
	 * Active hosts should be able to publish group events directly.
	 */
	public function test_group_host_can_publish_event_directly(): void {
		$user_id  = self::factory()->user->create();
		$group_id = $this->create_group();

		\WordPressdotorg\Community_Events\join_group(
			$group_id,
			$user_id,
			array(
				'role' => MEMBERSHIP_ROLE_HOST,
			)
		);
		wp_set_current_user( $user_id );

		$response = $this->post_group_event_request( $group_id );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'publish', $data['status'] );
		$this->assertSame( EVENT_APPROVAL_STATUS_APPROVED, $data['approval_status'] );
		$this->assertSame( $user_id, $data['host_user_id'] );
	}

	/**
	 * Public group discovery should expose published groups filtered by taxonomy.
	 */
	public function test_groups_endpoint_returns_filtered_published_groups(): void {
		$switzerland_id = self::factory()->term->create(
			array(
				'name'     => 'Switzerland',
				'slug'     => 'switzerland',
				'taxonomy' => TAXONOMY_COUNTRY,
			)
		);
		$germany_id     = self::factory()->term->create(
			array(
				'name'     => 'Germany',
				'slug'     => 'germany',
				'taxonomy' => TAXONOMY_COUNTRY,
			)
		);
		$zurich_id      = $this->create_group(
			array(
				'post_title' => 'WordPress Zurich',
				'meta_input' => array(
					'wporg_ce_city'           => 'Zurich',
					'wporg_ce_location_label' => 'Zurich, Switzerland',
					'wporg_ce_member_count'   => 42,
				),
			)
		);
		$berlin_id      = $this->create_group(
			array(
				'post_title' => 'WordPress Berlin',
			)
		);

		$this->create_group(
			array(
				'post_status' => 'draft',
				'post_title'  => 'Draft Zurich Group',
			)
		);

		wp_set_object_terms( $zurich_id, array( $switzerland_id ), TAXONOMY_COUNTRY );
		wp_set_object_terms( $berlin_id, array( $germany_id ), TAXONOMY_COUNTRY );

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/groups" );
		$request->set_query_params(
			array(
				'country'  => 'switzerland',
				'per_page' => 10,
			)
		);
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 1, $response->get_headers()['X-WP-Total'] );
		$this->assertSame( 1, $response->get_headers()['X-WP-TotalPages'] );
		$this->assertCount( 1, $data );
		$this->assertSame( $zurich_id, $data[0]['id'] );
		$this->assertSame( 'WordPress Zurich', $data[0]['title'] );
		$this->assertSame( 'Zurich', $data[0]['city'] );
		$this->assertSame( 'Zurich, Switzerland', $data[0]['location_label'] );
		$this->assertSame( 42, $data[0]['member_count'] );
		$this->assertSame( 'switzerland', $data[0]['taxonomies']['countries'][0]['slug'] );
	}

	/**
	 * Public group details should expose location, status, counts, and taxonomy data.
	 */
	public function test_group_endpoint_returns_published_group_details(): void {
		$local_id       = self::factory()->term->create(
			array(
				'name'     => 'Local',
				'slug'     => 'local',
				'taxonomy' => TAXONOMY_GROUP_TYPE,
			)
		);
		$topic_id       = self::factory()->term->create(
			array(
				'name'     => 'Accessibility',
				'slug'     => 'accessibility',
				'taxonomy' => TAXONOMY_TOPIC,
			)
		);
		$language_id    = self::factory()->term->create(
			array(
				'name'     => 'German',
				'slug'     => 'de',
				'taxonomy' => TAXONOMY_LANGUAGE,
			)
		);
		$switzerland_id = self::factory()->term->create(
			array(
				'name'     => 'Switzerland',
				'slug'     => 'switzerland',
				'taxonomy' => TAXONOMY_COUNTRY,
			)
		);
		$group_id       = $this->create_group(
			array(
				'post_name'    => 'wordpress-zurich',
				'post_title'   => 'WordPress Zurich',
				'post_content' => 'A local WordPress community group.',
				'post_excerpt' => 'Local WordPress events in Zurich.',
				'meta_input'   => array(
					'wporg_ce_city'              => 'Zurich',
					'wporg_ce_event_count'       => 18,
					'wporg_ce_location_label'    => 'Zurich, Switzerland',
					'wporg_ce_member_count'      => 321,
					'wporg_ce_official_status'   => 'official',
					'wporg_ce_region'            => 'ZH',
					'wporg_ce_source_meetup_url' => 'https://www.meetup.com/wordpress-zurich/',
					'wporg_ce_timezone'          => 'Europe/Zurich',
					'wporg_ce_website_url'       => 'https://wpzurich.example.com/',
				),
			)
		);

		wp_set_object_terms( $group_id, array( $local_id ), TAXONOMY_GROUP_TYPE );
		wp_set_object_terms( $group_id, array( $topic_id ), TAXONOMY_TOPIC );
		wp_set_object_terms( $group_id, array( $language_id ), TAXONOMY_LANGUAGE );
		wp_set_object_terms( $group_id, array( $switzerland_id ), TAXONOMY_COUNTRY );

		wp_set_current_user( 0 );

		$response = rest_do_request(
			new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/groups/{$group_id}" )
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $group_id, $data['id'] );
		$this->assertSame( 'wordpress-zurich', $data['slug'] );
		$this->assertStringContainsString( 'wordpress-zurich', $data['link'] );
		$this->assertStringContainsString( 'wordpress-zurich', $data['url'] );
		$this->assertSame( 'WordPress Zurich', $data['title'] );
		$this->assertSame( 'A local WordPress community group.', $data['description'] );
		$this->assertSame( 'Local WordPress events in Zurich.', $data['excerpt'] );
		$this->assertSame( 'publish', $data['status'] );
		$this->assertSame( 'Europe/Zurich', $data['timezone'] );
		$this->assertSame( 'Zurich', $data['city'] );
		$this->assertSame( 'ZH', $data['region'] );
		$this->assertSame( 'Zurich, Switzerland', $data['location_label'] );
		$this->assertSame( 'official', $data['official_status'] );
		$this->assertSame( 'https://www.meetup.com/wordpress-zurich/', $data['source_meetup_url'] );
		$this->assertSame( 'https://wpzurich.example.com/', $data['website_url'] );
		$this->assertSame( 321, $data['member_count'] );
		$this->assertSame( 18, $data['event_count'] );
		$this->assertSame( 'local', $data['taxonomies']['group_types'][0]['slug'] );
		$this->assertSame( 'accessibility', $data['taxonomies']['topics'][0]['slug'] );
		$this->assertSame( 'de', $data['taxonomies']['languages'][0]['slug'] );
		$this->assertSame( 'switzerland', $data['taxonomies']['countries'][0]['slug'] );
	}

	/**
	 * Active group organizers should be able to update group website URLs.
	 */
	public function test_group_organizer_can_update_group_website_url(): void {
		$group_id     = $this->create_group(
			array(
				'meta_input' => array(
					'wporg_ce_website_url' => 'https://old.example.com/',
				),
			)
		);
		$organizer_id = self::factory()->user->create();

		\WordPressdotorg\Community_Events\join_group(
			$group_id,
			$organizer_id,
			array(
				'role' => MEMBERSHIP_ROLE_ORGANIZER,
			)
		);

		wp_set_current_user( $organizer_id );

		$response = $this->patch_group_request(
			$group_id,
			array(
				'website_url' => 'https://wpzurich.example.com/',
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'https://wpzurich.example.com/', $data['website_url'] );
		$this->assertSame( 'https://wpzurich.example.com/', get_post_meta( $group_id, 'wporg_ce_website_url', true ) );

		$clear_response = $this->patch_group_request(
			$group_id,
			array(
				'website_url' => '',
			)
		);
		$clear_data     = $clear_response->get_data();

		$this->assertSame( 200, $clear_response->get_status() );
		$this->assertSame( '', $clear_data['website_url'] );
		$this->assertSame( '', get_post_meta( $group_id, 'wporg_ce_website_url', true ) );
	}

	/**
	 * Group hosts should not be able to update group profile fields.
	 */
	public function test_group_host_cannot_update_group_profile(): void {
		$group_id = $this->create_group(
			array(
				'meta_input' => array(
					'wporg_ce_website_url' => 'https://old.example.com/',
				),
			)
		);
		$host_id  = self::factory()->user->create();

		\WordPressdotorg\Community_Events\join_group(
			$group_id,
			$host_id,
			array(
				'role' => MEMBERSHIP_ROLE_HOST,
			)
		);

		wp_set_current_user( $host_id );

		$response = $this->patch_group_request(
			$group_id,
			array(
				'website_url' => 'https://host-edit.example.com/',
			)
		);

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'wporg_ce_cannot_manage_group_profile', $response->as_error()->get_error_code() );
		$this->assertSame( 'https://old.example.com/', get_post_meta( $group_id, 'wporg_ce_website_url', true ) );
	}

	/**
	 * Public group details should not expose unpublished groups.
	 */
	public function test_group_endpoint_hides_unpublished_groups(): void {
		$group_id = $this->create_group(
			array(
				'post_status' => 'draft',
			)
		);

		wp_set_current_user( 0 );

		$response = rest_do_request(
			new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/groups/{$group_id}" )
		);

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'wporg_ce_invalid_relationship_target', $response->as_error()->get_error_code() );
	}

	/**
	 * Public organizer lists should include only active visible organizers and hosts.
	 */
	public function test_group_organizers_endpoint_returns_public_active_organizers(): void {
		$group_id             = $this->create_group();
		$other_group_id       = $this->create_group(
			array(
				'post_title' => 'WordPress Basel',
			)
		);
		$organizer_id         = self::factory()->user->create(
			array(
				'display_name'  => 'Public Organizer',
				'user_login'    => 'public-organizer',
				'user_nicename' => 'public-organizer',
			)
		);
		$host_id              = self::factory()->user->create(
			array(
				'display_name'  => 'Public Host',
				'user_login'    => 'public-host',
				'user_nicename' => 'public-host',
			)
		);
		$member_id            = self::factory()->user->create();
		$private_organizer_id = self::factory()->user->create();
		$pending_organizer_id = self::factory()->user->create();
		$other_organizer_id   = self::factory()->user->create();

		$organizer_membership_id = \WordPressdotorg\Community_Events\join_group(
			$group_id,
			$organizer_id,
			array(
				'joined_at_utc' => '2026-01-01 00:00:00',
				'role'          => MEMBERSHIP_ROLE_ORGANIZER,
			)
		);
		$host_membership_id      = \WordPressdotorg\Community_Events\join_group(
			$group_id,
			$host_id,
			array(
				'joined_at_utc' => '2026-01-02 00:00:00',
				'role'          => MEMBERSHIP_ROLE_HOST,
			)
		);

		\WordPressdotorg\Community_Events\join_group( $group_id, $member_id );
		\WordPressdotorg\Community_Events\join_group(
			$group_id,
			$private_organizer_id,
			array(
				'role'       => MEMBERSHIP_ROLE_ORGANIZER,
				'visibility' => RELATIONSHIP_VISIBILITY_PRIVATE,
			)
		);
		\WordPressdotorg\Community_Events\join_group(
			$group_id,
			$pending_organizer_id,
			array(
				'role'   => MEMBERSHIP_ROLE_ORGANIZER,
				'status' => MEMBERSHIP_STATUS_PENDING,
			)
		);
		\WordPressdotorg\Community_Events\join_group(
			$other_group_id,
			$other_organizer_id,
			array(
				'role' => MEMBERSHIP_ROLE_ORGANIZER,
			)
		);

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/groups/{$group_id}/organizers" );
		$request->set_query_params(
			array(
				'per_page' => 10,
			)
		);
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 2, $response->get_headers()['X-WP-Total'] );
		$this->assertCount( 2, $data );
		$this->assertSame( $organizer_membership_id, $data[0]['id'] );
		$this->assertSame( $organizer_membership_id, $data[0]['membership_id'] );
		$this->assertSame( $organizer_id, $data[0]['user_id'] );
		$this->assertSame( 'public-organizer', $data[0]['user']['slug'] );
		$this->assertSame( 'Public Organizer', $data[0]['user']['name'] );
		$this->assertSame( 'https://profiles.wordpress.org/public-organizer/', $data[0]['user']['profile_url'] );
		$this->assertSame( MEMBERSHIP_ROLE_ORGANIZER, $data[0]['role'] );
		$this->assertSame( '2026-01-01 00:00:00', $data[0]['joined_at_utc'] );
		$this->assertSame( $host_membership_id, $data[1]['id'] );
		$this->assertSame( $host_membership_id, $data[1]['membership_id'] );
		$this->assertSame( $host_id, $data[1]['user_id'] );
		$this->assertSame( 'public-host', $data[1]['user']['slug'] );
		$this->assertSame( MEMBERSHIP_ROLE_HOST, $data[1]['role'] );

		$host_request = new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/groups/{$group_id}/organizers" );
		$host_request->set_query_params(
			array(
				'role' => MEMBERSHIP_ROLE_HOST,
			)
		);
		$host_response = rest_do_request( $host_request );
		$host_data     = $host_response->get_data();

		$this->assertSame( 200, $host_response->get_status() );
		$this->assertSame( 1, $host_response->get_headers()['X-WP-Total'] );
		$this->assertCount( 1, $host_data );
		$this->assertSame( $host_id, $host_data[0]['user_id'] );
	}

	/**
	 * Public group member lists should include active visible members by role.
	 */
	public function test_group_members_endpoint_returns_public_active_members(): void {
		$group_id       = $this->create_group();
		$other_group_id = $this->create_group(
			array(
				'post_title' => 'WordPress Basel',
			)
		);
		$member_id      = self::factory()->user->create(
			array(
				'display_name'  => 'Visible Member',
				'user_login'    => 'visible-member',
				'user_nicename' => 'visible-member',
			)
		);
		$host_id        = self::factory()->user->create();
		$private_id     = self::factory()->user->create();
		$left_id        = self::factory()->user->create();
		$other_id       = self::factory()->user->create();

		$member_membership_id = \WordPressdotorg\Community_Events\join_group( $group_id, $member_id );
		$host_membership_id   = \WordPressdotorg\Community_Events\join_group(
			$group_id,
			$host_id,
			array(
				'role' => MEMBERSHIP_ROLE_HOST,
			)
		);

		\WordPressdotorg\Community_Events\join_group(
			$group_id,
			$private_id,
			array(
				'visibility' => RELATIONSHIP_VISIBILITY_PRIVATE,
			)
		);
		\WordPressdotorg\Community_Events\join_group(
			$group_id,
			$left_id,
			array(
				'status' => MEMBERSHIP_STATUS_LEFT,
			)
		);
		\WordPressdotorg\Community_Events\join_group( $other_group_id, $other_id );

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/groups/{$group_id}/members" );
		$request->set_query_params(
			array(
				'per_page' => 10,
				'role'     => MEMBERSHIP_ROLE_MEMBER,
			)
		);
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 1, $response->get_headers()['X-WP-Total'] );
		$this->assertCount( 1, $data );
		$this->assertSame( $member_membership_id, $data[0]['id'] );
		$this->assertSame( $member_membership_id, $data[0]['membership_id'] );
		$this->assertSame( $member_id, $data[0]['user_id'] );
		$this->assertSame( 'visible-member', $data[0]['user']['slug'] );
		$this->assertSame( MEMBERSHIP_ROLE_MEMBER, $data[0]['role'] );

		$all_request = new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/groups/{$group_id}/members" );
		$all_request->set_query_params(
			array(
				'per_page' => 10,
			)
		);
		$all_response = rest_do_request( $all_request );
		$all_data     = $all_response->get_data();

		$this->assertSame( 200, $all_response->get_status() );
		$this->assertSame( 2, $all_response->get_headers()['X-WP-Total'] );
		$this->assertSame( $member_membership_id, $all_data[0]['membership_id'] );
		$this->assertSame( $host_membership_id, $all_data[1]['membership_id'] );
	}

	/**
	 * Active organizers should be able to promote and demote hosts.
	 */
	public function test_group_organizer_can_manage_host_team_members(): void {
		$group_id     = $this->create_group();
		$organizer_id = self::factory()->user->create();
		$member_id    = self::factory()->user->create(
			array(
				'display_name'  => 'Future Host',
				'user_login'    => 'future-host',
				'user_nicename' => 'future-host',
			)
		);

		\WordPressdotorg\Community_Events\join_group(
			$group_id,
			$organizer_id,
			array(
				'role' => MEMBERSHIP_ROLE_ORGANIZER,
			)
		);
		\WordPressdotorg\Community_Events\join_group( $group_id, $member_id );

		wp_set_current_user( $organizer_id );

		$create_response = $this->post_group_organizer_request(
			$group_id,
			array(
				'role'    => MEMBERSHIP_ROLE_HOST,
				'user_id' => $member_id,
			)
		);
		$create_data     = $create_response->get_data();

		$this->assertSame( 201, $create_response->get_status() );
		$this->assertSame( $member_id, $create_data['user_id'] );
		$this->assertSame( 'future-host', $create_data['user']['slug'] );
		$this->assertSame( MEMBERSHIP_ROLE_HOST, $create_data['role'] );
		$this->assertTrue( \WordPressdotorg\Community_Events\can_user_publish_group_events( $group_id, $member_id ) );

		$membership_id  = (int) $create_data['membership_id'];
		$patch_response = $this->patch_group_organizer_request(
			$group_id,
			$membership_id,
			array(
				'role' => MEMBERSHIP_ROLE_MEMBER,
			)
		);
		$patch_data     = $patch_response->get_data();

		$this->assertSame( 200, $patch_response->get_status() );
		$this->assertSame( MEMBERSHIP_ROLE_MEMBER, $patch_data['role'] );
		$this->assertFalse( \WordPressdotorg\Community_Events\can_user_publish_group_events( $group_id, $member_id ) );
	}

	/**
	 * Only Community Team moderators should be able to assign organizer roles.
	 */
	public function test_only_moderators_can_assign_group_organizers(): void {
		$group_id     = $this->create_group();
		$organizer_id = self::factory()->user->create();
		$member_id    = self::factory()->user->create();
		$admin_id     = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		\WordPressdotorg\Community_Events\join_group(
			$group_id,
			$organizer_id,
			array(
				'role' => MEMBERSHIP_ROLE_ORGANIZER,
			)
		);
		\WordPressdotorg\Community_Events\join_group( $group_id, $member_id );

		wp_set_current_user( $organizer_id );

		$organizer_response = $this->post_group_organizer_request(
			$group_id,
			array(
				'role'    => MEMBERSHIP_ROLE_ORGANIZER,
				'user_id' => $member_id,
			)
		);

		$this->assertSame( 403, $organizer_response->get_status() );
		$this->assertSame( 'wporg_ce_cannot_manage_group_organizers', $organizer_response->as_error()->get_error_code() );

		wp_set_current_user( $admin_id );

		$admin_response = $this->post_group_organizer_request(
			$group_id,
			array(
				'role'    => MEMBERSHIP_ROLE_ORGANIZER,
				'user_id' => $member_id,
			)
		);
		$admin_data     = $admin_response->get_data();

		$this->assertSame( 201, $admin_response->get_status() );
		$this->assertSame( MEMBERSHIP_ROLE_ORGANIZER, $admin_data['role'] );
		$this->assertTrue( \WordPressdotorg\Community_Events\can_user_publish_group_events( $group_id, $member_id ) );
	}

	/**
	 * Hosts and regular members should not be able to manage organizer teams.
	 */
	public function test_group_host_cannot_manage_organizer_team(): void {
		$group_id  = $this->create_group();
		$host_id   = self::factory()->user->create();
		$member_id = self::factory()->user->create();

		\WordPressdotorg\Community_Events\join_group(
			$group_id,
			$host_id,
			array(
				'role' => MEMBERSHIP_ROLE_HOST,
			)
		);
		\WordPressdotorg\Community_Events\join_group( $group_id, $member_id );

		wp_set_current_user( $host_id );

		$response = $this->post_group_organizer_request(
			$group_id,
			array(
				'role'    => MEMBERSHIP_ROLE_HOST,
				'user_id' => $member_id,
			)
		);

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'wporg_ce_cannot_manage_group_organizers', $response->as_error()->get_error_code() );
	}

	/**
	 * Organizer management should only promote active group members.
	 */
	public function test_group_organizer_management_rejects_nonmembers(): void {
		$group_id     = $this->create_group();
		$organizer_id = self::factory()->user->create();
		$outsider_id  = self::factory()->user->create();

		\WordPressdotorg\Community_Events\join_group(
			$group_id,
			$organizer_id,
			array(
				'role' => MEMBERSHIP_ROLE_ORGANIZER,
			)
		);

		wp_set_current_user( $organizer_id );

		$response = $this->post_group_organizer_request(
			$group_id,
			array(
				'role'    => MEMBERSHIP_ROLE_HOST,
				'user_id' => $outsider_id,
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'wporg_ce_group_member_required', $response->as_error()->get_error_code() );
	}

	/**
	 * Public organizer lists should not expose unpublished groups.
	 */
	public function test_group_organizers_endpoint_hides_unpublished_groups(): void {
		$group_id = $this->create_group(
			array(
				'post_status' => 'draft',
			)
		);

		wp_set_current_user( 0 );

		$response = rest_do_request(
			new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/groups/{$group_id}/organizers" )
		);

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'wporg_ce_invalid_relationship_target', $response->as_error()->get_error_code() );
	}

	/**
	 * Public venue discovery should expose published venues filtered by location.
	 */
	public function test_venues_endpoint_returns_filtered_published_venues(): void {
		$switzerland_id = self::factory()->term->create(
			array(
				'name'     => 'Switzerland',
				'slug'     => 'switzerland',
				'taxonomy' => TAXONOMY_COUNTRY,
			)
		);
		$germany_id     = self::factory()->term->create(
			array(
				'name'     => 'Germany',
				'slug'     => 'germany',
				'taxonomy' => TAXONOMY_COUNTRY,
			)
		);
		$zurich_id      = $this->create_venue(
			array(
				'post_name'    => 'zurich-community-space',
				'post_title'   => 'Zurich Community Space',
				'post_content' => 'A local venue for WordPress events.',
				'meta_input'   => array(
					'wporg_ce_accessibility_notes' => 'Step-free entrance.',
					'wporg_ce_address'             => 'Limmatstrasse 123',
					'wporg_ce_city'                => 'Zurich',
					'wporg_ce_latitude'            => 47.384,
					'wporg_ce_longitude'           => 8.532,
					'wporg_ce_online_url'          => 'https://example.org/venue-stream/',
					'wporg_ce_postal_code'         => '8005',
					'wporg_ce_region'              => 'ZH',
				),
			)
		);
		$berlin_id      = $this->create_venue(
			array(
				'post_title' => 'Berlin Community Space',
				'meta_input' => array(
					'wporg_ce_city' => 'Berlin',
				),
			)
		);

		$this->create_venue(
			array(
				'post_status' => 'draft',
				'post_title'  => 'Draft Zurich Venue',
				'meta_input'  => array(
					'wporg_ce_city' => 'Zurich',
				),
			)
		);

		wp_set_object_terms( $zurich_id, array( $switzerland_id ), TAXONOMY_COUNTRY );
		wp_set_object_terms( $berlin_id, array( $germany_id ), TAXONOMY_COUNTRY );

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/venues" );
		$request->set_query_params(
			array(
				'city'     => 'Zurich',
				'country'  => 'switzerland',
				'per_page' => 10,
			)
		);
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 1, $response->get_headers()['X-WP-Total'] );
		$this->assertSame( 1, $response->get_headers()['X-WP-TotalPages'] );
		$this->assertCount( 1, $data );
		$this->assertSame( $zurich_id, $data[0]['id'] );
		$this->assertSame( 'zurich-community-space', $data[0]['slug'] );
		$this->assertStringContainsString( 'zurich-community-space', $data[0]['url'] );
		$this->assertSame( 'Zurich Community Space', $data[0]['title'] );
		$this->assertSame( 'A local venue for WordPress events.', $data[0]['description'] );
		$this->assertSame( 'Limmatstrasse 123', $data[0]['address'] );
		$this->assertSame( 'Zurich', $data[0]['city'] );
		$this->assertSame( 'ZH', $data[0]['region'] );
		$this->assertSame( '8005', $data[0]['postal_code'] );
		$this->assertSame( 47.384, $data[0]['latitude'] );
		$this->assertSame( 8.532, $data[0]['longitude'] );
		$this->assertSame( 'Step-free entrance.', $data[0]['accessibility_notes'] );
		$this->assertSame( 'https://example.org/venue-stream/', $data[0]['online_url'] );
		$this->assertSame( 'switzerland', $data[0]['taxonomies']['countries'][0]['slug'] );
	}

	/**
	 * Public venue details should expose published venue data.
	 */
	public function test_venue_endpoint_returns_published_venue_details(): void {
		$venue_id = $this->create_venue(
			array(
				'post_name'    => 'online-training-room',
				'post_title'   => 'Online Training Room',
				'post_content' => 'A reusable online venue for WordPress training events.',
				'meta_input'   => array(
					'wporg_ce_accessibility_notes' => 'Live captions are available.',
					'wporg_ce_city'                => 'Online',
					'wporg_ce_online_url'          => 'https://example.org/training-room/',
				),
			)
		);

		wp_set_current_user( 0 );

		$response = rest_do_request(
			new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/venues/{$venue_id}" )
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $venue_id, $data['id'] );
		$this->assertSame( 'online-training-room', $data['slug'] );
		$this->assertStringContainsString( 'online-training-room', $data['link'] );
		$this->assertSame( 'Online Training Room', $data['title'] );
		$this->assertSame( 'A reusable online venue for WordPress training events.', $data['description'] );
		$this->assertSame( 'Online', $data['city'] );
		$this->assertSame( 'Live captions are available.', $data['accessibility_notes'] );
		$this->assertSame( 'https://example.org/training-room/', $data['online_url'] );
	}

	/**
	 * Public venue details should not expose unpublished venues.
	 */
	public function test_venue_endpoint_hides_unpublished_venues(): void {
		$venue_id = $this->create_venue(
			array(
				'post_status' => 'draft',
			)
		);

		wp_set_current_user( 0 );

		$response = rest_do_request(
			new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/venues/{$venue_id}" )
		);

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'wporg_ce_invalid_relationship_target', $response->as_error()->get_error_code() );
	}

	/**
	 * Group organizers should be able to create and update reusable venues.
	 */
	public function test_group_organizer_can_create_and_update_venue(): void {
		$organizer_id  = self::factory()->user->create();
		$other_user_id = self::factory()->user->create();
		$group_id      = $this->create_group();

		self::factory()->term->create(
			array(
				'name'     => 'Switzerland',
				'slug'     => 'switzerland',
				'taxonomy' => TAXONOMY_COUNTRY,
			)
		);

		\WordPressdotorg\Community_Events\join_group(
			$group_id,
			$organizer_id,
			array(
				'role' => MEMBERSHIP_ROLE_ORGANIZER,
			)
		);

		wp_set_current_user( $organizer_id );

		$create_request = new WP_REST_Request( WP_REST_Server::CREATABLE, "/{$this->route_namespace()}/venues" );
		$create_request->set_body_params(
			array(
				'accessibility_notes' => 'Step-free entrance.',
				'address'             => 'Limmatstrasse 123',
				'city'                => 'Zurich',
				'countries'           => array( 'switzerland' ),
				'description'         => 'A reusable venue for local events.',
				'group_id'            => $group_id,
				'latitude'            => 47.384,
				'longitude'           => 8.532,
				'online_url'          => 'https://example.org/venue-stream/',
				'postal_code'         => '8005',
				'region'              => 'ZH',
				'title'               => 'Zurich Community Space',
			)
		);
		$create_response = rest_do_request( $create_request );
		$create_data     = $create_response->get_data();

		$this->assertSame( 200, $create_response->get_status() );
		$this->assertSame( $organizer_id, (int) get_post_field( 'post_author', $create_data['id'] ) );
		$this->assertSame( 'Zurich Community Space', $create_data['title'] );
		$this->assertSame( 'A reusable venue for local events.', $create_data['description'] );
		$this->assertSame( 'Limmatstrasse 123', $create_data['address'] );
		$this->assertSame( 'Zurich', $create_data['city'] );
		$this->assertSame( 'ZH', $create_data['region'] );
		$this->assertSame( '8005', $create_data['postal_code'] );
		$this->assertSame( 47.384, $create_data['latitude'] );
		$this->assertSame( 8.532, $create_data['longitude'] );
		$this->assertSame( 'Step-free entrance.', $create_data['accessibility_notes'] );
		$this->assertSame( 'https://example.org/venue-stream/', $create_data['online_url'] );
		$this->assertSame( 'switzerland', $create_data['taxonomies']['countries'][0]['slug'] );

		wp_set_current_user( $other_user_id );

		$unauthorized_request = new WP_REST_Request( 'PATCH', "/{$this->route_namespace()}/venues/{$create_data['id']}" );
		$unauthorized_request->set_body_params(
			array(
				'title' => 'Unauthorized Update',
			)
		);
		$unauthorized_response = rest_do_request( $unauthorized_request );

		$this->assertSame( 403, $unauthorized_response->get_status() );
		$this->assertSame( 'wporg_ce_cannot_manage_venue', $unauthorized_response->as_error()->get_error_code() );

		wp_set_current_user( $organizer_id );

		$update_request = new WP_REST_Request( 'PATCH', "/{$this->route_namespace()}/venues/{$create_data['id']}" );
		$update_request->set_body_params(
			array(
				'city'  => 'Winterthur',
				'title' => 'Updated Community Space',
			)
		);
		$update_response = rest_do_request( $update_request );
		$update_data     = $update_response->get_data();

		$this->assertSame( 200, $update_response->get_status() );
		$this->assertSame( $create_data['id'], $update_data['id'] );
		$this->assertSame( 'Updated Community Space', $update_data['title'] );
		$this->assertSame( 'Winterthur', $update_data['city'] );
		$this->assertSame( 'Limmatstrasse 123', $update_data['address'] );
	}

	/**
	 * Regular group members should not be able to create venues for a group.
	 */
	public function test_group_member_cannot_create_venue(): void {
		$user_id  = self::factory()->user->create();
		$group_id = $this->create_group();

		\WordPressdotorg\Community_Events\join_group( $group_id, $user_id );

		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( WP_REST_Server::CREATABLE, "/{$this->route_namespace()}/venues" );
		$request->set_body_params(
			array(
				'group_id' => $group_id,
				'title'    => 'Member Venue',
			)
		);
		$response = rest_do_request( $request );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'wporg_ce_cannot_create_venue', $response->as_error()->get_error_code() );
	}

	/**
	 * Regular group members should not be able to update or cancel group events.
	 */
	public function test_group_member_cannot_manage_published_event(): void {
		$group_id  = $this->create_group();
		$member_id = self::factory()->user->create();
		$event_id  = $this->create_event(
			$group_id,
			0,
			array(
				'meta_input' => array(
					'wporg_ce_approval_status' => EVENT_APPROVAL_STATUS_APPROVED,
				),
			)
		);

		\WordPressdotorg\Community_Events\join_group( $group_id, $member_id );
		wp_set_current_user( $member_id );

		$update_response = $this->patch_event_request(
			$event_id,
			array(
				'title' => 'Member Edited Meetup',
			)
		);
		$cancel_response = $this->post_event_cancellation_request( $event_id );
		$copy_response   = $this->post_event_copy_request(
			$event_id,
			array(
				'start_utc' => '2026-07-02T18:00:00Z',
			)
		);

		$this->assertSame( 403, $update_response->get_status() );
		$this->assertSame( 'wporg_ce_cannot_manage_event', $update_response->as_error()->get_error_code() );
		$this->assertSame( 403, $cancel_response->get_status() );
		$this->assertSame( 'wporg_ce_cannot_manage_event', $cancel_response->as_error()->get_error_code() );
		$this->assertSame( 403, $copy_response->get_status() );
		$this->assertSame( 'wporg_ce_cannot_manage_event', $copy_response->as_error()->get_error_code() );
		$this->assertSame( 'Community Meetup', get_the_title( $event_id ) );
		$this->assertSame( EVENT_APPROVAL_STATUS_APPROVED, get_post_meta( $event_id, 'wporg_ce_approval_status', true ) );
	}

	/**
	 * Active organizers should be able to update published group events.
	 */
	public function test_group_organizer_can_update_published_event(): void {
		$group_id     = $this->create_group();
		$organizer_id = self::factory()->user->create(
			array(
				'display_name'  => 'Event Organizer',
				'user_login'    => 'event-organizer',
				'user_nicename' => 'event-organizer',
			)
		);
		$cohost_id    = self::factory()->user->create(
			array(
				'display_name'  => 'Updated Co-Host',
				'user_login'    => 'updated-cohost',
				'user_nicename' => 'updated-cohost',
			)
		);
		$venue_id     = $this->create_venue(
			array(
				'post_title' => 'Updated Venue',
			)
		);
		self::factory()->term->create(
			array(
				'name'     => 'Meetup',
				'slug'     => 'meetup',
				'taxonomy' => TAXONOMY_EVENT_TYPE,
			)
		);
		self::factory()->term->create(
			array(
				'name'     => 'Online',
				'slug'     => 'online',
				'taxonomy' => TAXONOMY_EVENT_FORMAT,
			)
		);
		$event_id = $this->create_event(
			$group_id,
			10,
			array(
				'post_author'  => $organizer_id,
				'post_content' => 'Original description.',
				'post_excerpt' => 'Original summary.',
				'meta_input'   => array(
					'wporg_ce_approval_status' => EVENT_APPROVAL_STATUS_APPROVED,
					'wporg_ce_end_utc'         => '2026-06-02T19:00:00Z',
					'wporg_ce_host_user_id'    => $organizer_id,
					'wporg_ce_host_user_ids'   => array( $organizer_id ),
					'wporg_ce_rsvp_policy'     => 'open',
					'wporg_ce_start_utc'       => '2026-06-02T18:00:00Z',
					'wporg_ce_timezone'        => 'Europe/Zurich',
				),
			)
		);

		\WordPressdotorg\Community_Events\join_group(
			$group_id,
			$organizer_id,
			array(
				'role' => MEMBERSHIP_ROLE_ORGANIZER,
			)
		);
		\WordPressdotorg\Community_Events\join_group( $group_id, $cohost_id );
		wp_set_current_user( $organizer_id );

		$response = $this->patch_event_request(
			$event_id,
			array(
				'capacity'      => 24,
				'description'   => 'Updated description.',
				'end_utc'       => '2026-06-03T20:00:00Z',
				'event_formats' => array( 'online' ),
				'event_types'   => array( 'meetup' ),
				'excerpt'       => 'Updated summary.',
				'host_user_ids' => array( $organizer_id, $cohost_id ),
				'online_url'    => 'https://example.org/updated-event/',
				'rsvp_policy'   => 'closed',
				'start_utc'     => '2026-06-03T18:00:00Z',
				'timezone'      => 'Europe/Berlin',
				'title'         => 'Updated Community Meetup',
				'venue_id'      => $venue_id,
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $event_id, $data['id'] );
		$this->assertSame( 'Updated Community Meetup', $data['title'] );
		$this->assertSame( 'Updated description.', $data['description'] );
		$this->assertSame( 'Updated summary.', $data['excerpt'] );
		$this->assertSame( 24, $data['capacity'] );
		$this->assertSame( '2026-06-03T18:00:00Z', $data['start_utc'] );
		$this->assertSame( '2026-06-03T20:00:00Z', $data['end_utc'] );
		$this->assertSame( 'Europe/Berlin', $data['timezone'] );
		$this->assertSame( 'https://example.org/updated-event/', $data['online_url'] );
		$this->assertSame( 'closed', $data['rsvp_policy'] );
		$this->assertSame( $venue_id, $data['venue_id'] );
		$this->assertSame( array( $organizer_id, $cohost_id ), $data['host_user_ids'] );
		$this->assertSame( 'updated-cohost', $data['hosts'][1]['slug'] );
		$this->assertSame( 'meetup', $data['taxonomies']['event_types'][0]['slug'] );
		$this->assertSame( 'online', $data['taxonomies']['event_formats'][0]['slug'] );
	}

	/**
	 * Active organizers should be able to copy an event for a future date.
	 */
	public function test_group_organizer_can_copy_published_event(): void {
		$group_id     = $this->create_group();
		$organizer_id = self::factory()->user->create(
			array(
				'display_name'  => 'Event Organizer',
				'user_login'    => 'copy-organizer',
				'user_nicename' => 'copy-organizer',
			)
		);
		$cohost_id    = self::factory()->user->create(
			array(
				'display_name'  => 'Copy Co-Host',
				'user_login'    => 'copy-cohost',
				'user_nicename' => 'copy-cohost',
			)
		);
		$venue_id     = $this->create_venue(
			array(
				'post_title' => 'Copied Venue',
			)
		);
		self::factory()->term->create(
			array(
				'name'     => 'Meetup',
				'slug'     => 'meetup',
				'taxonomy' => TAXONOMY_EVENT_TYPE,
			)
		);
		self::factory()->term->create(
			array(
				'name'     => 'Hybrid',
				'slug'     => 'hybrid',
				'taxonomy' => TAXONOMY_EVENT_FORMAT,
			)
		);
		$event_id = $this->create_event(
			$group_id,
			40,
			array(
				'post_author'  => $organizer_id,
				'post_content' => 'Original event description.',
				'post_excerpt' => 'Original event summary.',
				'post_title'   => 'Monthly Contributor Meetup',
				'meta_input'   => array(
					'wporg_ce_approval_status' => EVENT_APPROVAL_STATUS_APPROVED,
					'wporg_ce_end_utc'         => '2026-06-02T20:00:00Z',
					'wporg_ce_host_user_id'    => $organizer_id,
					'wporg_ce_host_user_ids'   => array( $organizer_id, $cohost_id ),
					'wporg_ce_online_url'      => 'https://example.org/original-event/',
					'wporg_ce_rsvp_policy'     => 'closed',
					'wporg_ce_start_utc'       => '2026-06-02T18:00:00Z',
					'wporg_ce_timezone'        => 'Europe/Zurich',
					'wporg_ce_venue_id'        => $venue_id,
				),
			)
		);
		wp_set_object_terms( $event_id, array( 'meetup' ), TAXONOMY_EVENT_TYPE );
		wp_set_object_terms( $event_id, array( 'hybrid' ), TAXONOMY_EVENT_FORMAT );

		\WordPressdotorg\Community_Events\join_group(
			$group_id,
			$organizer_id,
			array(
				'role' => MEMBERSHIP_ROLE_ORGANIZER,
			)
		);
		\WordPressdotorg\Community_Events\join_group( $group_id, $cohost_id );
		wp_set_current_user( $organizer_id );

		$response = $this->post_event_copy_request(
			$event_id,
			array(
				'start_utc' => '2026-07-02T18:00:00Z',
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotSame( $event_id, $data['id'] );
		$this->assertSame( $event_id, $data['copied_from_event_id'] );
		$this->assertSame( $group_id, $data['group_id'] );
		$this->assertSame( 'Monthly Contributor Meetup', $data['title'] );
		$this->assertSame( 'Original event description.', $data['description'] );
		$this->assertSame( 'Original event summary.', $data['excerpt'] );
		$this->assertSame( '2026-07-02T18:00:00Z', $data['start_utc'] );
		$this->assertSame( '2026-07-02T20:00:00Z', $data['end_utc'] );
		$this->assertSame( $venue_id, $data['venue_id'] );
		$this->assertSame( 40, $data['capacity'] );
		$this->assertSame( 'closed', $data['rsvp_policy'] );
		$this->assertSame( 'https://example.org/original-event/', $data['online_url'] );
		$this->assertSame( array( $organizer_id, $cohost_id ), $data['host_user_ids'] );
		$this->assertSame( 'meetup', $data['taxonomies']['event_types'][0]['slug'] );
		$this->assertSame( 'hybrid', $data['taxonomies']['event_formats'][0]['slug'] );
		$this->assertSame( 0, $data['event_counts']['attending'] );
		$this->assertSame( 0, $data['event_counts']['waitlisted'] );
		$this->assertSame( $event_id, (int) get_post_meta( $data['id'], 'wporg_ce_copied_from_event_id', true ) );
		$this->assertTrue( comments_open( $data['id'] ) );
	}

	/**
	 * Assigned event hosts should be able to manage their own events.
	 */
	public function test_assigned_event_host_can_update_published_event(): void {
		$group_id     = $this->create_group();
		$organizer_id = self::factory()->user->create();
		$host_id      = self::factory()->user->create(
			array(
				'display_name'  => 'Assigned Event Host',
				'user_login'    => 'assigned-event-host',
				'user_nicename' => 'assigned-event-host',
			)
		);
		$event_id     = $this->create_event(
			$group_id,
			0,
			array(
				'post_author' => $organizer_id,
				'meta_input'  => array(
					'wporg_ce_approval_status' => EVENT_APPROVAL_STATUS_APPROVED,
					'wporg_ce_host_user_id'    => $host_id,
					'wporg_ce_host_user_ids'   => array( $host_id ),
				),
			)
		);

		\WordPressdotorg\Community_Events\join_group( $group_id, $host_id );
		wp_set_current_user( $host_id );

		$response = $this->patch_event_request(
			$event_id,
			array(
				'title' => 'Host Managed Meetup',
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Host Managed Meetup', $data['title'] );
		$this->assertSame( $host_id, $data['host_user_id'] );
		$this->assertSame( array( $host_id ), $data['host_user_ids'] );
		$this->assertSame( 'assigned-event-host', $data['hosts'][0]['slug'] );
	}

	/**
	 * Active organizers should be able to cancel published events without deleting history.
	 */
	public function test_group_organizer_can_cancel_published_event(): void {
		$group_id     = $this->create_group();
		$organizer_id = self::factory()->user->create(
			array(
				'display_name'  => 'Canceling Organizer',
				'user_login'    => 'canceling-organizer',
				'user_nicename' => 'canceling-organizer',
			)
		);
		$event_id     = $this->create_event(
			$group_id,
			0,
			array(
				'meta_input' => array(
					'wporg_ce_approval_status' => EVENT_APPROVAL_STATUS_APPROVED,
					'wporg_ce_rsvp_policy'     => 'open',
				),
			)
		);

		\WordPressdotorg\Community_Events\join_group(
			$group_id,
			$organizer_id,
			array(
				'role' => MEMBERSHIP_ROLE_ORGANIZER,
			)
		);
		wp_set_current_user( $organizer_id );

		$response = $this->post_event_cancellation_request(
			$event_id,
			array(
				'reason' => 'The venue is unavailable.',
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'publish', get_post_status( $event_id ) );
		$this->assertSame( EVENT_APPROVAL_STATUS_CANCELED, $data['approval_status'] );
		$this->assertSame( 'closed', $data['rsvp_policy'] );
		$this->assertSame( 'The venue is unavailable.', $data['cancellation_reason'] );
		$this->assertSame( $organizer_id, $data['canceled_by_user_id'] );
		$this->assertSame( 'canceling-organizer', $data['canceled_by']['slug'] );
		$this->assertNotEmpty( $data['canceled_at_utc'] );

		wp_set_current_user( self::factory()->user->create() );

		$rsvp_response = $this->post_rsvp_request( $event_id );

		$this->assertSame( 400, $rsvp_response->get_status() );
		$this->assertSame( 'wporg_ce_event_canceled', $rsvp_response->as_error()->get_error_code() );
	}

	/**
	 * Public group event discovery should return upcoming published events.
	 */
	public function test_group_events_endpoint_returns_upcoming_published_events(): void {
		$group_id       = $this->create_group();
		$earlier_future = gmdate( 'Y-m-d\TH:i:s\Z', time() + DAY_IN_SECONDS );
		$later_future   = gmdate( 'Y-m-d\TH:i:s\Z', time() + ( 2 * DAY_IN_SECONDS ) );

		$future_event_id = $this->create_event(
			$group_id,
			0,
			array(
				'post_title'         => 'First Future Meetup',
				'wporg_ce_start_utc' => $earlier_future,
			)
		);
		$later_event_id  = $this->create_event(
			$group_id,
			0,
			array(
				'post_title'         => 'Second Future Meetup',
				'wporg_ce_start_utc' => $later_future,
			)
		);
		$this->create_event(
			$group_id,
			0,
			array(
				'post_status'        => 'pending',
				'post_title'         => 'Pending Future Meetup',
				'wporg_ce_start_utc' => $earlier_future,
			)
		);
		$this->create_event(
			$group_id,
			0,
			array(
				'post_title'         => 'Past Meetup',
				'wporg_ce_start_utc' => gmdate( 'Y-m-d\TH:i:s\Z', time() - DAY_IN_SECONDS ),
			)
		);

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/groups/{$group_id}/events" );
		$request->set_query_params(
			array(
				'per_page' => 1,
			)
		);
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 2, $response->get_headers()['X-WP-Total'] );
		$this->assertSame( 2, $response->get_headers()['X-WP-TotalPages'] );
		$this->assertCount( 1, $data );
		$this->assertSame( $future_event_id, $data[0]['id'] );
		$this->assertSame( $group_id, $data[0]['group_id'] );
		$this->assertSame( 'First Future Meetup', $data[0]['title'] );
		$this->assertSame( $earlier_future, $data[0]['start_utc'] );

		$second_page_request = new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/groups/{$group_id}/events" );
		$second_page_request->set_query_params(
			array(
				'page'     => 2,
				'per_page' => 1,
			)
		);
		$second_page_response = rest_do_request( $second_page_request );
		$second_page_data     = $second_page_response->get_data();

		$this->assertSame( 200, $second_page_response->get_status() );
		$this->assertSame( 2, $second_page_response->get_headers()['X-WP-Total'] );
		$this->assertSame( 2, $second_page_response->get_headers()['X-WP-TotalPages'] );
		$this->assertCount( 1, $second_page_data );
		$this->assertSame( $later_event_id, $second_page_data[0]['id'] );
	}

	/**
	 * Public event discovery should return paginated events across groups.
	 */
	public function test_events_endpoint_returns_paginated_upcoming_events(): void {
		$first_group_id  = $this->create_group(
			array(
				'post_title' => 'WordPress Zurich',
			)
		);
		$second_group_id = $this->create_group(
			array(
				'post_title' => 'WordPress Basel',
			)
		);
		$draft_group_id  = $this->create_group(
			array(
				'post_status' => 'draft',
				'post_title'  => 'Draft WordPress Group',
			)
		);
		$earlier_future  = gmdate( 'Y-m-d\TH:i:s\Z', time() + DAY_IN_SECONDS );
		$later_future    = gmdate( 'Y-m-d\TH:i:s\Z', time() + ( 2 * DAY_IN_SECONDS ) );

		$first_event_id  = $this->create_event(
			$second_group_id,
			0,
			array(
				'post_title'         => 'First Public Event',
				'wporg_ce_start_utc' => $earlier_future,
			)
		);
		$second_event_id = $this->create_event(
			$first_group_id,
			0,
			array(
				'post_title'         => 'Second Public Event',
				'wporg_ce_start_utc' => $later_future,
			)
		);
		$this->create_event(
			$first_group_id,
			0,
			array(
				'post_status'        => 'pending',
				'post_title'         => 'Pending Event',
				'wporg_ce_start_utc' => $earlier_future,
			)
		);
		$this->create_event(
			$draft_group_id,
			0,
			array(
				'post_title'         => 'Event In Draft Group',
				'wporg_ce_start_utc' => $earlier_future,
			)
		);

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/events" );
		$request->set_query_params(
			array(
				'per_page' => 1,
			)
		);
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 2, $response->get_headers()['X-WP-Total'] );
		$this->assertSame( 2, $response->get_headers()['X-WP-TotalPages'] );
		$this->assertCount( 1, $data );
		$this->assertSame( $first_event_id, $data[0]['id'] );
		$this->assertSame( $second_group_id, $data[0]['group_id'] );

		$second_page_request = new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/events" );
		$second_page_request->set_query_params(
			array(
				'page'     => 2,
				'per_page' => 1,
			)
		);
		$second_page_response = rest_do_request( $second_page_request );
		$second_page_data     = $second_page_response->get_data();

		$this->assertSame( 200, $second_page_response->get_status() );
		$this->assertSame( $second_event_id, $second_page_data[0]['id'] );

		$group_request = new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/events" );
		$group_request->set_query_params(
			array(
				'group_id' => $first_group_id,
			)
		);
		$group_response = rest_do_request( $group_request );
		$group_data     = $group_response->get_data();

		$this->assertSame( 200, $group_response->get_status() );
		$this->assertSame( 1, $group_response->get_headers()['X-WP-Total'] );
		$this->assertSame( $second_event_id, $group_data[0]['id'] );

		$draft_group_request = new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/events" );
		$draft_group_request->set_query_params(
			array(
				'group_id' => $draft_group_id,
			)
		);
		$draft_group_response = rest_do_request( $draft_group_request );

		$this->assertSame( 404, $draft_group_response->get_status() );
		$this->assertSame( 'wporg_ce_invalid_relationship_target', $draft_group_response->as_error()->get_error_code() );
	}

	/**
	 * Public event discovery should filter by event taxonomy slugs.
	 */
	public function test_events_endpoint_filters_by_event_taxonomies(): void {
		$group_id     = $this->create_group();
		$meetup_id    = self::factory()->term->create(
			array(
				'name'     => 'Meetup',
				'slug'     => 'meetup',
				'taxonomy' => TAXONOMY_EVENT_TYPE,
			)
		);
		$workshop_id  = self::factory()->term->create(
			array(
				'name'     => 'Workshop',
				'slug'     => 'workshop',
				'taxonomy' => TAXONOMY_EVENT_TYPE,
			)
		);
		$online_id    = self::factory()->term->create(
			array(
				'name'     => 'Online',
				'slug'     => 'online',
				'taxonomy' => TAXONOMY_EVENT_FORMAT,
			)
		);
		$in_person_id = self::factory()->term->create(
			array(
				'name'     => 'In person',
				'slug'     => 'in-person',
				'taxonomy' => TAXONOMY_EVENT_FORMAT,
			)
		);

		$meetup_event_id   = $this->create_event(
			$group_id,
			0,
			array(
				'post_title' => 'Local Meetup',
			)
		);
		$workshop_event_id = $this->create_event(
			$group_id,
			0,
			array(
				'post_title' => 'Online Workshop',
			)
		);

		wp_set_object_terms( $meetup_event_id, array( $meetup_id ), TAXONOMY_EVENT_TYPE );
		wp_set_object_terms( $meetup_event_id, array( $in_person_id ), TAXONOMY_EVENT_FORMAT );
		wp_set_object_terms( $workshop_event_id, array( $workshop_id ), TAXONOMY_EVENT_TYPE );
		wp_set_object_terms( $workshop_event_id, array( $online_id ), TAXONOMY_EVENT_FORMAT );

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/events" );
		$request->set_query_params(
			array(
				'event_format' => 'online',
				'event_type'   => 'workshop',
				'per_page'     => 10,
			)
		);
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 1, $response->get_headers()['X-WP-Total'] );
		$this->assertCount( 1, $data );
		$this->assertSame( $workshop_event_id, $data[0]['id'] );
		$this->assertSame( 'workshop', $data[0]['taxonomies']['event_types'][0]['slug'] );
		$this->assertSame( 'online', $data[0]['taxonomies']['event_formats'][0]['slug'] );

		$group_request = new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/groups/{$group_id}/events" );
		$group_request->set_query_params(
			array(
				'event_type' => 'meetup',
				'per_page'   => 10,
			)
		);
		$group_response = rest_do_request( $group_request );
		$group_data     = $group_response->get_data();

		$this->assertSame( 200, $group_response->get_status() );
		$this->assertSame( 1, $group_response->get_headers()['X-WP-Total'] );
		$this->assertCount( 1, $group_data );
		$this->assertSame( $meetup_event_id, $group_data[0]['id'] );
	}

	/**
	 * Public event discovery should let SQL paginate by event start time.
	 */
	public function test_public_event_collection_query_paginates_by_start_time(): void {
		$group_id       = $this->create_group();
		$draft_group_id = $this->create_group(
			array(
				'post_status' => 'draft',
				'post_title'  => 'Draft WordPress Group',
			)
		);
		$first_start    = gmdate( 'Y-m-d\TH:i:s\Z', time() + DAY_IN_SECONDS );
		$second_start   = gmdate( 'Y-m-d\TH:i:s\Z', time() + ( 2 * DAY_IN_SECONDS ) );
		$third_start    = gmdate( 'Y-m-d\TH:i:s\Z', time() + ( 3 * DAY_IN_SECONDS ) );

		$this->create_event(
			$group_id,
			0,
			array(
				'post_title'         => 'First SQL-Paginated Event',
				'wporg_ce_start_utc' => $first_start,
			)
		);
		$second_event_id = $this->create_event(
			$group_id,
			0,
			array(
				'post_title'         => 'Second SQL-Paginated Event',
				'wporg_ce_start_utc' => $second_start,
			)
		);
		$this->create_event(
			$group_id,
			0,
			array(
				'post_title'         => 'Third SQL-Paginated Event',
				'wporg_ce_start_utc' => $third_start,
			)
		);
		$this->create_event(
			$group_id,
			0,
			array(
				'post_title'         => 'Past SQL-Paginated Event',
				'wporg_ce_start_utc' => gmdate( 'Y-m-d\TH:i:s\Z', time() - DAY_IN_SECONDS ),
			)
		);
		$this->create_event(
			$group_id,
			0,
			array(
				'post_status'        => 'pending',
				'post_title'         => 'Pending SQL-Paginated Event',
				'wporg_ce_start_utc' => $first_start,
			)
		);
		$this->create_event(
			$draft_group_id,
			0,
			array(
				'post_title'         => 'Draft Group SQL-Paginated Event',
				'wporg_ce_start_utc' => $first_start,
			)
		);
		self::factory()->post->create(
			array(
				'post_parent' => $group_id,
				'post_status' => 'publish',
				'post_title'  => 'Unscheduled Event',
				'post_type'   => POST_TYPE_EVENT,
				'meta_input'  => array(
					'wporg_ce_group_id' => $group_id,
				),
			)
		);

		$query = \WordPressdotorg\Community_Events\get_public_event_collection_query( 0, 'upcoming', 2, 1 );

		$this->assertSame( 3, (int) $query->found_posts );
		$this->assertSame( 3, (int) $query->max_num_pages );
		$this->assertSame( 1, $query->post_count );
		$this->assertSame( array( $second_event_id ), array_map( 'intval', $query->posts ) );
		$this->assertSame( 1, $query->get( 'posts_per_page' ) );
		$this->assertSame( 2, $query->get( 'paged' ) );
		$this->assertSame( array(), $query->get( 'post_parent__in' ) );
		$this->assertTrue( $query->get( 'wporg_ce_require_public_parent_group' ) );
	}

	/**
	 * Public group event discovery should support past event filtering.
	 */
	public function test_group_events_endpoint_filters_past_events(): void {
		$group_id      = $this->create_group();
		$past_start    = gmdate( 'Y-m-d\TH:i:s\Z', time() - DAY_IN_SECONDS );
		$past_event_id = $this->create_event(
			$group_id,
			0,
			array(
				'post_title'         => 'Past Meetup',
				'wporg_ce_start_utc' => $past_start,
			)
		);

		$this->create_event(
			$group_id,
			0,
			array(
				'post_title'         => 'Future Meetup',
				'wporg_ce_start_utc' => gmdate( 'Y-m-d\TH:i:s\Z', time() + DAY_IN_SECONDS ),
			)
		);

		$request = new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/groups/{$group_id}/events" );
		$request->set_query_params(
			array(
				'timeframe' => 'past',
			)
		);
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 1, $response->get_headers()['X-WP-Total'] );
		$this->assertCount( 1, $data );
		$this->assertSame( $past_event_id, $data[0]['id'] );
		$this->assertSame( $past_start, $data[0]['start_utc'] );
	}

	/**
	 * Public event details should expose published event data.
	 */
	public function test_event_endpoint_returns_published_event_details(): void {
		$group_id      = $this->create_group();
		$host_id       = self::factory()->user->create(
			array(
				'display_name'  => 'Event Host',
				'user_login'    => 'event-detail-host',
				'user_nicename' => 'event-detail-host',
			)
		);
		$cohost_id     = self::factory()->user->create(
			array(
				'display_name'  => 'Event Detail Co-Host',
				'user_login'    => 'event-detail-cohost',
				'user_nicename' => 'event-detail-cohost',
			)
		);
		$country_id    = self::factory()->term->create(
			array(
				'name'     => 'Switzerland',
				'slug'     => 'switzerland',
				'taxonomy' => TAXONOMY_COUNTRY,
			)
		);
		$event_type_id = self::factory()->term->create(
			array(
				'name'     => 'Meetup',
				'slug'     => 'meetup',
				'taxonomy' => TAXONOMY_EVENT_TYPE,
			)
		);
		$format_id     = self::factory()->term->create(
			array(
				'name'     => 'In person',
				'slug'     => 'in-person',
				'taxonomy' => TAXONOMY_EVENT_FORMAT,
			)
		);
		$language_id   = self::factory()->term->create(
			array(
				'name'     => 'German',
				'slug'     => 'de',
				'taxonomy' => TAXONOMY_LANGUAGE,
			)
		);
		$topic_id      = self::factory()->term->create(
			array(
				'name'     => 'Accessibility',
				'slug'     => 'accessibility',
				'taxonomy' => TAXONOMY_TOPIC,
			)
		);
		$venue_id      = $this->create_venue(
			array(
				'post_name'    => 'zurich-community-space',
				'post_title'   => 'Zurich Community Space',
				'post_content' => 'A local venue for WordPress events.',
				'meta_input'   => array(
					'wporg_ce_accessibility_notes' => 'Step-free entrance.',
					'wporg_ce_address'             => 'Limmatstrasse 123',
					'wporg_ce_city'                => 'Zurich',
					'wporg_ce_latitude'            => 47.384,
					'wporg_ce_longitude'           => 8.532,
					'wporg_ce_online_url'          => 'https://example.org/venue-stream/',
					'wporg_ce_postal_code'         => '8005',
					'wporg_ce_region'              => 'ZH',
				),
			)
		);

		wp_set_object_terms( $venue_id, array( $country_id ), TAXONOMY_COUNTRY );

		$event_id = $this->create_event(
			$group_id,
			1,
			array(
				'post_author'  => $host_id,
				'post_title'   => 'Published Community Meetup',
				'post_content' => 'A public event description.',
				'post_excerpt' => 'A public event summary.',
				'meta_input'   => array(
					'wporg_ce_approval_status' => EVENT_APPROVAL_STATUS_APPROVED,
					'wporg_ce_end_utc'         => '2026-06-02T19:30:00Z',
					'wporg_ce_host_user_id'    => $host_id,
					'wporg_ce_host_user_ids'   => array( $host_id, $cohost_id ),
					'wporg_ce_online_url'      => 'https://example.org/event-stream/',
					'wporg_ce_rsvp_policy'     => 'open',
					'wporg_ce_start_utc'       => '2026-06-02T18:00:00Z',
					'wporg_ce_timezone'        => 'Europe/Zurich',
					'wporg_ce_venue_id'        => $venue_id,
				),
			)
		);

		wp_set_object_terms( $event_id, array( $country_id ), TAXONOMY_COUNTRY );
		wp_set_object_terms( $event_id, array( $event_type_id ), TAXONOMY_EVENT_TYPE );
		wp_set_object_terms( $event_id, array( $format_id ), TAXONOMY_EVENT_FORMAT );
		wp_set_object_terms( $event_id, array( $language_id ), TAXONOMY_LANGUAGE );
		wp_set_object_terms( $event_id, array( $topic_id ), TAXONOMY_TOPIC );

		\WordPressdotorg\Community_Events\rsvp_to_event( $event_id, self::factory()->user->create() );
		\WordPressdotorg\Community_Events\rsvp_to_event( $event_id, self::factory()->user->create() );

		wp_set_current_user( 0 );

		$response = rest_do_request(
			new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/events/{$event_id}" )
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $event_id, $data['id'] );
		$this->assertSame( $group_id, $data['group_id'] );
		$this->assertSame( $host_id, $data['host_user_id'] );
		$this->assertSame( array( $host_id, $cohost_id ), $data['host_user_ids'] );
		$this->assertSame( 'event-detail-host', $data['host']['slug'] );
		$this->assertSame( 'Event Host', $data['host']['name'] );
		$this->assertSame( 'https://profiles.wordpress.org/event-detail-host/', $data['host']['profile_url'] );
		$this->assertSame( 'event-detail-host', $data['hosts'][0]['slug'] );
		$this->assertSame( 'event-detail-cohost', $data['hosts'][1]['slug'] );
		$this->assertStringContainsString( 'published-community-meetup', $data['link'] );
		$this->assertSame( 'Published Community Meetup', $data['title'] );
		$this->assertSame( 'A public event description.', $data['description'] );
		$this->assertSame( 'A public event summary.', $data['excerpt'] );
		$this->assertSame( 'publish', $data['status'] );
		$this->assertSame( EVENT_APPROVAL_STATUS_APPROVED, $data['approval_status'] );
		$this->assertSame( $venue_id, $data['venue_id'] );
		$this->assertSame( $venue_id, $data['venue']['id'] );
		$this->assertSame( 'zurich-community-space', $data['venue']['slug'] );
		$this->assertStringContainsString( 'zurich-community-space', $data['venue']['link'] );
		$this->assertStringContainsString( 'zurich-community-space', $data['venue']['url'] );
		$this->assertSame( 'Zurich Community Space', $data['venue']['title'] );
		$this->assertSame( 'A local venue for WordPress events.', $data['venue']['description'] );
		$this->assertSame( 'Limmatstrasse 123', $data['venue']['address'] );
		$this->assertSame( 'Zurich', $data['venue']['city'] );
		$this->assertSame( 'ZH', $data['venue']['region'] );
		$this->assertSame( '8005', $data['venue']['postal_code'] );
		$this->assertSame( 47.384, $data['venue']['latitude'] );
		$this->assertSame( 8.532, $data['venue']['longitude'] );
		$this->assertSame( 'Step-free entrance.', $data['venue']['accessibility_notes'] );
		$this->assertSame( 'https://example.org/venue-stream/', $data['venue']['online_url'] );
		$this->assertSame( 'switzerland', $data['venue']['taxonomies']['countries'][0]['slug'] );
		$this->assertSame( '2026-06-02T18:00:00Z', $data['start_utc'] );
		$this->assertSame( '2026-06-02T19:30:00Z', $data['end_utc'] );
		$this->assertSame( 'Europe/Zurich', $data['timezone'] );
		$this->assertSame( 1, $data['capacity'] );
		$this->assertSame( 'https://example.org/event-stream/', $data['online_url'] );
		$this->assertSame( 'open', $data['rsvp_policy'] );
		$this->assertSame( 1, $data['event_counts']['attending'] );
		$this->assertSame( 1, $data['event_counts']['waitlisted'] );
		$this->assertSame( 'switzerland', $data['taxonomies']['countries'][0]['slug'] );
		$this->assertSame( 'meetup', $data['taxonomies']['event_types'][0]['slug'] );
		$this->assertSame( 'in-person', $data['taxonomies']['event_formats'][0]['slug'] );
		$this->assertSame( 'de', $data['taxonomies']['languages'][0]['slug'] );
		$this->assertSame( 'accessibility', $data['taxonomies']['topics'][0]['slug'] );
	}

	/**
	 * Public event calendar exports should return an iCalendar file.
	 */
	public function test_event_calendar_endpoint_returns_ics_file(): void {
		$group_id = $this->create_group();
		$venue_id = $this->create_venue(
			array(
				'post_title' => 'Zurich Community Space',
				'meta_input' => array(
					'wporg_ce_address'     => 'Limmatstrasse 123',
					'wporg_ce_city'        => 'Zurich',
					'wporg_ce_postal_code' => '8005',
					'wporg_ce_region'      => 'ZH',
				),
			)
		);
		$event_id = $this->create_event(
			$group_id,
			0,
			array(
				'post_content' => 'Bring laptops, snacks; and questions.',
				'post_name'    => 'calendar-meetup',
				'post_title'   => 'Calendar Meetup',
				'meta_input'   => array(
					'wporg_ce_end_utc'         => '2026-06-02T20:00:00Z',
					'wporg_ce_start_utc'       => '2026-06-02T18:00:00Z',
					'wporg_ce_approval_status' => EVENT_APPROVAL_STATUS_APPROVED,
					'wporg_ce_venue_id'        => $venue_id,
				),
			)
		);

		wp_set_current_user( 0 );

		$response = rest_do_request(
			new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/events/{$event_id}/calendar.ics" )
		);
		$calendar = (string) $response->get_data();
		$headers  = $response->get_headers();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'text/calendar; charset=utf-8', $headers['Content-Type'] );
		$this->assertSame( 'inline; filename="calendar-meetup.ics"', $headers['Content-Disposition'] );
		$this->assertSame( '1', $headers['X-WPorg-Community-Events-Calendar'] );
		$this->assertStringContainsString( "BEGIN:VCALENDAR\r\n", $calendar );
		$this->assertStringContainsString( 'BEGIN:VEVENT', $calendar );
		$this->assertStringContainsString( "UID:wporg-community-event-{$event_id}@wordpress.org", $calendar );
		$this->assertMatchesRegularExpression( '/DTSTAMP:\d{8}T\d{6}Z/', $calendar );
		$this->assertStringContainsString( 'DTSTART:20260602T180000Z', $calendar );
		$this->assertStringContainsString( 'DTEND:20260602T200000Z', $calendar );
		$this->assertStringContainsString( 'SUMMARY:Calendar Meetup', $calendar );
		$this->assertStringContainsString( 'DESCRIPTION:Bring laptops\\, snacks\\; and questions.', $calendar );
		$this->assertStringContainsString( 'LOCATION:Zurich Community Space\\, Limmatstrasse 123\\, 8005 Zurich\\, ZH', $calendar );
		$this->assertStringContainsString( 'URL:', $calendar );
		$this->assertStringContainsString( 'STATUS:CONFIRMED', $calendar );
		$this->assertStringEndsWith( "END:VCALENDAR\r\n", $calendar );
	}

	/**
	 * Group calendar exports should include upcoming public events for that group.
	 */
	public function test_group_calendar_endpoint_returns_upcoming_events(): void {
		$group_id       = $this->create_group(
			array(
				'post_title' => 'WordPress Zurich',
			)
		);
		$other_group_id = $this->create_group(
			array(
				'post_title' => 'WordPress Basel',
			)
		);

		$this->create_event(
			$group_id,
			0,
			array(
				'post_title'         => 'Future Calendar Meetup',
				'wporg_ce_start_utc' => gmdate( 'Y-m-d\TH:i:s\Z', time() + DAY_IN_SECONDS ),
			)
		);
		$this->create_event(
			$group_id,
			0,
			array(
				'post_status'        => 'pending',
				'post_title'         => 'Pending Calendar Meetup',
				'wporg_ce_start_utc' => gmdate( 'Y-m-d\TH:i:s\Z', time() + DAY_IN_SECONDS ),
			)
		);
		$this->create_event(
			$group_id,
			0,
			array(
				'post_title'         => 'Past Calendar Meetup',
				'wporg_ce_start_utc' => gmdate( 'Y-m-d\TH:i:s\Z', time() - DAY_IN_SECONDS ),
			)
		);
		$this->create_event(
			$other_group_id,
			0,
			array(
				'post_title'         => 'Other Group Calendar Meetup',
				'wporg_ce_start_utc' => gmdate( 'Y-m-d\TH:i:s\Z', time() + DAY_IN_SECONDS ),
			)
		);

		wp_set_current_user( 0 );

		$response = rest_do_request(
			new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/groups/{$group_id}/calendar.ics" )
		);
		$calendar = (string) $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertStringContainsString( 'X-WR-CALNAME:WordPress Zurich Events', $calendar );
		$this->assertStringContainsString( 'SUMMARY:Future Calendar Meetup', $calendar );
		$this->assertStringNotContainsString( 'Pending Calendar Meetup', $calendar );
		$this->assertStringNotContainsString( 'Past Calendar Meetup', $calendar );
		$this->assertStringNotContainsString( 'Other Group Calendar Meetup', $calendar );
	}

	/**
	 * Calendar exports should not expose unpublished groups or events.
	 */
	public function test_calendar_endpoints_hide_unpublished_objects(): void {
		$group_id = $this->create_group(
			array(
				'post_status' => 'draft',
			)
		);
		$event_id = $this->create_event( $group_id );

		wp_set_current_user( 0 );

		$group_response = rest_do_request(
			new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/groups/{$group_id}/calendar.ics" )
		);
		$event_response = rest_do_request(
			new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/events/{$event_id}/calendar.ics" )
		);

		$this->assertSame( 404, $group_response->get_status() );
		$this->assertSame( 'wporg_ce_invalid_relationship_target', $group_response->as_error()->get_error_code() );
		$this->assertSame( 404, $event_response->get_status() );
		$this->assertSame( 'wporg_ce_invalid_relationship_target', $event_response->as_error()->get_error_code() );
	}

	/**
	 * Public event details should not expose unpublished events.
	 */
	public function test_event_endpoint_hides_unpublished_events(): void {
		$group_id = $this->create_group();
		$event_id = $this->create_event(
			$group_id,
			0,
			array(
				'post_status' => 'pending',
			)
		);

		wp_set_current_user( 0 );

		$response = rest_do_request(
			new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/events/{$event_id}" )
		);

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'wporg_ce_invalid_relationship_target', $response->as_error()->get_error_code() );
	}

	/**
	 * Public event details should not expose events in unpublished groups.
	 */
	public function test_event_endpoint_hides_events_in_unpublished_groups(): void {
		$group_id = $this->create_group(
			array(
				'post_status' => 'draft',
			)
		);
		$event_id = $this->create_event( $group_id );

		wp_set_current_user( 0 );

		$response = rest_do_request(
			new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/events/{$event_id}" )
		);

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'wporg_ce_invalid_relationship_target', $response->as_error()->get_error_code() );
	}

	/**
	 * Current-user RSVP collections should expose upcoming active event plans.
	 */
	public function test_current_user_rsvps_endpoint_returns_upcoming_active_rsvps(): void {
		$group_id       = $this->create_group();
		$user_id        = self::factory()->user->create();
		$other_user_id  = self::factory()->user->create();
		$later_event    = $this->create_event(
			$group_id,
			0,
			array(
				'post_title'         => 'Later Meetup',
				'wporg_ce_start_utc' => gmdate( 'Y-m-d\TH:i:s\Z', time() + ( 3 * DAY_IN_SECONDS ) ),
			)
		);
		$earlier_event  = $this->create_event(
			$group_id,
			0,
			array(
				'post_title'         => 'Earlier Meetup',
				'wporg_ce_start_utc' => gmdate( 'Y-m-d\TH:i:s\Z', time() + DAY_IN_SECONDS ),
			)
		);
		$past_event     = $this->create_event(
			$group_id,
			0,
			array(
				'post_title'         => 'Past Meetup',
				'wporg_ce_start_utc' => gmdate( 'Y-m-d\TH:i:s\Z', time() - DAY_IN_SECONDS ),
			)
		);
		$canceled_event = $this->create_event(
			$group_id,
			0,
			array(
				'post_title'         => 'Canceled RSVP Meetup',
				'wporg_ce_start_utc' => gmdate( 'Y-m-d\TH:i:s\Z', time() + ( 2 * DAY_IN_SECONDS ) ),
			)
		);
		$other_event    = $this->create_event(
			$group_id,
			0,
			array(
				'post_title'         => 'Other User Meetup',
				'wporg_ce_start_utc' => gmdate( 'Y-m-d\TH:i:s\Z', time() + DAY_IN_SECONDS ),
			)
		);

		\WordPressdotorg\Community_Events\rsvp_to_event( $later_event, $user_id );
		\WordPressdotorg\Community_Events\rsvp_to_event(
			$earlier_event,
			$user_id,
			array(
				'status' => RSVP_STATUS_WAITLISTED,
			)
		);
		\WordPressdotorg\Community_Events\rsvp_to_event( $past_event, $user_id );
		\WordPressdotorg\Community_Events\rsvp_to_event(
			$canceled_event,
			$user_id,
			array(
				'status' => RSVP_STATUS_NOT_ATTENDING,
			)
		);
		\WordPressdotorg\Community_Events\rsvp_to_event( $other_event, $other_user_id );

		wp_set_current_user( $user_id );

		$response = rest_do_request(
			new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/me/rsvps" )
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 2, $response->get_headers()['X-WP-Total'] );
		$this->assertCount( 2, $data );
		$this->assertSame( $earlier_event, $data[0]['event_id'] );
		$this->assertSame( RSVP_STATUS_WAITLISTED, $data[0]['status'] );
		$this->assertSame( 'Earlier Meetup', $data[0]['event']['title'] );
		$this->assertSame( $group_id, $data[0]['event']['group_id'] );
		$this->assertSame( $later_event, $data[1]['event_id'] );
		$this->assertSame( RSVP_STATUS_ATTENDING, $data[1]['status'] );

		$waitlist_request = new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/me/rsvps" );
		$waitlist_request->set_query_params(
			array(
				'status' => RSVP_STATUS_WAITLISTED,
			)
		);
		$waitlist_response = rest_do_request( $waitlist_request );
		$waitlist_data     = $waitlist_response->get_data();

		$this->assertSame( 200, $waitlist_response->get_status() );
		$this->assertSame( 1, $waitlist_response->get_headers()['X-WP-Total'] );
		$this->assertCount( 1, $waitlist_data );
		$this->assertSame( $earlier_event, $waitlist_data[0]['event_id'] );
	}

	/**
	 * A logged-in user should be able to RSVP and cancel through REST.
	 */
	public function test_rsvp_endpoint_creates_waitlists_and_cancels_rsvps(): void {
		$group_id      = $this->create_group();
		$event_id      = $this->create_event( $group_id, 1 );
		$attendee_id   = self::factory()->user->create(
			array(
				'display_name'  => 'Meetup Attendee',
				'user_login'    => 'meetup-attendee',
				'user_nicename' => 'meetup-attendee',
			)
		);
		$waitlisted_id = self::factory()->user->create();

		wp_set_current_user( $attendee_id );

		$attending_response = $this->post_rsvp_request(
			$event_id,
			array(
				'guest_count' => 1,
			)
		);
		$attending_data     = $attending_response->get_data();

		$this->assertSame( 200, $attending_response->get_status() );
		$this->assertSame( RSVP_STATUS_ATTENDING, $attending_data['status'] );
		$this->assertSame( ATTENDANCE_STATUS_NOT_CHECKED_IN, $attending_data['attendance_status'] );
		$this->assertSame( 'meetup-attendee', $attending_data['user']['slug'] );
		$this->assertSame( 'https://profiles.wordpress.org/meetup-attendee/', $attending_data['user']['profile_url'] );
		$this->assertSame( 1, $attending_data['guest_count'] );
		$this->assertSame( 1, $attending_data['event_counts']['attending'] );
		$this->assertSame( 0, $attending_data['event_counts']['waitlisted'] );

		wp_set_current_user( $waitlisted_id );

		$waitlisted_response = $this->post_rsvp_request( $event_id );
		$waitlisted_data     = $waitlisted_response->get_data();

		$this->assertSame( 200, $waitlisted_response->get_status() );
		$this->assertSame( RSVP_STATUS_WAITLISTED, $waitlisted_data['status'] );
		$this->assertSame( 1, $waitlisted_data['waitlist_position'] );
		$this->assertSame( 1, $waitlisted_data['event_counts']['attending'] );
		$this->assertSame( 1, $waitlisted_data['event_counts']['waitlisted'] );

		$delete_response = rest_do_request(
			new WP_REST_Request( WP_REST_Server::DELETABLE, "/{$this->route_namespace()}/events/{$event_id}/rsvp" )
		);
		$delete_data     = $delete_response->get_data();

		$this->assertSame( 200, $delete_response->get_status() );
		$this->assertSame( RSVP_STATUS_NOT_ATTENDING, $delete_data['status'] );
		$this->assertSame( ATTENDANCE_STATUS_NOT_COMING, $delete_data['attendance_status'] );
		$this->assertSame( 1, $delete_data['event_counts']['attending'] );
		$this->assertSame( 0, $delete_data['event_counts']['waitlisted'] );
	}

	/**
	 * Canceling an attending RSVP should promote the next waitlisted user.
	 */
	public function test_rsvp_endpoint_promotes_waitlist_when_attendee_cancels(): void {
		$group_id      = $this->create_group();
		$event_id      = $this->create_event( $group_id, 1 );
		$attendee_id   = self::factory()->user->create();
		$waitlisted_id = self::factory()->user->create();

		wp_set_current_user( $attendee_id );
		$this->post_rsvp_request( $event_id );

		wp_set_current_user( $waitlisted_id );
		$this->post_rsvp_request( $event_id );

		$waitlist_rsvp_id = \WordPressdotorg\Community_Events\get_event_rsvp_id( $event_id, $waitlisted_id );

		wp_set_current_user( $attendee_id );

		$delete_response = rest_do_request(
			new WP_REST_Request( WP_REST_Server::DELETABLE, "/{$this->route_namespace()}/events/{$event_id}/rsvp" )
		);
		$delete_data     = $delete_response->get_data();

		$this->assertSame( 200, $delete_response->get_status() );
		$this->assertSame( RSVP_STATUS_NOT_ATTENDING, $delete_data['status'] );
		$this->assertSame( 1, $delete_data['event_counts']['attending'] );
		$this->assertSame( 0, $delete_data['event_counts']['waitlisted'] );
		$this->assertSame( RSVP_STATUS_ATTENDING, get_post_meta( $waitlist_rsvp_id, 'wporg_ce_status', true ) );
		$this->assertSame( 0, (int) get_post_meta( $waitlist_rsvp_id, 'wporg_ce_waitlist_position', true ) );
	}

	/**
	 * Required RSVP questions should be answered before joining an event.
	 */
	public function test_rsvp_endpoint_requires_answers_for_required_questions(): void {
		$user_id  = self::factory()->user->create();
		$group_id = $this->create_group();
		$event_id = $this->create_event(
			$group_id,
			0,
			array(
				'meta_input' => array(
					'wporg_ce_rsvp_questions' => array(
						array(
							'id'       => 'experience',
							'label'    => 'What is your WordPress experience?',
							'type'     => 'textarea',
							'required' => true,
						),
						array(
							'id'      => 'track',
							'label'   => 'Preferred track',
							'type'    => 'select',
							'choices' => array( 'Development', 'Design' ),
						),
					),
				),
			)
		);

		wp_set_current_user( $user_id );

		$response = $this->post_rsvp_request( $event_id );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'wporg_ce_rsvp_answer_required', $response->as_error()->get_error_code() );

		$response = $this->post_rsvp_request(
			$event_id,
			array(
				'answers' => array(
					'experience' => "I've organized a contributor table before.",
					'track'      => 'Design',
					'ignored'    => 'This should be discarded.',
				),
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( RSVP_STATUS_ATTENDING, $data['status'] );
		$this->assertSame( "I've organized a contributor table before.", $data['answers']['experience'] );
		$this->assertSame( 'Design', $data['answers']['track'] );
		$this->assertArrayNotHasKey( 'ignored', $data['answers'] );
		$this->assertSame( $data['answers'], get_post_meta( $data['id'], 'wporg_ce_answers', true ) );
	}

	/**
	 * Clients should not be able to force a waitlisted status directly.
	 */
	public function test_rsvp_endpoint_rejects_client_forced_waitlist_status(): void {
		$user_id  = self::factory()->user->create();
		$group_id = $this->create_group();
		$event_id = $this->create_event( $group_id );

		wp_set_current_user( $user_id );

		$response = $this->post_rsvp_request(
			$event_id,
			array(
				'status' => RSVP_STATUS_WAITLISTED,
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->as_error()->get_error_code() );
		$this->assertSame( 0, \WordPressdotorg\Community_Events\get_event_rsvp_id( $event_id, $user_id ) );
	}

	/**
	 * Public attendee lists should include only visible RSVPs for the requested status.
	 */
	public function test_event_attendees_endpoint_returns_public_attendees(): void {
		$group_id      = $this->create_group();
		$event_id      = $this->create_event( $group_id );
		$attendee_id   = self::factory()->user->create(
			array(
				'display_name'  => 'Visible Attendee',
				'user_login'    => 'visible-attendee',
				'user_nicename' => 'visible-attendee',
			)
		);
		$second_id     = self::factory()->user->create();
		$private_id    = self::factory()->user->create();
		$not_going_id  = self::factory()->user->create();
		$waitlisted_id = self::factory()->user->create(
			array(
				'display_name'  => 'Waitlisted Attendee',
				'user_login'    => 'waitlisted-attendee',
				'user_nicename' => 'waitlisted-attendee',
			)
		);
		$attendee_rsvp = \WordPressdotorg\Community_Events\rsvp_to_event( $event_id, $attendee_id );
		$waitlist_rsvp = \WordPressdotorg\Community_Events\rsvp_to_event(
			$event_id,
			$waitlisted_id,
			array(
				'status' => RSVP_STATUS_WAITLISTED,
			)
		);

		\WordPressdotorg\Community_Events\rsvp_to_event( $event_id, $second_id );
		\WordPressdotorg\Community_Events\rsvp_to_event(
			$event_id,
			$private_id,
			array(
				'visibility' => RELATIONSHIP_VISIBILITY_PRIVATE,
			)
		);
		\WordPressdotorg\Community_Events\rsvp_to_event(
			$event_id,
			$not_going_id,
			array(
				'status' => RSVP_STATUS_NOT_ATTENDING,
			)
		);

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/events/{$event_id}/attendees" );
		$request->set_query_params(
			array(
				'per_page' => 1,
			)
		);
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 1, $response->get_headers()['X-WP-Total'] );
		$this->assertCount( 1, $data );
		$this->assertSame( $attendee_rsvp, $data[0]['id'] );
		$this->assertSame( $attendee_rsvp, $data[0]['rsvp_id'] );
		$this->assertSame( $attendee_id, $data[0]['user_id'] );
		$this->assertSame( 'visible-attendee', $data[0]['user']['slug'] );
		$this->assertSame( 'https://profiles.wordpress.org/visible-attendee/', $data[0]['user']['profile_url'] );
		$this->assertSame( RSVP_STATUS_ATTENDING, $data[0]['status'] );
		$this->assertSame( ATTENDANCE_STATUS_NOT_CHECKED_IN, $data[0]['attendance_status'] );

		$waitlist_request = new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/events/{$event_id}/attendees" );
		$waitlist_request->set_query_params(
			array(
				'status' => RSVP_STATUS_WAITLISTED,
			)
		);
		$waitlist_response = rest_do_request( $waitlist_request );
		$waitlist_data     = $waitlist_response->get_data();

		$this->assertSame( 200, $waitlist_response->get_status() );
		$this->assertSame( 1, $waitlist_response->get_headers()['X-WP-Total'] );
		$this->assertCount( 1, $waitlist_data );
		$this->assertSame( $waitlist_rsvp, $waitlist_data[0]['rsvp_id'] );
		$this->assertSame( 'waitlisted-attendee', $waitlist_data[0]['user']['slug'] );
		$this->assertSame( RSVP_STATUS_WAITLISTED, $waitlist_data[0]['status'] );
		$this->assertSame( 1, $waitlist_data[0]['waitlist_position'] );

		$organizer_id = self::factory()->user->create();

		\WordPressdotorg\Community_Events\join_group(
			$group_id,
			$organizer_id,
			array(
				'role' => MEMBERSHIP_ROLE_ORGANIZER,
			)
		);
		wp_set_current_user( $organizer_id );

		$manager_request = new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/events/{$event_id}/attendees" );
		$manager_request->set_query_params(
			array(
				'per_page' => 10,
			)
		);
		$manager_response = rest_do_request( $manager_request );
		$manager_data     = $manager_response->get_data();

		$this->assertSame( 200, $manager_response->get_status() );
		$this->assertSame( 3, $manager_response->get_headers()['X-WP-Total'] );
		$this->assertSameSets(
			array( $attendee_id, $second_id, $private_id ),
			wp_list_pluck( $manager_data, 'user_id' )
		);
	}

	/**
	 * RSVP answers should only be visible to the attendee and event managers.
	 */
	public function test_rsvp_answers_are_hidden_from_public_attendee_lists(): void {
		$group_id     = $this->create_group();
		$event_id     = $this->create_event(
			$group_id,
			0,
			array(
				'meta_input' => array(
					'wporg_ce_rsvp_questions' => array(
						array(
							'id'       => 'topic',
							'label'    => 'What topic do you want covered?',
							'type'     => 'text',
							'required' => true,
						),
					),
				),
			)
		);
		$attendee_id  = self::factory()->user->create();
		$organizer_id = self::factory()->user->create();
		$rsvp_id      = \WordPressdotorg\Community_Events\rsvp_to_event(
			$event_id,
			$attendee_id,
			array(
				'answers' => array(
					'topic' => 'Plugin security reviews',
				),
			)
		);

		$this->assertNotWPError( $rsvp_id );

		wp_set_current_user( 0 );

		$public_response = rest_do_request(
			new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/events/{$event_id}/attendees" )
		);
		$public_data     = $public_response->get_data();

		$this->assertSame( 200, $public_response->get_status() );
		$this->assertSame( array(), $public_data[0]['answers'] );

		wp_set_current_user( $attendee_id );

		$rsvp_response = rest_do_request(
			new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/events/{$event_id}/rsvp" )
		);
		$rsvp_data     = $rsvp_response->get_data();

		$this->assertSame( 200, $rsvp_response->get_status() );
		$this->assertSame( 'Plugin security reviews', $rsvp_data['answers']['topic'] );

		\WordPressdotorg\Community_Events\join_group(
			$group_id,
			$organizer_id,
			array(
				'role' => MEMBERSHIP_ROLE_ORGANIZER,
			)
		);
		wp_set_current_user( $organizer_id );

		$manager_response = rest_do_request(
			new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/events/{$event_id}/attendees" )
		);
		$manager_data     = $manager_response->get_data();

		$this->assertSame( 200, $manager_response->get_status() );
		$this->assertSame( 'Plugin security reviews', $manager_data[0]['answers']['topic'] );
	}

	/**
	 * Past-event attendees should be able to leave public event feedback.
	 */
	public function test_event_feedback_endpoint_creates_and_lists_feedback(): void {
		$group_id    = $this->create_group();
		$event_id    = $this->create_event(
			$group_id,
			0,
			array(
				'wporg_ce_start_utc' => gmdate( 'Y-m-d\TH:i:s\Z', time() - DAY_IN_SECONDS ),
				'meta_input'         => array(
					'wporg_ce_approval_status' => EVENT_APPROVAL_STATUS_APPROVED,
					'wporg_ce_rsvp_policy'     => 'open',
				),
			)
		);
		$attendee_id = self::factory()->user->create(
			array(
				'display_name'  => 'Feedback Attendee',
				'user_login'    => 'feedback-attendee',
				'user_nicename' => 'feedback-attendee',
			)
		);

		\WordPressdotorg\Community_Events\rsvp_to_event( $event_id, $attendee_id );
		wp_set_current_user( $attendee_id );

		$response = $this->post_event_feedback_request(
			$event_id,
			array(
				'rating' => 5,
				'review' => 'Great contributor session.',
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( POST_TYPE_FEEDBACK, get_post_type( $data['id'] ) );
		$this->assertSame( $event_id, $data['event_id'] );
		$this->assertSame( $group_id, $data['group_id'] );
		$this->assertSame( $attendee_id, $data['user_id'] );
		$this->assertSame( 'feedback-attendee', $data['user']['slug'] );
		$this->assertSame( 5, $data['rating'] );
		$this->assertSame( 'Great contributor session.', $data['review'] );
		$this->assertNotEmpty( $data['created_at_utc'] );

		wp_set_current_user( 0 );

		$list_request = new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/events/{$event_id}/feedback" );
		$list_data    = rest_do_request( $list_request )->get_data();

		$this->assertCount( 1, $list_data );
		$this->assertSame( $data['id'], $list_data[0]['id'] );

		$event_response = rest_do_request(
			new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/events/{$event_id}" )
		);
		$event_data     = $event_response->get_data();

		$this->assertSame( 1, $event_data['feedback_summary']['count'] );
		$this->assertSame( 5.0, $event_data['feedback_summary']['average_rating'] );
	}

	/**
	 * Event feedback should enforce attendee, timing, rating, and duplicate rules.
	 */
	public function test_event_feedback_endpoint_validates_submission_rules(): void {
		$group_id      = $this->create_group();
		$future_event  = $this->create_event( $group_id );
		$past_event    = $this->create_event(
			$group_id,
			0,
			array(
				'wporg_ce_start_utc' => gmdate( 'Y-m-d\TH:i:s\Z', time() - DAY_IN_SECONDS ),
				'meta_input'         => array(
					'wporg_ce_approval_status' => EVENT_APPROVAL_STATUS_APPROVED,
					'wporg_ce_rsvp_policy'     => 'open',
				),
			)
		);
		$attendee_id   = self::factory()->user->create();
		$organizer_id  = self::factory()->user->create();
		$not_coming_id = self::factory()->user->create();

		\WordPressdotorg\Community_Events\rsvp_to_event( $future_event, $attendee_id );
		\WordPressdotorg\Community_Events\rsvp_to_event( $past_event, $attendee_id );
		\WordPressdotorg\Community_Events\rsvp_to_event(
			$past_event,
			$not_coming_id,
			array(
				'status' => RSVP_STATUS_NOT_ATTENDING,
			)
		);
		\WordPressdotorg\Community_Events\join_group(
			$group_id,
			$organizer_id,
			array(
				'role' => MEMBERSHIP_ROLE_ORGANIZER,
			)
		);
		\WordPressdotorg\Community_Events\rsvp_to_event( $past_event, $organizer_id );

		wp_set_current_user( $attendee_id );

		$future_response = $this->post_event_feedback_request(
			$future_event,
			array(
				'rating' => 5,
			)
		);

		$this->assertSame( 400, $future_response->get_status() );
		$this->assertSame( 'wporg_ce_event_feedback_not_open', $future_response->as_error()->get_error_code() );

		$low_rating_response = $this->post_event_feedback_request(
			$past_event,
			array(
				'rating' => 2,
			)
		);

		$this->assertSame( 400, $low_rating_response->get_status() );
		$this->assertSame( 'wporg_ce_event_feedback_review_required', $low_rating_response->as_error()->get_error_code() );

		$first_response = $this->post_event_feedback_request(
			$past_event,
			array(
				'rating' => 4,
			)
		);

		$this->assertSame( 201, $first_response->get_status() );

		$duplicate_response = $this->post_event_feedback_request(
			$past_event,
			array(
				'rating' => 5,
			)
		);

		$this->assertSame( 409, $duplicate_response->get_status() );
		$this->assertSame( 'wporg_ce_event_feedback_exists', $duplicate_response->as_error()->get_error_code() );

		wp_set_current_user( $not_coming_id );

		$not_attendee_response = $this->post_event_feedback_request(
			$past_event,
			array(
				'rating' => 5,
			)
		);

		$this->assertSame( 403, $not_attendee_response->get_status() );
		$this->assertSame( 'wporg_ce_event_feedback_attendee_required', $not_attendee_response->as_error()->get_error_code() );

		wp_set_current_user( $organizer_id );

		$organizer_response = $this->post_event_feedback_request(
			$past_event,
			array(
				'rating' => 5,
			)
		);

		$this->assertSame( 403, $organizer_response->get_status() );
		$this->assertSame( 'wporg_ce_cannot_feedback_own_event', $organizer_response->as_error()->get_error_code() );
	}

	/**
	 * Event managers should be able to add attendees and update check-in status.
	 */
	public function test_event_manager_can_create_and_update_event_attendee(): void {
		$group_id    = $this->create_group();
		$manager_id  = self::factory()->user->create();
		$attendee_id = self::factory()->user->create(
			array(
				'display_name'  => 'Managed Attendee',
				'user_login'    => 'managed-attendee',
				'user_nicename' => 'managed-attendee',
			)
		);
		$event_id    = $this->create_event(
			$group_id,
			0,
			array(
				'meta_input' => array(
					'wporg_ce_approval_status' => EVENT_APPROVAL_STATUS_APPROVED,
					'wporg_ce_host_user_id'    => $manager_id,
					'wporg_ce_host_user_ids'   => array( $manager_id ),
					'wporg_ce_rsvp_questions'  => array(
						array(
							'id'       => 'access_needs',
							'label'    => 'Accessibility needs',
							'type'     => 'textarea',
							'required' => true,
						),
					),
					'wporg_ce_rsvp_policy'     => 'open',
				),
			)
		);

		\WordPressdotorg\Community_Events\join_group( $group_id, $manager_id );
		\WordPressdotorg\Community_Events\join_group( $group_id, $attendee_id );
		wp_set_current_user( $manager_id );

		$create_response = $this->post_event_attendee_request(
			$event_id,
			array(
				'attendance_status' => ATTENDANCE_STATUS_CHECKED_IN,
				'answers'           => array(
					'access_needs' => 'Reserved front-row seating.',
				),
				'guest_count'       => 2,
				'user_id'           => $attendee_id,
			)
		);
		$create_data     = $create_response->get_data();

		$this->assertSame( 201, $create_response->get_status() );
		$this->assertSame( $create_data['rsvp_id'], $create_data['id'] );
		$this->assertSame( $attendee_id, $create_data['user_id'] );
		$this->assertSame( 'managed-attendee', $create_data['user']['slug'] );
		$this->assertSame( RSVP_STATUS_ATTENDING, $create_data['status'] );
		$this->assertSame( ATTENDANCE_STATUS_CHECKED_IN, $create_data['attendance_status'] );
		$this->assertSame( 'Reserved front-row seating.', $create_data['answers']['access_needs'] );
		$this->assertSame( 2, $create_data['guest_count'] );
		$this->assertSame( $manager_id, $create_data['attendance_updated_by_user_id'] );
		$this->assertNotEmpty( $create_data['attended_at_utc'] );
		$this->assertSame( 1, (int) get_post_meta( $event_id, 'wporg_ce_rsvp_count', true ) );

		$rsvp_id        = (int) $create_data['rsvp_id'];
		$no_show_update = $this->patch_event_attendee_request(
			$event_id,
			$rsvp_id,
			array(
				'attendance_status' => ATTENDANCE_STATUS_NO_SHOW,
			)
		);
		$no_show_data   = $no_show_update->get_data();

		$this->assertSame(
			200,
			$no_show_update->get_status(),
			$no_show_update->as_error() ? $no_show_update->as_error()->get_error_code() : ''
		);
		$this->assertSame( RSVP_STATUS_ATTENDING, $no_show_data['status'] );
		$this->assertSame( ATTENDANCE_STATUS_NO_SHOW, $no_show_data['attendance_status'] );
		$this->assertSame( 2, $no_show_data['guest_count'] );
		$this->assertSame( '', $no_show_data['attended_at_utc'] );
		$this->assertSame( $manager_id, $no_show_data['attendance_updated_by_user_id'] );

		$not_coming_update = $this->patch_event_attendee_request(
			$event_id,
			$rsvp_id,
			array(
				'attendance_status' => ATTENDANCE_STATUS_NOT_COMING,
				'status'            => RSVP_STATUS_NOT_ATTENDING,
			)
		);
		$not_coming_data   = $not_coming_update->get_data();

		$this->assertSame( 200, $not_coming_update->get_status() );
		$this->assertSame( RSVP_STATUS_NOT_ATTENDING, $not_coming_data['status'] );
		$this->assertSame( ATTENDANCE_STATUS_NOT_COMING, $not_coming_data['attendance_status'] );
		$this->assertSame( 0, (int) get_post_meta( $event_id, 'wporg_ce_rsvp_count', true ) );
	}

	/**
	 * Regular group members should not be able to manage event attendees.
	 */
	public function test_group_member_cannot_manage_event_attendees(): void {
		$group_id    = $this->create_group();
		$member_id   = self::factory()->user->create();
		$attendee_id = self::factory()->user->create();
		$event_id    = $this->create_event(
			$group_id,
			0,
			array(
				'meta_input' => array(
					'wporg_ce_approval_status' => EVENT_APPROVAL_STATUS_APPROVED,
					'wporg_ce_rsvp_policy'     => 'open',
				),
			)
		);

		\WordPressdotorg\Community_Events\join_group( $group_id, $member_id );
		\WordPressdotorg\Community_Events\join_group( $group_id, $attendee_id );
		wp_set_current_user( $member_id );

		$response = $this->post_event_attendee_request(
			$event_id,
			array(
				'user_id' => $attendee_id,
			)
		);

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'wporg_ce_cannot_manage_event', $response->as_error()->get_error_code() );
	}

	/**
	 * Event managers should only be able to add active group members as attendees.
	 */
	public function test_event_attendee_management_rejects_nonmembers(): void {
		$group_id     = $this->create_group();
		$organizer_id = self::factory()->user->create();
		$outsider_id  = self::factory()->user->create();
		$event_id     = $this->create_event(
			$group_id,
			0,
			array(
				'meta_input' => array(
					'wporg_ce_approval_status' => EVENT_APPROVAL_STATUS_APPROVED,
					'wporg_ce_rsvp_policy'     => 'open',
				),
			)
		);

		\WordPressdotorg\Community_Events\join_group(
			$group_id,
			$organizer_id,
			array(
				'role' => MEMBERSHIP_ROLE_ORGANIZER,
			)
		);
		wp_set_current_user( $organizer_id );

		$response = $this->post_event_attendee_request(
			$event_id,
			array(
				'user_id' => $outsider_id,
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'wporg_ce_invalid_event_attendee', $response->as_error()->get_error_code() );
	}

	/**
	 * Event managers should be able to email public and private attendees.
	 */
	public function test_event_manager_can_message_attendees(): void {
		$group_id     = $this->create_group();
		$organizer_id = self::factory()->user->create(
			array(
				'display_name' => 'Event Organizer',
				'user_email'   => 'organizer@example.org',
			)
		);
		$public_id    = self::factory()->user->create(
			array(
				'user_email' => 'public@example.org',
			)
		);
		$private_id   = self::factory()->user->create(
			array(
				'user_email' => 'private@example.org',
			)
		);
		$waitlist_id  = self::factory()->user->create(
			array(
				'user_email' => 'waitlist@example.org',
			)
		);
		$not_going_id = self::factory()->user->create(
			array(
				'user_email' => 'not-going@example.org',
			)
		);
		$event_id     = $this->create_event(
			$group_id,
			0,
			array(
				'post_title' => 'Attendee Message Meetup',
				'meta_input' => array(
					'wporg_ce_approval_status' => EVENT_APPROVAL_STATUS_APPROVED,
					'wporg_ce_rsvp_policy'     => 'open',
				),
			)
		);
		$sent_mail    = array();
		$mail_capture = static function ( $preempt, $atts ) use ( &$sent_mail ) {
			unset( $preempt );

			$sent_mail[] = $atts;

			return true;
		};

		\WordPressdotorg\Community_Events\join_group(
			$group_id,
			$organizer_id,
			array(
				'role' => MEMBERSHIP_ROLE_ORGANIZER,
			)
		);
		\WordPressdotorg\Community_Events\rsvp_to_event( $event_id, $public_id );
		\WordPressdotorg\Community_Events\rsvp_to_event(
			$event_id,
			$private_id,
			array(
				'visibility' => RELATIONSHIP_VISIBILITY_PRIVATE,
			)
		);
		\WordPressdotorg\Community_Events\rsvp_to_event(
			$event_id,
			$waitlist_id,
			array(
				'status' => RSVP_STATUS_WAITLISTED,
			)
		);
		\WordPressdotorg\Community_Events\rsvp_to_event(
			$event_id,
			$not_going_id,
			array(
				'status' => RSVP_STATUS_NOT_ATTENDING,
			)
		);

		wp_set_current_user( $organizer_id );
		add_filter( 'pre_wp_mail', $mail_capture, 10, 2 );

		try {
			$response = $this->post_event_message_request(
				$event_id,
				array(
					'message' => 'Please use the side entrance tonight.',
					'subject' => 'Venue update',
				)
			);
			$data     = $response->get_data();

			$this->assertSame( 200, $response->get_status() );
			$this->assertSame( 3, $data['recipient_count'] );
			$this->assertSame( 3, $data['sent_count'] );
			$this->assertSameSets(
				array( 'public@example.org', 'private@example.org', 'waitlist@example.org' ),
				wp_list_pluck( $sent_mail, 'to' )
			);
			$this->assertSame( '[Attendee Message Meetup] Venue update', $sent_mail[0]['subject'] );
			$this->assertStringContainsString( 'Please use the side entrance tonight.', $sent_mail[0]['message'] );
			$this->assertStringContainsString( 'Sent by: Event Organizer', $sent_mail[0]['message'] );

			$sent_mail        = array();
			$waitlist_message = $this->post_event_message_request(
				$event_id,
				array(
					'message' => 'A spot may open soon.',
					'status'  => RSVP_STATUS_WAITLISTED,
					'subject' => 'Waitlist update',
				)
			);
			$waitlist_data    = $waitlist_message->get_data();

			$this->assertSame( 200, $waitlist_message->get_status() );
			$this->assertSame( 1, $waitlist_data['recipient_count'] );
			$this->assertSame( array( 'waitlist@example.org' ), wp_list_pluck( $sent_mail, 'to' ) );
		} finally {
			remove_filter( 'pre_wp_mail', $mail_capture, 10 );
		}
	}

	/**
	 * Attendee messaging should require event-manager privileges and recipients.
	 */
	public function test_event_message_endpoint_rejects_invalid_requests(): void {
		$group_id     = $this->create_group();
		$organizer_id = self::factory()->user->create();
		$member_id    = self::factory()->user->create();
		$event_id     = $this->create_event( $group_id );

		\WordPressdotorg\Community_Events\join_group(
			$group_id,
			$organizer_id,
			array(
				'role' => MEMBERSHIP_ROLE_ORGANIZER,
			)
		);
		\WordPressdotorg\Community_Events\join_group( $group_id, $member_id );

		wp_set_current_user( $member_id );

		$forbidden = $this->post_event_message_request(
			$event_id,
			array(
				'message' => 'Message body.',
				'subject' => 'Message subject',
			)
		);

		$this->assertSame( 403, $forbidden->get_status() );
		$this->assertSame( 'wporg_ce_cannot_manage_event', $forbidden->as_error()->get_error_code() );

		wp_set_current_user( $organizer_id );

		$empty = $this->post_event_message_request(
			$event_id,
			array(
				'message' => 'Message body.',
				'subject' => 'Message subject',
			)
		);

		$this->assertSame( 400, $empty->get_status() );
		$this->assertSame( 'wporg_ce_event_message_no_recipients', $empty->as_error()->get_error_code() );
	}

	/**
	 * Public attendee lists should not expose events in unpublished groups.
	 */
	public function test_event_attendees_endpoint_hides_events_in_unpublished_groups(): void {
		$group_id = $this->create_group(
			array(
				'post_status' => 'draft',
			)
		);
		$event_id = $this->create_event( $group_id );

		wp_set_current_user( 0 );

		$response = rest_do_request(
			new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/events/{$event_id}/attendees" )
		);

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'wporg_ce_invalid_relationship_target', $response->as_error()->get_error_code() );
	}

	/**
	 * Event managers should be able to export active attendees as CSV.
	 */
	public function test_event_manager_can_export_attendee_csv(): void {
		$group_id     = $this->create_group();
		$organizer_id = self::factory()->user->create();
		$public_id    = self::factory()->user->create(
			array(
				'display_name'  => 'Public Attendee',
				'user_email'    => 'public-export@example.org',
				'user_login'    => 'public-export',
				'user_nicename' => 'public-export',
			)
		);
		$private_id   = self::factory()->user->create(
			array(
				'display_name'  => 'Private Attendee',
				'user_email'    => 'private-export@example.org',
				'user_login'    => 'private-export',
				'user_nicename' => 'private-export',
			)
		);
		$waitlist_id  = self::factory()->user->create(
			array(
				'display_name'  => 'Waitlist Attendee',
				'user_email'    => 'waitlist-export@example.org',
				'user_login'    => 'waitlist-export',
				'user_nicename' => 'waitlist-export',
			)
		);
		$not_going_id = self::factory()->user->create(
			array(
				'display_name'  => 'Not Going',
				'user_email'    => 'not-going-export@example.org',
				'user_login'    => 'not-going-export',
				'user_nicename' => 'not-going-export',
			)
		);
		$event_id     = $this->create_event(
			$group_id,
			0,
			array(
				'post_name'  => 'export-meetup',
				'post_title' => 'Export Meetup',
				'meta_input' => array(
					'wporg_ce_approval_status' => EVENT_APPROVAL_STATUS_APPROVED,
					'wporg_ce_rsvp_policy'     => 'open',
					'wporg_ce_rsvp_questions'  => array(
						array(
							'id'    => 'dietary_needs',
							'label' => 'Dietary needs',
							'type'  => 'textarea',
						),
						array(
							'id'      => 'preferred_track',
							'label'   => 'Preferred track',
							'type'    => 'select',
							'choices' => array( 'Core', 'Docs' ),
						),
					),
				),
			)
		);

		\WordPressdotorg\Community_Events\join_group(
			$group_id,
			$organizer_id,
			array(
				'role' => MEMBERSHIP_ROLE_ORGANIZER,
			)
		);
		\WordPressdotorg\Community_Events\rsvp_to_event(
			$event_id,
			$public_id,
			array(
				'guest_count' => 2,
				'answers'     => array(
					'dietary_needs'   => 'Vegetarian',
					'preferred_track' => 'Core',
				),
			)
		);
		\WordPressdotorg\Community_Events\rsvp_to_event(
			$event_id,
			$private_id,
			array(
				'visibility' => RELATIONSHIP_VISIBILITY_PRIVATE,
				'answers'    => array(
					'dietary_needs' => 'No peanuts',
				),
			)
		);
		\WordPressdotorg\Community_Events\rsvp_to_event(
			$event_id,
			$waitlist_id,
			array(
				'status'  => RSVP_STATUS_WAITLISTED,
				'answers' => array(
					'preferred_track' => 'Docs',
				),
			)
		);
		\WordPressdotorg\Community_Events\rsvp_to_event(
			$event_id,
			$not_going_id,
			array(
				'status'  => RSVP_STATUS_NOT_ATTENDING,
				'answers' => array(
					'dietary_needs' => 'Do not export',
				),
			)
		);

		wp_set_current_user( $organizer_id );

		$response = rest_do_request(
			new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/events/{$event_id}/attendees.csv" )
		);
		$headers  = $response->get_headers();
		$csv      = (string) $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'text/csv; charset=utf-8', $headers['Content-Type'] );
		$this->assertSame( 'attachment; filename="export-meetup-attendees.csv"', $headers['Content-Disposition'] );
		$this->assertSame( '1', $headers['X-WPorg-Community-Events-CSV'] );
		$this->assertStringStartsWith( '"RSVP ID","User ID",Name,"Profile URL","RSVP Status"', $csv );
		$this->assertStringContainsString( '"Public Attendee",https://profiles.wordpress.org/public-export/,attending,not_checked_in,2,0,public', $csv );
		$this->assertStringContainsString( '"Private Attendee",https://profiles.wordpress.org/private-export/,attending,not_checked_in,0,0,private', $csv );
		$this->assertStringContainsString( '"Waitlist Attendee",https://profiles.wordpress.org/waitlist-export/,waitlisted,not_checked_in,0,1,public', $csv );
		$this->assertStringContainsString( '"Question: Dietary needs","Question: Preferred track"', $csv );
		$this->assertStringContainsString( 'Vegetarian,Core', $csv );
		$this->assertStringContainsString( '"No peanuts",', $csv );
		$this->assertStringContainsString( ',Docs', $csv );
		$this->assertStringNotContainsString( 'Not Going', $csv );
		$this->assertStringNotContainsString( 'Do not export', $csv );
		$this->assertStringNotContainsString( 'private-export@example.org', $csv );
	}

	/**
	 * Attendee exports should require event-manager privileges.
	 */
	public function test_event_attendee_export_requires_event_manager(): void {
		$group_id  = $this->create_group();
		$member_id = self::factory()->user->create();
		$event_id  = $this->create_event( $group_id );

		\WordPressdotorg\Community_Events\join_group( $group_id, $member_id );
		wp_set_current_user( $member_id );

		$response = rest_do_request(
			new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/events/{$event_id}/attendees.csv" )
		);

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'wporg_ce_cannot_manage_event', $response->as_error()->get_error_code() );
	}

	/**
	 * Dispatch a POST group suggestion request.
	 *
	 * @param array $params Request parameters.
	 *
	 * @return \WP_REST_Response
	 */
	private function post_group_suggestion_request( array $params = array() ): \WP_REST_Response {
		$request = new WP_REST_Request( WP_REST_Server::CREATABLE, "/{$this->route_namespace()}/group-suggestions" );
		$request->set_body_params(
			array_merge(
				array(
					'location_label' => 'Zurich, Switzerland',
					'title'          => 'WordPress Zurich',
				),
				$params
			)
		);

		return rest_do_request( $request );
	}

	/**
	 * Dispatch a GET current-user group suggestions request.
	 *
	 * @param array $params Request parameters.
	 *
	 * @return \WP_REST_Response
	 */
	private function get_current_user_group_suggestions_request( array $params = array() ): \WP_REST_Response {
		$request = new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/me/group-suggestions" );
		$request->set_query_params( $params );

		return rest_do_request( $request );
	}

	/**
	 * Dispatch a GET group suggestions moderation request.
	 *
	 * @param array $params Request parameters.
	 *
	 * @return \WP_REST_Response
	 */
	private function get_group_suggestions_request( array $params = array() ): \WP_REST_Response {
		$request = new WP_REST_Request( WP_REST_Server::READABLE, "/{$this->route_namespace()}/group-suggestions" );
		$request->set_query_params( $params );

		return rest_do_request( $request );
	}

	/**
	 * Dispatch a PATCH group suggestion request.
	 *
	 * @param int   $suggestion_id Group suggestion post ID.
	 * @param array $params        Request parameters.
	 *
	 * @return \WP_REST_Response
	 */
	private function patch_group_suggestion_request( int $suggestion_id, array $params = array() ): \WP_REST_Response {
		$request = new WP_REST_Request( 'PATCH', "/{$this->route_namespace()}/group-suggestions/{$suggestion_id}" );
		$request->set_body_params( $params );

		return rest_do_request( $request );
	}

	/**
	 * Dispatch a PATCH group request.
	 *
	 * @param int   $group_id Group post ID.
	 * @param array $params   Request parameters.
	 *
	 * @return \WP_REST_Response
	 */
	private function patch_group_request( int $group_id, array $params = array() ): \WP_REST_Response {
		$request = new WP_REST_Request( 'PATCH', "/{$this->route_namespace()}/groups/{$group_id}" );
		$request->set_body_params( $params );

		return rest_do_request( $request );
	}

	/**
	 * Dispatch a POST group organizer request.
	 *
	 * @param int   $group_id Group post ID.
	 * @param array $params   Request parameters.
	 *
	 * @return \WP_REST_Response
	 */
	private function post_group_organizer_request( int $group_id, array $params = array() ): \WP_REST_Response {
		$request = new WP_REST_Request( WP_REST_Server::CREATABLE, "/{$this->route_namespace()}/groups/{$group_id}/organizers" );
		$request->set_body_params( $params );

		return rest_do_request( $request );
	}

	/**
	 * Dispatch a PATCH group organizer request.
	 *
	 * @param int   $group_id      Group post ID.
	 * @param int   $membership_id Membership relationship post ID.
	 * @param array $params        Request parameters.
	 *
	 * @return \WP_REST_Response
	 */
	private function patch_group_organizer_request( int $group_id, int $membership_id, array $params = array() ): \WP_REST_Response {
		$request = new WP_REST_Request( 'PATCH', "/{$this->route_namespace()}/groups/{$group_id}/organizers/{$membership_id}" );
		$request->set_body_params( $params );

		return rest_do_request( $request );
	}

	/**
	 * Dispatch a POST RSVP request.
	 *
	 * @param int   $event_id Event post ID.
	 * @param array $params   Request parameters.
	 *
	 * @return \WP_REST_Response
	 */
	private function post_rsvp_request( int $event_id, array $params = array() ): \WP_REST_Response {
		$request = new WP_REST_Request( WP_REST_Server::CREATABLE, "/{$this->route_namespace()}/events/{$event_id}/rsvp" );
		$request->set_body_params( $params );

		return rest_do_request( $request );
	}

	/**
	 * Dispatch a POST attendee-management request.
	 *
	 * @param int   $event_id Event post ID.
	 * @param array $params   Request parameters.
	 *
	 * @return \WP_REST_Response
	 */
	private function post_event_attendee_request( int $event_id, array $params = array() ): \WP_REST_Response {
		$request = new WP_REST_Request( WP_REST_Server::CREATABLE, "/{$this->route_namespace()}/events/{$event_id}/attendees" );
		$request->set_body_params( $params );

		return rest_do_request( $request );
	}

	/**
	 * Dispatch a PATCH attendee-management request.
	 *
	 * @param int   $event_id Event post ID.
	 * @param int   $rsvp_id  RSVP relationship post ID.
	 * @param array $params   Request parameters.
	 *
	 * @return \WP_REST_Response
	 */
	private function patch_event_attendee_request( int $event_id, int $rsvp_id, array $params = array() ): \WP_REST_Response {
		$request = new WP_REST_Request( 'PATCH', "/{$this->route_namespace()}/events/{$event_id}/attendees/{$rsvp_id}" );
		$request->set_body_params( $params );

		return rest_do_request( $request );
	}

	/**
	 * Dispatch a POST group event request.
	 *
	 * @param int   $group_id Group post ID.
	 * @param array $params   Request parameters.
	 *
	 * @return \WP_REST_Response
	 */
	private function post_group_event_request( int $group_id, array $params = array() ): \WP_REST_Response {
		$request = new WP_REST_Request( WP_REST_Server::CREATABLE, "/{$this->route_namespace()}/groups/{$group_id}/events" );
		$request->set_body_params(
			array_merge(
				array(
					'title'     => 'Community Meetup',
					'start_utc' => '2026-06-02T18:00:00Z',
				),
				$params
			)
		);

		return rest_do_request( $request );
	}

	/**
	 * Dispatch a PATCH event request.
	 *
	 * @param int   $event_id Event post ID.
	 * @param array $params   Request parameters.
	 *
	 * @return \WP_REST_Response
	 */
	private function patch_event_request( int $event_id, array $params = array() ): \WP_REST_Response {
		$request = new WP_REST_Request( 'PATCH', "/{$this->route_namespace()}/events/{$event_id}" );
		$request->set_body_params( $params );

		return rest_do_request( $request );
	}

	/**
	 * Dispatch a POST event cancellation request.
	 *
	 * @param int   $event_id Event post ID.
	 * @param array $params   Request parameters.
	 *
	 * @return \WP_REST_Response
	 */
	private function post_event_cancellation_request( int $event_id, array $params = array() ): \WP_REST_Response {
		$request = new WP_REST_Request( WP_REST_Server::CREATABLE, "/{$this->route_namespace()}/events/{$event_id}/cancellation" );
		$request->set_body_params( $params );

		return rest_do_request( $request );
	}

	/**
	 * Dispatch a POST event copy request.
	 *
	 * @param int   $event_id Event post ID.
	 * @param array $params   Request parameters.
	 *
	 * @return \WP_REST_Response
	 */
	private function post_event_copy_request( int $event_id, array $params = array() ): \WP_REST_Response {
		$request = new WP_REST_Request( WP_REST_Server::CREATABLE, "/{$this->route_namespace()}/events/{$event_id}/copies" );
		$request->set_body_params( $params );

		return rest_do_request( $request );
	}

	/**
	 * Dispatch a POST event feedback request.
	 *
	 * @param int   $event_id Event post ID.
	 * @param array $params   Request parameters.
	 *
	 * @return \WP_REST_Response
	 */
	private function post_event_feedback_request( int $event_id, array $params = array() ): \WP_REST_Response {
		$request = new WP_REST_Request( WP_REST_Server::CREATABLE, "/{$this->route_namespace()}/events/{$event_id}/feedback" );
		$request->set_body_params( $params );

		return rest_do_request( $request );
	}

	/**
	 * Dispatch a POST event attendee message request.
	 *
	 * @param int   $event_id Event post ID.
	 * @param array $params   Request parameters.
	 *
	 * @return \WP_REST_Response
	 */
	private function post_event_message_request( int $event_id, array $params = array() ): \WP_REST_Response {
		$request = new WP_REST_Request( WP_REST_Server::CREATABLE, "/{$this->route_namespace()}/events/{$event_id}/messages" );
		$request->set_body_params( $params );

		return rest_do_request( $request );
	}

	/**
	 * Create a group suggestion.
	 *
	 * @param int   $user_id Submitting user ID.
	 * @param array $args    Suggestion arguments.
	 *
	 * @return int
	 */
	private function create_group_suggestion( int $user_id, array $args = array() ): int {
		$suggestion_id = \WordPressdotorg\Community_Events\create_group_suggestion(
			$user_id,
			array_merge(
				array(
					'location_label' => 'Zurich, Switzerland',
					'title'          => 'WordPress Zurich',
				),
				$args
			)
		);

		$this->assertNotWPError( $suggestion_id );

		return (int) $suggestion_id;
	}

	/**
	 * Create a community group.
	 *
	 * @param array $args Group factory arguments.
	 *
	 * @return int
	 */
	private function create_group( array $args = array() ): int {
		$meta = array_merge(
			array(
				'wporg_ce_event_count'  => 0,
				'wporg_ce_member_count' => 0,
			),
			$args['meta_input'] ?? array()
		);

		return self::factory()->post->create(
			array(
				'post_content' => $args['post_content'] ?? '',
				'post_excerpt' => $args['post_excerpt'] ?? '',
				'post_name'    => $args['post_name'] ?? '',
				'post_status'  => $args['post_status'] ?? 'publish',
				'post_title'   => $args['post_title'] ?? 'WordPress Zurich',
				'post_type'    => POST_TYPE_GROUP,
				'meta_input'   => $meta,
			)
		);
	}

	/**
	 * Create a venue.
	 *
	 * @param array $args Venue factory arguments.
	 *
	 * @return int
	 */
	private function create_venue( array $args = array() ): int {
		return self::factory()->post->create(
			array(
				'post_content' => $args['post_content'] ?? '',
				'post_name'    => $args['post_name'] ?? '',
				'post_status'  => $args['post_status'] ?? 'publish',
				'post_title'   => $args['post_title'] ?? 'Community Venue',
				'post_type'    => POST_TYPE_VENUE,
				'meta_input'   => $args['meta_input'] ?? array(),
			)
		);
	}

	/**
	 * Create an event attached to a group.
	 *
	 * @param int   $group_id Group post ID.
	 * @param int   $capacity Event capacity.
	 * @param array $args     Event factory arguments.
	 *
	 * @return int
	 */
	private function create_event( int $group_id, int $capacity = 0, array $args = array() ): int {
		$start_utc = $args['wporg_ce_start_utc'] ?? gmdate( 'Y-m-d\TH:i:s\Z', time() + DAY_IN_SECONDS );
		$meta      = array_merge(
			array(
				'wporg_ce_capacity'       => $capacity,
				'wporg_ce_group_id'       => $group_id,
				'wporg_ce_rsvp_count'     => 0,
				'wporg_ce_start_utc'      => $start_utc,
				'wporg_ce_waitlist_count' => 0,
			),
			$args['meta_input'] ?? array()
		);

		return self::factory()->post->create(
			array(
				'post_author'  => $args['post_author'] ?? 0,
				'post_content' => $args['post_content'] ?? '',
				'post_excerpt' => $args['post_excerpt'] ?? '',
				'post_name'    => $args['post_name'] ?? '',
				'post_parent'  => $group_id,
				'post_status'  => $args['post_status'] ?? 'publish',
				'post_title'   => $args['post_title'] ?? 'Community Meetup',
				'post_type'    => POST_TYPE_EVENT,
				'meta_input'   => $meta,
			)
		);
	}

	/**
	 * Assert a REST link relation contains the expected href.
	 *
	 * @param array  $data Resource response data.
	 * @param string $rel  Link relation key.
	 * @param string $href Expected href.
	 */
	private function assert_rest_link( array $data, string $rel, string $href ): void {
		$this->assertArrayHasKey( '_links', $data );
		$this->assertArrayHasKey( $rel, $data['_links'] );
		$this->assertSame( $href, $data['_links'][ $rel ][0]['href'] );
	}

	/**
	 * Assert that a route has a schema with expected property types.
	 *
	 * @param string $route      Route pattern without namespace.
	 * @param string $title      Expected schema title.
	 * @param array  $properties Expected property types keyed by property name.
	 */
	private function assert_route_schema_properties( string $route, string $title, array $properties ): void {
		$route_key = "/{$this->route_namespace()}{$route}";
		$routes    = rest_get_server()->get_routes();

		$this->assertArrayHasKey( $route_key, $routes, "{$route_key} should be registered." );

		$route_options = rest_get_server()->get_route_options( $route_key );

		$this->assertArrayHasKey( 'schema', $route_options, "{$route_key} should expose a schema callback." );
		$this->assertIsCallable( $route_options['schema'], "{$route_key} schema should be callable." );

		$schema = call_user_func( $route_options['schema'] );

		$this->assertSame( $title, $schema['title'] );

		foreach ( $properties as $property => $type ) {
			$this->assertArrayHasKey( $property, $schema['properties'], "{$route_key} should expose {$property}." );
			$this->assertSame( $type, $schema['properties'][ $property ]['type'], "{$route_key} {$property} should be {$type}." );
		}
	}

	/**
	 * Get the REST route namespace.
	 *
	 * @return string
	 */
	private function route_namespace(): string {
		return REST_NAMESPACE;
	}
}
