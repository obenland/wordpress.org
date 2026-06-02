<?php
/**
 * Tests for the Community Events registration layer.
 *
 * @package WordPressdotorg\Community_Events
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Community_Events\Tests;

use WP_UnitTestCase;

use const WordPressdotorg\Community_Events\EVENT_APPROVAL_STATUS_APPROVED;
use const WordPressdotorg\Community_Events\EVENT_REMINDER_CRON_HOOK;
use const WordPressdotorg\Community_Events\POST_TYPE_GROUP_SUGGESTION;
use const WordPressdotorg\Community_Events\POST_TYPE_EVENT;
use const WordPressdotorg\Community_Events\POST_TYPE_FEEDBACK;
use const WordPressdotorg\Community_Events\POST_TYPE_GROUP;
use const WordPressdotorg\Community_Events\POST_TYPE_IMPORT;
use const WordPressdotorg\Community_Events\POST_TYPE_MEMBERSHIP;
use const WordPressdotorg\Community_Events\POST_TYPE_RSVP;
use const WordPressdotorg\Community_Events\POST_TYPE_VENUE;
use const WordPressdotorg\Community_Events\TAXONOMY_COUNTRY;
use const WordPressdotorg\Community_Events\TAXONOMY_EVENT_FORMAT;
use const WordPressdotorg\Community_Events\TAXONOMY_EVENT_TYPE;
use const WordPressdotorg\Community_Events\TAXONOMY_GROUP_TYPE;
use const WordPressdotorg\Community_Events\TAXONOMY_LANGUAGE;
use const WordPressdotorg\Community_Events\TAXONOMY_TOPIC;

/**
 * Tests for registered post types, taxonomies, and meta fields.
 */
class Registration_Test extends WP_UnitTestCase {
	/**
	 * Register the plugin data model for each isolated test.
	 */
	public function set_up(): void {
		parent::set_up();

		\WordPressdotorg\Community_Events\register_post_types();
		\WordPressdotorg\Community_Events\register_taxonomies();
		\WordPressdotorg\Community_Events\register_meta_fields();
	}

	/**
	 * Public/editorial post types should be registered for the theme and REST UI.
	 */
	public function test_public_editorial_post_types_are_registered(): void {
		$this->assert_public_post_type( POST_TYPE_GROUP, 'groups' );
		$this->assert_public_post_type( POST_TYPE_EVENT, 'events' );
		$this->assert_public_post_type( POST_TYPE_VENUE, 'venues' );
		$this->assertTrue( post_type_supports( POST_TYPE_EVENT, 'comments' ) );
		$this->assertNull( get_post_type_object( 'wporg_ce_notice' ), 'Announcements are intentionally tabled for now.' );

		$suggestion = get_post_type_object( POST_TYPE_GROUP_SUGGESTION );
		$this->assertNotNull( $suggestion );
		$this->assertFalse( $suggestion->public );
		$this->assertTrue( $suggestion->show_ui );
		$this->assertTrue( $suggestion->show_in_rest );
		$this->assertSame( 'group-suggestions', $suggestion->rest_base );
		$this->assertTrue( post_type_supports( POST_TYPE_GROUP_SUGGESTION, 'custom-fields' ) );
	}

	/**
	 * Relationship post types should remain private while still being editable/exportable.
	 */
	public function test_relationship_post_types_are_private(): void {
		foreach ( array( POST_TYPE_MEMBERSHIP, POST_TYPE_RSVP, POST_TYPE_FEEDBACK, POST_TYPE_IMPORT ) as $post_type ) {
			$object = get_post_type_object( $post_type );

			$this->assertNotNull( $object, "{$post_type} should be registered." );
			$this->assertFalse( $object->public, "{$post_type} should not be public." );
			$this->assertFalse( $object->show_in_menu, "{$post_type} should not add a top-level menu." );
			$this->assertTrue( $object->show_ui, "{$post_type} should have an admin UI." );
			$this->assertTrue( $object->show_in_rest, "{$post_type} should be available to authenticated REST workflows." );
			$this->assertTrue( post_type_supports( $post_type, 'author' ), "{$post_type} should support post_author." );
		}
	}

	/**
	 * Discovery taxonomies should be attached to the objects they classify.
	 */
	public function test_taxonomies_are_registered_on_expected_objects(): void {
		$this->assert_taxonomy_object_types( TAXONOMY_GROUP_TYPE, array( POST_TYPE_GROUP, POST_TYPE_GROUP_SUGGESTION ) );
		$this->assert_taxonomy_object_types( TAXONOMY_EVENT_TYPE, array( POST_TYPE_EVENT ) );
		$this->assert_taxonomy_object_types( TAXONOMY_EVENT_FORMAT, array( POST_TYPE_EVENT ) );
		$this->assert_taxonomy_object_types( TAXONOMY_TOPIC, array( POST_TYPE_GROUP, POST_TYPE_GROUP_SUGGESTION, POST_TYPE_EVENT ) );
		$this->assert_taxonomy_object_types( TAXONOMY_LANGUAGE, array( POST_TYPE_GROUP, POST_TYPE_GROUP_SUGGESTION, POST_TYPE_EVENT ) );
		$this->assert_taxonomy_object_types( TAXONOMY_COUNTRY, array( POST_TYPE_GROUP, POST_TYPE_GROUP_SUGGESTION, POST_TYPE_EVENT, POST_TYPE_VENUE ) );
	}

	/**
	 * Registered meta should expose typed REST schemas for core relationship fields.
	 */
	public function test_registered_meta_fields_have_expected_types(): void {
		$group_meta      = get_registered_meta_keys( 'post', POST_TYPE_GROUP );
		$event_meta      = get_registered_meta_keys( 'post', POST_TYPE_EVENT );
		$feedback_meta   = get_registered_meta_keys( 'post', POST_TYPE_FEEDBACK );
		$suggestion_meta = get_registered_meta_keys( 'post', POST_TYPE_GROUP_SUGGESTION );
		$membership_meta = get_registered_meta_keys( 'post', POST_TYPE_MEMBERSHIP );
		$rsvp_meta       = get_registered_meta_keys( 'post', POST_TYPE_RSVP );
		$import_meta     = get_registered_meta_keys( 'post', POST_TYPE_IMPORT );

		$this->assertSame( 'string', $group_meta['wporg_ce_website_url']['type'] );
		$this->assertSame( 'string', $group_meta['wporg_ce_website_url']['show_in_rest']['schema']['type'] );
		$this->assertSame( 'uri', $group_meta['wporg_ce_website_url']['show_in_rest']['schema']['format'] );

		$this->assertSame( 'integer', $event_meta['wporg_ce_group_id']['type'] );
		$this->assertSame( 'integer', $event_meta['wporg_ce_venue_id']['type'] );
		$this->assertSame( 'integer', $event_meta['wporg_ce_host_user_id']['type'] );
		$this->assertSame( 'array', $event_meta['wporg_ce_host_user_ids']['type'] );
		$this->assertSame( 'string', $event_meta['wporg_ce_start_utc']['type'] );
		$this->assertSame( 'integer', $event_meta['wporg_ce_capacity']['type'] );
		$this->assertSame( 'string', $event_meta['wporg_ce_approval_status']['type'] );
		$this->assertSame( 'string', $event_meta['wporg_ce_canceled_at_utc']['type'] );
		$this->assertSame( 'integer', $event_meta['wporg_ce_canceled_by_user_id']['type'] );
		$this->assertSame( 'string', $event_meta['wporg_ce_cancellation_reason']['type'] );
		$this->assertSame( 'string', $event_meta['wporg_ce_attendee_reminder_sent_at_utc']['type'] );
		$this->assertSame( 'string', $event_meta['wporg_ce_attendee_reminder_start_utc']['type'] );
		$this->assertSame( 'integer', $event_meta['wporg_ce_copied_from_event_id']['type'] );
		$this->assertSame( 'array', $event_meta['wporg_ce_rsvp_questions']['type'] );
		$this->assertSame( 'integer', $event_meta['wporg_ce_group_id']['show_in_rest']['schema']['type'] );
		$this->assertSame( 'array', $event_meta['wporg_ce_host_user_ids']['show_in_rest']['schema']['type'] );
		$this->assertSame( 'integer', $event_meta['wporg_ce_host_user_ids']['show_in_rest']['schema']['items']['type'] );
		$this->assertSame( 'array', $event_meta['wporg_ce_rsvp_questions']['show_in_rest']['schema']['type'] );
		$this->assertSame( 'object', $event_meta['wporg_ce_rsvp_questions']['show_in_rest']['schema']['items']['type'] );

		$this->assertSame( 'string', $suggestion_meta['wporg_ce_location_label']['type'] );
		$this->assertSame( 'string', $suggestion_meta['wporg_ce_website_url']['type'] );
		$this->assertSame( 'uri', $suggestion_meta['wporg_ce_website_url']['show_in_rest']['schema']['format'] );
		$this->assertSame( 'string', $suggestion_meta['wporg_ce_review_status']['type'] );
		$this->assertSame( \WordPressdotorg\Community_Events\get_group_suggestion_review_statuses(), $suggestion_meta['wporg_ce_review_status']['show_in_rest']['schema']['enum'] );
		$this->assertSame( 'integer', $suggestion_meta['wporg_ce_reviewed_by_user_id']['type'] );
		$this->assertSame( 'integer', $suggestion_meta['wporg_ce_created_group_id']['type'] );
		$this->assertSame( 'integer', $suggestion_meta['wporg_ce_duplicate_group_id']['type'] );

		$this->assertSame( 'integer', $membership_meta['wporg_ce_group_id']['type'] );
		$this->assertSame( 'integer', $membership_meta['wporg_ce_user_id']['type'] );
		$this->assertSame( 'string', $membership_meta['wporg_ce_role']['type'] );
		$this->assertSame( 'object', $membership_meta['wporg_ce_notification_preferences']['type'] );
		$this->assertSame( \WordPressdotorg\Community_Events\get_default_notification_preferences(), $membership_meta['wporg_ce_notification_preferences']['default'] );
		$this->assertSame( 'object', $membership_meta['wporg_ce_notification_preferences']['show_in_rest']['schema']['type'] );
		$this->assertSame( 'boolean', $membership_meta['wporg_ce_notification_preferences']['show_in_rest']['schema']['properties']['new_events']['type'] );

		$this->assertSame( 'integer', $rsvp_meta['wporg_ce_event_id']['type'] );
		$this->assertSame( 'integer', $rsvp_meta['wporg_ce_user_id']['type'] );
		$this->assertSame( 'integer', $rsvp_meta['wporg_ce_waitlist_position']['type'] );
		$this->assertSame( 'string', $rsvp_meta['wporg_ce_status']['type'] );
		$this->assertSame( 'string', $rsvp_meta['wporg_ce_attendance_status']['type'] );
		$this->assertSame( 'string', $rsvp_meta['wporg_ce_attended_at_utc']['type'] );
		$this->assertSame( 'integer', $rsvp_meta['wporg_ce_attendance_by']['type'] );
		$this->assertSame( 'string', $rsvp_meta['wporg_ce_attendance_at_utc']['type'] );
		$this->assertSame( 'object', $rsvp_meta['wporg_ce_answers']['type'] );
		$this->assertSame( 'object', $rsvp_meta['wporg_ce_answers']['show_in_rest']['schema']['type'] );

		$this->assertSame( 'integer', $feedback_meta['wporg_ce_event_id']['type'] );
		$this->assertSame( 'integer', $feedback_meta['wporg_ce_group_id']['type'] );
		$this->assertSame( 'integer', $feedback_meta['wporg_ce_user_id']['type'] );
		$this->assertSame( 'integer', $feedback_meta['wporg_ce_rating']['type'] );
		$this->assertSame( 'string', $feedback_meta['wporg_ce_created_at_utc']['type'] );

		$this->assertSame( 'string', $import_meta['wporg_ce_source']['type'] );
		$this->assertSame( 'string', $import_meta['wporg_ce_source_id']['type'] );
		$this->assertSame( 'string', $import_meta['wporg_ce_target_type']['type'] );
		$this->assertSame( 'integer', $import_meta['wporg_ce_target_id']['type'] );
		$this->assertSame( 'string', $import_meta['wporg_ce_import_status']['type'] );
		$this->assertSame( \WordPressdotorg\Community_Events\get_import_statuses(), $import_meta['wporg_ce_import_status']['show_in_rest']['schema']['enum'] );
	}

	/**
	 * Attendee reminder checks should be attached to the platform cron hook.
	 */
	public function test_event_reminder_cron_hook_is_registered(): void {
		$this->assertSame(
			10,
			has_action( EVENT_REMINDER_CRON_HOOK, 'WordPressdotorg\Community_Events\send_due_event_reminders' )
		);
	}

	/**
	 * Event editors should have structured event detail controls in wp-admin.
	 */
	public function test_event_admin_details_meta_box_is_registered(): void {
		global $wp_meta_boxes;

		$event_id = self::factory()->post->create(
			array(
				'post_type'   => POST_TYPE_EVENT,
				'post_status' => 'publish',
				'post_title'  => 'Contributor Evening',
			)
		);

		do_action( 'add_meta_boxes_' . POST_TYPE_EVENT, get_post( $event_id ) );

		$this->assertArrayHasKey( 'wporg_ce_event_details', $wp_meta_boxes[ POST_TYPE_EVENT ]['normal']['high'] );
	}

	/**
	 * Event editor detail fields should save normalized event metadata.
	 */
	public function test_event_admin_details_meta_box_saves_event_meta(): void {
		update_option( 'timezone_string', 'Europe/Berlin' );

		$admin_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		$group_id = self::factory()->post->create(
			array(
				'post_type'   => POST_TYPE_GROUP,
				'post_status' => 'publish',
				'post_title'  => 'WordPress Berlin',
			)
		);
		$venue_id = self::factory()->post->create(
			array(
				'post_type'   => POST_TYPE_VENUE,
				'post_status' => 'publish',
				'post_title'  => 'Berlin Community Space',
			)
		);
		$event_id = self::factory()->post->create(
			array(
				'post_type'   => POST_TYPE_EVENT,
				'post_status' => 'publish',
				'post_title'  => 'Contributor Evening',
			)
		);

		wp_set_current_user( $admin_id );

		$previous_post = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Capturing test fixture state.

		try {
			$_POST = array( // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Test fixture for a nonce-protected save handler.
				'wporg_ce_event_details_nonce' => wp_create_nonce( 'wporg_ce_save_event_details' ),
				'wporg_ce_event_details'       => array(
					'capacity'    => '42',
					'end'         => '2026-06-02T20:00',
					'group_id'    => (string) $group_id,
					'online_url'  => 'https://example.org/stream/',
					'rsvp_policy' => 'closed',
					'start'       => '2026-06-02T18:30',
					'timezone'    => 'Europe/Berlin',
					'venue_id'    => (string) $venue_id,
				),
			);

			\WordPressdotorg\Community_Events\save_event_admin_meta( $event_id, get_post( $event_id ) );
		} finally {
			$_POST = $previous_post; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Restoring test fixture state.
		}

		$event = get_post( $event_id );

		$this->assertSame( $group_id, (int) $event->post_parent );
		$this->assertSame( $group_id, (int) get_post_meta( $event_id, 'wporg_ce_group_id', true ) );
		$this->assertSame( $venue_id, (int) get_post_meta( $event_id, 'wporg_ce_venue_id', true ) );
		$this->assertSame( '2026-06-02T16:30:00Z', get_post_meta( $event_id, 'wporg_ce_start_utc', true ) );
		$this->assertSame( '2026-06-02T18:00:00Z', get_post_meta( $event_id, 'wporg_ce_end_utc', true ) );
		$this->assertSame( 'Europe/Berlin', get_post_meta( $event_id, 'wporg_ce_timezone', true ) );
		$this->assertSame( 42, (int) get_post_meta( $event_id, 'wporg_ce_capacity', true ) );
		$this->assertSame( 'https://example.org/stream/', get_post_meta( $event_id, 'wporg_ce_online_url', true ) );
		$this->assertSame( 'closed', get_post_meta( $event_id, 'wporg_ce_rsvp_policy', true ) );
		$this->assertSame( EVENT_APPROVAL_STATUS_APPROVED, get_post_meta( $event_id, 'wporg_ce_approval_status', true ) );
	}

	/**
	 * Events should default to open discussion unless explicitly configured otherwise.
	 */
	public function test_events_open_comments_by_default(): void {
		$event_id = self::factory()->post->create(
			array(
				'post_type'   => POST_TYPE_EVENT,
				'post_status' => 'publish',
				'post_title'  => 'Contributor Evening',
			)
		);

		$this->assertSame( 'open', get_post_field( 'comment_status', $event_id ) );
	}

	/**
	 * Event comments should require an authenticated WordPress.org user.
	 */
	public function test_event_comments_reject_anonymous_authors(): void {
		$event_id = self::factory()->post->create(
			array(
				'post_type'      => POST_TYPE_EVENT,
				'post_status'    => 'publish',
				'post_title'     => 'Contributor Evening',
				'comment_status' => 'open',
			)
		);

		wp_set_current_user( 0 );

		$comment_id = wp_new_comment(
			array(
				'comment_author'       => 'Anonymous Attendee',
				'comment_author_email' => 'anonymous@example.org',
				'comment_author_url'   => '',
				'comment_content'      => 'Looking forward to this.',
				'comment_post_ID'      => $event_id,
			),
			true
		);

		$this->assertWPError( $comment_id );
		$this->assertSame( 'wporg_ce_event_comment_login_required', $comment_id->get_error_code() );
	}

	/**
	 * Logged-in users should be able to comment on open events.
	 */
	public function test_event_comments_allow_logged_in_authors(): void {
		$user_id  = self::factory()->user->create();
		$event_id = self::factory()->post->create(
			array(
				'post_type'      => POST_TYPE_EVENT,
				'post_status'    => 'publish',
				'post_title'     => 'Contributor Evening',
				'comment_status' => 'open',
			)
		);

		wp_set_current_user( $user_id );

		$comment_id = wp_new_comment(
			array(
				'comment_author'       => 'Logged In Attendee',
				'comment_author_email' => 'attendee@example.org',
				'comment_author_url'   => '',
				'comment_content'      => 'Looking forward to this.',
				'comment_post_ID'      => $event_id,
				'user_id'              => $user_id,
			),
			true
		);

		$this->assertIsInt( $comment_id );
		$this->assertSame( $event_id, (int) get_comment( $comment_id )->comment_post_ID );
	}

	/**
	 * Memberships should be modelable as user-owned posts attached to a group.
	 */
	public function test_membership_records_are_user_owned_group_relationships(): void {
		$user_id  = self::factory()->user->create();
		$group_id = self::factory()->post->create(
			array(
				'post_type'   => POST_TYPE_GROUP,
				'post_status' => 'publish',
				'post_title'  => 'WordPress Zurich',
			)
		);

		$membership_id = self::factory()->post->create(
			array(
				'post_type'   => POST_TYPE_MEMBERSHIP,
				'post_status' => 'publish',
				'post_title'  => "group-{$group_id}-user-{$user_id}",
				'post_name'   => "group-{$group_id}-user-{$user_id}",
				'post_author' => $user_id,
				'post_parent' => $group_id,
				'meta_input'  => array(
					'wporg_ce_group_id' => $group_id,
					'wporg_ce_user_id'  => $user_id,
					'wporg_ce_role'     => 'member',
					'wporg_ce_status'   => 'active',
				),
			)
		);

		$membership = get_post( $membership_id );

		$this->assertSame( POST_TYPE_MEMBERSHIP, $membership->post_type );
		$this->assertSame( $user_id, (int) $membership->post_author );
		$this->assertSame( $group_id, (int) $membership->post_parent );
		$this->assertSame( $group_id, (int) get_post_meta( $membership_id, 'wporg_ce_group_id', true ) );
		$this->assertSame( $user_id, (int) get_post_meta( $membership_id, 'wporg_ce_user_id', true ) );
		$this->assertSame( 'member', get_post_meta( $membership_id, 'wporg_ce_role', true ) );
		$this->assertSame( 'active', get_post_meta( $membership_id, 'wporg_ce_status', true ) );
	}

	/**
	 * Assert a public post type and archive slug.
	 *
	 * @param string $post_type Post type key.
	 * @param mixed  $archive   Expected archive slug.
	 */
	private function assert_public_post_type( string $post_type, $archive ): void {
		$object = get_post_type_object( $post_type );

		$this->assertNotNull( $object, "{$post_type} should be registered." );
		$this->assertTrue( $object->public, "{$post_type} should be public." );
		$this->assertSame( $archive, $object->has_archive );
		$this->assertTrue( $object->show_in_rest, "{$post_type} should be available in REST." );
	}

	/**
	 * Assert taxonomy object types.
	 *
	 * @param string $taxonomy     Taxonomy key.
	 * @param array  $object_types Expected object type keys.
	 */
	private function assert_taxonomy_object_types( string $taxonomy, array $object_types ): void {
		$object = get_taxonomy( $taxonomy );

		$this->assertNotFalse( $object, "{$taxonomy} should be registered." );
		$this->assertSameSets( $object_types, $object->object_type );
		$this->assertTrue( $object->show_in_rest, "{$taxonomy} should be available in REST." );
	}
}
