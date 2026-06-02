<?php
/**
 * Shared rendering helpers for WordPress.org Events.
 *
 * @package WordPressdotorg\Events_Theme
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Events_Theme;

defined( 'ABSPATH' ) || exit;

/**
 * Configure the shared Interactivity API store for rendered blocks.
 */
function configure_interactivity_store(): void {
	wp_interactivity_state(
		STORE_NAMESPACE,
		array(
			'dashboardView'  => 'rsvps',
			'eventCountry'   => '',
			'eventFormat'    => '',
			'eventLanguage'  => '',
			'eventQuery'     => '',
			'eventTopic'     => '',
			'eventTimeframe' => 'upcoming',
			'eventType'      => '',
			'groupCountry'   => '',
			'groupLanguage'  => '',
			'groupQuery'     => '',
			'groupTopic'     => '',
			'groupType'      => '',
			'venueQuery'     => '',
		)
	);

	wp_interactivity_config(
		STORE_NAMESPACE,
		array(
			'isLoggedIn' => is_user_logged_in(),
			'loginUrl'   => wp_login_url( get_current_url() ),
			'messages'   => array(
				'attendanceStatusCheckedIn'     => __( 'Checked in', 'wporg' ),
				'attendanceStatusNoShow'        => __( 'No show', 'wporg' ),
				'attendanceStatusNotCheckedIn'  => __( 'Not checked in', 'wporg' ),
				'attendanceStatusNotComing'     => __( 'Not coming', 'wporg' ),
				'addAttendee'                   => __( 'Add attendee', 'wporg' ),
				'attending'                     => __( 'You are attending.', 'wporg' ),
				'attendeeAdded'                 => __( 'Attendee added.', 'wporg' ),
				'attendeeAdding'                => __( 'Adding attendee...', 'wporg' ),
				'attendeeUpdating'              => __( 'Updating attendee...', 'wporg' ),
				'attendeeUserRequired'          => __( 'Choose a member to add.', 'wporg' ),
				'attendEvent'                   => __( 'Attend this event', 'wporg' ),
				'addToTeam'                     => __( 'Add to team', 'wporg' ),
				'cancelingRsvp'                 => __( 'Canceling RSVP...', 'wporg' ),
				'cancelRsvp'                    => __( 'Cancel RSVP', 'wporg' ),
				'eventCancel'                   => __( 'Cancel event', 'wporg' ),
				'eventCanceled'                 => __( 'Event canceled.', 'wporg' ),
				'eventCanceledButton'           => __( 'Event canceled', 'wporg' ),
				'eventCanceledPublic'           => __( 'This event has been canceled.', 'wporg' ),
				'eventCanceling'                => __( 'Canceling event...', 'wporg' ),
				'eventCopy'                     => __( 'Create copy', 'wporg' ),
				'eventCopied'                   => __( 'Event copy created.', 'wporg' ),
				'eventCopying'                  => __( 'Creating copy...', 'wporg' ),
				'eventCreated'                  => __( 'Event created.', 'wporg' ),
				'eventCreating'                 => __( 'Creating event...', 'wporg' ),
				'eventEndBeforeStart'           => __( 'Choose an end time after the start time.', 'wporg' ),
				'eventEndInvalid'               => __( 'Choose a valid end date and time.', 'wporg' ),
				'eventFeedbackRatingRequired'   => __( 'Choose a rating.', 'wporg' ),
				'eventFeedbackSaved'            => __( 'Feedback shared. Thank you.', 'wporg' ),
				'eventFeedbackSaving'           => __( 'Sharing feedback...', 'wporg' ),
				'eventMessageBodyRequired'      => __( 'Add a message for attendees.', 'wporg' ),
				'eventMessageSent'              => __( 'Message sent to attendees.', 'wporg' ),
				'eventMessageSending'           => __( 'Sending message...', 'wporg' ),
				'eventMessageSubjectRequired'   => __( 'Add a subject for the message.', 'wporg' ),
				'eventStartRequired'            => __( 'Choose a valid start date and time.', 'wporg' ),
				'eventStatusApproved'           => __( 'Approved', 'wporg' ),
				'eventStatusCanceled'           => __( 'Canceled', 'wporg' ),
				'eventUpdated'                  => __( 'Event updated.', 'wporg' ),
				'eventUpdating'                 => __( 'Saving event...', 'wporg' ),
				'groupProfileSaved'             => __( 'Group settings saved.', 'wporg' ),
				'groupProfileSaving'            => __( 'Saving group settings...', 'wporg' ),
				'venueCreating'                 => __( 'Creating venue...', 'wporg' ),
				'joinGroup'                     => __( 'Join group', 'wporg' ),
				'joinedGroup'                   => __( 'You joined this group.', 'wporg' ),
				'joiningGroup'                  => __( 'Joining...', 'wporg' ),
				'leaveGroup'                    => __( 'Leave group', 'wporg' ),
				'leavingGroup'                  => __( 'Leaving...', 'wporg' ),
				'leftGroup'                     => __( 'You left this group.', 'wporg' ),
				'groupOrganizerAdded'           => __( 'Team member added.', 'wporg' ),
				'groupOrganizerAdding'          => __( 'Adding team member...', 'wporg' ),
				'groupOrganizerUpdated'         => __( 'Team member updated.', 'wporg' ),
				'groupOrganizerUpdating'        => __( 'Updating team member...', 'wporg' ),
				'membershipNotificationsSaved'  => __( 'Membership settings saved.', 'wporg' ),
				'membershipNotificationsSaving' => __( 'Saving membership settings...', 'wporg' ),
				'membershipActive'              => __( 'Active', 'wporg' ),
				'membershipLeft'                => __( 'Left', 'wporg' ),
				'membershipPending'             => __( 'Pending', 'wporg' ),
				'membershipRoleHost'            => __( 'Host', 'wporg' ),
				'membershipRoleMember'          => __( 'Member', 'wporg' ),
				'membershipRoleOrganizer'       => __( 'Organizer', 'wporg' ),
				'createEvent'                   => __( 'Create event', 'wporg' ),
				'rsvp'                          => __( 'RSVP', 'wporg' ),
				'rsvpCanceled'                  => __( 'Your RSVP was canceled.', 'wporg' ),
				'rsvpSaved'                     => __( 'Your RSVP was saved.', 'wporg' ),
				'savingRsvp'                    => __( 'Saving RSVP...', 'wporg' ),
				'shareFeedback'                 => __( 'Share feedback', 'wporg' ),
				'rsvpStatusAttending'           => __( 'Attending', 'wporg' ),
				'rsvpStatusNotAttending'        => __( 'Not attending', 'wporg' ),
				'rsvpStatusWaitlisted'          => __( 'Waitlisted', 'wporg' ),
				'saveEvent'                     => __( 'Save event', 'wporg' ),
				'saveGroupProfile'              => __( 'Save group settings', 'wporg' ),
				'saveRsvp'                      => __( 'Save RSVP', 'wporg' ),
				'sendMessage'                   => __( 'Send message', 'wporg' ),
				'saveNotifications'             => __( 'Save settings', 'wporg' ),
				'suggestGroup'                  => __( 'Suggest group', 'wporg' ),
				'groupSuggestionApproved'       => __( 'Approved', 'wporg' ),
				'groupSuggestionDeclined'       => __( 'Declined', 'wporg' ),
				'groupSuggestionNeedsInfo'      => __( 'Needs more information', 'wporg' ),
				'groupSuggestionPending'        => __( 'Pending review', 'wporg' ),
				'groupSuggestionReviewSave'     => __( 'Save review', 'wporg' ),
				'groupSuggestionReviewSaved'    => __( 'Group suggestion review saved.', 'wporg' ),
				'groupSuggestionReviewSaving'   => __( 'Saving review...', 'wporg' ),
				'groupSuggestionReviewStatus'   => __( 'Choose a review status.', 'wporg' ),
				'suggestGroupSubmitting'        => __( 'Sending suggestion...', 'wporg' ),
				'unableCancelRsvp'              => __( 'Unable to cancel RSVP.', 'wporg' ),
				'unableAttendeeAdd'             => __( 'Unable to add attendee.', 'wporg' ),
				'unableAttendeeUpdate'          => __( 'Unable to update attendee.', 'wporg' ),
				'unableEventCancel'             => __( 'Unable to cancel event.', 'wporg' ),
				'unableEventCopy'               => __( 'Unable to create event copy.', 'wporg' ),
				'unableEventCreate'             => __( 'Unable to create event.', 'wporg' ),
				'unableEventFeedback'           => __( 'Unable to share feedback.', 'wporg' ),
				'unableEventMessage'            => __( 'Unable to send message.', 'wporg' ),
				'unableEventUpdate'             => __( 'Unable to update event.', 'wporg' ),
				'unableGroupProfileUpdate'      => __( 'Unable to update group settings.', 'wporg' ),
				'unableVenueCreate'             => __( 'Unable to create venue.', 'wporg' ),
				'unableGroupOrganizerUpdate'    => __( 'Unable to update organizer team.', 'wporg' ),
				'unableGroupNotifications'      => __( 'Unable to update notifications.', 'wporg' ),
				'unableRsvp'                    => __( 'Unable to RSVP.', 'wporg' ),
				'unableGroupMembership'         => __( 'Unable to update group membership.', 'wporg' ),
				'unableGroupSuggestion'         => __( 'Unable to send group suggestion.', 'wporg' ),
				'unableGroupSuggestionReview'   => __( 'Unable to update group suggestion review.', 'wporg' ),
				'updateOrganizerRole'           => __( 'Update role', 'wporg' ),
				'groupSuggestionLocation'       => __( 'Add a location for the group.', 'wporg' ),
				'groupSuggestionSent'           => __( 'Your group suggestion was sent for Community Team review.', 'wporg' ),
				'groupSuggestionTitle'          => __( 'Add a group name.', 'wporg' ),
				'unknownStatus'                 => __( 'Unknown', 'wporg' ),
				'waitlisted'                    => __( 'You are on the waitlist.', 'wporg' ),
			),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'restUrl'    => untrailingslashit( rest_url( REST_NAMESPACE ) ),
		)
	);
}

/**
 * Get the current request URL.
 *
 * @return string
 */
function get_current_url(): string {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

	return home_url( $request_uri );
}

/**
 * Dispatch an internal REST request against the community events API.
 *
 * @param string $method REST method.
 * @param string $path   Route path without namespace.
 * @param array  $params Query or body parameters.
 *
 * @return array
 */
function get_rest_data( string $method, string $path, array $params = array() ): array {
	$request = new \WP_REST_Request( $method, '/' . REST_NAMESPACE . $path );

	if ( \WP_REST_Server::READABLE === $method ) {
		$request->set_query_params( $params );
	} else {
		$request->set_body_params( $params );
	}

	$response = rest_do_request( $request );

	if ( $response->is_error() ) {
		return array();
	}

	$data = $response->get_data();

	return is_array( $data ) ? $data : array();
}

/**
 * Get public groups for display.
 *
 * @param int $per_page Maximum number of groups.
 *
 * @return array
 */
function get_groups( int $per_page = 12 ): array {
	return get_rest_data(
		\WP_REST_Server::READABLE,
		'/groups',
		array(
			'per_page' => $per_page,
		)
	);
}

/**
 * Get public events for display.
 *
 * @param array $args Event collection arguments.
 *
 * @return array
 */
function get_events( array $args = array() ): array {
	return get_rest_data(
		\WP_REST_Server::READABLE,
		'/events',
		array_merge(
			array(
				'per_page'  => 12,
				'timeframe' => 'all',
			),
			$args
		)
	);
}

/**
 * Get public venues for display.
 *
 * @param int $per_page Maximum number of venues.
 *
 * @return array
 */
function get_venues( int $per_page = 12 ): array {
	return get_rest_data(
		\WP_REST_Server::READABLE,
		'/venues',
		array(
			'per_page' => $per_page,
		)
	);
}

/**
 * Get group suggestions for Community Team review.
 *
 * @param string $review_status Review status filter.
 * @param int    $per_page      Maximum number of suggestions.
 *
 * @return array
 */
function get_group_suggestions_for_review( string $review_status = 'pending', int $per_page = 20 ): array {
	return get_rest_data(
		\WP_REST_Server::READABLE,
		'/group-suggestions',
		array(
			'per_page'      => $per_page,
			'review_status' => $review_status,
		)
	);
}

/**
 * Get taxonomy terms for event form controls.
 *
 * @param string $taxonomy Taxonomy key.
 *
 * @return array
 */
function get_taxonomy_term_options( string $taxonomy ): array {
	$terms = get_terms(
		array(
			'hide_empty' => false,
			'taxonomy'   => $taxonomy,
		)
	);

	if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
		return array();
	}

	return $terms;
}

/**
 * Render a taxonomy filter select control.
 *
 * @param string $name        Select field name.
 * @param string $label       Accessible label.
 * @param string $placeholder First option label.
 * @param array  $terms       Taxonomy terms.
 * @param string $action      Interactivity action name.
 */
function render_taxonomy_filter_select( string $name, string $label, string $placeholder, array $terms, string $action ): void {
	if ( ! $terms ) {
		return;
	}
	?>
	<label>
		<span class="screen-reader-text"><?php echo esc_html( $label ); ?></span>
		<select class="wporg-events-input" name="<?php echo esc_attr( $name ); ?>" aria-label="<?php echo esc_attr( $label ); ?>" data-wp-on--change="<?php echo esc_attr( $action ); ?>">
			<option value=""><?php echo esc_html( $placeholder ); ?></option>
			<?php foreach ( $terms as $term ) : ?>
				<?php if ( $term instanceof \WP_Term ) : ?>
					<option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
				<?php endif; ?>
			<?php endforeach; ?>
		</select>
	</label>
	<?php
}

/**
 * Render organizer-editable RSVP question definition fields.
 *
 * @param array $questions Existing question definitions.
 */
function render_rsvp_question_definition_fields( array $questions = array() ): void {
	$questions = array_values(
		array_filter(
			$questions,
			static function ( $question ): bool {
				return is_array( $question );
			}
		)
	);
	$row_count = min( 10, max( 5, count( $questions ) + 1 ) );
	$types     = array(
		'text'     => __( 'Short answer', 'wporg' ),
		'textarea' => __( 'Long answer', 'wporg' ),
		'select'   => __( 'Multiple choice', 'wporg' ),
	);
	?>
	<fieldset class="wporg-events-fieldset">
		<legend><?php esc_html_e( 'RSVP questions', 'wporg' ); ?></legend>
		<?php for ( $index = 0; $index < $row_count; ++$index ) : ?>
			<?php
			$question      = is_array( $questions[ $index ] ?? null ) ? $questions[ $index ] : array();
			$question_id   = sanitize_key( (string) ( $question['id'] ?? '' ) );
			$question_type = (string) ( $question['type'] ?? 'text' );
			$choices       = is_array( $question['choices'] ?? null ) ? $question['choices'] : array();

			if ( ! array_key_exists( $question_type, $types ) ) {
				$question_type = 'text';
			}
			?>
			<div class="wporg-events-rsvp-question">
				<label>
					<span>
						<?php
							printf(
								/* translators: %d: RSVP question number. */
								esc_html__( 'Question %d', 'wporg' ),
								esc_html( (string) ( $index + 1 ) )
							);
						?>
					</span>
					<input class="wporg-events-input" type="text" name="rsvp_questions[<?php echo esc_attr( (string) $index ); ?>][label]" value="<?php echo esc_attr( (string) ( $question['label'] ?? '' ) ); ?>" />
				</label>
				<input type="hidden" name="rsvp_questions[<?php echo esc_attr( (string) $index ); ?>][id]" value="<?php echo esc_attr( $question_id ); ?>" />
				<label>
					<span><?php esc_html_e( 'Answer type', 'wporg' ); ?></span>
					<select class="wporg-events-input" name="rsvp_questions[<?php echo esc_attr( (string) $index ); ?>][type]">
						<?php foreach ( $types as $type => $label ) : ?>
							<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $type, $question_type ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Choices', 'wporg' ); ?></span>
					<textarea class="wporg-events-input" name="rsvp_questions[<?php echo esc_attr( (string) $index ); ?>][choices]" rows="3" placeholder="<?php esc_attr_e( 'One choice per line', 'wporg' ); ?>"><?php echo esc_textarea( implode( "\n", array_map( 'strval', $choices ) ) ); ?></textarea>
				</label>
				<label class="wporg-events-checkbox-label">
					<input type="checkbox" name="rsvp_questions[<?php echo esc_attr( (string) $index ); ?>][required]" value="1" <?php checked( ! empty( $question['required'] ) ); ?> />
					<span><?php esc_html_e( 'Required', 'wporg' ); ?></span>
				</label>
			</div>
		<?php endfor; ?>
	</fieldset>
	<?php
}

/**
 * Render attendee-facing RSVP answer fields.
 *
 * @param array $questions RSVP question definitions.
 * @param array $answers   Existing RSVP answers keyed by question ID.
 */
function render_rsvp_answer_fields( array $questions, array $answers = array() ): void {
	if ( ! $questions ) {
		return;
	}
	?>
	<fieldset class="wporg-events-fieldset">
		<legend><?php esc_html_e( 'RSVP questions', 'wporg' ); ?></legend>
		<?php foreach ( $questions as $question ) : ?>
			<?php
			if ( ! is_array( $question ) ) {
				continue;
			}

			$question_id       = sanitize_key( (string) ( $question['id'] ?? '' ) );
			$question_label    = (string) ( $question['label'] ?? '' );
			$question_type     = (string) ( $question['type'] ?? 'text' );
			$question_required = ! empty( $question['required'] );
			$question_choices  = is_array( $question['choices'] ?? null ) ? $question['choices'] : array();
			$answer            = (string) ( $answers[ $question_id ] ?? '' );

			if ( '' === $question_id || '' === $question_label ) {
				continue;
			}
			?>
			<label>
				<span>
					<?php echo esc_html( $question_label ); ?>
					<?php if ( $question_required ) : ?>
						<?php esc_html_e( '(required)', 'wporg' ); ?>
					<?php endif; ?>
				</span>
				<?php if ( 'textarea' === $question_type ) : ?>
					<textarea class="wporg-events-input" name="answers[<?php echo esc_attr( $question_id ); ?>]" rows="3" <?php echo $question_required ? 'required' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_textarea( $answer ); ?></textarea>
				<?php elseif ( 'select' === $question_type && $question_choices ) : ?>
					<select class="wporg-events-input" name="answers[<?php echo esc_attr( $question_id ); ?>]" <?php echo $question_required ? 'required' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<option value=""><?php esc_html_e( 'Choose an answer', 'wporg' ); ?></option>
						<?php foreach ( $question_choices as $question_choice ) : ?>
							<?php $choice_label = (string) $question_choice; ?>
							<option value="<?php echo esc_attr( $choice_label ); ?>" <?php selected( $choice_label, $answer ); ?>><?php echo esc_html( $choice_label ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<input class="wporg-events-input" type="text" name="answers[<?php echo esc_attr( $question_id ); ?>]" value="<?php echo esc_attr( $answer ); ?>" <?php echo $question_required ? 'required' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
				<?php endif; ?>
			</label>
		<?php endforeach; ?>
	</fieldset>
	<?php
}

/**
 * Get one public group by ID.
 *
 * @param int $group_id Group post ID.
 *
 * @return array
 */
function get_group( int $group_id ): array {
	return get_rest_data( \WP_REST_Server::READABLE, "/groups/{$group_id}" );
}

/**
 * Get events for one group.
 *
 * @param int $group_id Group post ID.
 *
 * @return array
 */
function get_group_events( int $group_id ): array {
	return get_rest_data(
		\WP_REST_Server::READABLE,
		"/groups/{$group_id}/events",
		array(
			'per_page'  => 8,
			'timeframe' => 'all',
		)
	);
}

/**
 * Get the calendar feed URL for a group.
 *
 * @param int $group_id Group post ID.
 *
 * @return string
 */
function get_group_calendar_url( int $group_id ): string {
	return rest_url( REST_NAMESPACE . "/groups/{$group_id}/calendar.ics" );
}

/**
 * Get public organizers for one group.
 *
 * @param int $group_id Group post ID.
 * @param int $per_page Maximum number of organizers.
 *
 * @return array
 */
function get_group_organizers( int $group_id, int $per_page = 8 ): array {
	return get_rest_data(
		\WP_REST_Server::READABLE,
		"/groups/{$group_id}/organizers",
		array(
			'per_page' => $per_page,
		)
	);
}

/**
 * Get public members for one group.
 *
 * @param int    $group_id Group post ID.
 * @param string $role     Membership role.
 * @param int    $per_page Maximum number of members.
 *
 * @return array
 */
function get_group_members( int $group_id, string $role = 'all', int $per_page = 100 ): array {
	return get_rest_data(
		\WP_REST_Server::READABLE,
		"/groups/{$group_id}/members",
		array(
			'per_page' => $per_page,
			'role'     => $role,
		)
	);
}

/**
 * Get the current user's membership for one group.
 *
 * @param int $group_id Group post ID.
 *
 * @return array
 */
function get_group_membership( int $group_id ): array {
	if ( ! is_user_logged_in() ) {
		return array();
	}

	return get_rest_data( \WP_REST_Server::READABLE, "/groups/{$group_id}/membership" );
}

/**
 * Get one public event by ID.
 *
 * @param int $event_id Event post ID.
 *
 * @return array
 */
function get_event( int $event_id ): array {
	return get_rest_data( \WP_REST_Server::READABLE, "/events/{$event_id}" );
}

/**
 * Get the calendar export URL for an event.
 *
 * @param int $event_id Event post ID.
 *
 * @return string
 */
function get_event_calendar_url( int $event_id ): string {
	return rest_url( REST_NAMESPACE . "/events/{$event_id}/calendar.ics" );
}

/**
 * Get the attendee export URL for an event.
 *
 * @param int $event_id Event post ID.
 *
 * @return string
 */
function get_event_attendee_export_url( int $event_id ): string {
	return wp_nonce_url( rest_url( REST_NAMESPACE . "/events/{$event_id}/attendees.csv" ), 'wp_rest' );
}

/**
 * Get public attendees for one event.
 *
 * @param int    $event_id Event post ID.
 * @param string $status   RSVP status.
 * @param int    $per_page Maximum number of attendees.
 *
 * @return array
 */
function get_event_attendees( int $event_id, string $status = 'attending', int $per_page = 100 ): array {
	return get_rest_data(
		\WP_REST_Server::READABLE,
		"/events/{$event_id}/attendees",
		array(
			'per_page' => $per_page,
			'status'   => $status,
		)
	);
}

/**
 * Get public feedback for one event.
 *
 * @param int $event_id Event post ID.
 * @param int $per_page Maximum number of feedback records.
 *
 * @return array
 */
function get_event_feedback( int $event_id, int $per_page = 20 ): array {
	return get_rest_data(
		\WP_REST_Server::READABLE,
		"/events/{$event_id}/feedback",
		array(
			'per_page' => $per_page,
		)
	);
}

/**
 * Get public hosts from an event REST object.
 *
 * @param array $event Event REST data.
 *
 * @return array
 */
function get_event_hosts( array $event ): array {
	$hosts = is_array( $event['hosts'] ?? null ) ? $event['hosts'] : array();

	if ( $hosts ) {
		return array_values(
			array_filter(
				$hosts,
				static function ( $host ): bool {
					return is_array( $host ) && ! empty( $host['id'] );
				}
			)
		);
	}

	$host = is_array( $event['host'] ?? null ) ? $event['host'] : array();

	return ! empty( $host['id'] ) ? array( $host ) : array();
}

/**
 * Get the current user's RSVP for one event.
 *
 * @param int $event_id Event post ID.
 *
 * @return array
 */
function get_event_rsvp( int $event_id ): array {
	if ( ! $event_id || ! is_user_logged_in() ) {
		return array();
	}

	return get_rest_data( \WP_REST_Server::READABLE, "/events/{$event_id}/rsvp" );
}

/**
 * Get one public venue by ID.
 *
 * @param int $venue_id Venue post ID.
 *
 * @return array
 */
function get_venue( int $venue_id ): array {
	return get_rest_data( \WP_REST_Server::READABLE, "/venues/{$venue_id}" );
}

/**
 * Get current-user memberships for display.
 *
 * @return array
 */
function get_current_user_memberships(): array {
	if ( ! is_user_logged_in() ) {
		return array();
	}

	return get_rest_data(
		\WP_REST_Server::READABLE,
		'/me/memberships',
		array(
			'per_page' => 8,
		)
	);
}

/**
 * Get current-user RSVPs for display.
 *
 * @return array
 */
function get_current_user_rsvps(): array {
	if ( ! is_user_logged_in() ) {
		return array();
	}

	return get_rest_data(
		\WP_REST_Server::READABLE,
		'/me/rsvps',
		array(
			'per_page' => 8,
		)
	);
}

/**
 * Get current-user group suggestions for display.
 *
 * @return array
 */
function get_current_user_group_suggestions(): array {
	if ( ! is_user_logged_in() ) {
		return array();
	}

	return get_rest_data(
		\WP_REST_Server::READABLE,
		'/me/group-suggestions',
		array(
			'per_page'      => 8,
			'review_status' => 'all',
		)
	);
}

/**
 * Get the current user's RSVPs keyed by event ID.
 *
 * @return array
 */
function get_current_user_rsvp_map(): array {
	if ( ! is_user_logged_in() ) {
		return array();
	}

	$rsvps = get_rest_data(
		\WP_REST_Server::READABLE,
		'/me/rsvps',
		array(
			'per_page'  => 100,
			'status'    => 'all',
			'timeframe' => 'all',
		)
	);
	$map   = array();

	foreach ( $rsvps as $rsvp ) {
		if ( ! is_array( $rsvp ) ) {
			continue;
		}

		$event_id = (int) ( $rsvp['event_id'] ?? 0 );

		if ( $event_id ) {
			$map[ $event_id ] = $rsvp;
		}
	}

	return $map;
}

/**
 * Get Interactivity API context for an event RSVP button.
 *
 * @param int    $event_id   Event post ID.
 * @param string $join_label Button label for creating an RSVP.
 * @param array  $rsvp       Current-user RSVP data.
 * @param array  $event      Event REST data.
 *
 * @return array
 */
function get_event_rsvp_context( int $event_id, string $join_label, array $rsvp = array(), array $event = array() ): array {
	$status      = (string) ( $rsvp['status'] ?? '' );
	$is_canceled = is_event_canceled( $event );
	$is_rsvped   = is_active_rsvp_status( $status );
	$visibility  = (string) ( $rsvp['visibility'] ?? 'public' );

	if ( ! in_array( $visibility, array( 'public', 'private' ), true ) ) {
		$visibility = 'public';
	}

	return array(
		'eventId'         => $event_id,
		'isEventCanceled' => $is_canceled,
		'isEventRsvped'   => $is_rsvped,
		'rsvpBusy'        => false,
		'rsvpButton'      => $is_canceled ? __( 'Event canceled', 'wporg' ) : ( $is_rsvped ? __( 'Cancel RSVP', 'wporg' ) : $join_label ),
		'rsvpGuestCount'  => max( 0, (int) ( $rsvp['guest_count'] ?? 0 ) ),
		'rsvpId'          => (int) ( $rsvp['id'] ?? 0 ),
		'rsvpJoinLabel'   => $join_label,
		'rsvpMessage'     => $is_canceled ? __( 'This event has been canceled.', 'wporg' ) : '',
		'rsvpSaveButton'  => $is_canceled ? __( 'Event canceled', 'wporg' ) : ( $is_rsvped ? __( 'Save RSVP', 'wporg' ) : $join_label ),
		'rsvpStatus'      => $status,
		'rsvpStatusLabel' => get_rsvp_status_label( $status ),
		'rsvpVisibility'  => $visibility,
	);
}

/**
 * Get Interactivity API context for an event feedback form.
 *
 * @param int   $event_id Event post ID.
 * @param array $event    Event REST data.
 * @param array $rsvp     Current-user RSVP data.
 * @param array $feedback Public feedback records.
 *
 * @return array
 */
function get_event_feedback_context( int $event_id, array $event = array(), array $rsvp = array(), array $feedback = array() ): array {
	$has_feedback = false;
	$user_id      = get_current_user_id();

	foreach ( $feedback as $feedback_item ) {
		if ( is_array( $feedback_item ) && $user_id && (int) ( $feedback_item['user_id'] ?? 0 ) === $user_id ) {
			$has_feedback = true;
			break;
		}
	}

	$is_past_event = is_past_event( $event );
	$is_attendee   = 'attending' === (string) ( $rsvp['status'] ?? '' );

	return array(
		'eventId'                 => $event_id,
		'feedbackBusy'            => false,
		'feedbackButton'          => __( 'Share feedback', 'wporg' ),
		'feedbackMessage'         => $has_feedback ? __( 'Feedback shared. Thank you.', 'wporg' ) : '',
		'feedbackSubmitted'       => $has_feedback,
		'isFeedbackFormAvailable' => is_user_logged_in() && $is_past_event && $is_attendee && ! $has_feedback && ! is_event_canceled( $event ),
	);
}

/**
 * Get Interactivity API context for a group membership button.
 *
 * @param int   $group_id   Group post ID.
 * @param array $membership Current-user membership data.
 *
 * @return array
 */
function get_group_membership_context( int $group_id, array $membership = array() ): array {
	$status                   = (string) ( $membership['status'] ?? '' );
	$is_member                = is_active_membership_status( $status );
	$notification_preferences = get_membership_notification_preferences_context( $membership );
	$visibility               = (string) ( $membership['visibility'] ?? 'public' );

	if ( ! in_array( $visibility, array( 'public', 'private' ), true ) ) {
		$visibility = 'public';
	}

	return array(
		'groupId'                           => $group_id,
		'isGroupMember'                     => $is_member,
		'membershipBusy'                    => false,
		'membershipButton'                  => $is_member ? __( 'Leave group', 'wporg' ) : __( 'Join group', 'wporg' ),
		'membershipId'                      => (int) ( $membership['id'] ?? 0 ),
		'membershipMessage'                 => '',
		'membershipNotificationBusy'        => false,
		'membershipNotificationButton'      => __( 'Save settings', 'wporg' ),
		'membershipNotificationMessage'     => '',
		'membershipNotificationPreferences' => $notification_preferences,
		'membershipRole'                    => (string) ( $membership['role'] ?? '' ),
		'membershipRoleLabel'               => get_membership_role_label( (string) ( $membership['role'] ?? '' ) ),
		'membershipStatus'                  => $status,
		'membershipStatusLabel'             => get_membership_status_label( $status ),
		'membershipVisibility'              => $visibility,
	);
}

/**
 * Render current-user group membership settings.
 *
 * @param array $membership_context Interactivity API context for a membership.
 */
function render_membership_settings_form( array $membership_context ): void {
	$preferences = array_merge(
		get_membership_notification_preferences_context( array() ),
		is_array( $membership_context['membershipNotificationPreferences'] ?? null ) ? $membership_context['membershipNotificationPreferences'] : array()
	);
	$visibility  = (string) ( $membership_context['membershipVisibility'] ?? 'public' );

	if ( ! in_array( $visibility, array( 'public', 'private' ), true ) ) {
		$visibility = 'public';
	}
	?>
	<form class="wporg-events-notifications" data-wp-on--submit="actions.saveMembershipNotifications" data-wp-bind--hidden="state.isMembershipNotificationFormHidden">
		<fieldset data-wp-bind--disabled="context.membershipNotificationBusy">
			<legend><?php esc_html_e( 'Membership settings', 'wporg' ); ?></legend>
			<label class="wporg-events-notifications__field">
				<span><?php esc_html_e( 'Profile visibility', 'wporg' ); ?></span>
				<select class="wporg-events-input" name="visibility" data-wp-on--change="actions.updateMembershipVisibility">
					<option value="public" <?php selected( 'public', $visibility ); ?>><?php esc_html_e( 'Show my profile', 'wporg' ); ?></option>
					<option value="private" <?php selected( 'private', $visibility ); ?>><?php esc_html_e( 'Keep my profile private', 'wporg' ); ?></option>
				</select>
			</label>
		</fieldset>
		<fieldset data-wp-bind--disabled="context.membershipNotificationBusy">
			<legend><?php esc_html_e( 'Email notifications', 'wporg' ); ?></legend>
			<label>
				<input type="checkbox" name="new_events" <?php checked( true, (bool) $preferences['new_events'] ); ?> data-wp-on--change="actions.updateMembershipNotificationPreference" data-wp-bind--checked="state.isNewEventsNotificationChecked" />
				<span><?php esc_html_e( 'New events', 'wporg' ); ?></span>
			</label>
			<label>
				<input type="checkbox" name="event_updates" <?php checked( true, (bool) $preferences['event_updates'] ); ?> data-wp-on--change="actions.updateMembershipNotificationPreference" data-wp-bind--checked="state.isEventUpdatesNotificationChecked" />
				<span><?php esc_html_e( 'Event changes', 'wporg' ); ?></span>
			</label>
			<label>
				<input type="checkbox" name="event_cancellations" <?php checked( true, (bool) $preferences['event_cancellations'] ); ?> data-wp-on--change="actions.updateMembershipNotificationPreference" data-wp-bind--checked="state.isEventCancellationsNotificationChecked" />
				<span><?php esc_html_e( 'Event cancellations', 'wporg' ); ?></span>
			</label>
		</fieldset>
		<button class="wporg-events-button wporg-events-button--secondary" type="submit" data-wp-bind--disabled="context.membershipNotificationBusy" data-wp-text="context.membershipNotificationButton">
			<?php echo esc_html( (string) ( $membership_context['membershipNotificationButton'] ?? __( 'Save settings', 'wporg' ) ) ); ?>
		</button>
		<p class="wporg-events-status" data-wp-text="context.membershipNotificationMessage"></p>
	</form>
	<?php
}

/**
 * Get normalized notification preferences for Interactivity API context.
 *
 * @param array $membership Current-user membership data.
 *
 * @return array
 */
function get_membership_notification_preferences_context( array $membership ): array {
	$preferences = is_array( $membership['notification_preferences'] ?? null ) ? $membership['notification_preferences'] : array();
	$defaults    = array(
		'new_events'          => true,
		'event_updates'       => true,
		'event_cancellations' => true,
	);

	return array_merge( $defaults, array_intersect_key( $preferences, $defaults ) );
}

/**
 * Get Interactivity API context for a group event creation form.
 *
 * @param int   $group_id   Group post ID.
 * @param array $membership Current-user membership data.
 *
 * @return array
 */
function get_group_event_form_context( int $group_id, array $membership = array() ): array {
	$can_create_events = can_manage_group_events( $membership );

	return array(
		'eventFormBusy'    => false,
		'eventFormButton'  => __( 'Create event', 'wporg' ),
		'eventFormMessage' => '',
		'groupId'          => $group_id,
		'canCreateEvents'  => $can_create_events,
		'isGroupMember'    => is_active_membership_status( (string) ( $membership['status'] ?? '' ) ),
	);
}

/**
 * Get Interactivity API context for the group suggestion form.
 *
 * @return array
 */
function get_group_suggestion_form_context(): array {
	return array(
		'groupSuggestionBusy'      => false,
		'groupSuggestionButton'    => __( 'Suggest group', 'wporg' ),
		'groupSuggestionMessage'   => '',
		'groupSuggestionOpen'      => false,
		'groupSuggestionSubmitted' => false,
	);
}

/**
 * Determine whether the current user can review group suggestions.
 *
 * @return bool
 */
function can_review_group_suggestions(): bool {
	return \WordPressdotorg\Community_Events\current_user_can_moderate_group_suggestions();
}

/**
 * Get Interactivity API context for a group suggestion review item.
 *
 * @param array $suggestion Group suggestion REST data.
 *
 * @return array
 */
function get_group_suggestion_review_context( array $suggestion ): array {
	$review_status = (string) ( $suggestion['review_status'] ?? 'pending' );

	return array(
		'groupSuggestionCreatedGroupId'    => (int) ( $suggestion['created_group_id'] ?? 0 ),
		'groupSuggestionId'                => (int) ( $suggestion['id'] ?? 0 ),
		'groupSuggestionReviewBusy'        => false,
		'groupSuggestionReviewButton'      => __( 'Save review', 'wporg' ),
		'groupSuggestionReviewMessage'     => '',
		'groupSuggestionReviewStatus'      => $review_status,
		'groupSuggestionReviewStatusLabel' => get_group_suggestion_review_status_label( $review_status ),
	);
}

/**
 * Get a group suggestion review status label.
 *
 * @param string $review_status Review status key.
 *
 * @return string
 */
function get_group_suggestion_review_status_label( string $review_status ): string {
	switch ( $review_status ) {
		case 'approved':
			return __( 'Approved', 'wporg' );
		case 'declined':
			return __( 'Declined', 'wporg' );
		case 'needs_info':
			return __( 'Needs more information', 'wporg' );
		case 'pending':
			return __( 'Pending review', 'wporg' );
		default:
			return __( 'Unknown', 'wporg' );
	}
}

/**
 * Determine whether a membership can manage group events.
 *
 * @param array $membership Current-user membership data.
 *
 * @return bool
 */
function can_manage_group_events( array $membership ): bool {
	return is_active_membership_status( (string) ( $membership['status'] ?? '' ) )
		&& in_array( (string) ( $membership['role'] ?? '' ), array( 'organizer', 'host' ), true );
}

/**
 * Determine whether the current user can manage group organizer teams.
 *
 * @param array $membership Current-user membership data.
 *
 * @return bool
 */
function can_manage_group_organizers( array $membership ): bool {
	return current_user_can( 'manage_options' ) || (
		is_active_membership_status( (string) ( $membership['status'] ?? '' ) )
		&& 'organizer' === (string) ( $membership['role'] ?? '' )
	);
}

/**
 * Determine whether the current user can manage an event.
 *
 * @param array $event      Event REST data.
 * @param array $membership Current-user group membership data.
 *
 * @return bool
 */
function can_manage_event( array $event, array $membership ): bool {
	if ( can_manage_group_events( $membership ) ) {
		return true;
	}

	if ( ! is_active_membership_status( (string) ( $membership['status'] ?? '' ) ) ) {
		return false;
	}

	return in_array( get_current_user_id(), array_map( 'intval', (array) ( $event['host_user_ids'] ?? array() ) ), true );
}

/**
 * Get Interactivity API context for organizer event management.
 *
 * @param array $event Event REST data.
 *
 * @return array
 */
function get_event_management_context( array $event ): array {
	$approval_status = (string) ( $event['approval_status'] ?? '' );

	return array(
		'eventAttendeeAddBusy'    => false,
		'eventAttendeeAddButton'  => __( 'Add attendee', 'wporg' ),
		'eventAttendeeAddMessage' => '',
		'eventCancelBusy'         => false,
		'eventCancelButton'       => __( 'Cancel event', 'wporg' ),
		'eventCancelMessage'      => '',
		'eventCanceled'           => is_event_canceled( $event ),
		'eventCopyBusy'           => false,
		'eventCopyButton'         => __( 'Create copy', 'wporg' ),
		'eventCopyMessage'        => '',
		'eventCopyUrl'            => '',
		'eventId'                 => (int) ( $event['id'] ?? 0 ),
		'eventManageBusy'         => false,
		'eventManageButton'       => __( 'Save event', 'wporg' ),
		'eventManageMessage'      => '',
		'eventMessageBusy'        => false,
		'eventMessageButton'      => __( 'Send message', 'wporg' ),
		'eventMessageStatus'      => '',
		'eventStatusLabel'        => get_event_approval_status_label( $approval_status ),
	);
}

/**
 * Determine whether an RSVP status represents active attendance.
 *
 * @param string $status RSVP status slug.
 *
 * @return bool
 */
function is_active_rsvp_status( string $status ): bool {
	return in_array( $status, array( 'attending', 'waitlisted' ), true );
}

/**
 * Determine whether a membership status represents active participation.
 *
 * @param string $status Membership status slug.
 *
 * @return bool
 */
function is_active_membership_status( string $status ): bool {
	return 'active' === $status;
}

/**
 * Determine whether an event is canceled.
 *
 * @param array $event Event REST data.
 *
 * @return bool
 */
function is_event_canceled( array $event ): bool {
	return 'canceled' === (string) ( $event['approval_status'] ?? '' );
}

/**
 * Determine whether an event has started or ended in the past.
 *
 * @param array $event Event REST data.
 *
 * @return bool
 */
function is_past_event( array $event ): bool {
	$date = (string) ( $event['end_utc'] ?? '' );

	if ( '' === $date ) {
		$date = (string) ( $event['start_utc'] ?? '' );
	}

	$timestamp = strtotime( $date );

	return false !== $timestamp && $timestamp < time();
}

/**
 * Get a translated RSVP status label.
 *
 * @param string $status RSVP status slug.
 *
 * @return string
 */
function get_rsvp_status_label( string $status ): string {
	switch ( $status ) {
		case 'attending':
			return __( 'Attending', 'wporg' );
		case 'not_attending':
			return __( 'Not attending', 'wporg' );
		case 'waitlisted':
			return __( 'Waitlisted', 'wporg' );
		default:
			return '' === $status ? '' : __( 'Unknown', 'wporg' );
	}
}

/**
 * Get a translated attendance status label.
 *
 * @param string $status Attendance status slug.
 *
 * @return string
 */
function get_attendance_status_label( string $status ): string {
	switch ( $status ) {
		case 'checked_in':
			return __( 'Checked in', 'wporg' );
		case 'no_show':
			return __( 'No show', 'wporg' );
		case 'not_checked_in':
			return __( 'Not checked in', 'wporg' );
		case 'not_coming':
			return __( 'Not coming', 'wporg' );
		default:
			return '' === $status ? '' : __( 'Unknown', 'wporg' );
	}
}

/**
 * Get a translated membership role label.
 *
 * @param string $role Membership role slug.
 *
 * @return string
 */
function get_membership_role_label( string $role ): string {
	switch ( $role ) {
		case 'host':
			return __( 'Host', 'wporg' );
		case 'member':
			return __( 'Member', 'wporg' );
		case 'organizer':
			return __( 'Organizer', 'wporg' );
		default:
			return '' === $role ? '' : __( 'Unknown', 'wporg' );
	}
}

/**
 * Get a translated membership status label.
 *
 * @param string $status Membership status slug.
 *
 * @return string
 */
function get_membership_status_label( string $status ): string {
	switch ( $status ) {
		case 'active':
			return __( 'Active', 'wporg' );
		case 'left':
			return __( 'Left', 'wporg' );
		case 'pending':
			return __( 'Pending', 'wporg' );
		default:
			return '' === $status ? '' : __( 'Unknown', 'wporg' );
	}
}

/**
 * Get a translated event approval status label.
 *
 * @param string $status Event approval status slug.
 *
 * @return string
 */
function get_event_approval_status_label( string $status ): string {
	switch ( $status ) {
		case 'approved':
			return __( 'Approved', 'wporg' );
		case 'canceled':
			return __( 'Canceled', 'wporg' );
		default:
			return '' === $status ? '' : __( 'Unknown', 'wporg' );
	}
}

/**
 * Get a compact event start label.
 *
 * @param string $start_utc Event start time in UTC.
 *
 * @return string
 */
function get_event_start_label( string $start_utc ): string {
	$timestamp = strtotime( $start_utc );

	if ( false === $timestamp ) {
		return '';
	}

	return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
}

/**
 * Get an event start timestamp for Interactivity API filters.
 *
 * @param string $start_utc Event start time in UTC.
 *
 * @return int
 */
function get_event_start_timestamp( string $start_utc ): int {
	$timestamp = strtotime( $start_utc );

	return false === $timestamp ? 0 : $timestamp;
}

/**
 * Get an ISO 8601 datetime for schema.org data.
 *
 * @param string $utc_date Date-time in UTC.
 *
 * @return string
 */
function get_schema_datetime( string $utc_date ): string {
	$timestamp = strtotime( $utc_date );

	if ( false === $timestamp ) {
		return '';
	}

	return gmdate( 'c', $timestamp );
}

/**
 * Render schema.org JSON-LD for a public event page.
 *
 * @param array $event Event REST data.
 */
function render_event_structured_data( array $event ): void {
	$title      = trim( (string) ( $event['title'] ?? '' ) );
	$start_date = get_schema_datetime( (string) ( $event['start_utc'] ?? '' ) );

	if ( '' === $title || '' === $start_date ) {
		return;
	}

	$venue      = is_array( $event['venue'] ?? null ) ? $event['venue'] : array();
	$online_url = (string) ( $event['online_url'] ?? '' );

	if ( '' === $online_url && ! empty( $venue['online_url'] ) ) {
		$online_url = (string) $venue['online_url'];
	}

	$locations          = array();
	$has_venue_location = ! empty( $venue['title'] );

	if ( $has_venue_location ) {
		$countries      = is_array( $venue['taxonomies']['countries'] ?? null ) ? $venue['taxonomies']['countries'] : array();
		$country        = is_array( $countries[0] ?? null ) ? (string) ( $countries[0]['name'] ?? '' ) : '';
		$address_fields = array_filter(
			array(
				'streetAddress'   => (string) ( $venue['address'] ?? '' ),
				'addressLocality' => (string) ( $venue['city'] ?? '' ),
				'addressRegion'   => (string) ( $venue['region'] ?? '' ),
				'postalCode'      => (string) ( $venue['postal_code'] ?? '' ),
				'addressCountry'  => $country,
			)
		);
		$place          = array(
			'@type' => 'Place',
			'name'  => (string) $venue['title'],
			'url'   => (string) ( $venue['url'] ?? $venue['link'] ?? '' ),
		);
		$coordinates    = get_venue_coordinates( $venue );

		if ( $address_fields ) {
			$place['address'] = array_merge(
				array(
					'@type' => 'PostalAddress',
				),
				$address_fields
			);
		}

		if ( $coordinates ) {
			$place['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => $coordinates['latitude'],
				'longitude' => $coordinates['longitude'],
			);
		}

		$locations[] = array_filter(
			$place,
			static function ( $value ): bool {
				return '' !== $value && array() !== $value;
			}
		);
	}

	if ( '' !== $online_url ) {
		$locations[] = array(
			'@type' => 'VirtualLocation',
			'url'   => $online_url,
		);
	}

	$hosts = array();

	foreach ( get_event_hosts( $event ) as $host ) {
		if ( empty( $host['name'] ) ) {
			continue;
		}

		$hosts[] = array_filter(
			array(
				'@type' => 'Person',
				'name'  => (string) $host['name'],
				'url'   => (string) ( $host['profile_url'] ?? '' ),
			)
		);
	}

	$description_source = (string) ( $event['excerpt'] ?? '' );

	if ( '' === $description_source ) {
		$description_source = (string) ( $event['description'] ?? '' );
	}

	$attendance_mode = 'https://schema.org/OfflineEventAttendanceMode';

	if ( '' !== $online_url && $has_venue_location ) {
		$attendance_mode = 'https://schema.org/MixedEventAttendanceMode';
	} elseif ( '' !== $online_url ) {
		$attendance_mode = 'https://schema.org/OnlineEventAttendanceMode';
	}

	$event_url = (string) ( $event['link'] ?? '' );

	if ( '' === $event_url && ! empty( $event['id'] ) ) {
		$event_url = (string) get_permalink( (int) $event['id'] );
	}

	$data = array_filter(
		array(
			'@context'                => 'https://schema.org',
			'@type'                   => 'Event',
			'@id'                     => $event_url ? $event_url . '#event' : '',
			'name'                    => $title,
			'description'             => trim( wp_strip_all_tags( $description_source ) ),
			'url'                     => $event_url,
			'startDate'               => $start_date,
			'endDate'                 => get_schema_datetime( (string) ( $event['end_utc'] ?? '' ) ),
			'eventStatus'             => is_event_canceled( $event ) ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled',
			'eventAttendanceMode'     => $attendance_mode,
			'isAccessibleForFree'     => true,
			'location'                => 1 === count( $locations ) ? $locations[0] : $locations,
			'organizer'               => 1 === count( $hosts ) ? $hosts[0] : $hosts,
			'maximumAttendeeCapacity' => ! empty( $event['capacity'] ) ? (int) $event['capacity'] : null,
		),
		static function ( $value ): bool {
			return null !== $value && '' !== $value && array() !== $value;
		}
	);

	if ( ! $data ) {
		return;
	}
	?>
	<script type="application/ld+json">
		<?php echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</script>
	<?php
}

/**
 * Get a datetime-local input value for an event date.
 *
 * @param string $utc_date Date-time in UTC.
 *
 * @return string
 */
function get_datetime_local_value( string $utc_date ): string {
	$timestamp = strtotime( $utc_date );

	if ( false === $timestamp ) {
		return '';
	}

	return wp_date( 'Y-m-d\TH:i', $timestamp );
}

/**
 * Get a group location label.
 *
 * @param array $group Group REST data.
 *
 * @return string
 */
function get_group_location_label( array $group ): string {
	if ( ! empty( $group['location_label'] ) ) {
		return (string) $group['location_label'];
	}

	return implode(
		', ',
		array_filter(
			array(
				$group['city'] ?? '',
				$group['region'] ?? '',
			)
		)
	);
}

/**
 * Get an event location label.
 *
 * @param array $event Event REST data.
 *
 * @return string
 */
function get_event_location_label( array $event ): string {
	$venue = is_array( $event['venue'] ?? null ) ? $event['venue'] : array();

	if ( ! empty( $venue['title'] ) ) {
		return (string) $venue['title'];
	}

	return ! empty( $event['online_url'] ) ? __( 'Online', 'wporg' ) : '';
}

/**
 * Get event taxonomy term names from an event REST object.
 *
 * @param array  $event Event REST data.
 * @param string $key   Taxonomy response key.
 *
 * @return array
 */
function get_event_term_names( array $event, string $key ): array {
	$taxonomies = is_array( $event['taxonomies'] ?? null ) ? $event['taxonomies'] : array();
	$terms      = is_array( $taxonomies[ $key ] ?? null ) ? $taxonomies[ $key ] : array();
	$names      = array();

	foreach ( $terms as $term ) {
		if ( is_array( $term ) && ! empty( $term['name'] ) ) {
			$names[] = (string) $term['name'];
		}
	}

	return $names;
}

/**
 * Get event taxonomy term slugs from an event REST object.
 *
 * @param array  $event Event REST data.
 * @param string $key   Taxonomy response key.
 *
 * @return array
 */
function get_event_term_slugs( array $event, string $key ): array {
	$taxonomies = is_array( $event['taxonomies'] ?? null ) ? $event['taxonomies'] : array();
	$terms      = is_array( $taxonomies[ $key ] ?? null ) ? $taxonomies[ $key ] : array();
	$slugs      = array();

	foreach ( $terms as $term ) {
		if ( is_array( $term ) && ! empty( $term['slug'] ) ) {
			$slugs[] = (string) $term['slug'];
		}
	}

	return $slugs;
}

/**
 * Get taxonomy term names from a group REST object.
 *
 * @param array  $group Group REST data.
 * @param string $key   Taxonomy response key.
 *
 * @return array
 */
function get_group_term_names( array $group, string $key ): array {
	$taxonomies = is_array( $group['taxonomies'] ?? null ) ? $group['taxonomies'] : array();
	$terms      = is_array( $taxonomies[ $key ] ?? null ) ? $taxonomies[ $key ] : array();
	$names      = array();

	foreach ( $terms as $term ) {
		if ( is_array( $term ) && ! empty( $term['name'] ) ) {
			$names[] = (string) $term['name'];
		}
	}

	return $names;
}

/**
 * Get taxonomy term slugs from a group REST object.
 *
 * @param array  $group Group REST data.
 * @param string $key   Taxonomy response key.
 *
 * @return array
 */
function get_group_term_slugs( array $group, string $key ): array {
	$taxonomies = is_array( $group['taxonomies'] ?? null ) ? $group['taxonomies'] : array();
	$terms      = is_array( $taxonomies[ $key ] ?? null ) ? $taxonomies[ $key ] : array();
	$slugs      = array();

	foreach ( $terms as $term ) {
		if ( is_array( $term ) && ! empty( $term['slug'] ) ) {
			$slugs[] = (string) $term['slug'];
		}
	}

	return $slugs;
}

/**
 * Get an event type/format label.
 *
 * @param array $event Event REST data.
 *
 * @return string
 */
function get_event_classification_label( array $event ): string {
	return implode(
		' - ',
		array_filter(
			array(
				implode( ', ', get_event_term_names( $event, 'event_types' ) ),
				implode( ', ', get_event_term_names( $event, 'event_formats' ) ),
			)
		)
	);
}

/**
 * Get searchable event taxonomy text.
 *
 * @param array $event Event REST data.
 *
 * @return string
 */
function get_event_taxonomy_search_text( array $event ): string {
	return implode(
		' ',
		array_merge(
			get_event_term_names( $event, 'countries' ),
			get_event_term_names( $event, 'event_formats' ),
			get_event_term_names( $event, 'event_types' ),
			get_event_term_names( $event, 'languages' ),
			get_event_term_names( $event, 'topics' )
		)
	);
}

/**
 * Get searchable group taxonomy text.
 *
 * @param array $group Group REST data.
 *
 * @return string
 */
function get_group_taxonomy_search_text( array $group ): string {
	return implode(
		' ',
		array_merge(
			get_group_term_names( $group, 'countries' ),
			get_group_term_names( $group, 'group_types' ),
			get_group_term_names( $group, 'languages' ),
			get_group_term_names( $group, 'topics' )
		)
	);
}

/**
 * Get a venue location label.
 *
 * @param array $venue Venue REST data.
 *
 * @return string
 */
function get_venue_location_label( array $venue ): string {
	return implode(
		', ',
		array_filter(
			array(
				$venue['city'] ?? '',
				$venue['region'] ?? '',
			)
		)
	);
}

/**
 * Get a venue address label.
 *
 * @param array $venue Venue REST data.
 *
 * @return string
 */
function get_venue_address_label( array $venue ): string {
	return implode(
		' ',
		array_filter(
			array(
				$venue['address'] ?? '',
				$venue['postal_code'] ?? '',
				$venue['city'] ?? '',
			)
		)
	);
}

/**
 * Get normalized venue coordinates.
 *
 * @param array $venue Venue REST data.
 *
 * @return array
 */
function get_venue_coordinates( array $venue ): array {
	$latitude  = $venue['latitude'] ?? null;
	$longitude = $venue['longitude'] ?? null;

	if ( ! is_numeric( $latitude ) || ! is_numeric( $longitude ) ) {
		return array();
	}

	$latitude  = (float) $latitude;
	$longitude = (float) $longitude;

	if (
		( 0.0 === $latitude && 0.0 === $longitude )
		|| -90 > $latitude
		|| 90 < $latitude
		|| -180 > $longitude
		|| 180 < $longitude
	) {
		return array();
	}

	return array(
		'latitude'  => $latitude,
		'longitude' => $longitude,
	);
}

/**
 * Format a coordinate for map URLs.
 *
 * @param float $coordinate Latitude or longitude.
 *
 * @return string
 */
function format_map_coordinate( float $coordinate ): string {
	return rtrim( rtrim( sprintf( '%.6F', $coordinate ), '0' ), '.' );
}

/**
 * Get an OpenStreetMap embed URL for a venue.
 *
 * @param array $venue Venue REST data.
 *
 * @return string
 */
function get_venue_map_embed_url( array $venue ): string {
	$coordinates = get_venue_coordinates( $venue );

	if ( ! $coordinates ) {
		return '';
	}

	$latitude  = $coordinates['latitude'];
	$longitude = $coordinates['longitude'];
	$bbox      = array(
		max( -180, $longitude - 0.015 ),
		max( -90, $latitude - 0.01 ),
		min( 180, $longitude + 0.015 ),
		min( 90, $latitude + 0.01 ),
	);

	return add_query_arg(
		array(
			'bbox'   => implode( ',', array_map( __NAMESPACE__ . '\format_map_coordinate', $bbox ) ),
			'layer'  => 'mapnik',
			'marker' => format_map_coordinate( $latitude ) . ',' . format_map_coordinate( $longitude ),
		),
		'https://www.openstreetmap.org/export/embed.html'
	);
}

/**
 * Get an OpenStreetMap URL for a venue.
 *
 * @param array $venue Venue REST data.
 *
 * @return string
 */
function get_venue_external_map_url( array $venue ): string {
	$coordinates = get_venue_coordinates( $venue );

	if ( ! $coordinates ) {
		return '';
	}

	$latitude  = format_map_coordinate( $coordinates['latitude'] );
	$longitude = format_map_coordinate( $coordinates['longitude'] );

	return sprintf(
		'https://www.openstreetmap.org/?mlat=%1$s&mlon=%2$s#map=16/%1$s/%2$s',
		rawurlencode( $latitude ),
		rawurlencode( $longitude )
	);
}

/**
 * Get plain text for client-side search.
 *
 * @param array $parts Text fragments.
 *
 * @return string
 */
function get_search_text( array $parts ): string {
	return strtolower( wp_strip_all_tags( implode( ' ', array_filter( array_map( 'strval', $parts ) ) ) ) );
}

/**
 * Echo an Interactivity API context attribute.
 *
 * @param array $context Context data.
 */
function context_attribute( array $context ): void {
	echo wp_interactivity_data_wp_context( $context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core helper escapes JSON attribute data.
}
