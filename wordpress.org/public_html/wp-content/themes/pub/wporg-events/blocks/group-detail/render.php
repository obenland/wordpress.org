<?php
/**
 * Server render callback for the Group Detail block.
 *
 * @package WordPressdotorg\Events_Theme
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Events_Theme;

configure_interactivity_store();

$group_id               = get_the_ID();
$group                  = $group_id ? get_group( $group_id ) : array();
$events                 = $group_id ? get_group_events( $group_id ) : array();
$organizers             = $group_id ? get_group_organizers( $group_id ) : array();
$venues                 = get_venues( 100 );
$event_type_terms       = get_taxonomy_term_options( 'wporg_ce_event_type' );
$event_format_terms     = get_taxonomy_term_options( 'wporg_ce_event_format' );
$country_terms          = get_taxonomy_term_options( 'wporg_ce_country' );
$membership             = $group_id ? get_group_membership( $group_id ) : array();
$can_manage_events      = can_manage_group_events( $membership );
$can_manage_team        = can_manage_group_organizers( $membership );
$can_manage_group       = $can_manage_team;
$can_assign_organizer   = current_user_can( 'manage_options' );
$members                = $group_id ? get_group_members( $group_id, 'all', $can_manage_team ? 100 : 12 ) : array();
$public_members         = array_slice( $members, 0, 12 );
$location               = $group ? get_group_location_label( $group ) : '';
$website_url            = $group ? (string) ( $group['website_url'] ?? '' ) : '';
$membership_context     = get_group_membership_context( (int) $group_id, $membership );
$group_action_context   = array_merge(
	$membership_context,
	get_group_event_form_context( (int) $group_id, $membership ),
	array(
		'canAssignOrganizers'         => $can_assign_organizer,
		'canManageGroup'              => $can_manage_group,
		'canManageOrganizers'         => $can_manage_team,
		'groupOrganizerAddBusy'       => false,
		'groupOrganizerAddButton'     => __( 'Add to team', 'wporg' ),
		'groupOrganizerAddMessage'    => '',
		'groupOrganizerUpdateBusy'    => false,
		'groupOrganizerUpdateButton'  => __( 'Update role', 'wporg' ),
		'groupOrganizerUpdateMessage' => '',
		'groupProfileBusy'            => false,
		'groupProfileButton'          => __( 'Save group settings', 'wporg' ),
		'groupProfileMessage'         => '',
		'groupWebsiteUrl'             => $website_url,
	)
);
$team_candidate_members = array_values(
	array_filter(
		$members,
		static function ( array $member ): bool {
			return 'member' === (string) ( $member['role'] ?? '' );
		}
	)
);

ob_start();
?>
<section <?php echo get_block_wrapper_attributes( array( 'class' => 'wporg-events-detail wporg-events-group-detail' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-wp-interactive="<?php echo esc_attr( STORE_NAMESPACE ); ?>">
	<?php if ( $group ) : ?>
		<h1><?php echo esc_html( (string) ( $group['title'] ?? '' ) ); ?></h1>
		<ul class="wporg-events-meta">
			<?php if ( $location ) : ?>
				<li><?php echo esc_html( $location ); ?></li>
			<?php endif; ?>
			<?php if ( $website_url ) : ?>
				<li><a href="<?php echo esc_url( $website_url ); ?>" rel="external nofollow"><?php esc_html_e( 'Website', 'wporg' ); ?></a></li>
			<?php endif; ?>
			<li>
				<?php
				printf(
					/* translators: %d: number of members. */
					esc_html( _n( '%d member', '%d members', (int) ( $group['member_count'] ?? 0 ), 'wporg' ) ),
					(int) ( $group['member_count'] ?? 0 )
				);
				?>
			</li>
		</ul>

		<div class="wporg-events-detail__body">
			<div class="wporg-events-panel-stack">
				<?php if ( ! empty( $group['description'] ) ) : ?>
					<div><?php echo wp_kses_post( wpautop( (string) $group['description'] ) ); ?></div>
				<?php endif; ?>

				<div class="wporg-events-section">
					<div class="wporg-events-section__header">
						<h2><?php esc_html_e( 'Group events', 'wporg' ); ?></h2>
					</div>
					<?php if ( $events ) : ?>
						<ul class="wporg-events-list">
							<?php foreach ( $events as $event ) : ?>
								<?php
								$start_utc       = (string) ( $event['start_utc'] ?? '' );
								$start_timestamp = get_event_start_timestamp( $start_utc );
								$classification  = get_event_classification_label( $event );
								$is_canceled     = is_event_canceled( $event );
								$search_text     = get_search_text( array( $event['title'] ?? '', $event['excerpt'] ?? '', $classification, get_event_taxonomy_search_text( $event ), get_event_approval_status_label( (string) ( $event['approval_status'] ?? '' ) ) ) );
								$event_context   = array(
									'searchText'     => $search_text,
									'startTimestamp' => $start_timestamp,
								);
								?>
								<li <?php context_attribute( $event_context ); ?> data-wp-bind--hidden="state.isEventHidden">
									<a href="<?php echo esc_url( get_permalink( (int) ( $event['id'] ?? 0 ) ) ); ?>"><?php echo esc_html( (string) ( $event['title'] ?? '' ) ); ?></a>
									<?php if ( $start_utc ) : ?>
										<span class="wporg-events-meta"><?php echo esc_html( get_event_start_label( $start_utc ) ); ?></span>
									<?php endif; ?>
									<?php if ( $is_canceled ) : ?>
										<span class="wporg-events-meta"><?php esc_html_e( 'Canceled', 'wporg' ); ?></span>
									<?php endif; ?>
									<?php if ( $classification ) : ?>
										<span class="wporg-events-meta"><?php echo esc_html( $classification ); ?></span>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="wporg-events-empty"><?php esc_html_e( 'This group does not have public events yet.', 'wporg' ); ?></p>
					<?php endif; ?>
				</div>

				<div class="wporg-events-section">
					<div class="wporg-events-section__header">
						<h2><?php esc_html_e( 'Members', 'wporg' ); ?></h2>
					</div>
					<?php if ( $public_members ) : ?>
						<ul class="wporg-events-profile-list">
							<?php foreach ( $public_members as $member ) : ?>
								<?php $member_user = is_array( $member['user'] ?? null ) ? $member['user'] : array(); ?>
								<li class="wporg-events-profile">
									<?php if ( ! empty( $member_user['avatar_url'] ) ) : ?>
										<img src="<?php echo esc_url( (string) $member_user['avatar_url'] ); ?>" alt="" />
									<?php endif; ?>
									<a href="<?php echo esc_url( (string) ( $member_user['profile_url'] ?? '' ) ); ?>"><?php echo esc_html( (string) ( $member_user['name'] ?? '' ) ); ?></a>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="wporg-events-empty"><?php esc_html_e( 'No public members are listed yet.', 'wporg' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<div <?php context_attribute( $group_action_context ); ?>>
				<aside class="wporg-events-panel">
					<h2><?php esc_html_e( 'Membership', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'Join this group to keep track of events and participate in the local community.', 'wporg' ); ?></p>
					<button class="wporg-events-button" type="button" data-wp-on--click="actions.toggleGroupMembership" data-wp-bind--disabled="context.membershipBusy" data-wp-text="context.membershipButton">
						<?php echo esc_html( (string) $membership_context['membershipButton'] ); ?>
					</button>
					<?php if ( $group_id ) : ?>
						<a class="wporg-events-button wporg-events-button--link" href="<?php echo esc_url( get_group_calendar_url( (int) $group_id ) ); ?>"><?php esc_html_e( 'Subscribe to calendar', 'wporg' ); ?></a>
					<?php endif; ?>
					<p class="wporg-events-status" data-wp-text="context.membershipMessage"></p>
					<?php render_membership_settings_form( $membership_context ); ?>
				</aside>

				<?php if ( $can_manage_group ) : ?>
					<aside class="wporg-events-panel">
						<h2><?php esc_html_e( 'Group settings', 'wporg' ); ?></h2>
						<form class="wporg-events-form" data-wp-on--submit="actions.submitGroupProfile">
							<label>
								<span><?php esc_html_e( 'Website URL', 'wporg' ); ?></span>
								<input class="wporg-events-input" type="url" name="website_url" value="<?php echo esc_attr( $website_url ); ?>" />
							</label>
							<button class="wporg-events-button" type="submit" data-wp-bind--disabled="context.groupProfileBusy" data-wp-text="context.groupProfileButton">
								<?php echo esc_html( (string) $group_action_context['groupProfileButton'] ); ?>
							</button>
							<p class="wporg-events-status" aria-live="polite" data-wp-text="context.groupProfileMessage"></p>
						</form>
					</aside>
				<?php endif; ?>

				<aside class="wporg-events-panel">
						<h2><?php esc_html_e( 'Create an event', 'wporg' ); ?></h2>
						<p <?php echo $can_manage_events ? 'hidden' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-wp-bind--hidden="state.isEventCreatePromptHidden">
							<?php esc_html_e( 'Only organizers and hosts can create events for this group.', 'wporg' ); ?>
						</p>
						<form class="wporg-events-form" <?php echo $can_manage_events ? '' : 'hidden'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-wp-on--submit="actions.submitGroupEvent" data-wp-bind--hidden="state.isEventFormHidden">
							<label>
								<span><?php esc_html_e( 'Event title', 'wporg' ); ?></span>
								<input class="wporg-events-input" type="text" name="title" required />
							</label>
							<label>
								<span><?php esc_html_e( 'Start date and time', 'wporg' ); ?></span>
								<input class="wporg-events-input" type="datetime-local" name="start" required />
							</label>
							<label>
								<span><?php esc_html_e( 'End date and time', 'wporg' ); ?></span>
								<input class="wporg-events-input" type="datetime-local" name="end" />
							</label>
							<?php if ( $organizers ) : ?>
								<label>
									<span><?php esc_html_e( 'Hosts', 'wporg' ); ?></span>
									<select class="wporg-events-input" name="host_user_ids" multiple>
										<?php foreach ( $organizers as $organizer ) : ?>
											<?php $organizer_user = is_array( $organizer['user'] ?? null ) ? $organizer['user'] : array(); ?>
											<option value="<?php echo esc_attr( (string) ( $organizer_user['id'] ?? 0 ) ); ?>"><?php echo esc_html( (string) ( $organizer_user['name'] ?? '' ) ); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
							<?php endif; ?>
							<label>
								<span><?php esc_html_e( 'Venue', 'wporg' ); ?></span>
								<select class="wporg-events-input" name="venue_id">
									<option value="0"><?php esc_html_e( 'Online or to be announced', 'wporg' ); ?></option>
									<?php foreach ( $venues as $venue ) : ?>
										<option value="<?php echo esc_attr( (string) ( $venue['id'] ?? 0 ) ); ?>"><?php echo esc_html( (string) ( $venue['title'] ?? '' ) ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
							<fieldset class="wporg-events-fieldset">
								<legend><?php esc_html_e( 'New venue', 'wporg' ); ?></legend>
								<label>
									<span><?php esc_html_e( 'Venue name', 'wporg' ); ?></span>
									<input class="wporg-events-input" type="text" name="venue_title" />
								</label>
								<label>
									<span><?php esc_html_e( 'Street address', 'wporg' ); ?></span>
									<input class="wporg-events-input" type="text" name="venue_address" />
								</label>
								<label>
									<span><?php esc_html_e( 'City', 'wporg' ); ?></span>
									<input class="wporg-events-input" type="text" name="venue_city" />
								</label>
								<label>
									<span><?php esc_html_e( 'Region', 'wporg' ); ?></span>
									<input class="wporg-events-input" type="text" name="venue_region" />
								</label>
								<label>
									<span><?php esc_html_e( 'Postal code', 'wporg' ); ?></span>
									<input class="wporg-events-input" type="text" name="venue_postal_code" />
								</label>
								<?php if ( $country_terms ) : ?>
									<label>
										<span><?php esc_html_e( 'Country', 'wporg' ); ?></span>
										<select class="wporg-events-input" name="venue_country">
											<option value=""><?php esc_html_e( 'Choose a country', 'wporg' ); ?></option>
											<?php foreach ( $country_terms as $country_term ) : ?>
												<option value="<?php echo esc_attr( $country_term->slug ); ?>"><?php echo esc_html( $country_term->name ); ?></option>
											<?php endforeach; ?>
										</select>
									</label>
								<?php endif; ?>
								<label>
									<span><?php esc_html_e( 'Accessibility notes', 'wporg' ); ?></span>
									<textarea class="wporg-events-input" name="venue_accessibility_notes" rows="3"></textarea>
								</label>
							</fieldset>
							<?php if ( $event_type_terms ) : ?>
								<label>
									<span><?php esc_html_e( 'Event type', 'wporg' ); ?></span>
									<select class="wporg-events-input" name="event_type">
										<option value=""><?php esc_html_e( 'Choose an event type', 'wporg' ); ?></option>
										<?php foreach ( $event_type_terms as $event_type_term ) : ?>
											<option value="<?php echo esc_attr( $event_type_term->slug ); ?>"><?php echo esc_html( $event_type_term->name ); ?></option>
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
											<option value="<?php echo esc_attr( $event_format_term->slug ); ?>"><?php echo esc_html( $event_format_term->name ); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
							<?php endif; ?>
							<label>
								<span><?php esc_html_e( 'Short summary', 'wporg' ); ?></span>
								<input class="wporg-events-input" type="text" name="excerpt" />
							</label>
							<label>
								<span><?php esc_html_e( 'Description', 'wporg' ); ?></span>
								<textarea class="wporg-events-input" name="description" rows="4"></textarea>
							</label>
							<label>
								<span><?php esc_html_e( 'Online event URL', 'wporg' ); ?></span>
								<input class="wporg-events-input" type="url" name="online_url" />
							</label>
							<label>
								<span><?php esc_html_e( 'Capacity', 'wporg' ); ?></span>
								<input class="wporg-events-input" type="number" name="capacity" min="0" step="1" />
							</label>
							<?php render_rsvp_question_definition_fields(); ?>
							<button class="wporg-events-button" type="submit" data-wp-bind--disabled="context.eventFormBusy" data-wp-text="context.eventFormButton">
								<?php echo esc_html( (string) $group_action_context['eventFormButton'] ); ?>
							</button>
							<p class="wporg-events-status" aria-live="polite" data-wp-text="context.eventFormMessage"></p>
						</form>
					</aside>

				<aside class="wporg-events-panel">
					<h2><?php esc_html_e( 'Organizers', 'wporg' ); ?></h2>
					<?php if ( $organizers ) : ?>
						<ul class="wporg-events-list">
							<?php foreach ( $organizers as $organizer ) : ?>
								<?php
								$user              = is_array( $organizer['user'] ?? null ) ? $organizer['user'] : array();
								$organizer_role    = (string) ( $organizer['role'] ?? '' );
								$organizer_context = array(
									'groupId'            => (int) $group_id,
									'groupOrganizerMembershipId' => (int) ( $organizer['membership_id'] ?? 0 ),
									'groupOrganizerRole' => $organizer_role,
									'groupOrganizerRoleLabel' => get_membership_role_label( $organizer_role ),
									'groupOrganizerUpdateBusy' => false,
									'groupOrganizerUpdateButton' => __( 'Update role', 'wporg' ),
									'groupOrganizerUpdateMessage' => '',
								);
								?>
								<li class="wporg-events-profile wporg-events-profile--managed" <?php context_attribute( $organizer_context ); ?> data-wp-bind--hidden="state.isManagedOrganizerHidden">
									<div class="wporg-events-profile__summary">
										<?php if ( ! empty( $user['avatar_url'] ) ) : ?>
											<img src="<?php echo esc_url( (string) $user['avatar_url'] ); ?>" alt="" />
										<?php endif; ?>
										<div>
											<a href="<?php echo esc_url( (string) ( $user['profile_url'] ?? '' ) ); ?>"><?php echo esc_html( (string) ( $user['name'] ?? '' ) ); ?></a>
											<span class="wporg-events-meta" data-wp-text="context.groupOrganizerRoleLabel"><?php echo esc_html( get_membership_role_label( $organizer_role ) ); ?></span>
										</div>
									</div>
									<?php if ( $can_manage_team && ( $can_assign_organizer || 'organizer' !== $organizer_role ) ) : ?>
										<form class="wporg-events-inline-form" data-wp-on--submit="actions.updateGroupOrganizer">
											<label>
												<span><?php esc_html_e( 'Role', 'wporg' ); ?></span>
												<select class="wporg-events-input" name="role">
													<?php if ( $can_assign_organizer ) : ?>
														<option value="organizer" <?php selected( 'organizer', $organizer_role ); ?>><?php esc_html_e( 'Organizer', 'wporg' ); ?></option>
													<?php endif; ?>
													<option value="host" <?php selected( 'host', $organizer_role ); ?>><?php esc_html_e( 'Host', 'wporg' ); ?></option>
													<option value="member"><?php esc_html_e( 'Member', 'wporg' ); ?></option>
												</select>
											</label>
											<button class="wporg-events-button" type="submit" data-wp-bind--disabled="context.groupOrganizerUpdateBusy" data-wp-text="context.groupOrganizerUpdateButton">
												<?php esc_html_e( 'Update role', 'wporg' ); ?>
											</button>
											<p class="wporg-events-status" aria-live="polite" data-wp-text="context.groupOrganizerUpdateMessage"></p>
										</form>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p><?php esc_html_e( 'No public organizers are listed yet.', 'wporg' ); ?></p>
					<?php endif; ?>
					<?php if ( $can_manage_team && $team_candidate_members ) : ?>
						<form class="wporg-events-form" data-wp-on--submit="actions.addGroupOrganizer">
							<label>
								<span><?php esc_html_e( 'Member', 'wporg' ); ?></span>
								<select class="wporg-events-input" name="user_id" required>
									<option value=""><?php esc_html_e( 'Select a member', 'wporg' ); ?></option>
									<?php foreach ( $team_candidate_members as $member ) : ?>
										<?php $member_user = is_array( $member['user'] ?? null ) ? $member['user'] : array(); ?>
										<option value="<?php echo esc_attr( (string) ( $member_user['id'] ?? 0 ) ); ?>"><?php echo esc_html( (string) ( $member_user['name'] ?? '' ) ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
							<label>
								<span><?php esc_html_e( 'Role', 'wporg' ); ?></span>
								<select class="wporg-events-input" name="role">
									<option value="host"><?php esc_html_e( 'Host', 'wporg' ); ?></option>
									<?php if ( $can_assign_organizer ) : ?>
										<option value="organizer"><?php esc_html_e( 'Organizer', 'wporg' ); ?></option>
									<?php endif; ?>
								</select>
							</label>
							<button class="wporg-events-button" type="submit" data-wp-bind--disabled="context.groupOrganizerAddBusy" data-wp-text="context.groupOrganizerAddButton">
								<?php esc_html_e( 'Add to team', 'wporg' ); ?>
							</button>
							<p class="wporg-events-status" aria-live="polite" data-wp-text="context.groupOrganizerAddMessage"></p>
						</form>
					<?php endif; ?>
				</aside>
			</div>
		</div>
	<?php else : ?>
		<p class="wporg-events-empty"><?php esc_html_e( 'Group not found.', 'wporg' ); ?></p>
	<?php endif; ?>
</section>
<?php
echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block output is escaped while rendering.
