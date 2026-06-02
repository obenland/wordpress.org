<?php
/**
 * Server render callback for the Member Dashboard block.
 *
 * @package WordPressdotorg\Events_Theme
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Events_Theme;

configure_interactivity_store();

$memberships = get_current_user_memberships();
$rsvps       = get_current_user_rsvps();
$suggestions = get_current_user_group_suggestions();

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'wporg-events-section wporg-events-member-dashboard',
		'id'    => 'my-events',
	)
);

ob_start();
?>
<section <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-wp-interactive="<?php echo esc_attr( STORE_NAMESPACE ); ?>">
	<div class="wporg-events-section__header">
		<div>
			<h2><?php esc_html_e( 'My events', 'wporg' ); ?></h2>
			<p><?php esc_html_e( 'Track your RSVPs, groups, and submitted group suggestions.', 'wporg' ); ?></p>
		</div>
		<?php if ( is_user_logged_in() ) : ?>
			<div class="wporg-events-segmented" role="group" aria-label="<?php esc_attr_e( 'Dashboard view', 'wporg' ); ?>">
				<button type="button" <?php context_attribute( array( 'dashboardView' => 'rsvps' ) ); ?> data-wp-on--click="actions.setDashboardView" data-wp-class--is-active="state.isSelectedDashboardView"><?php esc_html_e( 'RSVPs', 'wporg' ); ?></button>
				<button type="button" <?php context_attribute( array( 'dashboardView' => 'memberships' ) ); ?> data-wp-on--click="actions.setDashboardView" data-wp-class--is-active="state.isSelectedDashboardView"><?php esc_html_e( 'Groups', 'wporg' ); ?></button>
				<button type="button" <?php context_attribute( array( 'dashboardView' => 'suggestions' ) ); ?> data-wp-on--click="actions.setDashboardView" data-wp-class--is-active="state.isSelectedDashboardView"><?php esc_html_e( 'Suggestions', 'wporg' ); ?></button>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( ! is_user_logged_in() ) : ?>
		<p class="wporg-events-empty">
			<?php
			printf(
				/* translators: %s: Login URL. */
				wp_kses_post( __( '<a href="%s">Log in with your WordPress.org account</a> to RSVP and manage your community events.', 'wporg' ) ),
				esc_url( wp_login_url( get_current_url() ) )
			);
			?>
		</p>
	<?php else : ?>
		<div class="wporg-events-panel" <?php context_attribute( array( 'dashboardView' => 'rsvps' ) ); ?> data-wp-bind--hidden="state.isDashboardViewHidden">
			<h3><?php esc_html_e( 'Upcoming RSVPs', 'wporg' ); ?></h3>
			<?php if ( $rsvps ) : ?>
				<ul class="wporg-events-list">
					<?php foreach ( $rsvps as $rsvp ) : ?>
						<?php
						$event        = is_array( $rsvp['event'] ?? null ) ? $rsvp['event'] : array();
						$rsvp_context = get_event_rsvp_context( (int) ( $rsvp['event_id'] ?? 0 ), __( 'RSVP', 'wporg' ), $rsvp, $event );
						?>
						<li class="wporg-events-dashboard-item" <?php context_attribute( $rsvp_context ); ?>>
							<div class="wporg-events-dashboard-item__content">
								<a href="<?php echo esc_url( get_permalink( (int) ( $rsvp['event_id'] ?? 0 ) ) ); ?>"><?php echo esc_html( (string) ( $event['title'] ?? __( 'Event', 'wporg' ) ) ); ?></a>
								<span class="wporg-events-meta" data-wp-text="context.rsvpStatusLabel"><?php echo esc_html( (string) $rsvp_context['rsvpStatusLabel'] ); ?></span>
							</div>
							<div class="wporg-events-dashboard-item__actions">
								<button class="wporg-events-button" type="button" data-wp-on--click="actions.rsvp" data-wp-bind--disabled="state.isRsvpButtonDisabled" data-wp-text="context.rsvpButton">
									<?php echo esc_html( (string) $rsvp_context['rsvpButton'] ); ?>
								</button>
								<p class="wporg-events-status" data-wp-text="context.rsvpMessage"></p>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p><?php esc_html_e( 'You do not have any upcoming RSVPs.', 'wporg' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="wporg-events-panel" hidden <?php context_attribute( array( 'dashboardView' => 'memberships' ) ); ?> data-wp-bind--hidden="state.isDashboardViewHidden">
			<h3><?php esc_html_e( 'My groups', 'wporg' ); ?></h3>
			<?php if ( $memberships ) : ?>
				<ul class="wporg-events-list">
					<?php foreach ( $memberships as $membership ) : ?>
						<?php
						$group              = is_array( $membership['group'] ?? null ) ? $membership['group'] : array();
						$membership_context = get_group_membership_context( (int) ( $membership['group_id'] ?? 0 ), $membership );
						?>
						<li class="wporg-events-dashboard-item" <?php context_attribute( $membership_context ); ?>>
							<div class="wporg-events-dashboard-item__content">
								<a href="<?php echo esc_url( get_permalink( (int) ( $membership['group_id'] ?? 0 ) ) ); ?>"><?php echo esc_html( (string) ( $group['title'] ?? __( 'Group', 'wporg' ) ) ); ?></a>
								<span class="wporg-events-meta">
									<span data-wp-text="context.membershipRoleLabel"><?php echo esc_html( (string) $membership_context['membershipRoleLabel'] ); ?></span>
									<span data-wp-text="context.membershipStatusLabel"><?php echo esc_html( (string) $membership_context['membershipStatusLabel'] ); ?></span>
								</span>
								<?php if ( $membership_context['isGroupMember'] ) : ?>
									<?php render_membership_settings_form( $membership_context ); ?>
								<?php endif; ?>
							</div>
							<div class="wporg-events-dashboard-item__actions">
								<button class="wporg-events-button" type="button" data-wp-on--click="actions.toggleGroupMembership" data-wp-bind--disabled="context.membershipBusy" data-wp-text="context.membershipButton">
									<?php echo esc_html( (string) $membership_context['membershipButton'] ); ?>
								</button>
								<p class="wporg-events-status" data-wp-text="context.membershipMessage"></p>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p><?php esc_html_e( 'You have not joined any groups yet.', 'wporg' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="wporg-events-panel" hidden <?php context_attribute( array( 'dashboardView' => 'suggestions' ) ); ?> data-wp-bind--hidden="state.isDashboardViewHidden">
			<h3><?php esc_html_e( 'Group suggestions', 'wporg' ); ?></h3>
			<?php if ( $suggestions ) : ?>
				<ul class="wporg-events-list">
					<?php foreach ( $suggestions as $suggestion ) : ?>
						<?php
						$created_group_id   = (int) ( $suggestion['created_group_id'] ?? 0 );
						$duplicate_group_id = (int) ( $suggestion['duplicate_group_id'] ?? 0 );
						$review_note        = (string) ( $suggestion['review_note'] ?? '' );
						$review_status      = (string) ( $suggestion['review_status'] ?? 'pending' );
						$website_url        = (string) ( $suggestion['website_url'] ?? '' );
						?>
						<li class="wporg-events-dashboard-item">
							<div class="wporg-events-dashboard-item__content">
								<strong><?php echo esc_html( (string) ( $suggestion['title'] ?? __( 'Group suggestion', 'wporg' ) ) ); ?></strong>
								<ul class="wporg-events-meta">
									<?php if ( ! empty( $suggestion['location_label'] ) ) : ?>
										<li><?php echo esc_html( (string) $suggestion['location_label'] ); ?></li>
									<?php endif; ?>
									<?php if ( $website_url ) : ?>
										<li><a href="<?php echo esc_url( $website_url ); ?>" rel="external nofollow"><?php esc_html_e( 'Website', 'wporg' ); ?></a></li>
									<?php endif; ?>
									<li><?php echo esc_html( get_group_suggestion_review_status_label( $review_status ) ); ?></li>
								</ul>
								<?php if ( $review_note ) : ?>
									<p><?php echo esc_html( $review_note ); ?></p>
								<?php endif; ?>
							</div>
							<?php if ( $created_group_id || $duplicate_group_id ) : ?>
								<div class="wporg-events-dashboard-item__actions">
									<?php if ( $created_group_id ) : ?>
										<a class="wporg-events-button" href="<?php echo esc_url( get_permalink( $created_group_id ) ); ?>"><?php esc_html_e( 'View group', 'wporg' ); ?></a>
									<?php endif; ?>
									<?php if ( $duplicate_group_id ) : ?>
										<a href="<?php echo esc_url( get_permalink( $duplicate_group_id ) ); ?>"><?php esc_html_e( 'Existing group', 'wporg' ); ?></a>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p><?php esc_html_e( 'You have not suggested any groups yet.', 'wporg' ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</section>
<?php
echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block output is escaped while rendering.
