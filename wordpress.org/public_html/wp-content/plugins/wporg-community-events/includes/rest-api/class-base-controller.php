<?php
/**
 * Base REST controller for Community Events.
 *
 * @package WordPressdotorg\Community_Events
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Community_Events;

defined( 'ABSPATH' ) || exit;

/**
 * Shared REST controller helpers.
 */
abstract class Base_Controller extends \WP_REST_Controller {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = REST_NAMESPACE;
	}

	/**
	 * Get the common group ID route argument schema.
	 *
	 * @return array
	 */
	protected function get_group_id_arg(): array {
		return array(
			'description' => 'Community group ID.',
			'type'        => 'integer',
			'minimum'     => 1,
			'required'    => true,
		);
	}

	/**
	 * Get the common group suggestion ID route argument schema.
	 *
	 * @return array
	 */
	protected function get_group_suggestion_id_arg(): array {
		return array(
			'description' => 'Community group suggestion ID.',
			'type'        => 'integer',
			'minimum'     => 1,
			'required'    => true,
		);
	}

	/**
	 * Get the common event ID route argument schema.
	 *
	 * @return array
	 */
	protected function get_event_id_arg(): array {
		return array(
			'description' => 'Community event ID.',
			'type'        => 'integer',
			'minimum'     => 1,
			'required'    => true,
		);
	}

	/**
	 * Get the common venue ID route argument schema.
	 *
	 * @return array
	 */
	protected function get_venue_id_arg(): array {
		return array(
			'description' => 'Community venue ID.',
			'type'        => 'integer',
			'minimum'     => 1,
			'required'    => true,
		);
	}

	/**
	 * Allow public route access.
	 *
	 * @return true
	 */
	public function allow_public_access(): bool {
		return true;
	}

	/**
	 * Check whether the current user can use self-service routes.
	 *
	 * @return true|\WP_Error
	 */
	public function can_use_self_service_route() {
		return can_use_self_service_relationship_route();
	}

	/**
	 * Get the public item schema.
	 *
	 * @return array
	 */
	public function get_public_item_schema(): array {
		return $this->get_item_schema();
	}

	/**
	 * Get the public schema for an event response.
	 *
	 * @return array
	 */
	public function get_public_event_schema(): array {
		return $this->get_event_schema();
	}

	/**
	 * Get the public schema for a public organizer response.
	 *
	 * @return array
	 */
	public function get_public_organizer_schema(): array {
		return $this->get_organizer_schema();
	}

	/**
	 * Get the public schema for a public group member response.
	 *
	 * @return array
	 */
	public function get_public_member_schema(): array {
		$schema          = $this->get_organizer_schema();
		$schema['title'] = 'wporg_community_event_member';

		return $schema;
	}

	/**
	 * Get the public schema for a public attendee response.
	 *
	 * @return array
	 */
	public function get_public_attendee_schema(): array {
		return $this->get_attendee_schema();
	}

	/**
	 * Get the public schema for attendee message result responses.
	 *
	 * @return array
	 */
	public function get_public_message_result_schema(): array {
		return $this->get_message_result_schema();
	}

	/**
	 * Get the public schema for a group suggestion response.
	 *
	 * @return array
	 */
	public function get_public_group_suggestion_schema(): array {
		return $this->get_group_suggestion_schema();
	}

	/**
	 * Get the public schema for a current-user membership response.
	 *
	 * @return array
	 */
	public function get_public_current_user_membership_schema(): array {
		$schema = $this->get_membership_schema();

		$schema['title']               = 'wporg_community_event_current_user_membership';
		$schema['properties']['group'] = $this->get_group_schema( false );

		return $schema;
	}

	/**
	 * Get the public schema for a current-user RSVP response.
	 *
	 * @return array
	 */
	public function get_public_current_user_rsvp_schema(): array {
		$schema = $this->get_rsvp_schema();

		$schema['title']               = 'wporg_community_event_current_user_rsvp';
		$schema['properties']['event'] = $this->get_event_schema( false );

		return $schema;
	}

	/**
	 * Get the public schema for a group response.
	 *
	 * @param bool $include_title Whether to include top-level schema metadata.
	 *
	 * @return array
	 */
	protected function get_group_schema( bool $include_title = true ): array {
		return $this->get_object_schema(
			$include_title ? 'wporg_community_event_group' : '',
			array(
				'id'                => $this->get_integer_property_schema( 'Group ID.' ),
				'slug'              => $this->get_string_property_schema( 'Group slug.' ),
				'link'              => $this->get_uri_property_schema( 'Group URL.' ),
				'url'               => $this->get_uri_property_schema( 'Group URL.' ),
				'title'             => $this->get_string_property_schema( 'Group title.' ),
				'description'       => $this->get_string_property_schema( 'Group description.' ),
				'excerpt'           => $this->get_string_property_schema( 'Short group summary.' ),
				'status'            => $this->get_string_property_schema( 'Post status.' ),
				'timezone'          => $this->get_string_property_schema( 'Primary group timezone.' ),
				'city'              => $this->get_string_property_schema( 'Group city.' ),
				'region'            => $this->get_string_property_schema( 'Group region.' ),
				'location_label'    => $this->get_string_property_schema( 'Human-readable group location.' ),
				'website_url'       => $this->get_uri_property_schema( 'Third-party group website URL.' ),
				'official_status'   => $this->get_string_property_schema( 'WordPress.org official status.' ),
				'source_meetup_url' => $this->get_uri_property_schema( 'Source Meetup.com URL.' ),
				'member_count'      => $this->get_integer_property_schema( 'Cached member count.' ),
				'event_count'       => $this->get_integer_property_schema( 'Cached event count.' ),
				'taxonomies'        => $this->get_group_taxonomies_schema(),
				'_links'            => $this->get_links_property_schema(),
			)
		);
	}

	/**
	 * Get the public schema for a group suggestion response.
	 *
	 * @param bool $include_title Whether to include top-level schema metadata.
	 *
	 * @return array
	 */
	protected function get_group_suggestion_schema( bool $include_title = true ): array {
		return $this->get_object_schema(
			$include_title ? 'wporg_community_event_group_suggestion' : '',
			array(
				'id'                  => $this->get_integer_property_schema( 'Group suggestion ID.' ),
				'submitter_user_id'   => $this->get_integer_property_schema( 'Submitting user ID.' ),
				'submitter'           => $this->get_user_schema(),
				'title'               => $this->get_string_property_schema( 'Suggested group title.' ),
				'description'         => $this->get_string_property_schema( 'Suggested group description.' ),
				'excerpt'             => $this->get_string_property_schema( 'Short suggested group summary.' ),
				'status'              => $this->get_string_property_schema( 'Post status.' ),
				'review_status'       => $this->get_string_property_schema( 'Community Team review status.' ),
				'reviewed_by_user_id' => $this->get_integer_property_schema( 'Reviewer user ID.' ),
				'reviewed_by'         => $this->get_user_schema(),
				'reviewed_at_utc'     => $this->get_datetime_property_schema( 'Review time in UTC.' ),
				'review_note'         => $this->get_string_property_schema( 'Community Team review note.' ),
				'created_group_id'    => $this->get_integer_property_schema( 'Created official group ID.' ),
				'duplicate_group_id'  => $this->get_integer_property_schema( 'Duplicate existing group ID.' ),
				'timezone'            => $this->get_string_property_schema( 'Suggested group timezone.' ),
				'city'                => $this->get_string_property_schema( 'Suggested group city.' ),
				'region'              => $this->get_string_property_schema( 'Suggested group region.' ),
				'location_label'      => $this->get_string_property_schema( 'Human-readable suggested group location.' ),
				'website_url'         => $this->get_uri_property_schema( 'Third-party suggested group website URL.' ),
				'taxonomies'          => $this->get_group_taxonomies_schema(),
				'_links'              => $this->get_links_property_schema(),
			)
		);
	}

	/**
	 * Get the public schema for an event response.
	 *
	 * @param bool $include_title Whether to include top-level schema metadata.
	 *
	 * @return array
	 */
	protected function get_event_schema( bool $include_title = true ): array {
		return $this->get_object_schema(
			$include_title ? 'wporg_community_event_event' : '',
			array(
				'id'                   => $this->get_integer_property_schema( 'Event ID.' ),
				'group_id'             => $this->get_integer_property_schema( 'Related group ID.' ),
				'host_user_id'         => $this->get_integer_property_schema( 'Host user ID.' ),
				'host_user_ids'        => $this->get_integers_property_schema( 'Event host user IDs.' ),
				'host'                 => $this->get_user_schema(),
				'hosts'                => $this->get_users_property_schema( 'Event hosts.' ),
				'link'                 => $this->get_uri_property_schema( 'Event URL.' ),
				'url'                  => $this->get_uri_property_schema( 'Event URL.' ),
				'slug'                 => $this->get_string_property_schema( 'Event slug.' ),
				'title'                => $this->get_string_property_schema( 'Event title.' ),
				'description'          => $this->get_string_property_schema( 'Event description.' ),
				'excerpt'              => $this->get_string_property_schema( 'Short event summary.' ),
				'status'               => $this->get_string_property_schema( 'Post status.' ),
				'approval_status'      => $this->get_string_property_schema( 'Organizer approval status.' ),
				'canceled_at_utc'      => $this->get_datetime_property_schema( 'Cancellation time in UTC.' ),
				'canceled_by_user_id'  => $this->get_integer_property_schema( 'Canceling user ID.' ),
				'canceled_by'          => $this->get_user_schema(),
				'cancellation_reason'  => $this->get_string_property_schema( 'Cancellation reason.' ),
				'copied_from_event_id' => $this->get_integer_property_schema( 'Source event ID when this event was copied from another event.' ),
				'venue_id'             => $this->get_integer_property_schema( 'Venue ID.' ),
				'venue'                => $this->get_venue_schema( false ),
				'start_utc'            => $this->get_datetime_property_schema( 'Event start time in UTC.' ),
				'end_utc'              => $this->get_datetime_property_schema( 'Event end time in UTC.' ),
				'timezone'             => $this->get_string_property_schema( 'Event timezone.' ),
				'capacity'             => $this->get_integer_property_schema( 'RSVP capacity.' ),
				'online_url'           => $this->get_uri_property_schema( 'Online event URL.' ),
				'rsvp_policy'          => $this->get_string_property_schema( 'RSVP policy.' ),
				'rsvp_questions'       => get_event_rsvp_questions_schema(),
				'event_counts'         => $this->get_event_counts_schema(),
				'feedback_summary'     => $this->get_feedback_summary_schema(),
				'taxonomies'           => $this->get_event_taxonomies_schema(),
				'_links'               => $this->get_links_property_schema(),
			)
		);
	}

	/**
	 * Get the public schema for a membership response.
	 *
	 * @return array
	 */
	protected function get_membership_schema(): array {
		return $this->get_object_schema(
			'wporg_community_event_membership',
			array(
				'id'                       => $this->get_integer_property_schema( 'Membership ID.' ),
				'group_id'                 => $this->get_integer_property_schema( 'Related group ID.' ),
				'user_id'                  => $this->get_integer_property_schema( 'WordPress.org user ID.' ),
				'user'                     => $this->get_user_schema(),
				'role'                     => $this->get_string_property_schema( 'Membership role.' ),
				'status'                   => $this->get_string_property_schema( 'Membership status.' ),
				'visibility'               => $this->get_string_property_schema( 'Public profile visibility for this relationship.' ),
				'notification_preferences' => get_notification_preferences_schema(),
				'joined_at_utc'            => $this->get_datetime_property_schema( 'Membership creation time in UTC.' ),
				'_links'                   => $this->get_links_property_schema(),
			)
		);
	}

	/**
	 * Get the public schema for an RSVP response.
	 *
	 * @return array
	 */
	protected function get_rsvp_schema(): array {
		return $this->get_object_schema(
			'wporg_community_event_rsvp',
			array(
				'id'                            => $this->get_integer_property_schema( 'RSVP ID.' ),
				'event_id'                      => $this->get_integer_property_schema( 'Related event ID.' ),
				'group_id'                      => $this->get_integer_property_schema( 'Related group ID.' ),
				'user_id'                       => $this->get_integer_property_schema( 'WordPress.org user ID.' ),
				'user'                          => $this->get_user_schema(),
				'status'                        => $this->get_string_property_schema( 'RSVP status.' ),
				'attendance_status'             => $this->get_string_property_schema( 'Attendance tracking status.' ),
				'waitlist_position'             => $this->get_integer_property_schema( 'Waitlist position.' ),
				'guest_count'                   => $this->get_integer_property_schema( 'Additional guest count.' ),
				'visibility'                    => $this->get_string_property_schema( 'Public profile visibility for this RSVP.' ),
				'answers'                       => get_event_rsvp_answers_schema(),
				'attended_at_utc'               => $this->get_datetime_property_schema( 'Check-in time in UTC.' ),
				'attendance_updated_by_user_id' => $this->get_integer_property_schema( 'User ID that last updated attendance.' ),
				'attendance_updated_at_utc'     => $this->get_datetime_property_schema( 'Attendance update time in UTC.' ),
				'created_at_utc'                => $this->get_datetime_property_schema( 'RSVP creation time in UTC.' ),
				'updated_at_utc'                => $this->get_datetime_property_schema( 'RSVP update time in UTC.' ),
				'event_counts'                  => $this->get_event_counts_schema(),
				'_links'                        => $this->get_links_property_schema(),
			)
		);
	}

	/**
	 * Get the public schema for a venue response.
	 *
	 * @param bool $include_title Whether to include top-level schema metadata.
	 *
	 * @return array
	 */
	protected function get_venue_schema( bool $include_title = true ): array {
		return $this->get_object_schema(
			$include_title ? 'wporg_community_event_venue' : '',
			array(
				'id'                  => $this->get_integer_property_schema( 'Venue ID.' ),
				'slug'                => $this->get_string_property_schema( 'Venue slug.' ),
				'link'                => $this->get_uri_property_schema( 'Venue URL.' ),
				'url'                 => $this->get_uri_property_schema( 'Venue URL.' ),
				'title'               => $this->get_string_property_schema( 'Venue title.' ),
				'description'         => $this->get_string_property_schema( 'Venue description.' ),
				'address'             => $this->get_string_property_schema( 'Street address.' ),
				'city'                => $this->get_string_property_schema( 'Venue city.' ),
				'region'              => $this->get_string_property_schema( 'Venue region.' ),
				'postal_code'         => $this->get_string_property_schema( 'Postal code.' ),
				'latitude'            => $this->get_number_property_schema( 'Venue latitude.' ),
				'longitude'           => $this->get_number_property_schema( 'Venue longitude.' ),
				'accessibility_notes' => $this->get_string_property_schema( 'Accessibility notes.' ),
				'online_url'          => $this->get_uri_property_schema( 'Online venue URL.' ),
				'taxonomies'          => $this->get_venue_taxonomies_schema(),
				'_links'              => $this->get_links_property_schema(),
			)
		);
	}

	/**
	 * Get the public schema for a public organizer response.
	 *
	 * @return array
	 */
	protected function get_organizer_schema(): array {
		return $this->get_object_schema(
			'wporg_community_event_organizer',
			array(
				'id'            => $this->get_integer_property_schema( 'Membership ID.' ),
				'membership_id' => $this->get_integer_property_schema( 'Membership ID.' ),
				'user_id'       => $this->get_integer_property_schema( 'WordPress.org user ID.' ),
				'user'          => $this->get_user_schema(),
				'role'          => $this->get_string_property_schema( 'Organizer role.' ),
				'joined_at_utc' => $this->get_datetime_property_schema( 'Membership creation time in UTC.' ),
				'_links'        => $this->get_links_property_schema(),
			)
		);
	}

	/**
	 * Get the public schema for an attendee response.
	 *
	 * @return array
	 */
	protected function get_attendee_schema(): array {
		return $this->get_object_schema(
			'wporg_community_event_attendee',
			array(
				'id'                            => $this->get_integer_property_schema( 'RSVP ID.' ),
				'rsvp_id'                       => $this->get_integer_property_schema( 'RSVP ID.' ),
				'user_id'                       => $this->get_integer_property_schema( 'WordPress.org user ID.' ),
				'user'                          => $this->get_user_schema(),
				'status'                        => $this->get_string_property_schema( 'RSVP status.' ),
				'attendance_status'             => $this->get_string_property_schema( 'Attendance tracking status.' ),
				'waitlist_position'             => $this->get_integer_property_schema( 'Waitlist position.' ),
				'guest_count'                   => $this->get_integer_property_schema( 'Additional guest count.' ),
				'answers'                       => get_event_rsvp_answers_schema(),
				'attended_at_utc'               => $this->get_datetime_property_schema( 'Check-in time in UTC.' ),
				'attendance_updated_by_user_id' => $this->get_integer_property_schema( 'User ID that last updated attendance.' ),
				'attendance_updated_at_utc'     => $this->get_datetime_property_schema( 'Attendance update time in UTC.' ),
				'created_at_utc'                => $this->get_datetime_property_schema( 'RSVP creation time in UTC.' ),
				'_links'                        => $this->get_links_property_schema(),
			)
		);
	}

	/**
	 * Get the public schema for an attendee message result.
	 *
	 * @return array
	 */
	protected function get_message_result_schema(): array {
		return $this->get_object_schema(
			'wporg_community_event_message_result',
			array(
				'recipient_count' => $this->get_integer_property_schema( 'Matched recipient count.' ),
				'sent_count'      => $this->get_integer_property_schema( 'Sent email count.' ),
			)
		);
	}

	/**
	 * Get the public schema for an event feedback response.
	 *
	 * @return array
	 */
	protected function get_feedback_schema(): array {
		return $this->get_object_schema(
			'wporg_community_event_feedback',
			array(
				'id'             => $this->get_integer_property_schema( 'Feedback ID.' ),
				'event_id'       => $this->get_integer_property_schema( 'Related event ID.' ),
				'group_id'       => $this->get_integer_property_schema( 'Related group ID.' ),
				'user_id'        => $this->get_integer_property_schema( 'WordPress.org user ID.' ),
				'user'           => $this->get_user_schema(),
				'rating'         => $this->get_integer_property_schema( 'Event rating from 1 to 5.' ),
				'review'         => $this->get_string_property_schema( 'Event feedback note.' ),
				'created_at_utc' => $this->get_datetime_property_schema( 'Feedback creation time in UTC.' ),
				'_links'         => $this->get_links_property_schema(),
			)
		);
	}

	/**
	 * Get the public schema for a WordPress.org profile response.
	 *
	 * @return array
	 */
	protected function get_user_schema(): array {
		return $this->get_object_schema(
			'',
			array(
				'id'          => $this->get_integer_property_schema( 'WordPress.org user ID.' ),
				'slug'        => $this->get_string_property_schema( 'Profiles.WordPress.org slug.' ),
				'name'        => $this->get_string_property_schema( 'Display name.' ),
				'profile_url' => $this->get_uri_property_schema( 'Profiles.WordPress.org URL.' ),
				'avatar_url'  => $this->get_uri_property_schema( 'Avatar URL.' ),
			)
		);
	}

	/**
	 * Get a property schema for a list of WordPress.org profile responses.
	 *
	 * @param string $description Property description.
	 *
	 * @return array
	 */
	protected function get_users_property_schema( string $description ): array {
		return array(
			'description' => $description,
			'type'        => 'array',
			'items'       => $this->get_user_schema(),
		);
	}

	/**
	 * Get the public schema for event counts.
	 *
	 * @return array
	 */
	protected function get_event_counts_schema(): array {
		return $this->get_object_schema(
			'',
			array(
				'attending'  => $this->get_integer_property_schema( 'Attending RSVP count.' ),
				'waitlisted' => $this->get_integer_property_schema( 'Waitlist RSVP count.' ),
			)
		);
	}

	/**
	 * Get the public schema for event feedback summary data.
	 *
	 * @return array
	 */
	protected function get_feedback_summary_schema(): array {
		return $this->get_object_schema(
			'',
			array(
				'average_rating' => $this->get_number_property_schema( 'Average event rating.' ),
				'count'          => $this->get_integer_property_schema( 'Feedback count.' ),
			)
		);
	}

	/**
	 * Get the public schema for group taxonomy collections.
	 *
	 * @return array
	 */
	protected function get_group_taxonomies_schema(): array {
		return $this->get_object_schema(
			'',
			array(
				'group_types' => $this->get_terms_property_schema( 'Group type terms.' ),
				'topics'      => $this->get_terms_property_schema( 'Topic terms.' ),
				'languages'   => $this->get_terms_property_schema( 'Language terms.' ),
				'countries'   => $this->get_terms_property_schema( 'Country terms.' ),
			)
		);
	}

	/**
	 * Get the public schema for event taxonomy collections.
	 *
	 * @return array
	 */
	protected function get_event_taxonomies_schema(): array {
		return $this->get_object_schema(
			'',
			array(
				'countries'     => $this->get_terms_property_schema( 'Country terms.' ),
				'event_formats' => $this->get_terms_property_schema( 'Event format terms.' ),
				'event_types'   => $this->get_terms_property_schema( 'Event type terms.' ),
				'languages'     => $this->get_terms_property_schema( 'Language terms.' ),
				'topics'        => $this->get_terms_property_schema( 'Topic terms.' ),
			)
		);
	}

	/**
	 * Get the public schema for venue taxonomy collections.
	 *
	 * @return array
	 */
	protected function get_venue_taxonomies_schema(): array {
		return $this->get_object_schema(
			'',
			array(
				'countries' => $this->get_terms_property_schema( 'Country terms.' ),
			)
		);
	}

	/**
	 * Get the public schema for a term response.
	 *
	 * @return array
	 */
	protected function get_term_schema(): array {
		return $this->get_object_schema(
			'',
			array(
				'id'       => $this->get_integer_property_schema( 'Term ID.' ),
				'slug'     => $this->get_string_property_schema( 'Term slug.' ),
				'name'     => $this->get_string_property_schema( 'Term name.' ),
				'taxonomy' => $this->get_string_property_schema( 'Taxonomy key.' ),
			)
		);
	}

	/**
	 * Get a property schema for a list of terms.
	 *
	 * @param string $description Property description.
	 *
	 * @return array
	 */
	protected function get_terms_property_schema( string $description ): array {
		return array(
			'description' => $description,
			'type'        => 'array',
			'items'       => $this->get_term_schema(),
		);
	}

	/**
	 * Prepare group data for REST responses.
	 *
	 * @param int $group_id Group post ID.
	 *
	 * @return array
	 */
	protected function prepare_group_item_response( int $group_id ): array {
		$group = get_post( $group_id );

		if ( ! $group ) {
			return array();
		}

		return array(
			'id'                => $group_id,
			'slug'              => $group->post_name,
			'link'              => (string) get_permalink( $group ),
			'url'               => (string) get_permalink( $group ),
			'title'             => get_the_title( $group ),
			'description'       => $group->post_content,
			'excerpt'           => $group->post_excerpt,
			'status'            => $group->post_status,
			'timezone'          => get_post_meta( $group_id, 'wporg_ce_timezone', true ),
			'city'              => get_post_meta( $group_id, 'wporg_ce_city', true ),
			'region'            => get_post_meta( $group_id, 'wporg_ce_region', true ),
			'location_label'    => get_post_meta( $group_id, 'wporg_ce_location_label', true ),
			'website_url'       => get_post_meta( $group_id, 'wporg_ce_website_url', true ),
			'official_status'   => get_post_meta( $group_id, 'wporg_ce_official_status', true ),
			'source_meetup_url' => get_post_meta( $group_id, 'wporg_ce_source_meetup_url', true ),
			'member_count'      => (int) get_post_meta( $group_id, 'wporg_ce_member_count', true ),
			'event_count'       => (int) get_post_meta( $group_id, 'wporg_ce_event_count', true ),
			'taxonomies'        => array(
				'group_types' => $this->prepare_object_terms_response( $group_id, TAXONOMY_GROUP_TYPE ),
				'topics'      => $this->prepare_object_terms_response( $group_id, TAXONOMY_TOPIC ),
				'languages'   => $this->prepare_object_terms_response( $group_id, TAXONOMY_LANGUAGE ),
				'countries'   => $this->prepare_object_terms_response( $group_id, TAXONOMY_COUNTRY ),
			),
			'_links'            => $this->get_entity_links(
				'/groups',
				$group_id,
				array(
					'wporg:events' => $this->get_rest_url( "/groups/{$group_id}/events" ),
				)
			),
		);
	}

	/**
	 * Prepare group suggestion data for REST responses.
	 *
	 * @param int $suggestion_id Group suggestion post ID.
	 *
	 * @return array
	 */
	protected function prepare_group_suggestion_item_response( int $suggestion_id ): array {
		$suggestion          = get_post( $suggestion_id );
		$reviewed_by_user_id = (int) get_post_meta( $suggestion_id, 'wporg_ce_reviewed_by_user_id', true );

		if ( ! $suggestion || POST_TYPE_GROUP_SUGGESTION !== $suggestion->post_type ) {
			return array();
		}

		return array(
			'id'                  => $suggestion_id,
			'submitter_user_id'   => (int) $suggestion->post_author,
			'submitter'           => prepare_user_rest_response( (int) $suggestion->post_author ),
			'title'               => get_the_title( $suggestion ),
			'description'         => $suggestion->post_content,
			'excerpt'             => $suggestion->post_excerpt,
			'status'              => $suggestion->post_status,
			'review_status'       => get_post_meta( $suggestion_id, 'wporg_ce_review_status', true ),
			'reviewed_by_user_id' => $reviewed_by_user_id,
			'reviewed_by'         => $reviewed_by_user_id ? prepare_user_rest_response( $reviewed_by_user_id ) : array(),
			'reviewed_at_utc'     => get_post_meta( $suggestion_id, 'wporg_ce_reviewed_at_utc', true ),
			'review_note'         => get_post_meta( $suggestion_id, 'wporg_ce_review_note', true ),
			'created_group_id'    => (int) get_post_meta( $suggestion_id, 'wporg_ce_created_group_id', true ),
			'duplicate_group_id'  => (int) get_post_meta( $suggestion_id, 'wporg_ce_duplicate_group_id', true ),
			'timezone'            => get_post_meta( $suggestion_id, 'wporg_ce_timezone', true ),
			'city'                => get_post_meta( $suggestion_id, 'wporg_ce_city', true ),
			'region'              => get_post_meta( $suggestion_id, 'wporg_ce_region', true ),
			'location_label'      => get_post_meta( $suggestion_id, 'wporg_ce_location_label', true ),
			'website_url'         => get_post_meta( $suggestion_id, 'wporg_ce_website_url', true ),
			'taxonomies'          => array(
				'countries'   => $this->prepare_object_terms_response( $suggestion_id, TAXONOMY_COUNTRY ),
				'group_types' => $this->prepare_object_terms_response( $suggestion_id, TAXONOMY_GROUP_TYPE ),
				'languages'   => $this->prepare_object_terms_response( $suggestion_id, TAXONOMY_LANGUAGE ),
				'topics'      => $this->prepare_object_terms_response( $suggestion_id, TAXONOMY_TOPIC ),
			),
			'_links'              => $this->get_group_suggestion_links( $suggestion_id ),
		);
	}

	/**
	 * Prepare venue data for REST responses.
	 *
	 * @param int $venue_id Venue post ID.
	 *
	 * @return array
	 */
	protected function prepare_venue_item_response( int $venue_id ): array {
		$venue = get_post( $venue_id );

		if ( ! $venue || POST_TYPE_VENUE !== $venue->post_type || 'publish' !== $venue->post_status ) {
			return array();
		}

		return array(
			'id'                  => $venue_id,
			'slug'                => $venue->post_name,
			'link'                => (string) get_permalink( $venue ),
			'url'                 => (string) get_permalink( $venue ),
			'title'               => get_the_title( $venue ),
			'description'         => $venue->post_content,
			'address'             => get_post_meta( $venue_id, 'wporg_ce_address', true ),
			'city'                => get_post_meta( $venue_id, 'wporg_ce_city', true ),
			'region'              => get_post_meta( $venue_id, 'wporg_ce_region', true ),
			'postal_code'         => get_post_meta( $venue_id, 'wporg_ce_postal_code', true ),
			'latitude'            => (float) get_post_meta( $venue_id, 'wporg_ce_latitude', true ),
			'longitude'           => (float) get_post_meta( $venue_id, 'wporg_ce_longitude', true ),
			'accessibility_notes' => get_post_meta( $venue_id, 'wporg_ce_accessibility_notes', true ),
			'online_url'          => get_post_meta( $venue_id, 'wporg_ce_online_url', true ),
			'taxonomies'          => array(
				'countries' => $this->prepare_object_terms_response( $venue_id, TAXONOMY_COUNTRY ),
			),
			'_links'              => $this->get_entity_links( '/venues', $venue_id ),
		);
	}

	/**
	 * Prepare membership data for REST responses.
	 *
	 * @param int $membership_id Membership post ID.
	 *
	 * @return array
	 */
	protected function prepare_membership_item_response( int $membership_id ): array {
		$user_id = (int) get_post_meta( $membership_id, 'wporg_ce_user_id', true );

		return array(
			'id'                       => $membership_id,
			'group_id'                 => (int) get_post_meta( $membership_id, 'wporg_ce_group_id', true ),
			'user_id'                  => $user_id,
			'user'                     => prepare_user_rest_response( $user_id ),
			'role'                     => get_post_meta( $membership_id, 'wporg_ce_role', true ),
			'status'                   => get_post_meta( $membership_id, 'wporg_ce_status', true ),
			'visibility'               => get_post_meta( $membership_id, 'wporg_ce_visibility', true ),
			'notification_preferences' => get_membership_notification_preferences( $membership_id ),
			'joined_at_utc'            => get_post_meta( $membership_id, 'wporg_ce_joined_at_utc', true ),
			'_links'                   => $this->get_membership_links( $membership_id ),
		);
	}

	/**
	 * Prepare event data for REST responses.
	 *
	 * @param int $event_id Event post ID.
	 *
	 * @return array
	 */
	protected function prepare_event_item_response( int $event_id ): array {
		$event = get_post( $event_id );

		if ( ! $event ) {
			return array();
		}

		$venue_id            = (int) get_post_meta( $event_id, 'wporg_ce_venue_id', true );
		$group_id            = get_event_group_id( $event );
		$canceled_by_user_id = (int) get_post_meta( $event_id, 'wporg_ce_canceled_by_user_id', true );
		$host_user_ids       = get_event_host_user_ids( $event_id );
		$host_user_id        = (int) ( $host_user_ids[0] ?? 0 );

		return array(
			'id'                   => $event_id,
			'group_id'             => $group_id,
			'host_user_id'         => $host_user_id,
			'host_user_ids'        => $host_user_ids,
			'host'                 => prepare_user_rest_response( $host_user_id ),
			'hosts'                => array_map( __NAMESPACE__ . '\prepare_user_rest_response', $host_user_ids ),
			'link'                 => (string) get_permalink( $event ),
			'url'                  => (string) get_permalink( $event ),
			'slug'                 => $event->post_name,
			'title'                => get_the_title( $event ),
			'description'          => $event->post_content,
			'excerpt'              => $event->post_excerpt,
			'status'               => $event->post_status,
			'approval_status'      => get_post_meta( $event_id, 'wporg_ce_approval_status', true ),
			'canceled_at_utc'      => get_post_meta( $event_id, 'wporg_ce_canceled_at_utc', true ),
			'canceled_by_user_id'  => $canceled_by_user_id,
			'canceled_by'          => $canceled_by_user_id ? prepare_user_rest_response( $canceled_by_user_id ) : array(),
			'cancellation_reason'  => get_post_meta( $event_id, 'wporg_ce_cancellation_reason', true ),
			'copied_from_event_id' => (int) get_post_meta( $event_id, 'wporg_ce_copied_from_event_id', true ),
			'venue_id'             => $venue_id,
			'venue'                => $this->prepare_venue_item_response( $venue_id ),
			'start_utc'            => get_post_meta( $event_id, 'wporg_ce_start_utc', true ),
			'end_utc'              => get_post_meta( $event_id, 'wporg_ce_end_utc', true ),
			'timezone'             => get_post_meta( $event_id, 'wporg_ce_timezone', true ),
			'capacity'             => (int) get_post_meta( $event_id, 'wporg_ce_capacity', true ),
			'online_url'           => get_post_meta( $event_id, 'wporg_ce_online_url', true ),
			'rsvp_policy'          => get_post_meta( $event_id, 'wporg_ce_rsvp_policy', true ),
			'rsvp_questions'       => get_event_rsvp_questions( $event_id ),
			'event_counts'         => array(
				'attending'  => (int) get_post_meta( $event_id, 'wporg_ce_rsvp_count', true ),
				'waitlisted' => (int) get_post_meta( $event_id, 'wporg_ce_waitlist_count', true ),
			),
			'feedback_summary'     => get_event_feedback_summary( $event_id ),
			'taxonomies'           => array(
				'countries'     => $this->prepare_object_terms_response( $event_id, TAXONOMY_COUNTRY ),
				'event_formats' => $this->prepare_object_terms_response( $event_id, TAXONOMY_EVENT_FORMAT ),
				'event_types'   => $this->prepare_object_terms_response( $event_id, TAXONOMY_EVENT_TYPE ),
				'languages'     => $this->prepare_object_terms_response( $event_id, TAXONOMY_LANGUAGE ),
				'topics'        => $this->prepare_object_terms_response( $event_id, TAXONOMY_TOPIC ),
			),
			'_links'               => $this->get_event_links( $event_id, $group_id, $venue_id ),
		);
	}

	/**
	 * Prepare event feedback data for REST responses.
	 *
	 * @param int $feedback_id Feedback post ID.
	 *
	 * @return array
	 */
	protected function prepare_feedback_item_response( int $feedback_id ): array {
		$user_id = (int) get_post_meta( $feedback_id, 'wporg_ce_user_id', true );
		$post    = get_post( $feedback_id );

		return array(
			'id'             => $feedback_id,
			'event_id'       => (int) get_post_meta( $feedback_id, 'wporg_ce_event_id', true ),
			'group_id'       => (int) get_post_meta( $feedback_id, 'wporg_ce_group_id', true ),
			'user_id'        => $user_id,
			'user'           => prepare_user_rest_response( $user_id ),
			'rating'         => (int) get_post_meta( $feedback_id, 'wporg_ce_rating', true ),
			'review'         => $post instanceof \WP_Post ? $post->post_content : '',
			'created_at_utc' => get_post_meta( $feedback_id, 'wporg_ce_created_at_utc', true ),
			'_links'         => $this->get_feedback_links( $feedback_id ),
		);
	}

	/**
	 * Prepare RSVP data for REST responses.
	 *
	 * @param int $rsvp_id RSVP post ID.
	 *
	 * @return array
	 */
	protected function prepare_rsvp_item_response( int $rsvp_id ): array {
		$event_id = (int) get_post_meta( $rsvp_id, 'wporg_ce_event_id', true );
		$user_id  = (int) get_post_meta( $rsvp_id, 'wporg_ce_user_id', true );

		return array(
			'id'                            => $rsvp_id,
			'event_id'                      => $event_id,
			'group_id'                      => (int) get_post_meta( $rsvp_id, 'wporg_ce_group_id', true ),
			'user_id'                       => $user_id,
			'user'                          => prepare_user_rest_response( $user_id ),
			'status'                        => get_post_meta( $rsvp_id, 'wporg_ce_status', true ),
			'attendance_status'             => get_post_meta( $rsvp_id, 'wporg_ce_attendance_status', true ),
			'waitlist_position'             => (int) get_post_meta( $rsvp_id, 'wporg_ce_waitlist_position', true ),
			'guest_count'                   => (int) get_post_meta( $rsvp_id, 'wporg_ce_guest_count', true ),
			'visibility'                    => get_post_meta( $rsvp_id, 'wporg_ce_visibility', true ),
			'answers'                       => can_user_view_rsvp_answers( $rsvp_id, get_current_user_id() ) ? get_event_rsvp_answers( $rsvp_id ) : array(),
			'attended_at_utc'               => get_post_meta( $rsvp_id, 'wporg_ce_attended_at_utc', true ),
			'attendance_updated_by_user_id' => (int) get_post_meta( $rsvp_id, 'wporg_ce_attendance_by', true ),
			'attendance_updated_at_utc'     => get_post_meta( $rsvp_id, 'wporg_ce_attendance_at_utc', true ),
			'created_at_utc'                => get_post_meta( $rsvp_id, 'wporg_ce_created_at_utc', true ),
			'updated_at_utc'                => get_post_meta( $rsvp_id, 'wporg_ce_updated_at_utc', true ),
			'event_counts'                  => array(
				'attending'  => (int) get_post_meta( $event_id, 'wporg_ce_rsvp_count', true ),
				'waitlisted' => (int) get_post_meta( $event_id, 'wporg_ce_waitlist_count', true ),
			),
			'_links'                        => $this->get_rsvp_links( $rsvp_id ),
		);
	}

	/**
	 * Get REST links for a group suggestion.
	 *
	 * @param int $suggestion_id Group suggestion post ID.
	 *
	 * @return array
	 */
	protected function get_group_suggestion_links( int $suggestion_id ): array {
		$links              = $this->get_entity_links( '/group-suggestions', $suggestion_id );
		$created_group_id   = (int) get_post_meta( $suggestion_id, 'wporg_ce_created_group_id', true );
		$duplicate_group_id = (int) get_post_meta( $suggestion_id, 'wporg_ce_duplicate_group_id', true );

		if ( $created_group_id ) {
			$links['wporg:created-group'] = array(
				array(
					'href' => $this->get_rest_url( "/groups/{$created_group_id}" ),
				),
			);
		}

		if ( $duplicate_group_id ) {
			$links['wporg:duplicate-group'] = array(
				array(
					'href' => $this->get_rest_url( "/groups/{$duplicate_group_id}" ),
				),
			);
		}

		return $links;
	}

	/**
	 * Get REST links for an event.
	 *
	 * @param int $event_id Event post ID.
	 * @param int $group_id Group post ID.
	 * @param int $venue_id Venue post ID.
	 *
	 * @return array
	 */
	protected function get_event_links( int $event_id, int $group_id, int $venue_id ): array {
		$links = $this->get_entity_links(
			'/events',
			$event_id,
			array(
				'wporg:calendar' => $this->get_rest_url( "/events/{$event_id}/calendar.ics" ),
			)
		);

		if ( $group_id ) {
			$links['wporg:group']        = array(
				array(
					'href' => $this->get_rest_url( "/groups/{$group_id}" ),
				),
			);
			$links['wporg:group-events'] = array(
				array(
					'href' => $this->get_rest_url( "/groups/{$group_id}/events" ),
				),
			);
		}

		if ( $venue_id ) {
			$links['wporg:venue'] = array(
				array(
					'href' => $this->get_rest_url( "/venues/{$venue_id}" ),
				),
			);
		}

		return $links;
	}

	/**
	 * Get REST links for a membership.
	 *
	 * @param int $membership_id Membership post ID.
	 *
	 * @return array
	 */
	protected function get_membership_links( int $membership_id ): array {
		$group_id = (int) get_post_meta( $membership_id, 'wporg_ce_group_id', true );
		$links    = array(
			'self'       => array(
				array(
					'href' => $group_id ? $this->get_rest_url( "/groups/{$group_id}/membership" ) : $this->get_rest_url( '/me/memberships' ),
				),
			),
			'collection' => array(
				array(
					'href' => $this->get_rest_url( '/me/memberships' ),
				),
			),
		);

		if ( $group_id ) {
			$links['wporg:group'] = array(
				array(
					'href' => $this->get_rest_url( "/groups/{$group_id}" ),
				),
			);
		}

		return $links;
	}

	/**
	 * Get REST links for an RSVP.
	 *
	 * @param int $rsvp_id RSVP post ID.
	 *
	 * @return array
	 */
	protected function get_rsvp_links( int $rsvp_id ): array {
		$event_id = (int) get_post_meta( $rsvp_id, 'wporg_ce_event_id', true );
		$links    = array(
			'self'       => array(
				array(
					'href' => $event_id ? $this->get_rest_url( "/events/{$event_id}/rsvp" ) : $this->get_rest_url( '/me/rsvps' ),
				),
			),
			'collection' => array(
				array(
					'href' => $this->get_rest_url( '/me/rsvps' ),
				),
			),
		);

		if ( $event_id ) {
			$links['wporg:event'] = array(
				array(
					'href' => $this->get_rest_url( "/events/{$event_id}" ),
				),
			);
		}

		return $links;
	}

	/**
	 * Get REST links for a group membership listed as a member, host, or organizer.
	 *
	 * @param int $membership_id Membership post ID.
	 *
	 * @return array
	 */
	protected function get_group_membership_links( int $membership_id ): array {
		$group_id = (int) get_post_meta( $membership_id, 'wporg_ce_group_id', true );

		if ( ! $group_id ) {
			return $this->get_membership_links( $membership_id );
		}

		return array(
			'self'             => array(
				array(
					'href' => $this->get_rest_url( "/groups/{$group_id}/organizers/{$membership_id}" ),
				),
			),
			'collection'       => array(
				array(
					'href' => $this->get_rest_url( "/groups/{$group_id}/members" ),
				),
			),
			'wporg:organizers' => array(
				array(
					'href' => $this->get_rest_url( "/groups/{$group_id}/organizers" ),
				),
			),
			'wporg:group'      => array(
				array(
					'href' => $this->get_rest_url( "/groups/{$group_id}" ),
				),
			),
		);
	}

	/**
	 * Get REST links for an attendee record.
	 *
	 * @param int $rsvp_id RSVP post ID.
	 *
	 * @return array
	 */
	protected function get_attendee_links( int $rsvp_id ): array {
		$event_id = (int) get_post_meta( $rsvp_id, 'wporg_ce_event_id', true );

		if ( ! $event_id ) {
			return $this->get_rsvp_links( $rsvp_id );
		}

		return array(
			'self'        => array(
				array(
					'href' => $this->get_rest_url( "/events/{$event_id}/attendees/{$rsvp_id}" ),
				),
			),
			'collection'  => array(
				array(
					'href' => $this->get_rest_url( "/events/{$event_id}/attendees" ),
				),
			),
			'wporg:event' => array(
				array(
					'href' => $this->get_rest_url( "/events/{$event_id}" ),
				),
			),
		);
	}

	/**
	 * Get REST links for a feedback item.
	 *
	 * @param int $feedback_id Feedback post ID.
	 *
	 * @return array
	 */
	protected function get_feedback_links( int $feedback_id ): array {
		$event_id = (int) get_post_meta( $feedback_id, 'wporg_ce_event_id', true );
		$links    = array(
			'collection' => array(
				array(
					'href' => $event_id ? $this->get_rest_url( "/events/{$event_id}/feedback" ) : $this->get_rest_url( '/events' ),
				),
			),
		);

		if ( $event_id ) {
			$links['wporg:event'] = array(
				array(
					'href' => $this->get_rest_url( "/events/{$event_id}" ),
				),
			);
		}

		return $links;
	}

	/**
	 * Get REST links for an entity-like resource.
	 *
	 * @param string $collection_path REST collection path without namespace.
	 * @param int    $id              Resource ID.
	 * @param array  $additional      Additional single-href links keyed by relationship.
	 *
	 * @return array
	 */
	protected function get_entity_links( string $collection_path, int $id, array $additional = array() ): array {
		$collection_path = '/' . trim( $collection_path, '/' );
		$links           = array(
			'self'       => array(
				array(
					'href' => $this->get_rest_url( "{$collection_path}/{$id}" ),
				),
			),
			'collection' => array(
				array(
					'href' => $this->get_rest_url( $collection_path ),
				),
			),
		);

		foreach ( $additional as $rel => $href ) {
			$links[ $rel ] = array(
				array(
					'href' => $href,
				),
			);
		}

		return $links;
	}

	/**
	 * Get a namespaced REST URL.
	 *
	 * @param string $path REST path without namespace.
	 *
	 * @return string
	 */
	protected function get_rest_url( string $path ): string {
		return rest_url( REST_NAMESPACE . '/' . ltrim( $path, '/' ) );
	}

	/**
	 * Prepare public terms for a REST response.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy key.
	 *
	 * @return array
	 */
	protected function prepare_object_terms_response( int $post_id, string $taxonomy ): array {
		$terms = get_the_terms( $post_id, $taxonomy );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return array();
		}

		return array_map(
			array( $this, 'prepare_term_response' ),
			array_values( $terms )
		);
	}

	/**
	 * Prepare a term for a REST response.
	 *
	 * @param \WP_Term $term Term object.
	 *
	 * @return array
	 */
	protected function prepare_term_response( \WP_Term $term ): array {
		return array(
			'id'       => (int) $term->term_id,
			'slug'     => $term->slug,
			'name'     => $term->name,
			'taxonomy' => $term->taxonomy,
		);
	}

	/**
	 * Get a JSON object schema.
	 *
	 * @param string $title      Optional schema title.
	 * @param array  $properties Object properties.
	 *
	 * @return array
	 */
	protected function get_object_schema( string $title, array $properties ): array {
		$schema = array(
			'type'                 => 'object',
			'properties'           => $properties,
			'additionalProperties' => false,
		);

		if ( '' !== $title ) {
			$schema['$schema'] = 'http://json-schema.org/draft-04/schema#';
			$schema['title']   = $title;
		}

		return $schema;
	}

	/**
	 * Get a string property schema.
	 *
	 * @param string $description Property description.
	 *
	 * @return array
	 */
	protected function get_string_property_schema( string $description ): array {
		return array(
			'description' => $description,
			'type'        => 'string',
		);
	}

	/**
	 * Get a URI property schema.
	 *
	 * @param string $description Property description.
	 *
	 * @return array
	 */
	protected function get_uri_property_schema( string $description ): array {
		return array(
			'description' => $description,
			'type'        => 'string',
			'format'      => 'uri',
		);
	}

	/**
	 * Get a date-time property schema.
	 *
	 * @param string $description Property description.
	 *
	 * @return array
	 */
	protected function get_datetime_property_schema( string $description ): array {
		return array(
			'description' => $description,
			'type'        => 'string',
			'format'      => 'date-time',
		);
	}

	/**
	 * Get an integer property schema.
	 *
	 * @param string $description Property description.
	 *
	 * @return array
	 */
	protected function get_integer_property_schema( string $description ): array {
		return array(
			'description' => $description,
			'type'        => 'integer',
		);
	}

	/**
	 * Get an integer array property schema.
	 *
	 * @param string $description Property description.
	 *
	 * @return array
	 */
	protected function get_integers_property_schema( string $description ): array {
		return array(
			'description' => $description,
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
		);
	}

	/**
	 * Get a number property schema.
	 *
	 * @param string $description Property description.
	 *
	 * @return array
	 */
	protected function get_number_property_schema( string $description ): array {
		return array(
			'description' => $description,
			'type'        => 'number',
		);
	}

	/**
	 * Get the REST links property schema.
	 *
	 * @return array
	 */
	protected function get_links_property_schema(): array {
		return array(
			'description'          => 'REST links for this resource.',
			'type'                 => 'object',
			'additionalProperties' => array(
				'type'  => 'array',
				'items' => array(
					'type'                 => 'object',
					'properties'           => array(
						'href'       => $this->get_uri_property_schema( 'Linked REST resource URL.' ),
						'embeddable' => array(
							'description' => 'Whether this link can be embedded.',
							'type'        => 'boolean',
						),
					),
					'additionalProperties' => true,
				),
			),
		);
	}
}
