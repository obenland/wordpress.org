<?php
/**
 * Tests for Community Events relationship helpers.
 *
 * @package WordPressdotorg\Community_Events
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Community_Events\Tests;

use WP_UnitTestCase;

use const WordPressdotorg\Community_Events\EVENT_APPROVAL_STATUS_CANCELED;
use const WordPressdotorg\Community_Events\EVENT_APPROVAL_STATUS_APPROVED;
use const WordPressdotorg\Community_Events\MEMBERSHIP_ROLE_HOST;
use const WordPressdotorg\Community_Events\MEMBERSHIP_ROLE_MEMBER;
use const WordPressdotorg\Community_Events\MEMBERSHIP_ROLE_ORGANIZER;
use const WordPressdotorg\Community_Events\MEMBERSHIP_STATUS_ACTIVE;
use const WordPressdotorg\Community_Events\NOTIFICATION_EVENT_CANCELLATIONS;
use const WordPressdotorg\Community_Events\NOTIFICATION_EVENT_UPDATES;
use const WordPressdotorg\Community_Events\NOTIFICATION_NEW_EVENTS;
use const WordPressdotorg\Community_Events\POST_TYPE_EVENT;
use const WordPressdotorg\Community_Events\POST_TYPE_GROUP;
use const WordPressdotorg\Community_Events\POST_TYPE_MEMBERSHIP;
use const WordPressdotorg\Community_Events\POST_TYPE_RSVP;
use const WordPressdotorg\Community_Events\RELATIONSHIP_VISIBILITY_PRIVATE;
use const WordPressdotorg\Community_Events\RSVP_STATUS_ATTENDING;
use const WordPressdotorg\Community_Events\RSVP_STATUS_NOT_ATTENDING;
use const WordPressdotorg\Community_Events\RSVP_STATUS_WAITLISTED;

/**
 * Tests for membership and RSVP behavior.
 */
class Relationships_Test extends WP_UnitTestCase {
	/**
	 * Register the plugin data model for each isolated test.
	 */
	public function set_up(): void {
		parent::set_up();

		\WordPressdotorg\Community_Events\register_post_types();
		\WordPressdotorg\Community_Events\register_meta_fields();
	}

	/**
	 * Joining a group should create one user-owned membership record.
	 */
	public function test_join_group_creates_user_owned_membership(): void {
		$user_id  = self::factory()->user->create();
		$group_id = $this->create_group();

		$membership_id = \WordPressdotorg\Community_Events\join_group( $group_id, $user_id );

		$this->assertNotWPError( $membership_id );

		$membership = get_post( $membership_id );

		$this->assertSame( POST_TYPE_MEMBERSHIP, $membership->post_type );
		$this->assertSame( $user_id, (int) $membership->post_author );
		$this->assertSame( $group_id, (int) $membership->post_parent );
		$this->assertSame( $group_id, (int) get_post_meta( $membership_id, 'wporg_ce_group_id', true ) );
		$this->assertSame( $user_id, (int) get_post_meta( $membership_id, 'wporg_ce_user_id', true ) );
		$this->assertSame( MEMBERSHIP_ROLE_MEMBER, get_post_meta( $membership_id, 'wporg_ce_role', true ) );
		$this->assertSame( MEMBERSHIP_STATUS_ACTIVE, get_post_meta( $membership_id, 'wporg_ce_status', true ) );
		$this->assertNotEmpty( get_post_meta( $membership_id, 'wporg_ce_joined_at_utc', true ) );
		$this->assertSame(
			\WordPressdotorg\Community_Events\get_default_notification_preferences(),
			get_post_meta( $membership_id, 'wporg_ce_notification_preferences', true )
		);
	}

	/**
	 * Joining an existing group membership should update the original record.
	 */
	public function test_join_group_updates_existing_membership(): void {
		$user_id  = self::factory()->user->create();
		$group_id = $this->create_group();

		$membership_id = \WordPressdotorg\Community_Events\join_group(
			$group_id,
			$user_id,
			array(
				'joined_at_utc' => '2026-01-01 00:00:00',
			)
		);
		$updated_id    = \WordPressdotorg\Community_Events\join_group(
			$group_id,
			$user_id,
			array(
				'role' => MEMBERSHIP_ROLE_HOST,
			)
		);

		$this->assertSame( $membership_id, $updated_id );
		$this->assertSame( MEMBERSHIP_ROLE_HOST, get_post_meta( $membership_id, 'wporg_ce_role', true ) );
		$this->assertSame( '2026-01-01 00:00:00', get_post_meta( $membership_id, 'wporg_ce_joined_at_utc', true ) );

		$preference_update_id = \WordPressdotorg\Community_Events\join_group(
			$group_id,
			$user_id,
			array(
				'notification_preferences' => array(
					NOTIFICATION_NEW_EVENTS => false,
				),
			)
		);

		$this->assertSame( $membership_id, $preference_update_id );
		$this->assertSame( MEMBERSHIP_ROLE_HOST, get_post_meta( $membership_id, 'wporg_ce_role', true ) );
	}

	/**
	 * Joining a group should save partial notification preferences and preserve them on later updates.
	 */
	public function test_join_group_saves_notification_preferences(): void {
		$user_id  = self::factory()->user->create();
		$group_id = $this->create_group();

		$membership_id = \WordPressdotorg\Community_Events\join_group(
			$group_id,
			$user_id,
			array(
				'notification_preferences' => array(
					NOTIFICATION_NEW_EVENTS => false,
				),
			)
		);

		$this->assertNotWPError( $membership_id );
		$this->assertSame(
			array(
				NOTIFICATION_NEW_EVENTS          => false,
				NOTIFICATION_EVENT_UPDATES       => true,
				NOTIFICATION_EVENT_CANCELLATIONS => true,
			),
			get_post_meta( $membership_id, 'wporg_ce_notification_preferences', true )
		);

		$updated_id = \WordPressdotorg\Community_Events\join_group(
			$group_id,
			$user_id,
			array(
				'role' => MEMBERSHIP_ROLE_HOST,
			)
		);

		$this->assertSame( $membership_id, $updated_id );
		$this->assertSame(
			array(
				NOTIFICATION_NEW_EVENTS          => false,
				NOTIFICATION_EVENT_UPDATES       => true,
				NOTIFICATION_EVENT_CANCELLATIONS => true,
			),
			get_post_meta( $membership_id, 'wporg_ce_notification_preferences', true )
		);
	}

	/**
	 * Event lifecycle notifications should only email active group members who opt in.
	 */
	public function test_event_lifecycle_notifications_respect_membership_preferences(): void {
		$organizer_id  = self::factory()->user->create(
			array(
				'user_email' => 'organizer@example.org',
			)
		);
		$subscriber_id = self::factory()->user->create(
			array(
				'user_email' => 'subscriber@example.org',
			)
		);
		$quiet_id      = self::factory()->user->create(
			array(
				'user_email' => 'quiet@example.org',
			)
		);
		$group_id      = $this->create_group();
		$sent_mail     = array();
		$mail_capture  = static function ( $preempt, $atts ) use ( &$sent_mail ) {
			unset( $preempt );

			$sent_mail[] = $atts;

			return true;
		};

		\WordPressdotorg\Community_Events\join_group(
			$group_id,
			$organizer_id,
			array(
				'role'                     => MEMBERSHIP_ROLE_ORGANIZER,
				'notification_preferences' => array(
					NOTIFICATION_NEW_EVENTS          => false,
					NOTIFICATION_EVENT_UPDATES       => false,
					NOTIFICATION_EVENT_CANCELLATIONS => false,
				),
			)
		);
		\WordPressdotorg\Community_Events\join_group( $group_id, $subscriber_id );
		\WordPressdotorg\Community_Events\join_group(
			$group_id,
			$quiet_id,
			array(
				'notification_preferences' => array(
					NOTIFICATION_NEW_EVENTS          => false,
					NOTIFICATION_EVENT_UPDATES       => false,
					NOTIFICATION_EVENT_CANCELLATIONS => false,
				),
			)
		);

		add_filter( 'pre_wp_mail', $mail_capture, 10, 2 );

		try {
			$event_id = \WordPressdotorg\Community_Events\create_group_event(
				$group_id,
				$organizer_id,
				array(
					'start_utc' => '2026-06-15 18:00:00',
					'title'     => 'Community Meetup',
				)
			);

			$this->assertNotWPError( $event_id );
			$this->assertCount( 1, $sent_mail );
			$this->assertSame( 'subscriber@example.org', $sent_mail[0]['to'] );
			$this->assertStringContainsString( 'New event:', $sent_mail[0]['subject'] );

			$sent_mail  = array();
			$updated_id = \WordPressdotorg\Community_Events\update_group_event(
				(int) $event_id,
				$organizer_id,
				array(
					'title' => 'Updated Community Meetup',
				)
			);

			$this->assertSame( $event_id, $updated_id );
			$this->assertCount( 1, $sent_mail );
			$this->assertSame( 'subscriber@example.org', $sent_mail[0]['to'] );
			$this->assertStringContainsString( 'Event updated:', $sent_mail[0]['subject'] );

			$sent_mail   = array();
			$canceled_id = \WordPressdotorg\Community_Events\cancel_group_event(
				(int) $event_id,
				$organizer_id,
				array(
					'reason' => 'The venue is unavailable.',
				)
			);

			$this->assertSame( $event_id, $canceled_id );
			$this->assertCount( 1, $sent_mail );
			$this->assertSame( 'subscriber@example.org', $sent_mail[0]['to'] );
			$this->assertStringContainsString( 'Event canceled:', $sent_mail[0]['subject'] );
			$this->assertStringContainsString( 'Reason: The venue is unavailable.', $sent_mail[0]['message'] );
		} finally {
			remove_filter( 'pre_wp_mail', $mail_capture, 10 );
		}
	}

	/**
	 * Attendee reminders should email attending RSVPs once per event start time.
	 */
	public function test_due_event_reminders_email_attending_rsvps_once(): void {
		$group_id     = $this->create_group();
		$event_id     = $this->create_event(
			$group_id,
			0,
			array(
				'wporg_ce_approval_status' => EVENT_APPROVAL_STATUS_APPROVED,
				'wporg_ce_online_url'      => 'https://example.org/meet',
				'wporg_ce_start_utc'       => '2026-06-02 18:00:00',
				'wporg_ce_timezone'        => 'Europe/Zurich',
			)
		);
		$attendee_id  = self::factory()->user->create(
			array(
				'user_email' => 'attendee@example.org',
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
		$sent_mail    = array();
		$mail_capture = static function ( $preempt, $atts ) use ( &$sent_mail ) {
			unset( $preempt );

			$sent_mail[] = $atts;

			return true;
		};

		\WordPressdotorg\Community_Events\rsvp_to_event( $event_id, $attendee_id );
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

		add_filter( 'pre_wp_mail', $mail_capture, 10, 2 );

		try {
			$sent = \WordPressdotorg\Community_Events\send_due_event_reminders( strtotime( '2026-06-01 20:00:00' ) );

			$this->assertArrayHasKey( $event_id, $sent );
			$this->assertSame( array( 'attendee@example.org', 'private@example.org' ), $sent[ $event_id ] );
			$this->assertCount( 2, $sent_mail );
			$this->assertSame( 'attendee@example.org', $sent_mail[0]['to'] );
			$this->assertStringContainsString( 'Reminder:', $sent_mail[0]['subject'] );
			$this->assertStringContainsString( 'Your event is coming up soon.', $sent_mail[0]['message'] );
			$this->assertStringContainsString( 'Location: Online', $sent_mail[0]['message'] );
			$this->assertSame( '2026-06-01 20:00:00', get_post_meta( $event_id, 'wporg_ce_attendee_reminder_sent_at_utc', true ) );
			$this->assertSame( '2026-06-02 18:00:00', get_post_meta( $event_id, 'wporg_ce_attendee_reminder_start_utc', true ) );

			$sent_mail = array();
			$this->assertSame( array(), \WordPressdotorg\Community_Events\send_due_event_reminders( strtotime( '2026-06-01 21:00:00' ) ) );
			$this->assertSame( array(), $sent_mail );
		} finally {
			remove_filter( 'pre_wp_mail', $mail_capture, 10 );
		}
	}

	/**
	 * Due reminder lookup should only return upcoming approved events that have not been reminded for their current start time.
	 */
	public function test_due_event_reminders_select_due_approved_unsent_events(): void {
		$group_id          = $this->create_group();
		$due_event_id      = $this->create_event(
			$group_id,
			0,
			array(
				'wporg_ce_approval_status' => EVENT_APPROVAL_STATUS_APPROVED,
				'wporg_ce_start_utc'       => '2026-06-02 18:00:00',
			)
		);
		$far_event_id      = $this->create_event(
			$group_id,
			0,
			array(
				'wporg_ce_approval_status' => EVENT_APPROVAL_STATUS_APPROVED,
				'wporg_ce_start_utc'       => '2026-06-04 18:00:00',
			)
		);
		$past_event_id     = $this->create_event(
			$group_id,
			0,
			array(
				'wporg_ce_approval_status' => EVENT_APPROVAL_STATUS_APPROVED,
				'wporg_ce_start_utc'       => '2026-06-01 18:00:00',
			)
		);
		$canceled_event_id = $this->create_event(
			$group_id,
			0,
			array(
				'wporg_ce_approval_status' => EVENT_APPROVAL_STATUS_CANCELED,
				'wporg_ce_start_utc'       => '2026-06-02 19:00:00',
			)
		);
		$reminded_event_id = $this->create_event(
			$group_id,
			0,
			array(
				'wporg_ce_approval_status'             => EVENT_APPROVAL_STATUS_APPROVED,
				'wporg_ce_attendee_reminder_start_utc' => '2026-06-02 20:00:00',
				'wporg_ce_start_utc'                   => '2026-06-02 20:00:00',
			)
		);

		$due_event_ids = \WordPressdotorg\Community_Events\get_due_event_reminder_ids( strtotime( '2026-06-01 20:00:00' ) );

		$this->assertSame( array( $due_event_id ), $due_event_ids );
		$this->assertNotContains( $far_event_id, $due_event_ids );
		$this->assertNotContains( $past_event_id, $due_event_ids );
		$this->assertNotContains( $canceled_event_id, $due_event_ids );
		$this->assertNotContains( $reminded_event_id, $due_event_ids );
	}

	/**
	 * RSVPing should create a user-owned event relationship and cached counts.
	 */
	public function test_rsvp_to_event_creates_relationship_and_updates_counts(): void {
		$user_id  = self::factory()->user->create();
		$group_id = $this->create_group();
		$event_id = $this->create_event( $group_id );

		$rsvp_id = \WordPressdotorg\Community_Events\rsvp_to_event(
			$event_id,
			$user_id,
			array(
				'guest_count' => 1,
			)
		);

		$this->assertNotWPError( $rsvp_id );

		$rsvp = get_post( $rsvp_id );

		$this->assertSame( POST_TYPE_RSVP, $rsvp->post_type );
		$this->assertSame( $user_id, (int) $rsvp->post_author );
		$this->assertSame( $event_id, (int) $rsvp->post_parent );
		$this->assertSame( $event_id, (int) get_post_meta( $rsvp_id, 'wporg_ce_event_id', true ) );
		$this->assertSame( $group_id, (int) get_post_meta( $rsvp_id, 'wporg_ce_group_id', true ) );
		$this->assertSame( $user_id, (int) get_post_meta( $rsvp_id, 'wporg_ce_user_id', true ) );
		$this->assertSame( RSVP_STATUS_ATTENDING, get_post_meta( $rsvp_id, 'wporg_ce_status', true ) );
		$this->assertSame( 1, (int) get_post_meta( $rsvp_id, 'wporg_ce_guest_count', true ) );
		$this->assertSame( 1, (int) get_post_meta( $event_id, 'wporg_ce_rsvp_count', true ) );
		$this->assertSame( 0, (int) get_post_meta( $event_id, 'wporg_ce_waitlist_count', true ) );
	}

	/**
	 * RSVP records should be updated instead of duplicated.
	 */
	public function test_rsvp_to_event_updates_existing_record(): void {
		$user_id  = self::factory()->user->create();
		$group_id = $this->create_group();
		$event_id = $this->create_event( $group_id );

		$rsvp_id    = \WordPressdotorg\Community_Events\rsvp_to_event( $event_id, $user_id );
		$updated_id = \WordPressdotorg\Community_Events\rsvp_to_event(
			$event_id,
			$user_id,
			array(
				'status' => RSVP_STATUS_NOT_ATTENDING,
			)
		);

		$this->assertSame( $rsvp_id, $updated_id );
		$this->assertSame( RSVP_STATUS_NOT_ATTENDING, get_post_meta( $rsvp_id, 'wporg_ce_status', true ) );
		$this->assertSame( 0, (int) get_post_meta( $event_id, 'wporg_ce_rsvp_count', true ) );
		$this->assertSame( 0, (int) get_post_meta( $event_id, 'wporg_ce_waitlist_count', true ) );
	}

	/**
	 * Full events should put additional RSVPs on the waitlist.
	 */
	public function test_rsvp_to_event_waitlists_when_capacity_is_full(): void {
		$group_id = $this->create_group();
		$event_id = $this->create_event( $group_id, 1 );
		$user_id  = self::factory()->user->create();
		$other_id = self::factory()->user->create();

		$attending_id = \WordPressdotorg\Community_Events\rsvp_to_event( $event_id, $user_id );
		$waitlist_id  = \WordPressdotorg\Community_Events\rsvp_to_event( $event_id, $other_id );

		$this->assertSame( RSVP_STATUS_ATTENDING, get_post_meta( $attending_id, 'wporg_ce_status', true ) );
		$this->assertSame( RSVP_STATUS_WAITLISTED, get_post_meta( $waitlist_id, 'wporg_ce_status', true ) );
		$this->assertSame( 1, (int) get_post_meta( $waitlist_id, 'wporg_ce_waitlist_position', true ) );
		$this->assertSame( 1, (int) get_post_meta( $event_id, 'wporg_ce_rsvp_count', true ) );
		$this->assertSame( 1, (int) get_post_meta( $event_id, 'wporg_ce_waitlist_count', true ) );
	}

	/**
	 * RSVP status changes should send transactional attendee emails.
	 */
	public function test_rsvp_status_notifications_email_attendees(): void {
		$group_id      = $this->create_group();
		$event_id      = $this->create_event( $group_id, 1 );
		$attendee_id   = self::factory()->user->create(
			array(
				'user_email' => 'attendee-rsvp@example.org',
			)
		);
		$waitlisted_id = self::factory()->user->create(
			array(
				'user_email' => 'waitlisted-rsvp@example.org',
			)
		);
		$sent_mail     = array();
		$mail_capture  = static function ( $preempt, $atts ) use ( &$sent_mail ) {
			unset( $preempt );

			$sent_mail[] = $atts;

			return true;
		};

		add_filter( 'pre_wp_mail', $mail_capture, 10, 2 );

		try {
			\WordPressdotorg\Community_Events\rsvp_to_event( $event_id, $attendee_id );
			\WordPressdotorg\Community_Events\rsvp_to_event( $event_id, $waitlisted_id );

			$this->assertCount( 2, $sent_mail );
			$this->assertSame( 'attendee-rsvp@example.org', $sent_mail[0]['to'] );
			$this->assertStringContainsString( 'RSVP confirmed:', $sent_mail[0]['subject'] );
			$this->assertStringContainsString( 'Your RSVP is confirmed.', $sent_mail[0]['message'] );
			$this->assertSame( 'waitlisted-rsvp@example.org', $sent_mail[1]['to'] );
			$this->assertStringContainsString( 'You are on the waitlist:', $sent_mail[1]['subject'] );
			$this->assertStringContainsString( 'You are on the waitlist for this event.', $sent_mail[1]['message'] );

			$sent_mail = array();

			\WordPressdotorg\Community_Events\rsvp_to_event(
				$event_id,
				$attendee_id,
				array(
					'guest_count' => 1,
				)
			);

			$this->assertSame( array(), $sent_mail );

			\WordPressdotorg\Community_Events\rsvp_to_event(
				$event_id,
				$waitlisted_id,
				array(
					'status' => RSVP_STATUS_NOT_ATTENDING,
				)
			);

			$this->assertCount( 1, $sent_mail );
			$this->assertSame( 'waitlisted-rsvp@example.org', $sent_mail[0]['to'] );
			$this->assertStringContainsString( 'RSVP canceled:', $sent_mail[0]['subject'] );
			$this->assertStringContainsString( 'Your RSVP has been canceled.', $sent_mail[0]['message'] );
		} finally {
			remove_filter( 'pre_wp_mail', $mail_capture, 10 );
		}
	}

	/**
	 * Waitlisted attendees should be promoted when an attending RSVP cancels.
	 */
	public function test_rsvp_to_event_promotes_waitlist_when_attendee_cancels(): void {
		$group_id             = $this->create_group();
		$event_id             = $this->create_event( $group_id, 1 );
		$attendee_id          = self::factory()->user->create();
		$first_waitlisted_id  = self::factory()->user->create();
		$second_waitlisted_id = self::factory()->user->create();
		$attendee_rsvp_id     = \WordPressdotorg\Community_Events\rsvp_to_event( $event_id, $attendee_id );
		$first_waitlist_rsvp  = \WordPressdotorg\Community_Events\rsvp_to_event( $event_id, $first_waitlisted_id );
		$second_waitlist_rsvp = \WordPressdotorg\Community_Events\rsvp_to_event( $event_id, $second_waitlisted_id );
		$canceled_attendee_id = \WordPressdotorg\Community_Events\rsvp_to_event(
			$event_id,
			$attendee_id,
			array(
				'status' => RSVP_STATUS_NOT_ATTENDING,
			)
		);

		$this->assertSame( $attendee_rsvp_id, $canceled_attendee_id );
		$this->assertSame( RSVP_STATUS_NOT_ATTENDING, get_post_meta( $attendee_rsvp_id, 'wporg_ce_status', true ) );
		$this->assertSame( RSVP_STATUS_ATTENDING, get_post_meta( $first_waitlist_rsvp, 'wporg_ce_status', true ) );
		$this->assertSame( 0, (int) get_post_meta( $first_waitlist_rsvp, 'wporg_ce_waitlist_position', true ) );
		$this->assertSame( RSVP_STATUS_WAITLISTED, get_post_meta( $second_waitlist_rsvp, 'wporg_ce_status', true ) );
		$this->assertSame( 1, (int) get_post_meta( $second_waitlist_rsvp, 'wporg_ce_waitlist_position', true ) );
		$this->assertSame( 1, (int) get_post_meta( $event_id, 'wporg_ce_rsvp_count', true ) );
		$this->assertSame( 1, (int) get_post_meta( $event_id, 'wporg_ce_waitlist_count', true ) );
	}

	/**
	 * Promoted waitlisted attendees should receive a transactional email.
	 */
	public function test_waitlist_promotion_notification_emails_promoted_attendee(): void {
		$group_id             = $this->create_group();
		$event_id             = $this->create_event(
			$group_id,
			1,
			array(
				'wporg_ce_start_utc' => '2026-06-15 18:00:00',
				'wporg_ce_timezone'  => 'Europe/Zurich',
			)
		);
		$attendee_id          = self::factory()->user->create(
			array(
				'user_email' => 'canceling@example.org',
			)
		);
		$first_waitlisted_id  = self::factory()->user->create(
			array(
				'user_email' => 'promoted@example.org',
			)
		);
		$second_waitlisted_id = self::factory()->user->create(
			array(
				'user_email' => 'still-waiting@example.org',
			)
		);
		$sent_mail            = array();
		$mail_capture         = static function ( $preempt, $atts ) use ( &$sent_mail ) {
			unset( $preempt );

			$sent_mail[] = $atts;

			return true;
		};

		\WordPressdotorg\Community_Events\rsvp_to_event( $event_id, $attendee_id );
		$first_waitlist_rsvp = \WordPressdotorg\Community_Events\rsvp_to_event( $event_id, $first_waitlisted_id );
		\WordPressdotorg\Community_Events\rsvp_to_event( $event_id, $second_waitlisted_id );

		add_filter( 'pre_wp_mail', $mail_capture, 10, 2 );

		try {
			\WordPressdotorg\Community_Events\rsvp_to_event(
				$event_id,
				$attendee_id,
				array(
					'status' => RSVP_STATUS_NOT_ATTENDING,
				)
			);

			$this->assertSame( RSVP_STATUS_ATTENDING, get_post_meta( $first_waitlist_rsvp, 'wporg_ce_status', true ) );
			$this->assertCount( 2, $sent_mail );
			$this->assertSame( 'canceling@example.org', $sent_mail[0]['to'] );
			$this->assertStringContainsString( 'RSVP canceled:', $sent_mail[0]['subject'] );
			$this->assertSame( 'promoted@example.org', $sent_mail[1]['to'] );
			$this->assertStringContainsString( 'You are in:', $sent_mail[1]['subject'] );
			$this->assertStringContainsString( 'Good news, your spot is confirmed.', $sent_mail[1]['message'] );
			$this->assertStringContainsString( 'You are receiving this because your RSVP moved from the waitlist to attending.', $sent_mail[1]['message'] );
		} finally {
			remove_filter( 'pre_wp_mail', $mail_capture, 10 );
		}
	}

	/**
	 * Increasing event capacity should promote the next waitlisted RSVP.
	 */
	public function test_update_group_event_promotes_waitlist_when_capacity_increases(): void {
		$group_id      = $this->create_group();
		$event_id      = $this->create_event( $group_id, 1 );
		$organizer_id  = self::factory()->user->create();
		$attendee_id   = self::factory()->user->create();
		$waitlisted_id = self::factory()->user->create();

		\WordPressdotorg\Community_Events\join_group(
			$group_id,
			$organizer_id,
			array(
				'role' => MEMBERSHIP_ROLE_ORGANIZER,
			)
		);

		\WordPressdotorg\Community_Events\rsvp_to_event( $event_id, $attendee_id );
		$waitlist_rsvp_id = \WordPressdotorg\Community_Events\rsvp_to_event( $event_id, $waitlisted_id );
		$updated_event_id = \WordPressdotorg\Community_Events\update_group_event(
			$event_id,
			$organizer_id,
			array(
				'capacity' => 2,
			)
		);

		$this->assertNotWPError( $updated_event_id );
		$this->assertSame( $event_id, $updated_event_id );
		$this->assertSame( RSVP_STATUS_ATTENDING, get_post_meta( $waitlist_rsvp_id, 'wporg_ce_status', true ) );
		$this->assertSame( 0, (int) get_post_meta( $waitlist_rsvp_id, 'wporg_ce_waitlist_position', true ) );
		$this->assertSame( 2, (int) get_post_meta( $event_id, 'wporg_ce_rsvp_count', true ) );
		$this->assertSame( 0, (int) get_post_meta( $event_id, 'wporg_ce_waitlist_count', true ) );
	}

	/**
	 * Create a community group.
	 *
	 * @return int
	 */
	private function create_group(): int {
		return self::factory()->post->create(
			array(
				'post_type'   => POST_TYPE_GROUP,
				'post_status' => 'publish',
				'post_title'  => 'WordPress Zurich',
			)
		);
	}

	/**
	 * Create an event attached to a group.
	 *
	 * @param int   $group_id Group post ID.
	 * @param int   $capacity Event capacity.
	 * @param array $meta     Event meta.
	 *
	 * @return int
	 */
	private function create_event( int $group_id, int $capacity = 0, array $meta = array() ): int {
		return self::factory()->post->create(
			array(
				'post_type'   => POST_TYPE_EVENT,
				'post_status' => 'publish',
				'post_title'  => 'Community Meetup',
				'meta_input'  => array(
					'wporg_ce_group_id' => $group_id,
					'wporg_ce_capacity' => $capacity,
				) + $meta,
			)
		);
	}
}
