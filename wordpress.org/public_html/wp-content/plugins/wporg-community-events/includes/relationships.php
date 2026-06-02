<?php
/**
 * Relationship helpers for groups, memberships, events, and RSVPs.
 *
 * @package WordPressdotorg\Community_Events
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Community_Events;

defined( 'ABSPATH' ) || exit;

const MEMBERSHIP_ROLE_MEMBER    = 'member';
const MEMBERSHIP_ROLE_ORGANIZER = 'organizer';
const MEMBERSHIP_ROLE_HOST      = 'host';

const MEMBERSHIP_STATUS_ACTIVE  = 'active';
const MEMBERSHIP_STATUS_PENDING = 'pending';
const MEMBERSHIP_STATUS_LEFT    = 'left';

const RSVP_STATUS_ATTENDING     = 'attending';
const RSVP_STATUS_WAITLISTED    = 'waitlisted';
const RSVP_STATUS_NOT_ATTENDING = 'not_attending';

const ATTENDANCE_STATUS_NOT_CHECKED_IN = 'not_checked_in';
const ATTENDANCE_STATUS_CHECKED_IN     = 'checked_in';
const ATTENDANCE_STATUS_NO_SHOW        = 'no_show';
const ATTENDANCE_STATUS_NOT_COMING     = 'not_coming';

const RELATIONSHIP_VISIBILITY_PUBLIC  = 'public';
const RELATIONSHIP_VISIBILITY_PRIVATE = 'private';

const NOTIFICATION_NEW_EVENTS          = 'new_events';
const NOTIFICATION_EVENT_UPDATES       = 'event_updates';
const NOTIFICATION_EVENT_CANCELLATIONS = 'event_cancellations';
const EVENT_REMINDER_CRON_HOOK         = 'wporg_ce_send_event_reminders';
const EVENT_REMINDER_LOOKAHEAD_SECONDS = DAY_IN_SECONDS;

const EVENT_APPROVAL_STATUS_APPROVED = 'approved';
const EVENT_APPROVAL_STATUS_CANCELED = 'canceled';

/**
 * Join or update a user's membership in a group.
 *
 * @param int   $group_id Group post ID.
 * @param int   $user_id  WordPress.org user ID.
 * @param array $args     Optional membership data.
 *
 * @return int|\WP_Error
 */
function join_group( int $group_id, int $user_id, array $args = array() ) {
	$validation = validate_user_relationship_target( POST_TYPE_GROUP, $group_id, $user_id );

	if ( is_wp_error( $validation ) ) {
		return $validation;
	}

	$membership_id  = get_group_membership_id( $group_id, $user_id );
	$joined_at_utc  = $args['joined_at_utc'] ?? '';
	$current_status = $membership_id ? (string) get_post_meta( $membership_id, 'wporg_ce_status', true ) : '';

	if ( ! $joined_at_utc && $membership_id ) {
		$joined_at_utc = get_post_meta( $membership_id, 'wporg_ce_joined_at_utc', true );
	}

	if ( ! $joined_at_utc ) {
		$joined_at_utc = current_time( 'mysql', true );
	}

	$notification_preferences = $membership_id ? get_membership_notification_preferences( $membership_id ) : get_default_notification_preferences();

	if ( array_key_exists( 'notification_preferences', $args ) ) {
		$notification_preferences = sanitize_notification_preferences( $args['notification_preferences'], $notification_preferences );
	}

	$role = array_key_exists( 'role', $args )
		? get_allowed_value( $args['role'], get_membership_roles(), MEMBERSHIP_ROLE_MEMBER )
		: MEMBERSHIP_ROLE_MEMBER;

	if ( ! array_key_exists( 'role', $args ) && $membership_id && MEMBERSHIP_STATUS_LEFT !== $current_status ) {
		$role = get_allowed_value( get_post_meta( $membership_id, 'wporg_ce_role', true ), get_membership_roles(), MEMBERSHIP_ROLE_MEMBER );
	}

	$status = array_key_exists( 'status', $args )
		? get_allowed_value( $args['status'], get_membership_statuses(), MEMBERSHIP_STATUS_ACTIVE )
		: MEMBERSHIP_STATUS_ACTIVE;

	$visibility = array_key_exists( 'visibility', $args )
		? get_allowed_value( $args['visibility'], get_relationship_visibilities(), RELATIONSHIP_VISIBILITY_PUBLIC )
		: RELATIONSHIP_VISIBILITY_PUBLIC;

	if ( ! array_key_exists( 'visibility', $args ) && $membership_id ) {
		$visibility = get_allowed_value( get_post_meta( $membership_id, 'wporg_ce_visibility', true ), get_relationship_visibilities(), RELATIONSHIP_VISIBILITY_PUBLIC );
	}

	$membership_id = upsert_relationship_post(
		POST_TYPE_MEMBERSHIP,
		'group',
		$group_id,
		$user_id,
		$membership_id
	);

	if ( is_wp_error( $membership_id ) ) {
		return $membership_id;
	}

	update_relationship_meta(
		$membership_id,
		array(
			'wporg_ce_group_id'                 => $group_id,
			'wporg_ce_user_id'                  => $user_id,
			'wporg_ce_role'                     => $role,
			'wporg_ce_status'                   => $status,
			'wporg_ce_joined_at_utc'            => $joined_at_utc,
			'wporg_ce_visibility'               => $visibility,
			'wporg_ce_notification_preferences' => $notification_preferences,
		)
	);

	return $membership_id;
}

/**
 * Manage a group organizer or host membership.
 *
 * @param int   $group_id       Group post ID.
 * @param int   $actor_user_id  WordPress.org user ID taking the action.
 * @param int   $target_user_id WordPress.org user ID being managed.
 * @param array $args           Organizer membership data.
 *
 * @return int|\WP_Error
 */
function manage_group_organizer( int $group_id, int $actor_user_id, int $target_user_id, array $args = array() ) {
	$group       = get_post( $group_id );
	$actor_user  = get_user_by( 'id', $actor_user_id );
	$target_user = get_user_by( 'id', $target_user_id );

	if ( ! $group || POST_TYPE_GROUP !== $group->post_type ) {
		return new \WP_Error( 'wporg_ce_invalid_relationship_target', __( 'Invalid community object.', 'wporg' ) );
	}

	if ( ! $actor_user || ! $target_user ) {
		return new \WP_Error( 'wporg_ce_invalid_relationship_user', __( 'Invalid community member.', 'wporg' ) );
	}

	$membership_id = get_group_membership_id( $group_id, $target_user_id );

	if ( ! $membership_id || MEMBERSHIP_STATUS_ACTIVE !== get_post_meta( $membership_id, 'wporg_ce_status', true ) ) {
		return new \WP_Error( 'wporg_ce_group_member_required', __( 'Only active group members can be added to the organizer team.', 'wporg' ) );
	}

	$current_role   = (string) get_post_meta( $membership_id, 'wporg_ce_role', true );
	$requested_role = array_key_exists( 'role', $args )
		? get_allowed_value( $args['role'], get_membership_roles(), '' )
		: $current_role;

	if ( ! $requested_role ) {
		$requested_role = MEMBERSHIP_ROLE_MEMBER;
	}

	if ( ! can_user_manage_group_organizer_role( $group_id, $actor_user_id, $membership_id, $requested_role ) ) {
		return new \WP_Error( 'wporg_ce_cannot_manage_group_organizers', __( 'You cannot manage this group organizer team.', 'wporg' ) );
	}

	$visibility = array_key_exists( 'visibility', $args )
		? get_allowed_value( $args['visibility'], get_relationship_visibilities(), RELATIONSHIP_VISIBILITY_PUBLIC )
		: (string) get_post_meta( $membership_id, 'wporg_ce_visibility', true );

	if ( '' === $visibility ) {
		$visibility = RELATIONSHIP_VISIBILITY_PUBLIC;
	}

	update_relationship_meta(
		$membership_id,
		array(
			'wporg_ce_role'       => $requested_role,
			'wporg_ce_status'     => MEMBERSHIP_STATUS_ACTIVE,
			'wporg_ce_visibility' => $visibility,
		)
	);

	return $membership_id;
}

/**
 * Update editable public profile fields for a community group.
 *
 * @param int   $group_id      Group post ID.
 * @param int   $actor_user_id WordPress.org user ID taking the action.
 * @param array $args          Group profile data.
 *
 * @return int|\WP_Error
 */
function update_group_profile( int $group_id, int $actor_user_id, array $args ) {
	$group = get_post( $group_id );
	$user  = get_user_by( 'id', $actor_user_id );

	if ( ! $group || POST_TYPE_GROUP !== $group->post_type ) {
		return new \WP_Error( 'wporg_ce_invalid_relationship_target', __( 'Invalid community object.', 'wporg' ) );
	}

	if ( ! $user ) {
		return new \WP_Error( 'wporg_ce_invalid_relationship_user', __( 'Invalid community member.', 'wporg' ) );
	}

	if ( ! can_user_manage_group_profile( $group_id, $actor_user_id ) ) {
		return new \WP_Error( 'wporg_ce_cannot_manage_group_profile', __( 'You cannot manage this group profile.', 'wporg' ) );
	}

	$meta_update = array();

	if ( array_key_exists( 'website_url', $args ) ) {
		$meta_update['wporg_ce_website_url'] = esc_url_raw( (string) $args['website_url'] );
	}

	update_relationship_meta( $group_id, $meta_update );

	return $group_id;
}

/**
 * RSVP a user to an event.
 *
 * @param int   $event_id Event post ID.
 * @param int   $user_id  WordPress.org user ID.
 * @param array $args     Optional RSVP data.
 *
 * @return int|\WP_Error
 */
function rsvp_to_event( int $event_id, int $user_id, array $args = array() ) {
	$validation = validate_user_relationship_target( POST_TYPE_EVENT, $event_id, $user_id );

	if ( is_wp_error( $validation ) ) {
		return $validation;
	}

	$rsvp_id          = get_event_rsvp_id( $event_id, $user_id );
	$current_status   = $rsvp_id ? get_post_meta( $rsvp_id, 'wporg_ce_status', true ) : '';
	$requested_status = array_key_exists( 'status', $args )
		? get_allowed_value( $args['status'], get_rsvp_statuses(), RSVP_STATUS_ATTENDING )
		: get_allowed_value( $current_status, get_rsvp_statuses(), RSVP_STATUS_ATTENDING );

	if ( RSVP_STATUS_NOT_ATTENDING !== $requested_status ) {
		if ( event_is_canceled( $event_id ) ) {
			return new \WP_Error( 'wporg_ce_event_canceled', __( 'This event has been canceled.', 'wporg' ) );
		}

		if ( 'closed' === get_post_meta( $event_id, 'wporg_ce_rsvp_policy', true ) ) {
			return new \WP_Error( 'wporg_ce_rsvp_closed', __( 'RSVPs are closed for this event.', 'wporg' ) );
		}
	}

	$status         = resolve_event_rsvp_status( $event_id, $requested_status, $current_status );
	$group_id       = (int) get_post_meta( $event_id, 'wporg_ce_group_id', true );
	$guest_count    = array_key_exists( 'guest_count', $args ) ? max( 0, (int) $args['guest_count'] ) : 0;
	$visibility     = array_key_exists( 'visibility', $args )
		? get_allowed_value( $args['visibility'], get_relationship_visibilities(), RELATIONSHIP_VISIBILITY_PUBLIC )
		: RELATIONSHIP_VISIBILITY_PUBLIC;
	$created_at_utc = $args['created_at_utc'] ?? '';
	$attendance     = get_rsvp_attendance_update( $rsvp_id, $status, $current_status, $args );
	$questions      = get_event_rsvp_questions( $event_id );
	$answers        = $rsvp_id ? get_event_rsvp_answers( $rsvp_id ) : array();
	$answers_given  = array_key_exists( 'answers', $args );

	if ( $answers_given ) {
		$answers = sanitize_event_rsvp_answers( $args['answers'], $questions );
	}

	if ( ! $created_at_utc && $rsvp_id ) {
		$created_at_utc = get_post_meta( $rsvp_id, 'wporg_ce_created_at_utc', true );
	}

	if ( ! array_key_exists( 'guest_count', $args ) && $rsvp_id ) {
		$guest_count = max( 0, (int) get_post_meta( $rsvp_id, 'wporg_ce_guest_count', true ) );
	}

	if ( ! array_key_exists( 'visibility', $args ) && $rsvp_id ) {
		$visibility = get_allowed_value( get_post_meta( $rsvp_id, 'wporg_ce_visibility', true ), get_relationship_visibilities(), RELATIONSHIP_VISIBILITY_PUBLIC );
	}

	if ( ! $created_at_utc ) {
		$created_at_utc = current_time( 'mysql', true );
	}

	if (
		RSVP_STATUS_NOT_ATTENDING !== $status &&
		( $answers_given || '' === $current_status || RSVP_STATUS_NOT_ATTENDING === $current_status )
	) {
		$answer_validation = validate_event_rsvp_answers( $event_id, $answers );

		if ( is_wp_error( $answer_validation ) ) {
			return $answer_validation;
		}
	}

	$rsvp_id = upsert_relationship_post(
		POST_TYPE_RSVP,
		'event',
		$event_id,
		$user_id,
		$rsvp_id
	);

	if ( is_wp_error( $rsvp_id ) ) {
		return $rsvp_id;
	}

	update_relationship_meta(
		$rsvp_id,
		array(
			'wporg_ce_event_id'          => $event_id,
			'wporg_ce_group_id'          => $group_id,
			'wporg_ce_user_id'           => $user_id,
			'wporg_ce_status'            => $status,
			'wporg_ce_waitlist_position' => get_rsvp_waitlist_position( $event_id, $rsvp_id, $status ),
			'wporg_ce_guest_count'       => $guest_count,
			'wporg_ce_visibility'        => $visibility,
			'wporg_ce_answers'           => $answers,
			'wporg_ce_attendance_status' => $attendance['status'],
			'wporg_ce_attended_at_utc'   => $attendance['attended_at_utc'],
			'wporg_ce_attendance_by'     => $attendance['by_user_id'],
			'wporg_ce_attendance_at_utc' => $attendance['updated_at_utc'],
			'wporg_ce_created_at_utc'    => $created_at_utc,
			'wporg_ce_updated_at_utc'    => current_time( 'mysql', true ),
		)
	);

	if ( should_send_event_rsvp_status_notification( (string) $current_status, $status ) ) {
		send_event_rsvp_status_notification( $event_id, $rsvp_id, $status );
	}

	refresh_event_rsvp_counts( $event_id );

	if ( RSVP_STATUS_ATTENDING === $current_status && RSVP_STATUS_ATTENDING !== $status ) {
		promote_event_waitlist( $event_id );
	} elseif ( RSVP_STATUS_WAITLISTED === $current_status || RSVP_STATUS_WAITLISTED === $status ) {
		normalize_event_waitlist_positions( $event_id );
	}

	return $rsvp_id;
}

/**
 * Manage an event attendee on behalf of an organizer or event host.
 *
 * @param int   $event_id         Event post ID.
 * @param int   $actor_user_id    Acting WordPress.org user ID.
 * @param int   $attendee_user_id Attendee WordPress.org user ID.
 * @param array $args             RSVP data.
 *
 * @return int|\WP_Error
 */
function manage_event_attendee( int $event_id, int $actor_user_id, int $attendee_user_id, array $args = array() ) {
	$validation = validate_group_event_manager( $event_id, $actor_user_id );

	if ( is_wp_error( $validation ) ) {
		return $validation;
	}

	$event = get_post( $event_id );

	if ( ! $event instanceof \WP_Post || ! is_active_group_member( get_event_group_id( $event ), $attendee_user_id ) ) {
		return new \WP_Error( 'wporg_ce_invalid_event_attendee', __( 'Attendees must be active group members.', 'wporg' ) );
	}

	$args['attendance_by_user_id'] = $actor_user_id;

	return rsvp_to_event( $event_id, $attendee_user_id, $args );
}

/**
 * Send an organizer message to event attendees.
 *
 * @param int   $event_id      Event post ID.
 * @param int   $actor_user_id Acting WordPress.org user ID.
 * @param array $args          Message data.
 *
 * @return array|\WP_Error
 */
function send_event_attendee_message( int $event_id, int $actor_user_id, array $args = array() ) {
	$validation = validate_group_event_manager( $event_id, $actor_user_id );

	if ( is_wp_error( $validation ) ) {
		return $validation;
	}

	$subject = trim( sanitize_text_field( (string) ( $args['subject'] ?? '' ) ) );
	$message = trim( sanitize_textarea_field( (string) ( $args['message'] ?? '' ) ) );
	$status  = get_allowed_value( $args['status'] ?? 'all', array( 'all', RSVP_STATUS_ATTENDING, RSVP_STATUS_WAITLISTED ), 'all' );

	if ( '' === $subject ) {
		return new \WP_Error( 'wporg_ce_event_message_subject_required', __( 'Message subject is required.', 'wporg' ) );
	}

	if ( '' === $message ) {
		return new \WP_Error( 'wporg_ce_event_message_body_required', __( 'Message body is required.', 'wporg' ) );
	}

	$recipient_user_ids = get_event_attendee_message_recipient_user_ids( $event_id, $status );

	/**
	 * Filters recipient user IDs for organizer attendee messages.
	 *
	 * @param int[]  $recipient_user_ids Recipient user IDs.
	 * @param int    $event_id           Event post ID.
	 * @param int    $actor_user_id      Acting WordPress.org user ID.
	 * @param string $status             RSVP status filter.
	 */
	$recipient_user_ids = apply_filters( 'wporg_ce_event_attendee_message_recipient_user_ids', $recipient_user_ids, $event_id, $actor_user_id, $status );
	$recipient_user_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $recipient_user_ids ) ) ) );

	if ( ! $recipient_user_ids ) {
		return new \WP_Error( 'wporg_ce_event_message_no_recipients', __( 'There are no attendees to message.', 'wporg' ) );
	}

	$sent = array();

	foreach ( $recipient_user_ids as $recipient_user_id ) {
		$user = get_user_by( 'id', $recipient_user_id );

		if ( ! $user || ! is_email( $user->user_email ) ) {
			continue;
		}

		if ( wp_mail( $user->user_email, get_event_attendee_message_subject( $event_id, $subject ), get_event_attendee_message_body( $event_id, $actor_user_id, $message ), array( 'Content-Type: text/plain; charset=UTF-8' ) ) ) {
			$sent[] = $user->user_email;
		}
	}

	return array(
		'recipient_count' => count( $recipient_user_ids ),
		'sent_count'      => count( $sent ),
	);
}

/**
 * Get recipient user IDs for an organizer attendee message.
 *
 * @param int    $event_id Event post ID.
 * @param string $status   RSVP status filter.
 *
 * @return int[]
 */
function get_event_attendee_message_recipient_user_ids( int $event_id, string $status = 'all' ): array {
	$statuses = 'all' === $status ? array( RSVP_STATUS_ATTENDING, RSVP_STATUS_WAITLISTED ) : array( $status );
	$user_ids = array();

	foreach ( $statuses as $rsvp_status ) {
		foreach ( get_event_attendee_rsvp_ids( $event_id, $rsvp_status, 0, true ) as $rsvp_id ) {
			$user_ids[] = (int) get_post_meta( $rsvp_id, 'wporg_ce_user_id', true );
		}
	}

	return array_values( array_unique( array_filter( $user_ids ) ) );
}

/**
 * Get an attendee message email subject.
 *
 * @param int    $event_id Event post ID.
 * @param string $subject  Organizer-provided subject.
 *
 * @return string
 */
function get_event_attendee_message_subject( int $event_id, string $subject ): string {
	return sprintf(
		/* translators: 1: Event title, 2: Organizer-provided message subject. */
		__( '[%1$s] %2$s', 'wporg' ),
		get_the_title( $event_id ),
		$subject
	);
}

/**
 * Get an attendee message email body.
 *
 * @param int    $event_id      Event post ID.
 * @param int    $actor_user_id Acting WordPress.org user ID.
 * @param string $message       Organizer-provided message body.
 *
 * @return string
 */
function get_event_attendee_message_body( int $event_id, int $actor_user_id, string $message ): string {
	$sender = get_user_by( 'id', $actor_user_id );
	$lines  = array( $message );

	$lines[] = '';
	$lines[] = sprintf(
		/* translators: %s: Event title. */
		__( 'Event: %s', 'wporg' ),
		get_the_title( $event_id )
	);

	if ( $sender ) {
		$lines[] = sprintf(
			/* translators: %s: Sender display name. */
			__( 'Sent by: %s', 'wporg' ),
			$sender->display_name
		);
	}

	if ( get_permalink( $event_id ) ) {
		$lines[] = sprintf(
			/* translators: %s: Event URL. */
			__( 'Details: %s', 'wporg' ),
			get_permalink( $event_id )
		);
	}

	return implode( "\n", $lines );
}

/**
 * Submit attendee feedback for a completed event.
 *
 * @param int   $event_id Event post ID.
 * @param int   $user_id  WordPress.org user ID.
 * @param array $args     Feedback data.
 *
 * @return int|\WP_Error
 */
function submit_event_feedback( int $event_id, int $user_id, array $args = array() ) {
	$validation = validate_event_feedback_submission( $event_id, $user_id, $args );

	if ( is_wp_error( $validation ) ) {
		return $validation;
	}

	$rating         = absint( $args['rating'] ?? 0 );
	$review         = sanitize_textarea_field( (string) ( $args['review'] ?? '' ) );
	$feedback_title = "feedback-{$event_id}-user-{$user_id}";
	$feedback_id    = wp_insert_post(
		wp_slash(
			array(
				'post_author'  => $user_id,
				'post_content' => $review,
				'post_name'    => $feedback_title,
				'post_parent'  => $event_id,
				'post_status'  => 'publish',
				'post_title'   => $feedback_title,
				'post_type'    => POST_TYPE_FEEDBACK,
			)
		),
		true
	);

	if ( is_wp_error( $feedback_id ) ) {
		return $feedback_id;
	}

	$event = get_post( $event_id );

	update_relationship_meta(
		(int) $feedback_id,
		array(
			'wporg_ce_event_id'       => $event_id,
			'wporg_ce_group_id'       => $event instanceof \WP_Post ? get_event_group_id( $event ) : 0,
			'wporg_ce_user_id'        => $user_id,
			'wporg_ce_rating'         => $rating,
			'wporg_ce_created_at_utc' => current_time( 'mysql', true ),
		)
	);

	return (int) $feedback_id;
}

/**
 * Validate a completed-event feedback submission.
 *
 * @param int   $event_id Event post ID.
 * @param int   $user_id  WordPress.org user ID.
 * @param array $args     Feedback data.
 *
 * @return true|\WP_Error
 */
function validate_event_feedback_submission( int $event_id, int $user_id, array $args ) {
	$validation = validate_user_relationship_target( POST_TYPE_EVENT, $event_id, $user_id );

	if ( is_wp_error( $validation ) ) {
		return $validation;
	}

	$event = get_post( $event_id );

	if ( ! is_public_event_post( $event ) ) {
		return new \WP_Error( 'wporg_ce_invalid_relationship_target', __( 'Invalid community object.', 'wporg' ) );
	}

	if ( ! event_feedback_is_open( $event_id ) ) {
		return new \WP_Error( 'wporg_ce_event_feedback_not_open', __( 'Feedback opens after the event.', 'wporg' ) );
	}

	if ( user_can_manage_event_feedback( $event_id, $user_id ) ) {
		return new \WP_Error( 'wporg_ce_cannot_feedback_own_event', __( 'Event organizers cannot leave feedback on their own events.', 'wporg' ) );
	}

	$rsvp_id = get_event_rsvp_id( $event_id, $user_id );

	if ( ! event_rsvp_can_leave_feedback( $rsvp_id ) ) {
		return new \WP_Error( 'wporg_ce_event_feedback_attendee_required', __( 'Only attendees can leave event feedback.', 'wporg' ) );
	}

	if ( get_event_feedback_id( $event_id, $user_id ) ) {
		return new \WP_Error( 'wporg_ce_event_feedback_exists', __( 'You have already left feedback for this event.', 'wporg' ) );
	}

	$rating = absint( $args['rating'] ?? 0 );

	if ( $rating < 1 || $rating > 5 ) {
		return new \WP_Error( 'wporg_ce_event_feedback_rating_invalid', __( 'Choose a rating from 1 to 5.', 'wporg' ) );
	}

	$review = trim( sanitize_textarea_field( (string) ( $args['review'] ?? '' ) ) );

	if ( $rating <= 3 && '' === $review ) {
		return new \WP_Error( 'wporg_ce_event_feedback_review_required', __( 'Add a short note for lower ratings.', 'wporg' ) );
	}

	return true;
}

/**
 * Determine whether feedback can be submitted for an event.
 *
 * @param int $event_id Event post ID.
 *
 * @return bool
 */
function event_feedback_is_open( int $event_id ): bool {
	$timestamp = get_event_feedback_open_timestamp( $event_id );

	return $timestamp > 0 && $timestamp < time();
}

/**
 * Get the timestamp after which event feedback opens.
 *
 * @param int $event_id Event post ID.
 *
 * @return int
 */
function get_event_feedback_open_timestamp( int $event_id ): int {
	$end_utc = (string) get_post_meta( $event_id, 'wporg_ce_end_utc', true );

	if ( '' !== $end_utc ) {
		$end_timestamp = strtotime( $end_utc );

		if ( false !== $end_timestamp ) {
			return $end_timestamp;
		}
	}

	return event_start_timestamp( $event_id );
}

/**
 * Determine whether a user manages an event and should not review it.
 *
 * @param int $event_id Event post ID.
 * @param int $user_id  WordPress.org user ID.
 *
 * @return bool
 */
function user_can_manage_event_feedback( int $event_id, int $user_id ): bool {
	$event = get_post( $event_id );

	if ( ! $event instanceof \WP_Post || ! $user_id ) {
		return false;
	}

	$group_id = get_event_group_id( $event );

	if ( in_array( $user_id, get_event_host_user_ids( $event_id ), true ) ) {
		return true;
	}

	return $group_id && can_user_publish_group_events( $group_id, $user_id );
}

/**
 * Determine whether an RSVP record can leave event feedback.
 *
 * @param int $rsvp_id RSVP post ID.
 *
 * @return bool
 */
function event_rsvp_can_leave_feedback( int $rsvp_id ): bool {
	if ( ! $rsvp_id || RSVP_STATUS_ATTENDING !== get_post_meta( $rsvp_id, 'wporg_ce_status', true ) ) {
		return false;
	}

	return ! in_array(
		get_post_meta( $rsvp_id, 'wporg_ce_attendance_status', true ),
		array( ATTENDANCE_STATUS_NO_SHOW, ATTENDANCE_STATUS_NOT_COMING ),
		true
	);
}

/**
 * Create a community event for a group.
 *
 * Active organizers and hosts can publish group events directly.
 *
 * @param int   $group_id Group post ID.
 * @param int   $user_id  WordPress.org user ID.
 * @param array $args     Event data.
 *
 * @return int|\WP_Error
 */
function create_group_event( int $group_id, int $user_id, array $args ) {
	$validation = validate_user_relationship_target( POST_TYPE_GROUP, $group_id, $user_id );

	if ( is_wp_error( $validation ) ) {
		return $validation;
	}

	if ( ! can_user_publish_group_events( $group_id, $user_id ) ) {
		return new \WP_Error( 'wporg_ce_cannot_create_event', __( 'You cannot create events for this group.', 'wporg' ) );
	}

	$title = trim( (string) ( $args['title'] ?? '' ) );

	if ( '' === $title ) {
		return new \WP_Error( 'wporg_ce_event_title_required', __( 'Event title is required.', 'wporg' ) );
	}

	$start_utc = trim( (string) ( $args['start_utc'] ?? '' ) );

	if ( '' === $start_utc ) {
		return new \WP_Error( 'wporg_ce_event_start_required', __( 'Event start time is required.', 'wporg' ) );
	}

	$host_user_ids = get_requested_event_host_user_ids( $args['host_user_ids'] ?? array( $user_id ) );
	$validation    = validate_event_host_user_ids( $group_id, $host_user_ids );

	if ( is_wp_error( $validation ) ) {
		return $validation;
	}

	$event_id = wp_insert_post(
		wp_slash(
			array(
				'post_type'      => POST_TYPE_EVENT,
				'post_status'    => 'publish',
				'post_title'     => $title,
				'post_content'   => (string) ( $args['description'] ?? '' ),
				'post_excerpt'   => (string) ( $args['excerpt'] ?? '' ),
				'post_author'    => $user_id,
				'post_parent'    => $group_id,
				'comment_status' => 'open',
			)
		),
		true
	);

	if ( is_wp_error( $event_id ) ) {
		return $event_id;
	}

	update_relationship_meta(
		$event_id,
		array(
			'wporg_ce_group_id'        => $group_id,
			'wporg_ce_venue_id'        => max( 0, (int) ( $args['venue_id'] ?? 0 ) ),
			'wporg_ce_host_user_id'    => $host_user_ids[0],
			'wporg_ce_host_user_ids'   => $host_user_ids,
			'wporg_ce_start_utc'       => $start_utc,
			'wporg_ce_end_utc'         => (string) ( $args['end_utc'] ?? '' ),
			'wporg_ce_timezone'        => (string) ( $args['timezone'] ?? get_post_meta( $group_id, 'wporg_ce_timezone', true ) ),
			'wporg_ce_capacity'        => max( 0, (int) ( $args['capacity'] ?? 0 ) ),
			'wporg_ce_rsvp_policy'     => (string) ( $args['rsvp_policy'] ?? 'open' ),
			'wporg_ce_rsvp_count'      => 0,
			'wporg_ce_waitlist_count'  => 0,
			'wporg_ce_rsvp_questions'  => sanitize_event_rsvp_questions( $args['rsvp_questions'] ?? array() ),
			'wporg_ce_approval_status' => EVENT_APPROVAL_STATUS_APPROVED,
			'wporg_ce_online_url'      => (string) ( $args['online_url'] ?? '' ),
		)
	);
	set_group_event_terms( (int) $event_id, $args );
	send_group_event_notification( (int) $event_id, NOTIFICATION_NEW_EVENTS );

	return $event_id;
}

/**
 * Update a published group event.
 *
 * @param int   $event_id      Event post ID.
 * @param int   $actor_user_id WordPress.org user ID taking the action.
 * @param array $args          Event data.
 *
 * @return int|\WP_Error
 */
function update_group_event( int $event_id, int $actor_user_id, array $args ) {
	$validation = validate_group_event_manager( $event_id, $actor_user_id );

	if ( is_wp_error( $validation ) ) {
		return $validation;
	}

	$event             = get_post( $event_id );
	$post_update       = array( 'ID' => $event_id );
	$meta_update       = array();
	$editable_post_map = array(
		'title'       => 'post_title',
		'description' => 'post_content',
		'excerpt'     => 'post_excerpt',
	);

	foreach ( $editable_post_map as $request_key => $post_key ) {
		if ( ! array_key_exists( $request_key, $args ) ) {
			continue;
		}

		$value = (string) $args[ $request_key ];

		if ( 'title' === $request_key ) {
			$value = trim( $value );

			if ( '' === $value ) {
				return new \WP_Error( 'wporg_ce_event_title_required', __( 'Event title is required.', 'wporg' ) );
			}
		}

		$post_update[ $post_key ] = $value;
	}

	$start_utc = array_key_exists( 'start_utc', $args ) ? trim( (string) $args['start_utc'] ) : (string) get_post_meta( $event_id, 'wporg_ce_start_utc', true );
	$end_utc   = array_key_exists( 'end_utc', $args ) ? trim( (string) $args['end_utc'] ) : (string) get_post_meta( $event_id, 'wporg_ce_end_utc', true );

	if ( array_key_exists( 'start_utc', $args ) && '' === $start_utc ) {
		return new \WP_Error( 'wporg_ce_event_start_required', __( 'Event start time is required.', 'wporg' ) );
	}

	$date_validation = validate_event_date_range( $start_utc, $end_utc );

	if ( is_wp_error( $date_validation ) ) {
		return $date_validation;
	}

	if ( array_key_exists( 'start_utc', $args ) ) {
		$meta_update['wporg_ce_start_utc'] = $start_utc;
	}

	if ( array_key_exists( 'end_utc', $args ) ) {
		$meta_update['wporg_ce_end_utc'] = $end_utc;
	}

	if ( array_key_exists( 'venue_id', $args ) ) {
		$meta_update['wporg_ce_venue_id'] = max( 0, (int) $args['venue_id'] );
	}

	if ( array_key_exists( 'timezone', $args ) ) {
		$meta_update['wporg_ce_timezone'] = (string) $args['timezone'];
	}

	if ( array_key_exists( 'capacity', $args ) ) {
		$meta_update['wporg_ce_capacity'] = max( 0, (int) $args['capacity'] );
	}

	if ( array_key_exists( 'online_url', $args ) ) {
		$meta_update['wporg_ce_online_url'] = (string) $args['online_url'];
	}

	if ( array_key_exists( 'rsvp_policy', $args ) ) {
		$meta_update['wporg_ce_rsvp_policy'] = get_allowed_value( $args['rsvp_policy'], array( 'open', 'closed' ), 'open' );
	}

	if ( array_key_exists( 'rsvp_questions', $args ) ) {
		$meta_update['wporg_ce_rsvp_questions'] = sanitize_event_rsvp_questions( $args['rsvp_questions'] );
	}

	if ( array_key_exists( 'host_user_ids', $args ) ) {
		$host_user_ids = get_requested_event_host_user_ids( $args['host_user_ids'] );
		$validation    = validate_event_host_user_ids( get_event_group_id( $event ), $host_user_ids );

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$meta_update['wporg_ce_host_user_id']  = $host_user_ids[0];
		$meta_update['wporg_ce_host_user_ids'] = $host_user_ids;
	}

	if ( 1 < count( $post_update ) ) {
		$updated_id = wp_update_post( wp_slash( $post_update ), true );

		if ( is_wp_error( $updated_id ) ) {
			return $updated_id;
		}
	}

	if ( $meta_update ) {
		update_relationship_meta( $event_id, $meta_update );
	}

	set_group_event_terms( $event_id, $args );

	if ( array_key_exists( 'wporg_ce_capacity', $meta_update ) ) {
		promote_event_waitlist( $event_id );
	}

	if ( $event instanceof \WP_Post && 'publish' === $event->post_status ) {
		clean_post_cache( $event_id );

		if ( event_update_should_notify( $event_id, $args ) ) {
			send_group_event_notification( $event_id, NOTIFICATION_EVENT_UPDATES );
		}
	}

	return $event_id;
}

/**
 * Copy an existing group event to a new start time.
 *
 * @param int   $event_id      Source event post ID.
 * @param int   $actor_user_id WordPress.org user ID taking the action.
 * @param array $args          Event override data.
 *
 * @return int|\WP_Error
 */
function copy_group_event( int $event_id, int $actor_user_id, array $args ) {
	$validation = validate_group_event_manager( $event_id, $actor_user_id );

	if ( is_wp_error( $validation ) ) {
		return $validation;
	}

	$event    = get_post( $event_id );
	$group_id = $event instanceof \WP_Post ? get_event_group_id( $event ) : 0;

	if ( ! can_user_publish_group_events( $group_id, $actor_user_id ) ) {
		return new \WP_Error( 'wporg_ce_cannot_create_event', __( 'You cannot create events for this group.', 'wporg' ) );
	}

	$start_utc = trim( (string) ( $args['start_utc'] ?? '' ) );

	if ( '' === $start_utc ) {
		return new \WP_Error( 'wporg_ce_event_start_required', __( 'Event start time is required.', 'wporg' ) );
	}

	$end_utc = array_key_exists( 'end_utc', $args )
		? trim( (string) $args['end_utc'] )
		: get_copied_event_end_utc( $event_id, $start_utc );

	$date_validation = validate_event_date_range( $start_utc, $end_utc );

	if ( is_wp_error( $date_validation ) ) {
		return $date_validation;
	}

	$copy_args = array_merge(
		array(
			'title'          => get_the_title( $event_id ),
			'description'    => $event instanceof \WP_Post ? $event->post_content : '',
			'excerpt'        => $event instanceof \WP_Post ? $event->post_excerpt : '',
			'venue_id'       => (int) get_post_meta( $event_id, 'wporg_ce_venue_id', true ),
			'host_user_ids'  => get_event_host_user_ids( $event_id ),
			'start_utc'      => $start_utc,
			'end_utc'        => $end_utc,
			'timezone'       => get_post_meta( $event_id, 'wporg_ce_timezone', true ),
			'capacity'       => (int) get_post_meta( $event_id, 'wporg_ce_capacity', true ),
			'online_url'     => get_post_meta( $event_id, 'wporg_ce_online_url', true ),
			'rsvp_policy'    => get_post_meta( $event_id, 'wporg_ce_rsvp_policy', true ),
			'rsvp_questions' => get_event_rsvp_questions( $event_id ),
		),
		get_copied_event_taxonomy_args( $event_id, $args )
	);

	$override_keys = array_merge(
		array(
			'title',
			'description',
			'excerpt',
			'venue_id',
			'host_user_ids',
			'timezone',
			'capacity',
			'online_url',
			'rsvp_policy',
			'rsvp_questions',
		),
		array_keys( get_event_taxonomy_request_map() )
	);

	foreach ( $override_keys as $key ) {
		if ( array_key_exists( $key, $args ) ) {
			$copy_args[ $key ] = $args[ $key ];
		}
	}

	$copy_args['start_utc'] = $start_utc;
	$copy_args['end_utc']   = $end_utc;

	$copied_event_id = create_group_event( $group_id, $actor_user_id, $copy_args );

	if ( is_wp_error( $copied_event_id ) ) {
		return $copied_event_id;
	}

	update_post_meta( (int) $copied_event_id, 'wporg_ce_copied_from_event_id', $event_id );

	return (int) $copied_event_id;
}

/**
 * Get the copied event end time while preserving the source duration.
 *
 * @param int    $event_id      Source event post ID.
 * @param string $new_start_utc New event start time in UTC.
 *
 * @return string
 */
function get_copied_event_end_utc( int $event_id, string $new_start_utc ): string {
	$source_start_timestamp = strtotime( (string) get_post_meta( $event_id, 'wporg_ce_start_utc', true ) );
	$source_end_timestamp   = strtotime( (string) get_post_meta( $event_id, 'wporg_ce_end_utc', true ) );
	$new_start_timestamp    = strtotime( $new_start_utc );

	if (
		false === $source_start_timestamp ||
		false === $source_end_timestamp ||
		false === $new_start_timestamp ||
		$source_end_timestamp <= $source_start_timestamp
	) {
		return '';
	}

	return gmdate( 'Y-m-d\TH:i:s\Z', $new_start_timestamp + ( $source_end_timestamp - $source_start_timestamp ) );
}

/**
 * Get taxonomy request arguments for a copied event.
 *
 * @param int   $event_id  Source event post ID.
 * @param array $overrides Explicit taxonomy request values.
 *
 * @return array
 */
function get_copied_event_taxonomy_args( int $event_id, array $overrides = array() ): array {
	$args = array();

	foreach ( get_event_taxonomy_request_map() as $request_key => $taxonomy ) {
		if ( array_key_exists( $request_key, $overrides ) ) {
			$args[ $request_key ] = $overrides[ $request_key ];
			continue;
		}

		$terms = get_the_terms( $event_id, $taxonomy );

		if ( ! $terms || is_wp_error( $terms ) ) {
			$args[ $request_key ] = array();
			continue;
		}

		$args[ $request_key ] = array_values( wp_list_pluck( $terms, 'slug' ) );
	}

	return $args;
}

/**
 * Get event host user IDs.
 *
 * Falls back to the legacy single host meta, then the event author.
 *
 * @param int $event_id Event post ID.
 *
 * @return int[]
 */
function get_event_host_user_ids( int $event_id ): array {
	$host_user_ids = get_post_meta( $event_id, 'wporg_ce_host_user_ids', true );

	if ( ! is_array( $host_user_ids ) || ! $host_user_ids ) {
		$host_user_ids = array( (int) get_post_meta( $event_id, 'wporg_ce_host_user_id', true ) );
	}

	$host_user_ids = get_requested_event_host_user_ids( $host_user_ids );

	if ( $host_user_ids ) {
		return $host_user_ids;
	}

	$event = get_post( $event_id );

	return $event instanceof \WP_Post ? get_requested_event_host_user_ids( array( (int) $event->post_author ) ) : array();
}

/**
 * Normalize event host IDs from request-style data.
 *
 * @param mixed $host_user_ids Host user IDs.
 *
 * @return int[]
 */
function get_requested_event_host_user_ids( $host_user_ids ): array {
	return array_values(
		array_unique(
			array_filter(
				array_map(
					static function ( $host_user_id ): int {
						return absint( $host_user_id );
					},
					(array) $host_user_ids
				)
			)
		)
	);
}

/**
 * Validate event host IDs for a group.
 *
 * @param int   $group_id       Group post ID.
 * @param int[] $host_user_ids  Host user IDs.
 *
 * @return true|\WP_Error
 */
function validate_event_host_user_ids( int $group_id, array $host_user_ids ) {
	if ( ! $host_user_ids ) {
		return new \WP_Error( 'wporg_ce_event_host_required', __( 'Choose at least one event host.', 'wporg' ) );
	}

	foreach ( $host_user_ids as $host_user_id ) {
		if ( ! get_user_by( 'id', $host_user_id ) || ! is_active_group_member( $group_id, $host_user_id ) ) {
			return new \WP_Error( 'wporg_ce_invalid_event_host', __( 'Event hosts must be active group members.', 'wporg' ) );
		}
	}

	return true;
}

/**
 * Set taxonomy terms for a group event from request-style data.
 *
 * @param int   $event_id Event post ID.
 * @param array $args     Event data.
 *
 * @return void
 */
function set_group_event_terms( int $event_id, array $args ): void {
	foreach ( get_event_taxonomy_request_map() as $request_key => $taxonomy ) {
		if ( ! array_key_exists( $request_key, $args ) ) {
			continue;
		}

		$terms = array_map( 'sanitize_title', (array) $args[ $request_key ] );
		$terms = array_filter( $terms );

		wp_set_object_terms( $event_id, $terms, $taxonomy, false );
	}
}

/**
 * Map REST request keys to event taxonomies.
 *
 * @return array
 */
function get_event_taxonomy_request_map(): array {
	return array(
		'countries'     => TAXONOMY_COUNTRY,
		'event_formats' => TAXONOMY_EVENT_FORMAT,
		'event_types'   => TAXONOMY_EVENT_TYPE,
		'languages'     => TAXONOMY_LANGUAGE,
		'topics'        => TAXONOMY_TOPIC,
	);
}

/**
 * Cancel a published group event while preserving the public event record.
 *
 * @param int   $event_id      Event post ID.
 * @param int   $actor_user_id WordPress.org user ID taking the action.
 * @param array $args          Cancellation data.
 *
 * @return int|\WP_Error
 */
function cancel_group_event( int $event_id, int $actor_user_id, array $args = array() ) {
	$validation = validate_group_event_manager( $event_id, $actor_user_id );

	if ( is_wp_error( $validation ) ) {
		return $validation;
	}

	if ( event_is_canceled( $event_id ) ) {
		return $event_id;
	}

	if ( EVENT_APPROVAL_STATUS_APPROVED !== get_post_meta( $event_id, 'wporg_ce_approval_status', true ) ) {
		return new \WP_Error( 'wporg_ce_event_not_cancelable', __( 'Only approved events can be canceled.', 'wporg' ) );
	}

	$canceled_at = current_time( 'mysql', true );

	update_relationship_meta(
		$event_id,
		array(
			'wporg_ce_approval_status'     => EVENT_APPROVAL_STATUS_CANCELED,
			'wporg_ce_canceled_at_utc'     => $canceled_at,
			'wporg_ce_canceled_by_user_id' => $actor_user_id,
			'wporg_ce_cancellation_reason' => trim( (string) ( $args['reason'] ?? '' ) ),
			'wporg_ce_rsvp_policy'         => 'closed',
		)
	);

	send_group_event_notification( $event_id, NOTIFICATION_EVENT_CANCELLATIONS );

	return $event_id;
}

/**
 * Get the group ID for an event.
 *
 * @param \WP_Post $event Event post object.
 *
 * @return int
 */
function get_event_group_id( \WP_Post $event ): int {
	if ( $event->post_parent ) {
		return (int) $event->post_parent;
	}

	return (int) get_post_meta( $event->ID, 'wporg_ce_group_id', true );
}

/**
 * Determine whether an event is canceled.
 *
 * @param int $event_id Event post ID.
 *
 * @return bool
 */
function event_is_canceled( int $event_id ): bool {
	return EVENT_APPROVAL_STATUS_CANCELED === get_post_meta( $event_id, 'wporg_ce_approval_status', true );
}

/**
 * Get notification preferences enabled for new memberships.
 *
 * @return array
 */
function get_default_notification_preferences(): array {
	return array(
		NOTIFICATION_NEW_EVENTS          => true,
		NOTIFICATION_EVENT_UPDATES       => true,
		NOTIFICATION_EVENT_CANCELLATIONS => true,
	);
}

/**
 * Get the REST/meta schema for membership notification preferences.
 *
 * @return array
 */
function get_notification_preferences_schema(): array {
	return array(
		'additionalProperties' => false,
		'context'              => array( 'view', 'edit' ),
		'default'              => get_default_notification_preferences(),
		'properties'           => array(
			NOTIFICATION_NEW_EVENTS          => array(
				'description' => 'Email new events for this group.',
				'default'     => true,
				'type'        => 'boolean',
			),
			NOTIFICATION_EVENT_UPDATES       => array(
				'description' => 'Email event updates for this group.',
				'default'     => true,
				'type'        => 'boolean',
			),
			NOTIFICATION_EVENT_CANCELLATIONS => array(
				'description' => 'Email event cancellations for this group.',
				'default'     => true,
				'type'        => 'boolean',
			),
		),
		'type'                 => 'object',
	);
}

/**
 * Sanitize notification preferences with the shared REST schema.
 *
 * @param mixed $preferences      Raw preferences.
 * @param array $base_preferences Existing preferences to preserve when a key is omitted.
 *
 * @return array
 */
function sanitize_notification_preferences( $preferences, array $base_preferences = array() ): array {
	$defaults = get_default_notification_preferences();
	$base     = array_merge( $defaults, array_intersect_key( $base_preferences, $defaults ) );

	if ( ! is_array( $preferences ) && ! is_object( $preferences ) ) {
		return $base;
	}

	$sanitized = rest_sanitize_value_from_schema(
		$preferences,
		get_notification_preferences_schema(),
		'notification_preferences'
	);

	$sanitized = is_array( $sanitized ) ? $sanitized : (array) $sanitized;

	foreach ( array_keys( $defaults ) as $key ) {
		if ( array_key_exists( $key, $sanitized ) ) {
			$base[ $key ] = (bool) $sanitized[ $key ];
		}
	}

	return $base;
}

/**
 * Get the REST/meta schema for event RSVP questions.
 *
 * @return array
 */
function get_event_rsvp_questions_schema(): array {
	return array(
		'context' => array( 'view', 'edit' ),
		'default' => array(),
		'items'   => array(
			'additionalProperties' => false,
			'properties'           => array(
				'id'       => array(
					'description' => 'Stable question ID.',
					'minLength'   => 1,
					'type'        => 'string',
				),
				'label'    => array(
					'description' => 'Question label.',
					'minLength'   => 1,
					'type'        => 'string',
				),
				'type'     => array(
					'description' => 'Question answer type.',
					'enum'        => get_event_rsvp_question_types(),
					'type'        => 'string',
				),
				'required' => array(
					'description' => 'Whether the question is required before RSVPing.',
					'default'     => false,
					'type'        => 'boolean',
				),
				'choices'  => array(
					'description' => 'Allowed choices for select questions.',
					'items'       => array(
						'type' => 'string',
					),
					'type'        => 'array',
				),
			),
			'required'             => array( 'id', 'label' ),
			'type'                 => 'object',
		),
		'type'    => 'array',
	);
}

/**
 * Get the REST/meta schema for RSVP answers.
 *
 * @return array
 */
function get_event_rsvp_answers_schema(): array {
	return array(
		'additionalProperties' => array(
			'type' => 'string',
		),
		'context'              => array( 'view', 'edit' ),
		'default'              => array(),
		'type'                 => 'object',
	);
}

/**
 * Get supported RSVP question answer types.
 *
 * @return string[]
 */
function get_event_rsvp_question_types(): array {
	return array( 'text', 'textarea', 'select' );
}

/**
 * Sanitize event RSVP question definitions.
 *
 * @param mixed $questions Raw RSVP question data.
 *
 * @return array
 */
function sanitize_event_rsvp_questions( $questions ): array {
	if ( ! is_array( $questions ) && ! is_object( $questions ) ) {
		return array();
	}

	$sanitized = rest_sanitize_value_from_schema(
		$questions,
		get_event_rsvp_questions_schema(),
		'rsvp_questions'
	);

	$normalized = array();

	foreach ( (array) $sanitized as $question ) {
		if ( ! is_array( $question ) ) {
			$question = (array) $question;
		}

		$label = trim( sanitize_text_field( (string) ( $question['label'] ?? '' ) ) );

		if ( '' === $label ) {
			continue;
		}

		$id = sanitize_key( (string) ( $question['id'] ?? '' ) );

		if ( '' === $id ) {
			$id = sanitize_key( sanitize_title( $label ) );
		}

		if ( '' === $id || isset( $normalized[ $id ] ) ) {
			continue;
		}

		$type    = get_allowed_value( $question['type'] ?? 'text', get_event_rsvp_question_types(), 'text' );
		$choices = array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $choice ): string {
							return trim( sanitize_text_field( (string) $choice ) );
						},
						(array) ( $question['choices'] ?? array() )
					)
				)
			)
		);

		if ( 'select' === $type && ! $choices ) {
			$type = 'text';
		}

		$normalized[ $id ] = array(
			'id'       => $id,
			'label'    => $label,
			'type'     => $type,
			'required' => ! empty( $question['required'] ),
			'choices'  => 'select' === $type ? $choices : array(),
		);

		if ( 10 <= count( $normalized ) ) {
			break;
		}
	}

	return array_values( $normalized );
}

/**
 * Get sanitized RSVP questions for an event.
 *
 * @param int $event_id Event post ID.
 *
 * @return array
 */
function get_event_rsvp_questions( int $event_id ): array {
	return sanitize_event_rsvp_questions( get_post_meta( $event_id, 'wporg_ce_rsvp_questions', true ) );
}

/**
 * Sanitize attendee answers against the event question definitions.
 *
 * @param mixed $answers   Raw RSVP answer data.
 * @param mixed $questions Optional sanitized question definitions.
 *
 * @return array
 */
function sanitize_event_rsvp_answers( $answers, $questions = array() ): array {
	if ( ! is_array( $answers ) && ! is_object( $answers ) ) {
		return array();
	}

	$answers = rest_sanitize_value_from_schema(
		$answers,
		get_event_rsvp_answers_schema(),
		'answers'
	);
	$answers = is_array( $answers ) ? $answers : (array) $answers;

	if ( ! is_array( $questions ) ) {
		$questions = array();
	}

	if ( ! $questions ) {
		$sanitized = array();

		foreach ( $answers as $question_id => $answer ) {
			$question_id = sanitize_key( (string) $question_id );
			$answer      = trim( sanitize_textarea_field( (string) $answer ) );

			if ( '' !== $question_id && '' !== $answer ) {
				$sanitized[ $question_id ] = $answer;
			}
		}

		return $sanitized;
	}

	$sanitized = array();

	foreach ( $questions as $question ) {
		$question_id = sanitize_key( (string) ( $question['id'] ?? '' ) );

		if ( '' === $question_id || ! array_key_exists( $question_id, $answers ) ) {
			continue;
		}

		$answer = 'textarea' === ( $question['type'] ?? 'text' )
			? trim( sanitize_textarea_field( (string) $answers[ $question_id ] ) )
			: trim( sanitize_text_field( (string) $answers[ $question_id ] ) );

		if ( '' === $answer ) {
			continue;
		}

		if (
			'select' === ( $question['type'] ?? 'text' ) &&
			! in_array( $answer, (array) ( $question['choices'] ?? array() ), true )
		) {
			continue;
		}

		$sanitized[ $question_id ] = $answer;
	}

	return $sanitized;
}

/**
 * Get sanitized answers for an RSVP.
 *
 * @param int $rsvp_id RSVP post ID.
 *
 * @return array
 */
function get_event_rsvp_answers( int $rsvp_id ): array {
	$event_id = (int) get_post_meta( $rsvp_id, 'wporg_ce_event_id', true );

	return sanitize_event_rsvp_answers(
		get_post_meta( $rsvp_id, 'wporg_ce_answers', true ),
		get_event_rsvp_questions( $event_id )
	);
}

/**
 * Validate required RSVP answers for an event.
 *
 * @param int   $event_id Event post ID.
 * @param array $answers  Sanitized answer data.
 *
 * @return true|\WP_Error
 */
function validate_event_rsvp_answers( int $event_id, array $answers ) {
	foreach ( get_event_rsvp_questions( $event_id ) as $question ) {
		$question_id = (string) ( $question['id'] ?? '' );

		if ( empty( $question['required'] ) || '' !== trim( (string) ( $answers[ $question_id ] ?? '' ) ) ) {
			continue;
		}

		return new \WP_Error(
			'wporg_ce_rsvp_answer_required',
			sprintf(
				/* translators: %s: RSVP question label. */
				__( 'Answer "%s" before RSVPing.', 'wporg' ),
				(string) ( $question['label'] ?? '' )
			)
		);
	}

	return true;
}

/**
 * Determine whether a user can view private RSVP answers.
 *
 * @param int $rsvp_id RSVP post ID.
 * @param int $user_id User ID.
 *
 * @return bool
 */
function can_user_view_rsvp_answers( int $rsvp_id, int $user_id ): bool {
	if ( ! $rsvp_id || ! $user_id ) {
		return false;
	}

	if ( (int) get_post_meta( $rsvp_id, 'wporg_ce_user_id', true ) === $user_id ) {
		return true;
	}

	$event_id = (int) get_post_meta( $rsvp_id, 'wporg_ce_event_id', true );

	return $event_id && ! is_wp_error( validate_group_event_manager( $event_id, $user_id ) );
}

/**
 * Get normalized notification preferences for a membership.
 *
 * @param int $membership_id Membership post ID.
 *
 * @return array
 */
function get_membership_notification_preferences( int $membership_id ): array {
	return sanitize_notification_preferences(
		get_post_meta( $membership_id, 'wporg_ce_notification_preferences', true ),
		get_default_notification_preferences()
	);
}

/**
 * Determine whether a membership is subscribed to an event notification type.
 *
 * @param int    $membership_id     Membership post ID.
 * @param string $notification_type Notification type.
 *
 * @return bool
 */
function membership_has_notification_enabled( int $membership_id, string $notification_type ): bool {
	$preferences = get_membership_notification_preferences( $membership_id );

	return ! empty( $preferences[ $notification_type ] );
}

/**
 * Determine whether an event update should notify group members.
 *
 * @param int   $event_id Event post ID.
 * @param array $args     Event update data.
 *
 * @return bool
 */
function event_update_should_notify( int $event_id, array $args ): bool {
	if ( event_is_canceled( $event_id ) ) {
		return false;
	}

	$notification_keys = array_merge(
		array(
			'title',
			'description',
			'excerpt',
			'start_utc',
			'end_utc',
			'venue_id',
			'timezone',
			'capacity',
			'online_url',
			'rsvp_policy',
			'host_user_ids',
		),
		array_keys( get_event_taxonomy_request_map() )
	);

	foreach ( $notification_keys as $key ) {
		if ( array_key_exists( $key, $args ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Send an event lifecycle notification to subscribed group members.
 *
 * @param int    $event_id          Event post ID.
 * @param string $notification_type Notification type.
 *
 * @return string[] Email addresses that accepted the notification.
 */
function send_group_event_notification( int $event_id, string $notification_type ): array {
	$event = get_post( $event_id );

	if ( ! $event instanceof \WP_Post || POST_TYPE_EVENT !== $event->post_type || 'publish' !== $event->post_status ) {
		return array();
	}

	$group_id = get_event_group_id( $event );

	if ( ! $group_id ) {
		return array();
	}

	$recipient_user_ids = get_group_notification_recipient_user_ids( $group_id, $notification_type );

	/**
	 * Filters event notification recipient user IDs.
	 *
	 * @param int[]  $recipient_user_ids Recipient user IDs.
	 * @param int    $event_id           Event post ID.
	 * @param string $notification_type  Notification type.
	 * @param int    $group_id           Group post ID.
	 */
	$recipient_user_ids = apply_filters( 'wporg_ce_event_notification_recipient_user_ids', $recipient_user_ids, $event_id, $notification_type, $group_id );
	$recipient_user_ids = array_values( array_unique( array_map( 'intval', (array) $recipient_user_ids ) ) );

	if ( ! $recipient_user_ids ) {
		return array();
	}

	$subject = get_event_notification_subject( $event_id, $notification_type );
	$message = get_event_notification_message( $event_id, $notification_type );
	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
	$sent    = array();

	foreach ( $recipient_user_ids as $user_id ) {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user || ! is_email( $user->user_email ) ) {
			continue;
		}

		if ( wp_mail( $user->user_email, $subject, $message, $headers ) ) {
			$sent[] = $user->user_email;
		}
	}

	return $sent;
}

/**
 * Get subscribed group member user IDs for a notification type.
 *
 * @param int    $group_id          Group post ID.
 * @param string $notification_type Notification type.
 *
 * @return int[]
 */
function get_group_notification_recipient_user_ids( int $group_id, string $notification_type ): array {
	$query = new \WP_Query(
		array(
			'fields'                 => 'ids',
			'post_type'              => POST_TYPE_MEMBERSHIP,
			'post_status'            => array( 'publish', 'private', 'pending', 'draft' ),
			'post_parent'            => $group_id,
			'posts_per_page'         => -1,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		)
	);

	$recipient_user_ids = array();

	foreach ( array_map( 'intval', $query->posts ) as $membership_id ) {
		if (
			MEMBERSHIP_STATUS_ACTIVE !== get_post_meta( $membership_id, 'wporg_ce_status', true ) ||
			! membership_has_notification_enabled( $membership_id, $notification_type )
		) {
			continue;
		}

		$recipient_user_ids[] = (int) get_post_meta( $membership_id, 'wporg_ce_user_id', true );
	}

	return array_values( array_filter( array_unique( $recipient_user_ids ) ) );
}

/**
 * Get an event notification email subject.
 *
 * @param int    $event_id          Event post ID.
 * @param string $notification_type Notification type.
 *
 * @return string
 */
function get_event_notification_subject( int $event_id, string $notification_type ): string {
	$event       = get_post( $event_id );
	$group_title = $event instanceof \WP_Post ? get_the_title( get_event_group_id( $event ) ) : '';
	$event_title = get_the_title( $event_id );

	if ( NOTIFICATION_EVENT_CANCELLATIONS === $notification_type ) {
		return sprintf(
			/* translators: 1: Community group title, 2: Event title. */
			__( '[%1$s] Event canceled: %2$s', 'wporg' ),
			$group_title,
			$event_title
		);
	}

	if ( NOTIFICATION_EVENT_UPDATES === $notification_type ) {
		return sprintf(
			/* translators: 1: Community group title, 2: Event title. */
			__( '[%1$s] Event updated: %2$s', 'wporg' ),
			$group_title,
			$event_title
		);
	}

	return sprintf(
		/* translators: 1: Community group title, 2: Event title. */
		__( '[%1$s] New event: %2$s', 'wporg' ),
		$group_title,
		$event_title
	);
}

/**
 * Get an event notification email body.
 *
 * @param int    $event_id          Event post ID.
 * @param string $notification_type Notification type.
 *
 * @return string
 */
function get_event_notification_message( int $event_id, string $notification_type ): string {
	$event     = get_post( $event_id );
	$group_id  = $event instanceof \WP_Post ? get_event_group_id( $event ) : 0;
	$start     = get_event_notification_start_label( $event_id );
	$permalink = get_permalink( $event_id );
	$lines     = array();

	if ( NOTIFICATION_EVENT_CANCELLATIONS === $notification_type ) {
		$lines[] = __( 'This event has been canceled.', 'wporg' );
	} elseif ( NOTIFICATION_EVENT_UPDATES === $notification_type ) {
		$lines[] = __( 'This event has been updated.', 'wporg' );
	} else {
		$lines[] = __( 'A new event has been published.', 'wporg' );
	}

	$lines[] = '';
	$lines[] = sprintf(
		/* translators: %s: Community group title. */
		__( 'Group: %s', 'wporg' ),
		get_the_title( $group_id )
	);
	$lines[] = sprintf(
		/* translators: %s: Event title. */
		__( 'Event: %s', 'wporg' ),
		get_the_title( $event_id )
	);

	if ( $start ) {
		$lines[] = sprintf(
			/* translators: %s: Event start date and time. */
			__( 'Starts: %s', 'wporg' ),
			$start
		);
	}

	if ( NOTIFICATION_EVENT_CANCELLATIONS === $notification_type ) {
		$reason = trim( (string) get_post_meta( $event_id, 'wporg_ce_cancellation_reason', true ) );

		if ( '' !== $reason ) {
			$lines[] = sprintf(
				/* translators: %s: Cancellation reason. */
				__( 'Reason: %s', 'wporg' ),
				$reason
			);
		}
	}

	if ( $permalink ) {
		$lines[] = sprintf(
			/* translators: %s: Event URL. */
			__( 'Details: %s', 'wporg' ),
			$permalink
		);
	}

	$lines[] = '';
	$lines[] = __( 'You are receiving this because you joined this WordPress community group.', 'wporg' );

	return implode( "\n", $lines );
}

/**
 * Get a readable event start label for notification emails.
 *
 * @param int $event_id Event post ID.
 *
 * @return string
 */
function get_event_notification_start_label( int $event_id ): string {
	$start_utc = (string) get_post_meta( $event_id, 'wporg_ce_start_utc', true );
	$timestamp = $start_utc ? strtotime( $start_utc ) : false;

	if ( false === $timestamp ) {
		return '';
	}

	$timezone = (string) get_post_meta( $event_id, 'wporg_ce_timezone', true );

	try {
		$timezone_object = new \DateTimeZone( '' !== $timezone ? $timezone : 'UTC' );
	} catch ( \Exception $exception ) {
		unset( $exception );

		$timezone_object = new \DateTimeZone( 'UTC' );
	}

	return sprintf(
		'%1$s %2$s',
		wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp, $timezone_object ),
		$timezone_object->getName()
	);
}

/**
 * Schedule recurring attendee reminder checks.
 */
function maybe_schedule_event_reminders(): void {
	if ( wp_next_scheduled( EVENT_REMINDER_CRON_HOOK ) ) {
		return;
	}

	wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', EVENT_REMINDER_CRON_HOOK );
}

/**
 * Clear the recurring attendee reminder schedule.
 */
function clear_event_reminder_schedule(): void {
	wp_clear_scheduled_hook( EVENT_REMINDER_CRON_HOOK );
}

/**
 * Send attendee reminders for due upcoming events.
 *
 * @param int|null $now Current Unix timestamp. Defaults to the current server time.
 *
 * @return array<int, string[]> Map of event IDs to accepted recipient email addresses.
 */
function send_due_event_reminders( ?int $now = null ): array {
	$now  = null === $now ? time() : $now;
	$sent = array();

	foreach ( get_due_event_reminder_ids( $now ) as $event_id ) {
		$sent[ $event_id ] = send_event_attendee_reminder( $event_id );

		update_post_meta( $event_id, 'wporg_ce_attendee_reminder_sent_at_utc', gmdate( 'Y-m-d H:i:s', $now ) );
		update_post_meta( $event_id, 'wporg_ce_attendee_reminder_start_utc', get_post_meta( $event_id, 'wporg_ce_start_utc', true ) );
	}

	return $sent;
}

/**
 * Get upcoming event IDs that should receive attendee reminders.
 *
 * @param int $now Current Unix timestamp.
 *
 * @return int[]
 */
function get_due_event_reminder_ids( int $now ): array {
	$window_start = gmdate( 'Y-m-d H:i:s', $now );
	$window_end   = gmdate( 'Y-m-d H:i:s', $now + EVENT_REMINDER_LOOKAHEAD_SECONDS );
	$query        = new \WP_Query(
		array(
			'fields'                 => 'ids',
			'meta_key'               => 'wporg_ce_start_utc', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Reminder queue sorting uses registered event start meta.
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Reminder queue filters by registered event status and start meta.
			'meta_query'             => array(
				'relation' => 'AND',
				array(
					'key'     => 'wporg_ce_approval_status',
					'value'   => EVENT_APPROVAL_STATUS_APPROVED,
					'compare' => '=',
				),
				array(
					'key'     => 'wporg_ce_start_utc',
					'value'   => array( $window_start, $window_end ),
					'compare' => 'BETWEEN',
					'type'    => 'DATETIME',
				),
			),
			'no_found_rows'          => true,
			'order'                  => 'ASC',
			'orderby'                => 'meta_value',
			'post_status'            => 'publish',
			'post_type'              => POST_TYPE_EVENT,
			'posts_per_page'         => -1,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		)
	);
	$event_ids    = array();

	foreach ( array_map( 'intval', $query->posts ) as $event_id ) {
		if ( event_attendee_reminder_is_due( $event_id, $now ) ) {
			$event_ids[] = $event_id;
		}
	}

	return $event_ids;
}

/**
 * Determine whether an event should receive an attendee reminder.
 *
 * @param int $event_id Event post ID.
 * @param int $now      Current Unix timestamp.
 *
 * @return bool
 */
function event_attendee_reminder_is_due( int $event_id, int $now ): bool {
	$event = get_post( $event_id );

	if ( ! $event instanceof \WP_Post || POST_TYPE_EVENT !== $event->post_type || 'publish' !== $event->post_status ) {
		return false;
	}

	if ( event_is_canceled( $event_id ) || EVENT_APPROVAL_STATUS_APPROVED !== get_post_meta( $event_id, 'wporg_ce_approval_status', true ) ) {
		return false;
	}

	$start_utc = (string) get_post_meta( $event_id, 'wporg_ce_start_utc', true );
	$timestamp = $start_utc ? strtotime( $start_utc ) : false;

	if ( false === $timestamp || $timestamp < $now || $timestamp > $now + EVENT_REMINDER_LOOKAHEAD_SECONDS ) {
		return false;
	}

	return get_post_meta( $event_id, 'wporg_ce_attendee_reminder_start_utc', true ) !== $start_utc;
}

/**
 * Send an attendee reminder for one event.
 *
 * @param int $event_id Event post ID.
 *
 * @return string[] Email addresses that accepted the reminder.
 */
function send_event_attendee_reminder( int $event_id ): array {
	$event = get_post( $event_id );

	if ( ! $event instanceof \WP_Post || POST_TYPE_EVENT !== $event->post_type || 'publish' !== $event->post_status || event_is_canceled( $event_id ) ) {
		return array();
	}

	$recipient_user_ids = get_event_reminder_recipient_user_ids( $event_id );

	/**
	 * Filters recipient user IDs for attendee reminder emails.
	 *
	 * @param int[] $recipient_user_ids Recipient user IDs.
	 * @param int   $event_id           Event post ID.
	 */
	$recipient_user_ids = apply_filters( 'wporg_ce_event_reminder_recipient_user_ids', $recipient_user_ids, $event_id );
	$recipient_user_ids = array_values( array_unique( array_map( 'intval', (array) $recipient_user_ids ) ) );

	if ( ! $recipient_user_ids ) {
		return array();
	}

	$subject = get_event_reminder_subject( $event_id );
	$message = get_event_reminder_message( $event_id );
	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
	$sent    = array();

	foreach ( $recipient_user_ids as $user_id ) {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user || ! is_email( $user->user_email ) ) {
			continue;
		}

		if ( wp_mail( $user->user_email, $subject, $message, $headers ) ) {
			$sent[] = $user->user_email;
		}
	}

	return $sent;
}

/**
 * Get attending user IDs for an event reminder.
 *
 * @param int $event_id Event post ID.
 *
 * @return int[]
 */
function get_event_reminder_recipient_user_ids( int $event_id ): array {
	$recipient_user_ids = array();

	foreach ( get_event_attendee_rsvp_ids( $event_id, RSVP_STATUS_ATTENDING, 0, true ) as $rsvp_id ) {
		$recipient_user_ids[] = (int) get_post_meta( $rsvp_id, 'wporg_ce_user_id', true );
	}

	return array_values( array_filter( array_unique( $recipient_user_ids ) ) );
}

/**
 * Get an attendee reminder email subject.
 *
 * @param int $event_id Event post ID.
 *
 * @return string
 */
function get_event_reminder_subject( int $event_id ): string {
	$event       = get_post( $event_id );
	$group_title = $event instanceof \WP_Post ? get_the_title( get_event_group_id( $event ) ) : '';
	$event_title = get_the_title( $event_id );

	return sprintf(
		/* translators: 1: Community group title, 2: Event title. */
		__( '[%1$s] Reminder: %2$s', 'wporg' ),
		$group_title,
		$event_title
	);
}

/**
 * Get an attendee reminder email body.
 *
 * @param int $event_id Event post ID.
 *
 * @return string
 */
function get_event_reminder_message( int $event_id ): string {
	$event     = get_post( $event_id );
	$group_id  = $event instanceof \WP_Post ? get_event_group_id( $event ) : 0;
	$location  = get_event_reminder_location_label( $event_id );
	$permalink = get_permalink( $event_id );
	$start     = get_event_notification_start_label( $event_id );
	$lines     = array(
		__( 'Your event is coming up soon.', 'wporg' ),
		'',
		sprintf(
			/* translators: %s: Community group title. */
			__( 'Group: %s', 'wporg' ),
			get_the_title( $group_id )
		),
		sprintf(
			/* translators: %s: Event title. */
			__( 'Event: %s', 'wporg' ),
			get_the_title( $event_id )
		),
	);

	if ( $start ) {
		$lines[] = sprintf(
			/* translators: %s: Event start date and time. */
			__( 'Starts: %s', 'wporg' ),
			$start
		);
	}

	if ( $location ) {
		$lines[] = sprintf(
			/* translators: %s: Event location. */
			__( 'Location: %s', 'wporg' ),
			$location
		);
	}

	if ( $permalink ) {
		$lines[] = sprintf(
			/* translators: %s: Event URL. */
			__( 'Details: %s', 'wporg' ),
			$permalink
		);
	}

	$lines[] = '';
	$lines[] = __( 'You are receiving this because you RSVPed to this event.', 'wporg' );

	return implode( "\n", $lines );
}

/**
 * Get a readable location label for attendee reminder emails.
 *
 * @param int $event_id Event post ID.
 *
 * @return string
 */
function get_event_reminder_location_label( int $event_id ): string {
	$venue_id = (int) get_post_meta( $event_id, 'wporg_ce_venue_id', true );

	if ( $venue_id ) {
		$venue_title = get_the_title( $venue_id );

		if ( $venue_title ) {
			return $venue_title;
		}
	}

	if ( get_post_meta( $event_id, 'wporg_ce_online_url', true ) ) {
		return __( 'Online', 'wporg' );
	}

	return '';
}

/**
 * Determine whether a user is an active group member.
 *
 * @param int $group_id Group post ID.
 * @param int $user_id  WordPress.org user ID.
 *
 * @return bool
 */
function is_active_group_member( int $group_id, int $user_id ): bool {
	$membership_id = get_group_membership_id( $group_id, $user_id );

	if ( ! $membership_id ) {
		return false;
	}

	return MEMBERSHIP_STATUS_ACTIVE === get_post_meta( $membership_id, 'wporg_ce_status', true );
}

/**
 * Determine whether a user can publish events for a group.
 *
 * @param int $group_id Group post ID.
 * @param int $user_id  WordPress.org user ID.
 *
 * @return bool
 */
function can_user_publish_group_events( int $group_id, int $user_id ): bool {
	$membership_id = get_group_membership_id( $group_id, $user_id );

	if ( ! $membership_id || MEMBERSHIP_STATUS_ACTIVE !== get_post_meta( $membership_id, 'wporg_ce_status', true ) ) {
		return false;
	}

	return in_array(
		get_post_meta( $membership_id, 'wporg_ce_role', true ),
		array( MEMBERSHIP_ROLE_ORGANIZER, MEMBERSHIP_ROLE_HOST ),
		true
	);
}

/**
 * Determine whether a user can manage a group's public profile fields.
 *
 * @param int $group_id Group post ID.
 * @param int $user_id  WordPress.org user ID.
 *
 * @return bool
 */
function can_user_manage_group_profile( int $group_id, int $user_id ): bool {
	if ( user_can_moderate_group_organizers( $user_id, $group_id ) ) {
		return true;
	}

	$membership_id = get_group_membership_id( $group_id, $user_id );

	if ( ! $membership_id || MEMBERSHIP_STATUS_ACTIVE !== get_post_meta( $membership_id, 'wporg_ce_status', true ) ) {
		return false;
	}

	return MEMBERSHIP_ROLE_ORGANIZER === get_post_meta( $membership_id, 'wporg_ce_role', true );
}

/**
 * Determine whether a user can manage a target group organizer role.
 *
 * @param int    $group_id             Group post ID.
 * @param int    $user_id              Acting WordPress.org user ID.
 * @param int    $target_membership_id Target membership post ID.
 * @param string $requested_role       Requested target membership role.
 *
 * @return bool
 */
function can_user_manage_group_organizer_role( int $group_id, int $user_id, int $target_membership_id, string $requested_role ): bool {
	if ( user_can_moderate_group_organizers( $user_id, $group_id ) ) {
		return true;
	}

	$membership_id = get_group_membership_id( $group_id, $user_id );

	if (
		! $membership_id ||
		MEMBERSHIP_STATUS_ACTIVE !== get_post_meta( $membership_id, 'wporg_ce_status', true ) ||
		MEMBERSHIP_ROLE_ORGANIZER !== get_post_meta( $membership_id, 'wporg_ce_role', true )
	) {
		return false;
	}

	if ( MEMBERSHIP_ROLE_ORGANIZER === $requested_role ) {
		return false;
	}

	return MEMBERSHIP_ROLE_ORGANIZER !== get_post_meta( $target_membership_id, 'wporg_ce_role', true );
}

/**
 * Determine whether a user has Community Team organizer-management privileges.
 *
 * @param int $user_id  WordPress.org user ID.
 * @param int $group_id Optional group post ID.
 *
 * @return bool
 */
function user_can_moderate_group_organizers( int $user_id, int $group_id = 0 ): bool {
	return (bool) apply_filters(
		'wporg_ce_can_manage_group_organizers',
		user_can( $user_id, 'manage_options' ),
		$user_id,
		$group_id
	);
}

/**
 * Get a user's membership record for a group.
 *
 * @param int $group_id Group post ID.
 * @param int $user_id  WordPress.org user ID.
 *
 * @return int
 */
function get_group_membership_id( int $group_id, int $user_id ): int {
	return get_relationship_post_id( POST_TYPE_MEMBERSHIP, $group_id, $user_id );
}

/**
 * Get a user's RSVP record for an event.
 *
 * @param int $event_id Event post ID.
 * @param int $user_id  WordPress.org user ID.
 *
 * @return int
 */
function get_event_rsvp_id( int $event_id, int $user_id ): int {
	return get_relationship_post_id( POST_TYPE_RSVP, $event_id, $user_id );
}

/**
 * Get event RSVP IDs for an attendee list.
 *
 * @param int    $event_id        Event post ID.
 * @param string $status          RSVP status.
 * @param int    $per_page        Maximum number of RSVP IDs. Zero returns all.
 * @param bool   $include_private Whether private RSVPs should be included.
 *
 * @return int[]
 */
function get_event_attendee_rsvp_ids( int $event_id, string $status, int $per_page, bool $include_private = false ): array {
	$query = new \WP_Query(
		array(
			'fields'                 => 'ids',
			'post_type'              => POST_TYPE_RSVP,
			'post_status'            => array( 'publish', 'private', 'pending', 'draft' ),
			'post_parent'            => $event_id,
			'posts_per_page'         => -1,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		)
	);

	$rsvp_ids = array_filter(
		array_map( 'intval', $query->posts ),
		static function ( int $rsvp_id ) use ( $status, $include_private ): bool {
			if ( get_post_meta( $rsvp_id, 'wporg_ce_status', true ) !== $status ) {
				return false;
			}

			return $include_private || RELATIONSHIP_VISIBILITY_PRIVATE !== get_post_meta( $rsvp_id, 'wporg_ce_visibility', true );
		}
	);

	if ( 0 < $per_page ) {
		$rsvp_ids = array_slice( $rsvp_ids, 0, $per_page );
	}

	return array_values( $rsvp_ids );
}

/**
 * Get a user's feedback record for an event.
 *
 * @param int $event_id Event post ID.
 * @param int $user_id  WordPress.org user ID.
 *
 * @return int
 */
function get_event_feedback_id( int $event_id, int $user_id ): int {
	return get_relationship_post_id( POST_TYPE_FEEDBACK, $event_id, $user_id );
}

/**
 * Get a paginated public feedback query for an event.
 *
 * @param int $event_id Event post ID.
 * @param int $page     Results page.
 * @param int $per_page Maximum number of feedback records.
 *
 * @return \WP_Query
 */
function get_public_event_feedback_query( int $event_id, int $page, int $per_page ): \WP_Query {
	return new \WP_Query(
		array(
			'fields'                 => 'ids',
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'paged'                  => max( 1, $page ),
			'post_parent'            => $event_id,
			'post_status'            => 'publish',
			'post_type'              => POST_TYPE_FEEDBACK,
			'posts_per_page'         => max( 1, $per_page ),
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		)
	);
}

/**
 * Get aggregate feedback stats for an event.
 *
 * @param int $event_id Event post ID.
 *
 * @return array
 */
function get_event_feedback_summary( int $event_id ): array {
	$query = new \WP_Query(
		array(
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'post_parent'            => $event_id,
			'post_status'            => 'publish',
			'post_type'              => POST_TYPE_FEEDBACK,
			'posts_per_page'         => -1,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		)
	);
	$count = 0;
	$sum   = 0;

	foreach ( array_map( 'intval', $query->posts ) as $feedback_id ) {
		$rating = (int) get_post_meta( $feedback_id, 'wporg_ce_rating', true );

		if ( $rating < 1 || $rating > 5 ) {
			continue;
		}

		++$count;
		$sum += $rating;
	}

	return array(
		'average_rating' => $count ? round( $sum / $count, 1 ) : 0.0,
		'count'          => $count,
	);
}

/**
 * Refresh cached RSVP counts on an event.
 *
 * @param int $event_id Event post ID.
 *
 * @return array
 */
function refresh_event_rsvp_counts( int $event_id ): array {
	$counts = get_event_rsvp_counts( $event_id );

	update_post_meta( $event_id, 'wporg_ce_rsvp_count', $counts[ RSVP_STATUS_ATTENDING ] );
	update_post_meta( $event_id, 'wporg_ce_waitlist_count', $counts[ RSVP_STATUS_WAITLISTED ] );

	return $counts;
}

/**
 * Promote waitlisted RSVPs when an event has available capacity.
 *
 * @param int $event_id Event post ID.
 *
 * @return array Final RSVP counts.
 */
function promote_event_waitlist( int $event_id ): array {
	$counts = refresh_event_rsvp_counts( $event_id );

	if ( event_is_canceled( $event_id ) || 1 > $counts[ RSVP_STATUS_WAITLISTED ] ) {
		return $counts;
	}

	$capacity        = (int) get_post_meta( $event_id, 'wporg_ce_capacity', true );
	$promotion_limit = 1 > $capacity
		? $counts[ RSVP_STATUS_WAITLISTED ]
		: max( 0, $capacity - $counts[ RSVP_STATUS_ATTENDING ] );

	if ( 1 > $promotion_limit ) {
		normalize_event_waitlist_positions( $event_id );

		return $counts;
	}

	$promoted_rsvp_ids = array_slice( get_event_waitlist_rsvp_ids( $event_id ), 0, $promotion_limit );

	foreach ( $promoted_rsvp_ids as $rsvp_id ) {
		update_relationship_meta(
			$rsvp_id,
			array(
				'wporg_ce_status'            => RSVP_STATUS_ATTENDING,
				'wporg_ce_waitlist_position' => 0,
				'wporg_ce_updated_at_utc'    => current_time( 'mysql', true ),
			)
		);

		send_event_waitlist_promotion_notification( $event_id, $rsvp_id );
	}

	normalize_event_waitlist_positions( $event_id );

	return refresh_event_rsvp_counts( $event_id );
}

/**
 * Normalize remaining event waitlist positions.
 *
 * @param int $event_id Event post ID.
 */
function normalize_event_waitlist_positions( int $event_id ): void {
	$position = 1;

	foreach ( get_event_waitlist_rsvp_ids( $event_id ) as $rsvp_id ) {
		update_post_meta( $rsvp_id, 'wporg_ce_waitlist_position', $position );
		++$position;
	}
}

/**
 * Get waitlisted RSVP IDs in promotion order.
 *
 * @param int $event_id Event post ID.
 *
 * @return int[]
 */
function get_event_waitlist_rsvp_ids( int $event_id ): array {
	$rsvp_ids = get_event_attendee_rsvp_ids( $event_id, RSVP_STATUS_WAITLISTED, 0, true );

	usort( $rsvp_ids, __NAMESPACE__ . '\\compare_waitlisted_rsvp_ids' );

	return $rsvp_ids;
}

/**
 * Compare two waitlisted RSVP IDs by queue position.
 *
 * @param int $first_rsvp_id  First RSVP post ID.
 * @param int $second_rsvp_id Second RSVP post ID.
 *
 * @return int
 */
function compare_waitlisted_rsvp_ids( int $first_rsvp_id, int $second_rsvp_id ): int {
	$first_position  = get_waitlist_sort_position( $first_rsvp_id );
	$second_position = get_waitlist_sort_position( $second_rsvp_id );

	if ( $first_position === $second_position ) {
		return $first_rsvp_id <=> $second_rsvp_id;
	}

	return $first_position <=> $second_position;
}

/**
 * Get a sortable waitlist position for an RSVP.
 *
 * @param int $rsvp_id RSVP post ID.
 *
 * @return int
 */
function get_waitlist_sort_position( int $rsvp_id ): int {
	$position = (int) get_post_meta( $rsvp_id, 'wporg_ce_waitlist_position', true );

	return 0 < $position ? $position : PHP_INT_MAX;
}

/**
 * Send an RSVP confirmation email when a waitlisted attendee is promoted.
 *
 * @param int $event_id Event post ID.
 * @param int $rsvp_id  RSVP post ID.
 *
 * @return string[] Email addresses that accepted the notification.
 */
function send_event_waitlist_promotion_notification( int $event_id, int $rsvp_id ): array {
	$event = get_post( $event_id );

	if (
		! $event instanceof \WP_Post ||
		POST_TYPE_EVENT !== $event->post_type ||
		'publish' !== $event->post_status ||
		event_is_canceled( $event_id ) ||
		RSVP_STATUS_ATTENDING !== get_post_meta( $rsvp_id, 'wporg_ce_status', true )
	) {
		return array();
	}

	$recipient_user_ids = array( (int) get_post_meta( $rsvp_id, 'wporg_ce_user_id', true ) );

	/**
	 * Filters recipient user IDs for waitlist promotion emails.
	 *
	 * @param int[] $recipient_user_ids Recipient user IDs.
	 * @param int   $event_id           Event post ID.
	 * @param int   $rsvp_id            RSVP post ID.
	 */
	$recipient_user_ids = apply_filters( 'wporg_ce_event_waitlist_promotion_recipient_user_ids', $recipient_user_ids, $event_id, $rsvp_id );
	$recipient_user_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $recipient_user_ids ) ) ) );

	if ( ! $recipient_user_ids ) {
		return array();
	}

	$subject = get_event_waitlist_promotion_subject( $event_id );
	$message = get_event_waitlist_promotion_message( $event_id );
	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
	$sent    = array();

	foreach ( $recipient_user_ids as $user_id ) {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user || ! is_email( $user->user_email ) ) {
			continue;
		}

		if ( wp_mail( $user->user_email, $subject, $message, $headers ) ) {
			$sent[] = $user->user_email;
		}
	}

	return $sent;
}

/**
 * Get a waitlist promotion email subject.
 *
 * @param int $event_id Event post ID.
 *
 * @return string
 */
function get_event_waitlist_promotion_subject( int $event_id ): string {
	$event       = get_post( $event_id );
	$group_title = $event instanceof \WP_Post ? get_the_title( get_event_group_id( $event ) ) : '';
	$event_title = get_the_title( $event_id );

	return sprintf(
		/* translators: 1: Community group title, 2: Event title. */
		__( '[%1$s] You are in: %2$s', 'wporg' ),
		$group_title,
		$event_title
	);
}

/**
 * Get a waitlist promotion email body.
 *
 * @param int $event_id Event post ID.
 *
 * @return string
 */
function get_event_waitlist_promotion_message( int $event_id ): string {
	$event     = get_post( $event_id );
	$group_id  = $event instanceof \WP_Post ? get_event_group_id( $event ) : 0;
	$permalink = get_permalink( $event_id );
	$start     = get_event_notification_start_label( $event_id );
	$lines     = array(
		__( 'Good news, your spot is confirmed.', 'wporg' ),
		'',
		sprintf(
			/* translators: %s: Community group title. */
			__( 'Group: %s', 'wporg' ),
			get_the_title( $group_id )
		),
		sprintf(
			/* translators: %s: Event title. */
			__( 'Event: %s', 'wporg' ),
			get_the_title( $event_id )
		),
	);

	if ( $start ) {
		$lines[] = sprintf(
			/* translators: %s: Event start date and time. */
			__( 'Starts: %s', 'wporg' ),
			$start
		);
	}

	if ( $permalink ) {
		$lines[] = sprintf(
			/* translators: %s: Event URL. */
			__( 'Details: %s', 'wporg' ),
			$permalink
		);
	}

	$lines[] = '';
	$lines[] = __( 'You are receiving this because your RSVP moved from the waitlist to attending.', 'wporg' );

	return implode( "\n", $lines );
}

/**
 * Determine whether an RSVP status change should send an email.
 *
 * @param string $current_status Previous RSVP status.
 * @param string $status         New RSVP status.
 *
 * @return bool
 */
function should_send_event_rsvp_status_notification( string $current_status, string $status ): bool {
	if ( $current_status === $status || ! in_array( $status, get_rsvp_statuses(), true ) ) {
		return false;
	}

	if ( '' === $current_status && RSVP_STATUS_NOT_ATTENDING === $status ) {
		return false;
	}

	return true;
}

/**
 * Send a transactional email when an attendee RSVP status changes.
 *
 * @param int    $event_id Event post ID.
 * @param int    $rsvp_id  RSVP post ID.
 * @param string $status   New RSVP status.
 *
 * @return string[] Email addresses that accepted the notification.
 */
function send_event_rsvp_status_notification( int $event_id, int $rsvp_id, string $status ): array {
	$event = get_post( $event_id );

	if (
		! $event instanceof \WP_Post ||
		POST_TYPE_EVENT !== $event->post_type ||
		'publish' !== $event->post_status ||
		! in_array( $status, get_rsvp_statuses(), true )
	) {
		return array();
	}

	$recipient_user_ids = array( (int) get_post_meta( $rsvp_id, 'wporg_ce_user_id', true ) );

	/**
	 * Filters recipient user IDs for RSVP status emails.
	 *
	 * @param int[]  $recipient_user_ids Recipient user IDs.
	 * @param int    $event_id           Event post ID.
	 * @param int    $rsvp_id            RSVP post ID.
	 * @param string $status             New RSVP status.
	 */
	$recipient_user_ids = apply_filters( 'wporg_ce_event_rsvp_status_recipient_user_ids', $recipient_user_ids, $event_id, $rsvp_id, $status );
	$recipient_user_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $recipient_user_ids ) ) ) );

	if ( ! $recipient_user_ids ) {
		return array();
	}

	$subject = get_event_rsvp_status_subject( $event_id, $status );
	$message = get_event_rsvp_status_message( $event_id, $status );
	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
	$sent    = array();

	foreach ( $recipient_user_ids as $user_id ) {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user || ! is_email( $user->user_email ) ) {
			continue;
		}

		if ( wp_mail( $user->user_email, $subject, $message, $headers ) ) {
			$sent[] = $user->user_email;
		}
	}

	return $sent;
}

/**
 * Get an RSVP status email subject.
 *
 * @param int    $event_id Event post ID.
 * @param string $status   RSVP status.
 *
 * @return string
 */
function get_event_rsvp_status_subject( int $event_id, string $status ): string {
	$event       = get_post( $event_id );
	$group_title = $event instanceof \WP_Post ? get_the_title( get_event_group_id( $event ) ) : '';
	$event_title = get_the_title( $event_id );

	if ( RSVP_STATUS_WAITLISTED === $status ) {
		return sprintf(
			/* translators: 1: Community group title, 2: Event title. */
			__( '[%1$s] You are on the waitlist: %2$s', 'wporg' ),
			$group_title,
			$event_title
		);
	}

	if ( RSVP_STATUS_NOT_ATTENDING === $status ) {
		return sprintf(
			/* translators: 1: Community group title, 2: Event title. */
			__( '[%1$s] RSVP canceled: %2$s', 'wporg' ),
			$group_title,
			$event_title
		);
	}

	return sprintf(
		/* translators: 1: Community group title, 2: Event title. */
		__( '[%1$s] RSVP confirmed: %2$s', 'wporg' ),
		$group_title,
		$event_title
	);
}

/**
 * Get an RSVP status email body.
 *
 * @param int    $event_id Event post ID.
 * @param string $status   RSVP status.
 *
 * @return string
 */
function get_event_rsvp_status_message( int $event_id, string $status ): string {
	$event     = get_post( $event_id );
	$group_id  = $event instanceof \WP_Post ? get_event_group_id( $event ) : 0;
	$permalink = get_permalink( $event_id );
	$start     = get_event_notification_start_label( $event_id );
	$lines     = array(
		get_event_rsvp_status_intro( $status ),
		'',
		sprintf(
			/* translators: %s: Community group title. */
			__( 'Group: %s', 'wporg' ),
			get_the_title( $group_id )
		),
		sprintf(
			/* translators: %s: Event title. */
			__( 'Event: %s', 'wporg' ),
			get_the_title( $event_id )
		),
	);

	if ( $start ) {
		$lines[] = sprintf(
			/* translators: %s: Event start date and time. */
			__( 'Starts: %s', 'wporg' ),
			$start
		);
	}

	if ( $permalink ) {
		$lines[] = sprintf(
			/* translators: %s: Event URL. */
			__( 'Details: %s', 'wporg' ),
			$permalink
		);
	}

	$lines[] = '';
	$lines[] = __( 'You are receiving this because you RSVPed to this event.', 'wporg' );

	return implode( "\n", $lines );
}

/**
 * Get an RSVP status email intro line.
 *
 * @param string $status RSVP status.
 *
 * @return string
 */
function get_event_rsvp_status_intro( string $status ): string {
	if ( RSVP_STATUS_WAITLISTED === $status ) {
		return __( 'You are on the waitlist for this event. We will let you know if a spot opens.', 'wporg' );
	}

	if ( RSVP_STATUS_NOT_ATTENDING === $status ) {
		return __( 'Your RSVP has been canceled.', 'wporg' );
	}

	return __( 'Your RSVP is confirmed.', 'wporg' );
}

/**
 * Count active RSVP records for an event.
 *
 * @param int $event_id Event post ID.
 *
 * @return array
 */
function get_event_rsvp_counts( int $event_id ): array {
	$counts = array(
		RSVP_STATUS_ATTENDING  => 0,
		RSVP_STATUS_WAITLISTED => 0,
	);

	$query = new \WP_Query(
		array(
			'fields'                 => 'ids',
			'post_type'              => POST_TYPE_RSVP,
			'post_status'            => array( 'publish', 'private', 'pending', 'draft' ),
			'post_parent'            => $event_id,
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	foreach ( $query->posts as $rsvp_id ) {
		$status = get_post_meta( $rsvp_id, 'wporg_ce_status', true );

		if ( array_key_exists( $status, $counts ) ) {
			++$counts[ $status ];
		}
	}

	return $counts;
}

/**
 * Resolve an RSVP status, accounting for event capacity.
 *
 * @param int    $event_id         Event post ID.
 * @param string $requested_status Requested RSVP status.
 * @param string $current_status   Current RSVP status.
 *
 * @return string
 */
function resolve_event_rsvp_status( int $event_id, string $requested_status, string $current_status ): string {
	if ( RSVP_STATUS_ATTENDING !== $requested_status ) {
		return $requested_status;
	}

	$capacity = (int) get_post_meta( $event_id, 'wporg_ce_capacity', true );

	if ( 1 > $capacity || RSVP_STATUS_ATTENDING === $current_status ) {
		return RSVP_STATUS_ATTENDING;
	}

	$counts = get_event_rsvp_counts( $event_id );

	if ( $capacity <= $counts[ RSVP_STATUS_ATTENDING ] ) {
		return RSVP_STATUS_WAITLISTED;
	}

	return RSVP_STATUS_ATTENDING;
}

/**
 * Get the waitlist position for an RSVP.
 *
 * @param int    $event_id Event post ID.
 * @param int    $rsvp_id  RSVP post ID.
 * @param string $status   RSVP status.
 *
 * @return int
 */
function get_rsvp_waitlist_position( int $event_id, int $rsvp_id, string $status ): int {
	if ( RSVP_STATUS_WAITLISTED !== $status ) {
		return 0;
	}

	$existing_position = (int) get_post_meta( $rsvp_id, 'wporg_ce_waitlist_position', true );

	if ( 0 < $existing_position ) {
		return $existing_position;
	}

	$counts = get_event_rsvp_counts( $event_id );

	return $counts[ RSVP_STATUS_WAITLISTED ] + 1;
}

/**
 * Get normalized attendance tracking data for an RSVP update.
 *
 * @param int    $rsvp_id        RSVP post ID.
 * @param string $status         New RSVP status.
 * @param string $current_status Current RSVP status.
 * @param array  $args           RSVP data.
 *
 * @return array
 */
function get_rsvp_attendance_update( int $rsvp_id, string $status, string $current_status, array $args ): array {
	$current_attendance_status = $rsvp_id ? (string) get_post_meta( $rsvp_id, 'wporg_ce_attendance_status', true ) : '';
	$current_attendance_by     = $rsvp_id ? (int) get_post_meta( $rsvp_id, 'wporg_ce_attendance_by', true ) : 0;
	$attendance_status         = array_key_exists( 'attendance_status', $args )
		? get_allowed_value( $args['attendance_status'], get_attendance_statuses(), ATTENDANCE_STATUS_NOT_CHECKED_IN )
		: get_default_rsvp_attendance_status( $status, $current_status, $current_attendance_status );
	$attended_at_utc           = $rsvp_id ? (string) get_post_meta( $rsvp_id, 'wporg_ce_attended_at_utc', true ) : '';
	$updated_at_utc            = $rsvp_id ? (string) get_post_meta( $rsvp_id, 'wporg_ce_attendance_at_utc', true ) : '';
	$attendance_changed        = $attendance_status !== $current_attendance_status || array_key_exists( 'attendance_status', $args );
	$attendance_by             = $attendance_changed ? max( 0, (int) ( $args['attendance_by_user_id'] ?? 0 ) ) : $current_attendance_by;

	if ( ATTENDANCE_STATUS_CHECKED_IN === $attendance_status && '' === $attended_at_utc ) {
		$attended_at_utc = current_time( 'mysql', true );
	}

	if ( ATTENDANCE_STATUS_CHECKED_IN !== $attendance_status ) {
		$attended_at_utc = '';
	}

	if ( $attendance_changed ) {
		$updated_at_utc = current_time( 'mysql', true );
	}

	return array(
		'attended_at_utc' => $attended_at_utc,
		'by_user_id'      => $attendance_by,
		'status'          => $attendance_status,
		'updated_at_utc'  => $updated_at_utc,
	);
}

/**
 * Get a default attendance status for an RSVP update.
 *
 * @param string $status                    New RSVP status.
 * @param string $current_status            Current RSVP status.
 * @param string $current_attendance_status Current attendance status.
 *
 * @return string
 */
function get_default_rsvp_attendance_status( string $status, string $current_status, string $current_attendance_status ): string {
	if ( RSVP_STATUS_NOT_ATTENDING === $status ) {
		return ATTENDANCE_STATUS_NOT_COMING;
	}

	if ( $status === $current_status && in_array( $current_attendance_status, get_attendance_statuses(), true ) ) {
		return $current_attendance_status;
	}

	return ATTENDANCE_STATUS_NOT_CHECKED_IN;
}

/**
 * Create or update a relationship post.
 *
 * @param string $post_type     Relationship post type.
 * @param string $relationship  Relationship name for titles.
 * @param int    $parent_id     Parent object ID.
 * @param int    $user_id       WordPress.org user ID.
 * @param int    $existing_id   Existing relationship post ID.
 *
 * @return int|\WP_Error
 */
function upsert_relationship_post( string $post_type, string $relationship, int $parent_id, int $user_id, int $existing_id = 0 ) {
	$slug = "{$relationship}-{$parent_id}-user-{$user_id}";
	$post = array(
		'post_type'   => $post_type,
		'post_status' => 'publish',
		'post_title'  => $slug,
		'post_name'   => $slug,
		'post_author' => $user_id,
		'post_parent' => $parent_id,
	);

	if ( $existing_id ) {
		$post['ID'] = $existing_id;
	}

	return wp_insert_post( wp_slash( $post ), true );
}

/**
 * Update a relationship's registered meta fields.
 *
 * @param int   $post_id Relationship post ID.
 * @param array $meta    Meta values.
 */
function update_relationship_meta( int $post_id, array $meta ): void {
	foreach ( $meta as $key => $value ) {
		update_post_meta( $post_id, $key, $value );
	}
}

/**
 * Find an existing user-owned relationship post.
 *
 * @param string $post_type Relationship post type.
 * @param int    $parent_id Parent post ID.
 * @param int    $user_id   WordPress.org user ID.
 *
 * @return int
 */
function get_relationship_post_id( string $post_type, int $parent_id, int $user_id ): int {
	$query = new \WP_Query(
		array(
			'fields'                 => 'ids',
			'post_type'              => $post_type,
			'post_status'            => array( 'publish', 'private', 'pending', 'draft' ),
			'post_parent'            => $parent_id,
			'author'                 => $user_id,
			'posts_per_page'         => 1,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	return (int) ( $query->posts[0] ?? 0 );
}

/**
 * Validate a user relationship target.
 *
 * @param string $post_type Expected target post type.
 * @param int    $post_id   Target post ID.
 * @param int    $user_id   WordPress.org user ID.
 *
 * @return true|\WP_Error
 */
function validate_user_relationship_target( string $post_type, int $post_id, int $user_id ) {
	$post = get_post( $post_id );
	$user = get_user_by( 'id', $user_id );

	if ( ! $post || $post_type !== $post->post_type ) {
		return new \WP_Error( 'wporg_ce_invalid_relationship_target', __( 'Invalid community object.', 'wporg' ) );
	}

	if ( ! $user ) {
		return new \WP_Error( 'wporg_ce_invalid_relationship_user', __( 'Invalid community member.', 'wporg' ) );
	}

	return true;
}

/**
 * Validate that a user can manage a group event.
 *
 * @param int $event_id Event post ID.
 * @param int $user_id  WordPress.org user ID.
 *
 * @return true|\WP_Error
 */
function validate_group_event_manager( int $event_id, int $user_id ) {
	$event = get_post( $event_id );
	$user  = get_user_by( 'id', $user_id );

	if ( ! $event || POST_TYPE_EVENT !== $event->post_type ) {
		return new \WP_Error( 'wporg_ce_invalid_relationship_target', __( 'Invalid community object.', 'wporg' ) );
	}

	if ( ! $user ) {
		return new \WP_Error( 'wporg_ce_invalid_relationship_user', __( 'Invalid community member.', 'wporg' ) );
	}

	$group_id        = get_event_group_id( $event );
	$is_event_hosted = in_array( $user_id, get_event_host_user_ids( $event_id ), true ) && is_active_group_member( $group_id, $user_id );

	if ( ! $group_id || ( ! can_user_publish_group_events( $group_id, $user_id ) && ! $is_event_hosted ) ) {
		return new \WP_Error( 'wporg_ce_cannot_manage_event', __( 'You cannot manage events for this group.', 'wporg' ) );
	}

	return true;
}

/**
 * Validate an event start/end date range.
 *
 * @param string $start_utc Event start time in UTC.
 * @param string $end_utc   Event end time in UTC.
 *
 * @return true|\WP_Error
 */
function validate_event_date_range( string $start_utc, string $end_utc ) {
	if ( '' === $start_utc || '' === $end_utc ) {
		return true;
	}

	$start_timestamp = strtotime( $start_utc );
	$end_timestamp   = strtotime( $end_utc );

	if ( false === $start_timestamp || false === $end_timestamp ) {
		return new \WP_Error( 'wporg_ce_event_datetime_invalid', __( 'Choose valid event dates.', 'wporg' ) );
	}

	if ( $end_timestamp <= $start_timestamp ) {
		return new \WP_Error( 'wporg_ce_event_end_before_start', __( 'Choose an end time after the start time.', 'wporg' ) );
	}

	return true;
}

/**
 * Get an allowed value or a fallback.
 *
 * @param mixed  $value    Requested value.
 * @param array  $allowed  Allowed values.
 * @param string $fallback Fallback value.
 *
 * @return string
 */
function get_allowed_value( $value, array $allowed, string $fallback ): string {
	if ( is_string( $value ) && in_array( $value, $allowed, true ) ) {
		return $value;
	}

	return $fallback;
}

/**
 * Membership roles.
 *
 * @return string[]
 */
function get_membership_roles(): array {
	return array(
		MEMBERSHIP_ROLE_MEMBER,
		MEMBERSHIP_ROLE_ORGANIZER,
		MEMBERSHIP_ROLE_HOST,
	);
}

/**
 * Membership statuses.
 *
 * @return string[]
 */
function get_membership_statuses(): array {
	return array(
		MEMBERSHIP_STATUS_ACTIVE,
		MEMBERSHIP_STATUS_PENDING,
		MEMBERSHIP_STATUS_LEFT,
	);
}

/**
 * RSVP statuses.
 *
 * @return string[]
 */
function get_rsvp_statuses(): array {
	return array(
		RSVP_STATUS_ATTENDING,
		RSVP_STATUS_WAITLISTED,
		RSVP_STATUS_NOT_ATTENDING,
	);
}

/**
 * Attendance tracking statuses.
 *
 * @return string[]
 */
function get_attendance_statuses(): array {
	return array(
		ATTENDANCE_STATUS_NOT_CHECKED_IN,
		ATTENDANCE_STATUS_CHECKED_IN,
		ATTENDANCE_STATUS_NO_SHOW,
		ATTENDANCE_STATUS_NOT_COMING,
	);
}

/**
 * Relationship visibility options.
 *
 * @return string[]
 */
function get_relationship_visibilities(): array {
	return array(
		RELATIONSHIP_VISIBILITY_PUBLIC,
		RELATIONSHIP_VISIBILITY_PRIVATE,
	);
}

/**
 * Event approval statuses.
 *
 * @return string[]
 */
function get_event_approval_statuses(): array {
	return array(
		EVENT_APPROVAL_STATUS_APPROVED,
		EVENT_APPROVAL_STATUS_CANCELED,
	);
}
