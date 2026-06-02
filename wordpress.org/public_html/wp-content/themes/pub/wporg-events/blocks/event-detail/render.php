<?php
/**
 * Server render callback for the Event Detail block.
 *
 * @package WordPressdotorg\Events_Theme
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Events_Theme;

configure_interactivity_store();

$event_id         = get_the_ID();
$event            = $event_id ? get_event( $event_id ) : array();
$location         = $event ? get_event_location_label( $event ) : '';
$hosts            = $event ? get_event_hosts( $event ) : array();
$venue            = is_array( $event['venue'] ?? null ) ? $event['venue'] : array();
$map_embed_url    = $venue ? get_venue_map_embed_url( $venue ) : '';
$map_external_url = $venue ? get_venue_external_map_url( $venue ) : '';
$map_title        = $venue ? sprintf(
	/* translators: %s: venue name. */
	__( 'Map showing %s', 'wporg' ),
	(string) ( $venue['title'] ?? __( 'venue location', 'wporg' ) )
) : '';
$rest_id             = (int) ( $event['id'] ?? 0 );
$event_group_id      = (int) ( $event['group_id'] ?? 0 );
$membership          = $event_group_id ? get_group_membership( $event_group_id ) : array();
$can_manage          = $event && can_manage_event( $event, $membership );
$venues              = $can_manage ? get_venues( 100 ) : array();
$organizers          = $can_manage && $event_group_id ? get_group_organizers( $event_group_id, 100 ) : array();
$event_type_terms    = $can_manage ? get_taxonomy_term_options( 'wporg_ce_event_type' ) : array();
$event_format_terms  = $can_manage ? get_taxonomy_term_options( 'wporg_ce_event_format' ) : array();
$event_type_slugs    = get_event_term_slugs( $event, 'event_types' );
$event_format_slugs  = get_event_term_slugs( $event, 'event_formats' );
$host_user_ids       = array_map( 'intval', (array) ( $event['host_user_ids'] ?? array() ) );
$classification      = $event ? get_event_classification_label( $event ) : '';
$is_canceled         = $event && is_event_canceled( $event );
$attendees           = $rest_id ? get_event_attendees( $rest_id, 'attending' ) : array();
$waitlisted          = $rest_id ? get_event_attendees( $rest_id, 'waitlisted' ) : array();
$managed_attendees   = $can_manage ? array_merge( $attendees, $waitlisted ) : array();
$group_members       = $can_manage && $event_group_id ? get_group_members( $event_group_id, 'all', 100 ) : array();
$rsvp                = $rest_id ? get_event_rsvp( $rest_id ) : array();
$rsvp_questions      = array_values(
	array_filter(
		is_array( $event['rsvp_questions'] ?? null ) ? $event['rsvp_questions'] : array(),
		static function ( $question ): bool {
			return is_array( $question ) && ! empty( $question['id'] ) && ! empty( $question['label'] );
		}
	)
);
$rsvp_answers        = is_array( $rsvp['answers'] ?? null ) ? $rsvp['answers'] : array();
$feedback            = $rest_id ? get_event_feedback( $rest_id, 20 ) : array();
$feedback_summary    = is_array( $event['feedback_summary'] ?? null ) ? $event['feedback_summary'] : array();
$context             = get_event_rsvp_context( $rest_id, __( 'Attend this event', 'wporg' ), $rsvp, $event );
$feedback_context    = get_event_feedback_context( $rest_id, $event, $rsvp, $feedback );
$management_context  = $can_manage ? get_event_management_context( $event ) : array();
$managed_user_ids    = array();
$attendee_candidates = array();
$host_options        = array();
$copy_start_value    = '';
$copy_end_value      = '';

foreach ( $managed_attendees as $managed_attendee ) {
	$managed_user_id = (int) ( $managed_attendee['user_id'] ?? 0 );

	if ( $managed_user_id ) {
		$managed_user_ids[] = $managed_user_id;
	}
}

$managed_user_ids = array_values( array_unique( $managed_user_ids ) );

foreach ( $group_members as $group_member ) {
	$group_member_user = is_array( $group_member['user'] ?? null ) ? $group_member['user'] : array();
	$group_member_id   = (int) ( $group_member_user['id'] ?? 0 );

	if ( $group_member_id && ! in_array( $group_member_id, $managed_user_ids, true ) ) {
		$attendee_candidates[] = $group_member;
	}
}

if ( $can_manage && ! empty( $event['start_utc'] ) ) {
	$copy_start_timestamp = strtotime( (string) $event['start_utc'] );

	if ( false !== $copy_start_timestamp ) {
		$copy_start_value = get_datetime_local_value( gmdate( 'Y-m-d H:i:s', $copy_start_timestamp + WEEK_IN_SECONDS ) );
	}
}

if ( $can_manage && ! empty( $event['end_utc'] ) ) {
	$copy_end_timestamp = strtotime( (string) $event['end_utc'] );

	if ( false !== $copy_end_timestamp ) {
		$copy_end_value = get_datetime_local_value( gmdate( 'Y-m-d H:i:s', $copy_end_timestamp + WEEK_IN_SECONDS ) );
	}
}

foreach ( $organizers as $organizer ) {
	$host_option_user = is_array( $organizer['user'] ?? null ) ? $organizer['user'] : array();
	$host_option_id   = (int) ( $host_option_user['id'] ?? 0 );

	if ( $host_option_id ) {
		$host_options[ $host_option_id ] = $host_option_user;
	}
}

foreach ( $hosts as $event_host ) {
	$host_option_id = (int) ( $event_host['id'] ?? 0 );

	if ( $host_option_id ) {
		$host_options[ $host_option_id ] = $event_host;
	}
}

ob_start();
?>
<section <?php echo get_block_wrapper_attributes( array( 'class' => 'wporg-events-detail wporg-events-event-detail' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-wp-interactive="<?php echo esc_attr( STORE_NAMESPACE ); ?>">
		<?php if ( $event ) : ?>
			<?php render_event_structured_data( $event ); ?>
			<h1><?php echo esc_html( (string) ( $event['title'] ?? '' ) ); ?></h1>
			<ul class="wporg-events-meta">
			<?php if ( ! empty( $event['start_utc'] ) ) : ?>
				<li><?php echo esc_html( get_event_start_label( (string) $event['start_utc'] ) ); ?></li>
			<?php endif; ?>
				<?php if ( $location ) : ?>
					<li><?php echo esc_html( $location ); ?></li>
				<?php endif; ?>
				<?php if ( $is_canceled ) : ?>
					<li><?php esc_html_e( 'Canceled', 'wporg' ); ?></li>
				<?php endif; ?>
				<?php if ( $classification ) : ?>
					<li><?php echo esc_html( $classification ); ?></li>
				<?php endif; ?>
				<li>
					<?php
					printf(
					/* translators: %d: number of attendees. */
						esc_html( _n( '%d attendee', '%d attendees', (int) ( $event['event_counts']['attending'] ?? 0 ), 'wporg' ) ),
						(int) ( $event['event_counts']['attending'] ?? 0 )
					);
					?>
				</li>
			</ul>
			<?php if ( $is_canceled ) : ?>
				<p class="wporg-events-empty">
					<?php esc_html_e( 'This event has been canceled.', 'wporg' ); ?>
					<?php if ( ! empty( $event['cancellation_reason'] ) ) : ?>
						<?php echo esc_html( (string) $event['cancellation_reason'] ); ?>
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<div class="wporg-events-detail__body">
				<div>
					<?php if ( ! empty( $event['description'] ) ) : ?>
						<div><?php echo wp_kses_post( wpautop( (string) $event['description'] ) ); ?></div>
					<?php endif; ?>
				</div>

				<div class="wporg-events-panel-stack">
					<aside class="wporg-events-panel" <?php context_attribute( $context ); ?>>
						<h2><?php esc_html_e( 'RSVP', 'wporg' ); ?></h2>
						<form class="wporg-events-form wporg-events-rsvp-form" data-wp-on--submit="actions.saveRsvp" data-wp-bind--hidden="state.isRsvpOptionsHidden">
							<label>
								<span><?php esc_html_e( 'Guests', 'wporg' ); ?></span>
								<input class="wporg-events-input" type="number" name="guest_count" min="0" step="1" value="<?php echo esc_attr( (string) $context['rsvpGuestCount'] ); ?>" data-wp-on--input="actions.updateRsvpGuestCount" />
							</label>
							<label>
								<span><?php esc_html_e( 'Attendee list visibility', 'wporg' ); ?></span>
								<select class="wporg-events-input" name="visibility" data-wp-on--change="actions.updateRsvpVisibility">
									<option value="public" <?php selected( 'public', (string) $context['rsvpVisibility'] ); ?>><?php esc_html_e( 'Show my profile', 'wporg' ); ?></option>
									<option value="private" <?php selected( 'private', (string) $context['rsvpVisibility'] ); ?>><?php esc_html_e( 'Keep my profile private', 'wporg' ); ?></option>
								</select>
							</label>
							<?php render_rsvp_answer_fields( $rsvp_questions, $rsvp_answers ); ?>
							<button class="wporg-events-button" type="submit" data-wp-bind--disabled="state.isRsvpButtonDisabled" data-wp-text="context.rsvpSaveButton">
								<?php echo esc_html( (string) $context['rsvpSaveButton'] ); ?>
							</button>
						</form>
						<button class="wporg-events-button wporg-events-button--secondary" type="button" data-wp-on--click="actions.rsvp" data-wp-bind--disabled="state.isRsvpButtonDisabled" data-wp-bind--hidden="state.isRsvpCancelButtonHidden" data-wp-text="context.rsvpButton">
							<?php echo esc_html( (string) $context['rsvpButton'] ); ?>
						</button>
						<?php if ( $rest_id ) : ?>
							<a class="wporg-events-button wporg-events-button--link" href="<?php echo esc_url( get_event_calendar_url( $rest_id ) ); ?>"><?php esc_html_e( 'Add to calendar', 'wporg' ); ?></a>
						<?php endif; ?>
						<p class="wporg-events-status" data-wp-text="context.rsvpMessage"></p>

						<h3><?php esc_html_e( 'Attendees', 'wporg' ); ?></h3>
						<?php if ( $attendees ) : ?>
							<ul class="wporg-events-list">
								<?php foreach ( $attendees as $attendee ) : ?>
									<?php $attendee_user = is_array( $attendee['user'] ?? null ) ? $attendee['user'] : array(); ?>
									<li class="wporg-events-profile">
										<?php if ( ! empty( $attendee_user['avatar_url'] ) ) : ?>
											<img src="<?php echo esc_url( (string) $attendee_user['avatar_url'] ); ?>" alt="" />
										<?php endif; ?>
										<a href="<?php echo esc_url( (string) ( $attendee_user['profile_url'] ?? '' ) ); ?>"><?php echo esc_html( (string) ( $attendee_user['name'] ?? '' ) ); ?></a>
										<?php if ( ! empty( $attendee['guest_count'] ) ) : ?>
											<span class="wporg-events-meta">
												<?php
												printf(
													/* translators: %d: number of guests. */
													esc_html( _n( '+%d guest', '+%d guests', (int) $attendee['guest_count'], 'wporg' ) ),
													(int) $attendee['guest_count']
												);
												?>
											</span>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p><?php esc_html_e( 'No attendees yet.', 'wporg' ); ?></p>
						<?php endif; ?>

						<?php if ( $waitlisted ) : ?>
							<h3><?php esc_html_e( 'Waitlist', 'wporg' ); ?></h3>
							<ul class="wporg-events-list">
								<?php foreach ( $waitlisted as $attendee ) : ?>
									<?php $attendee_user = is_array( $attendee['user'] ?? null ) ? $attendee['user'] : array(); ?>
									<li class="wporg-events-profile">
										<?php if ( ! empty( $attendee_user['avatar_url'] ) ) : ?>
											<img src="<?php echo esc_url( (string) $attendee_user['avatar_url'] ); ?>" alt="" />
										<?php endif; ?>
										<a href="<?php echo esc_url( (string) ( $attendee_user['profile_url'] ?? '' ) ); ?>"><?php echo esc_html( (string) ( $attendee_user['name'] ?? '' ) ); ?></a>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>

						<?php if ( $hosts ) : ?>
							<h3><?php esc_html_e( 'Hosts', 'wporg' ); ?></h3>
							<ul class="wporg-events-list">
								<?php foreach ( $hosts as $event_host ) : ?>
									<li class="wporg-events-profile">
										<?php if ( ! empty( $event_host['avatar_url'] ) ) : ?>
											<img src="<?php echo esc_url( (string) $event_host['avatar_url'] ); ?>" alt="" />
										<?php endif; ?>
										<a href="<?php echo esc_url( (string) ( $event_host['profile_url'] ?? '' ) ); ?>"><?php echo esc_html( (string) ( $event_host['name'] ?? '' ) ); ?></a>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>

						<?php if ( $venue ) : ?>
							<h3><?php esc_html_e( 'Location', 'wporg' ); ?></h3>
							<p>
								<?php if ( ! empty( $venue['url'] ) ) : ?>
									<a href="<?php echo esc_url( (string) $venue['url'] ); ?>"><?php echo esc_html( (string) ( $venue['title'] ?? '' ) ); ?></a>
								<?php else : ?>
									<?php echo esc_html( (string) ( $venue['title'] ?? '' ) ); ?>
								<?php endif; ?>
							</p>
							<?php if ( get_venue_address_label( $venue ) ) : ?>
								<p><?php echo esc_html( get_venue_address_label( $venue ) ); ?></p>
							<?php endif; ?>
							<?php if ( $map_embed_url && $map_external_url ) : ?>
								<div class="wporg-events-map wporg-events-map--compact">
									<iframe
										title="<?php echo esc_attr( $map_title ); ?>"
										src="<?php echo esc_url( $map_embed_url ); ?>"
										loading="lazy"
										referrerpolicy="no-referrer-when-downgrade"
									></iframe>
									<p class="wporg-events-map__link">
										<a href="<?php echo esc_url( $map_external_url ); ?>"><?php esc_html_e( 'Open in OpenStreetMap', 'wporg' ); ?></a>
									</p>
								</div>
							<?php endif; ?>
						<?php endif; ?>
					</aside>

					<?php if ( $can_manage ) : ?>
						<aside class="wporg-events-panel" <?php context_attribute( $management_context ); ?>>
							<h2><?php esc_html_e( 'Manage event', 'wporg' ); ?></h2>
							<p class="wporg-events-meta" data-wp-text="context.eventStatusLabel"><?php echo esc_html( (string) $management_context['eventStatusLabel'] ); ?></p>
							<div class="wporg-events-attendee-management">
								<h3><?php esc_html_e( 'Manage attendees', 'wporg' ); ?></h3>
								<p>
									<a class="wporg-events-button wporg-events-button--link" href="<?php echo esc_url( get_event_attendee_export_url( $rest_id ) ); ?>">
										<?php esc_html_e( 'Download attendee CSV', 'wporg' ); ?>
									</a>
								</p>
								<?php if ( $attendee_candidates ) : ?>
									<form class="wporg-events-form wporg-events-attendee-add-form" data-wp-on--submit="actions.addEventAttendee">
										<h4><?php esc_html_e( 'Add attendee', 'wporg' ); ?></h4>
										<label>
											<span><?php esc_html_e( 'Member', 'wporg' ); ?></span>
											<select class="wporg-events-input" name="user_id" required>
												<option value=""><?php esc_html_e( 'Select a member', 'wporg' ); ?></option>
												<?php foreach ( $attendee_candidates as $attendee_candidate ) : ?>
													<?php
													$attendee_candidate_user = is_array( $attendee_candidate['user'] ?? null ) ? $attendee_candidate['user'] : array();
													$attendee_candidate_id   = (int) ( $attendee_candidate_user['id'] ?? 0 );

													if ( ! $attendee_candidate_id ) {
														continue;
													}
													?>
													<option value="<?php echo esc_attr( (string) $attendee_candidate_id ); ?>"><?php echo esc_html( (string) ( $attendee_candidate_user['name'] ?? '' ) ); ?></option>
												<?php endforeach; ?>
											</select>
										</label>
										<label>
											<span><?php esc_html_e( 'RSVP status', 'wporg' ); ?></span>
											<select class="wporg-events-input" name="status">
												<option value="attending"><?php esc_html_e( 'Attending', 'wporg' ); ?></option>
												<option value="waitlisted"><?php esc_html_e( 'Waitlisted', 'wporg' ); ?></option>
											</select>
										</label>
										<label>
											<span><?php esc_html_e( 'Guests', 'wporg' ); ?></span>
											<input class="wporg-events-input" type="number" name="guest_count" min="0" step="1" value="0" />
										</label>
										<label>
											<span><?php esc_html_e( 'Attendee list visibility', 'wporg' ); ?></span>
											<select class="wporg-events-input" name="visibility">
												<option value="public"><?php esc_html_e( 'Show profile', 'wporg' ); ?></option>
												<option value="private"><?php esc_html_e( 'Keep profile private', 'wporg' ); ?></option>
											</select>
										</label>
										<?php render_rsvp_answer_fields( $rsvp_questions ); ?>
										<button class="wporg-events-button" type="submit" data-wp-bind--disabled="context.eventAttendeeAddBusy" data-wp-text="context.eventAttendeeAddButton">
											<?php echo esc_html( (string) $management_context['eventAttendeeAddButton'] ); ?>
										</button>
										<p class="wporg-events-status" aria-live="polite" data-wp-text="context.eventAttendeeAddMessage"></p>
									</form>
								<?php else : ?>
									<p><?php esc_html_e( 'No additional group members are available to add.', 'wporg' ); ?></p>
								<?php endif; ?>
								<?php if ( $managed_attendees ) : ?>
									<ul class="wporg-events-list">
										<?php foreach ( $managed_attendees as $managed_attendee ) : ?>
											<?php
											$managed_attendee_user    = is_array( $managed_attendee['user'] ?? null ) ? $managed_attendee['user'] : array();
											$managed_attendee_answers = is_array( $managed_attendee['answers'] ?? null ) ? $managed_attendee['answers'] : array();
											$attendance_status        = (string) ( $managed_attendee['attendance_status'] ?? 'not_checked_in' );
											$managed_attendee_context = array(
												'attendeeManageBusy' => false,
												'attendeeManageMessage' => '',
												'attendanceStatus' => $attendance_status,
												'attendanceStatusLabel' => get_attendance_status_label( $attendance_status ),
												'eventId' => $rest_id,
												'guestCount' => (int) ( $managed_attendee['guest_count'] ?? 0 ),
												'rsvpId'  => (int) ( $managed_attendee['rsvp_id'] ?? 0 ),
												'rsvpStatus' => (string) ( $managed_attendee['status'] ?? 'attending' ),
											);
											?>
											<li class="wporg-events-attendee-row" <?php context_attribute( $managed_attendee_context ); ?>>
												<div class="wporg-events-attendee-row__summary">
													<?php if ( ! empty( $managed_attendee_user['avatar_url'] ) ) : ?>
														<img src="<?php echo esc_url( (string) $managed_attendee_user['avatar_url'] ); ?>" alt="" />
													<?php endif; ?>
													<div>
														<a href="<?php echo esc_url( (string) ( $managed_attendee_user['profile_url'] ?? '' ) ); ?>"><?php echo esc_html( (string) ( $managed_attendee_user['name'] ?? '' ) ); ?></a>
														<span class="wporg-events-meta" data-wp-text="context.attendanceStatusLabel"><?php echo esc_html( get_attendance_status_label( $attendance_status ) ); ?></span>
													</div>
												</div>
												<?php if ( $rsvp_questions && $managed_attendee_answers ) : ?>
													<dl class="wporg-events-rsvp-answers">
														<?php foreach ( $rsvp_questions as $rsvp_question ) : ?>
															<?php
															$rsvp_question_id = sanitize_key( (string) ( $rsvp_question['id'] ?? '' ) );
															$rsvp_answer      = (string) ( $managed_attendee_answers[ $rsvp_question_id ] ?? '' );

															if ( '' === $rsvp_question_id || '' === $rsvp_answer ) {
																continue;
															}
															?>
															<div>
																<dt><?php echo esc_html( (string) ( $rsvp_question['label'] ?? '' ) ); ?></dt>
																<dd><?php echo esc_html( $rsvp_answer ); ?></dd>
															</div>
														<?php endforeach; ?>
													</dl>
												<?php endif; ?>
												<div class="wporg-events-attendee-row__actions">
													<button class="wporg-events-button" type="button" data-attendance-status="checked_in" data-rsvp-status="attending" data-wp-on--click="actions.updateAttendeeAttendance" data-wp-bind--disabled="context.attendeeManageBusy">
														<?php esc_html_e( 'Check in', 'wporg' ); ?>
													</button>
													<button class="wporg-events-button" type="button" data-attendance-status="no_show" data-rsvp-status="attending" data-wp-on--click="actions.updateAttendeeAttendance" data-wp-bind--disabled="context.attendeeManageBusy">
														<?php esc_html_e( 'No show', 'wporg' ); ?>
													</button>
													<button class="wporg-events-button" type="button" data-attendance-status="not_coming" data-rsvp-status="not_attending" data-wp-on--click="actions.updateAttendeeAttendance" data-wp-bind--disabled="context.attendeeManageBusy">
														<?php esc_html_e( 'Not coming', 'wporg' ); ?>
													</button>
												</div>
												<p class="wporg-events-status" aria-live="polite" data-wp-text="context.attendeeManageMessage"></p>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php else : ?>
									<p><?php esc_html_e( 'No active attendees yet.', 'wporg' ); ?></p>
								<?php endif; ?>
							</div>
							<form class="wporg-events-form wporg-events-attendee-message-form" data-wp-on--submit="actions.sendEventAttendeeMessage">
								<h3><?php esc_html_e( 'Message attendees', 'wporg' ); ?></h3>
								<label>
									<span><?php esc_html_e( 'Recipients', 'wporg' ); ?></span>
									<select class="wporg-events-input" name="status">
										<option value="all"><?php esc_html_e( 'All active RSVPs', 'wporg' ); ?></option>
										<option value="attending"><?php esc_html_e( 'Attending only', 'wporg' ); ?></option>
										<option value="waitlisted"><?php esc_html_e( 'Waitlist only', 'wporg' ); ?></option>
									</select>
								</label>
								<label>
									<span><?php esc_html_e( 'Subject', 'wporg' ); ?></span>
									<input class="wporg-events-input" type="text" name="subject" required />
								</label>
								<label>
									<span><?php esc_html_e( 'Message', 'wporg' ); ?></span>
									<textarea class="wporg-events-input" name="message" rows="4" required></textarea>
								</label>
								<button class="wporg-events-button" type="submit" data-wp-bind--disabled="context.eventMessageBusy" data-wp-text="context.eventMessageButton">
									<?php echo esc_html( (string) $management_context['eventMessageButton'] ); ?>
								</button>
								<p class="wporg-events-status" aria-live="polite" data-wp-text="context.eventMessageStatus"></p>
							</form>
							<form class="wporg-events-form" data-wp-on--submit="actions.submitEventUpdate" data-wp-bind--hidden="context.eventCanceled">
								<label>
									<span><?php esc_html_e( 'Event title', 'wporg' ); ?></span>
									<input class="wporg-events-input" type="text" name="title" value="<?php echo esc_attr( (string) ( $event['title'] ?? '' ) ); ?>" required />
								</label>
								<label>
									<span><?php esc_html_e( 'Start date and time', 'wporg' ); ?></span>
									<input class="wporg-events-input" type="datetime-local" name="start" value="<?php echo esc_attr( get_datetime_local_value( (string) ( $event['start_utc'] ?? '' ) ) ); ?>" required />
								</label>
								<label>
									<span><?php esc_html_e( 'End date and time', 'wporg' ); ?></span>
									<input class="wporg-events-input" type="datetime-local" name="end" value="<?php echo esc_attr( get_datetime_local_value( (string) ( $event['end_utc'] ?? '' ) ) ); ?>" />
								</label>
								<?php if ( $host_options ) : ?>
									<label>
										<span><?php esc_html_e( 'Hosts', 'wporg' ); ?></span>
										<select class="wporg-events-input" name="host_user_ids" multiple>
											<?php foreach ( $host_options as $host_option ) : ?>
												<?php $host_option_id = (int) ( $host_option['id'] ?? 0 ); ?>
												<option value="<?php echo esc_attr( (string) $host_option_id ); ?>" <?php selected( in_array( $host_option_id, $host_user_ids, true ) ); ?>>
													<?php echo esc_html( (string) ( $host_option['name'] ?? '' ) ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</label>
								<?php endif; ?>
								<label>
									<span><?php esc_html_e( 'Venue', 'wporg' ); ?></span>
									<select class="wporg-events-input" name="venue_id">
										<option value="0"><?php esc_html_e( 'Online or to be announced', 'wporg' ); ?></option>
										<?php foreach ( $venues as $option_venue ) : ?>
											<?php $option_venue_id = (int) ( $option_venue['id'] ?? 0 ); ?>
											<option value="<?php echo esc_attr( (string) $option_venue_id ); ?>" <?php selected( $option_venue_id, (int) ( $event['venue_id'] ?? 0 ) ); ?>>
												<?php echo esc_html( (string) ( $option_venue['title'] ?? '' ) ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</label>
								<?php if ( $event_type_terms ) : ?>
									<label>
										<span><?php esc_html_e( 'Event type', 'wporg' ); ?></span>
										<select class="wporg-events-input" name="event_type">
											<option value=""><?php esc_html_e( 'Choose an event type', 'wporg' ); ?></option>
											<?php foreach ( $event_type_terms as $event_type_term ) : ?>
												<option value="<?php echo esc_attr( $event_type_term->slug ); ?>" <?php selected( in_array( $event_type_term->slug, $event_type_slugs, true ) ); ?>>
													<?php echo esc_html( $event_type_term->name ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</label>
								<?php endif; ?>
								<?php if ( $event_format_terms ) : ?>
									<label>
										<span><?php esc_html_e( 'Format', 'wporg' ); ?></span>
										<select class="wporg-events-input" name="event_format">
											<option value=""><?php esc_html_e( 'Choose a format', 'wporg' ); ?></option>
											<?php foreach ( $event_format_terms as $event_format_term ) : ?>
												<option value="<?php echo esc_attr( $event_format_term->slug ); ?>" <?php selected( in_array( $event_format_term->slug, $event_format_slugs, true ) ); ?>>
													<?php echo esc_html( $event_format_term->name ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</label>
								<?php endif; ?>
								<label>
									<span><?php esc_html_e( 'Short summary', 'wporg' ); ?></span>
									<input class="wporg-events-input" type="text" name="excerpt" value="<?php echo esc_attr( (string) ( $event['excerpt'] ?? '' ) ); ?>" />
								</label>
								<label>
									<span><?php esc_html_e( 'Description', 'wporg' ); ?></span>
									<textarea class="wporg-events-input" name="description" rows="4"><?php echo esc_textarea( (string) ( $event['description'] ?? '' ) ); ?></textarea>
								</label>
								<label>
									<span><?php esc_html_e( 'Online event URL', 'wporg' ); ?></span>
									<input class="wporg-events-input" type="url" name="online_url" value="<?php echo esc_attr( (string) ( $event['online_url'] ?? '' ) ); ?>" />
								</label>
								<label>
									<span><?php esc_html_e( 'Capacity', 'wporg' ); ?></span>
									<input class="wporg-events-input" type="number" name="capacity" min="0" step="1" value="<?php echo esc_attr( (string) ( $event['capacity'] ?? 0 ) ); ?>" />
								</label>
								<label>
									<span><?php esc_html_e( 'RSVP policy', 'wporg' ); ?></span>
									<select class="wporg-events-input" name="rsvp_policy">
										<option value="open" <?php selected( 'open', (string) ( $event['rsvp_policy'] ?? 'open' ) ); ?>><?php esc_html_e( 'Open', 'wporg' ); ?></option>
										<option value="closed" <?php selected( 'closed', (string) ( $event['rsvp_policy'] ?? 'open' ) ); ?>><?php esc_html_e( 'Closed', 'wporg' ); ?></option>
									</select>
								</label>
								<?php render_rsvp_question_definition_fields( $rsvp_questions ); ?>
								<button class="wporg-events-button" type="submit" data-wp-bind--disabled="context.eventManageBusy" data-wp-text="context.eventManageButton">
									<?php echo esc_html( (string) $management_context['eventManageButton'] ); ?>
								</button>
								<p class="wporg-events-status" aria-live="polite" data-wp-text="context.eventManageMessage"></p>
							</form>
							<form class="wporg-events-form" data-wp-on--submit="actions.copyEvent" data-wp-bind--hidden="context.eventCanceled">
								<h3><?php esc_html_e( 'Create a similar event', 'wporg' ); ?></h3>
								<label>
									<span><?php esc_html_e( 'New event title', 'wporg' ); ?></span>
									<input class="wporg-events-input" type="text" name="title" value="<?php echo esc_attr( (string) ( $event['title'] ?? '' ) ); ?>" required />
								</label>
								<label>
									<span><?php esc_html_e( 'New start date and time', 'wporg' ); ?></span>
									<input class="wporg-events-input" type="datetime-local" name="start" value="<?php echo esc_attr( $copy_start_value ); ?>" required />
								</label>
								<label>
									<span><?php esc_html_e( 'New end date and time', 'wporg' ); ?></span>
									<input class="wporg-events-input" type="datetime-local" name="end" value="<?php echo esc_attr( $copy_end_value ); ?>" />
								</label>
								<button class="wporg-events-button" type="submit" data-wp-bind--disabled="context.eventCopyBusy" data-wp-text="context.eventCopyButton">
									<?php echo esc_html( (string) $management_context['eventCopyButton'] ); ?>
								</button>
								<p class="wporg-events-status" aria-live="polite" data-wp-text="context.eventCopyMessage"></p>
								<a class="wporg-events-button wporg-events-button--link" href="" data-wp-bind--href="context.eventCopyUrl" data-wp-bind--hidden="state.isEventCopyLinkHidden">
									<?php esc_html_e( 'View copied event', 'wporg' ); ?>
								</a>
							</form>
							<div class="wporg-events-form" data-wp-bind--hidden="context.eventCanceled">
								<label>
									<span><?php esc_html_e( 'Cancellation reason', 'wporg' ); ?></span>
									<textarea class="wporg-events-input" name="cancellation_reason" rows="3"></textarea>
								</label>
								<button class="wporg-events-button wporg-events-button--secondary" type="button" data-wp-on--click="actions.cancelEvent" data-wp-bind--disabled="context.eventCancelBusy" data-wp-text="context.eventCancelButton">
									<?php echo esc_html( (string) $management_context['eventCancelButton'] ); ?>
								</button>
							</div>
							<p class="wporg-events-status" aria-live="polite" data-wp-text="context.eventCancelMessage"></p>
						</aside>
					<?php endif; ?>
				</div>
			</div>

			<section class="wporg-events-section wporg-events-feedback" <?php context_attribute( $feedback_context ); ?>>
				<h2><?php esc_html_e( 'Event feedback', 'wporg' ); ?></h2>
				<?php if ( ! empty( $feedback_summary['count'] ) ) : ?>
					<p class="wporg-events-meta">
						<?php
						printf(
							/* translators: 1: average event rating, 2: number of feedback records. */
							esc_html( _n( '%1$s average rating from %2$d response', '%1$s average rating from %2$d responses', (int) $feedback_summary['count'], 'wporg' ) ),
							esc_html( number_format_i18n( (float) ( $feedback_summary['average_rating'] ?? 0 ), 1 ) ),
							(int) $feedback_summary['count']
						);
						?>
					</p>
				<?php endif; ?>

				<form class="wporg-events-form wporg-events-feedback-form" data-wp-on--submit="actions.submitEventFeedback" data-wp-bind--hidden="state.isFeedbackFormHidden">
					<label>
						<span><?php esc_html_e( 'Rating', 'wporg' ); ?></span>
						<select class="wporg-events-input" name="rating" required>
							<option value=""><?php esc_html_e( 'Choose a rating', 'wporg' ); ?></option>
							<?php for ( $rating = 5; $rating >= 1; --$rating ) : ?>
								<option value="<?php echo esc_attr( (string) $rating ); ?>">
									<?php
										printf(
											/* translators: %d: rating value from 1 to 5. */
											esc_html__( '%d out of 5', 'wporg' ),
											(int) $rating
										);
									?>
								</option>
							<?php endfor; ?>
						</select>
					</label>
					<label>
						<span><?php esc_html_e( 'Review', 'wporg' ); ?></span>
						<textarea class="wporg-events-input" name="review" rows="4"></textarea>
					</label>
					<button class="wporg-events-button" type="submit" data-wp-bind--disabled="state.isFeedbackButtonDisabled" data-wp-text="context.feedbackButton">
						<?php echo esc_html( (string) $feedback_context['feedbackButton'] ); ?>
					</button>
				</form>
				<p class="wporg-events-status" aria-live="polite" data-wp-text="context.feedbackMessage"><?php echo esc_html( (string) $feedback_context['feedbackMessage'] ); ?></p>

				<?php if ( $feedback ) : ?>
					<ul class="wporg-events-feedback-list">
						<?php foreach ( $feedback as $feedback_item ) : ?>
							<?php $feedback_user = is_array( $feedback_item['user'] ?? null ) ? $feedback_item['user'] : array(); ?>
							<li>
								<div class="wporg-events-feedback-list__meta">
									<?php if ( ! empty( $feedback_user['avatar_url'] ) ) : ?>
										<img src="<?php echo esc_url( (string) $feedback_user['avatar_url'] ); ?>" alt="" />
									<?php endif; ?>
									<div>
										<a href="<?php echo esc_url( (string) ( $feedback_user['profile_url'] ?? '' ) ); ?>"><?php echo esc_html( (string) ( $feedback_user['name'] ?? '' ) ); ?></a>
										<span>
											<?php
											printf(
												/* translators: %d: rating value from 1 to 5. */
												esc_html__( '%d out of 5', 'wporg' ),
												(int) ( $feedback_item['rating'] ?? 0 )
											);
											?>
										</span>
									</div>
								</div>
								<?php if ( ! empty( $feedback_item['review'] ) ) : ?>
									<p><?php echo esc_html( (string) $feedback_item['review'] ); ?></p>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p class="wporg-events-empty"><?php esc_html_e( 'No feedback yet.', 'wporg' ); ?></p>
				<?php endif; ?>
			</section>

			<?php if ( comments_open( $rest_id ) || get_comments_number( $rest_id ) ) : ?>
				<div class="wporg-events-section wporg-events-comments">
					<?php comments_template(); ?>
				</div>
			<?php endif; ?>
		<?php else : ?>
		<p class="wporg-events-empty"><?php esc_html_e( 'Event not found.', 'wporg' ); ?></p>
	<?php endif; ?>
</section>
<?php
echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block output is escaped while rendering.
