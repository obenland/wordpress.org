<?php
/**
 * Admin and editor UI helpers for Community Events.
 *
 * @package WordPressdotorg\Community_Events
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Community_Events;

defined( 'ABSPATH' ) || exit;

add_action( 'add_meta_boxes_' . POST_TYPE_EVENT, __NAMESPACE__ . '\add_event_admin_meta_boxes' );
add_action( 'save_post_' . POST_TYPE_EVENT, __NAMESPACE__ . '\save_event_admin_meta', 10, 2 );

/**
 * Register event editor meta boxes.
 *
 * @param \WP_Post $post Current event post.
 */
function add_event_admin_meta_boxes( \WP_Post $post ): void {
	unset( $post );

	add_meta_box(
		'wporg_ce_event_details',
		__( 'Event details', 'wporg' ),
		__NAMESPACE__ . '\render_event_admin_details_meta_box',
		POST_TYPE_EVENT,
		'normal',
		'high'
	);
}

/**
 * Render the event details meta box.
 *
 * @param \WP_Post $post Current event post.
 */
function render_event_admin_details_meta_box( \WP_Post $post ): void {
	$event_id    = (int) $post->ID;
	$group_id    = (int) get_post_meta( $event_id, 'wporg_ce_group_id', true );
	$venue_id    = (int) get_post_meta( $event_id, 'wporg_ce_venue_id', true );
	$rsvp_policy = (string) get_post_meta( $event_id, 'wporg_ce_rsvp_policy', true );
	$timezone    = (string) get_post_meta( $event_id, 'wporg_ce_timezone', true );

	if ( '' === $timezone ) {
		$timezone = wp_timezone_string();
	}

	if ( '' === $rsvp_policy ) {
		$rsvp_policy = 'open';
	}

	wp_nonce_field( 'wporg_ce_save_event_details', 'wporg_ce_event_details_nonce' );
	?>
	<div class="wporg-ce-admin-event-details">
		<p>
			<label for="wporg-ce-event-group"><?php esc_html_e( 'Group', 'wporg' ); ?></label>
			<select id="wporg-ce-event-group" name="wporg_ce_event_details[group_id]" class="widefat">
				<option value="0"><?php esc_html_e( 'No group selected', 'wporg' ); ?></option>
				<?php foreach ( get_event_admin_group_options() as $group ) : ?>
					<option value="<?php echo esc_attr( (string) $group->ID ); ?>" <?php selected( $group_id, (int) $group->ID ); ?>>
						<?php echo esc_html( get_the_title( $group ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="wporg-ce-event-start"><?php esc_html_e( 'Start date and time', 'wporg' ); ?></label>
			<input id="wporg-ce-event-start" name="wporg_ce_event_details[start]" type="datetime-local" class="widefat" value="<?php echo esc_attr( get_event_admin_datetime_local_value( (string) get_post_meta( $event_id, 'wporg_ce_start_utc', true ) ) ); ?>" />
		</p>
		<p>
			<label for="wporg-ce-event-end"><?php esc_html_e( 'End date and time', 'wporg' ); ?></label>
			<input id="wporg-ce-event-end" name="wporg_ce_event_details[end]" type="datetime-local" class="widefat" value="<?php echo esc_attr( get_event_admin_datetime_local_value( (string) get_post_meta( $event_id, 'wporg_ce_end_utc', true ) ) ); ?>" />
		</p>
		<p>
			<label for="wporg-ce-event-timezone"><?php esc_html_e( 'Timezone', 'wporg' ); ?></label>
			<input id="wporg-ce-event-timezone" name="wporg_ce_event_details[timezone]" type="text" class="widefat" value="<?php echo esc_attr( $timezone ); ?>" />
		</p>
		<p>
			<label for="wporg-ce-event-venue"><?php esc_html_e( 'Venue', 'wporg' ); ?></label>
			<select id="wporg-ce-event-venue" name="wporg_ce_event_details[venue_id]" class="widefat">
				<option value="0"><?php esc_html_e( 'Online or to be announced', 'wporg' ); ?></option>
				<?php foreach ( get_event_admin_venue_options() as $venue ) : ?>
					<option value="<?php echo esc_attr( (string) $venue->ID ); ?>" <?php selected( $venue_id, (int) $venue->ID ); ?>>
						<?php echo esc_html( get_the_title( $venue ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="wporg-ce-event-online-url"><?php esc_html_e( 'Online event URL', 'wporg' ); ?></label>
			<input id="wporg-ce-event-online-url" name="wporg_ce_event_details[online_url]" type="url" class="widefat" value="<?php echo esc_attr( (string) get_post_meta( $event_id, 'wporg_ce_online_url', true ) ); ?>" />
		</p>
		<p>
			<label for="wporg-ce-event-capacity"><?php esc_html_e( 'Capacity', 'wporg' ); ?></label>
			<input id="wporg-ce-event-capacity" name="wporg_ce_event_details[capacity]" type="number" min="0" step="1" class="widefat" value="<?php echo esc_attr( (string) max( 0, (int) get_post_meta( $event_id, 'wporg_ce_capacity', true ) ) ); ?>" />
		</p>
		<p>
			<label for="wporg-ce-event-rsvp-policy"><?php esc_html_e( 'RSVP policy', 'wporg' ); ?></label>
			<select id="wporg-ce-event-rsvp-policy" name="wporg_ce_event_details[rsvp_policy]" class="widefat">
				<option value="open" <?php selected( 'open', $rsvp_policy ); ?>><?php esc_html_e( 'Open', 'wporg' ); ?></option>
				<option value="closed" <?php selected( 'closed', $rsvp_policy ); ?>><?php esc_html_e( 'Closed', 'wporg' ); ?></option>
			</select>
		</p>
	</div>
	<?php
}

/**
 * Save editable event details from wp-admin.
 *
 * @param int      $post_id Event post ID.
 * @param \WP_Post $post    Event post object.
 */
function save_event_admin_meta( int $post_id, \WP_Post $post ): void {
	if ( ! isset( $_POST['wporg_ce_event_details_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['wporg_ce_event_details_nonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'wporg_ce_save_event_details' ) ) {
		return;
	}

	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$fields = array();

	if ( isset( $_POST['wporg_ce_event_details'] ) && is_array( $_POST['wporg_ce_event_details'] ) ) {
		$fields = wp_unslash( $_POST['wporg_ce_event_details'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per field below after nonce verification.
	}

	$group_id = max( 0, absint( $fields['group_id'] ?? 0 ) );

	update_post_meta( $post_id, 'wporg_ce_group_id', $group_id );
	update_post_meta( $post_id, 'wporg_ce_venue_id', max( 0, absint( $fields['venue_id'] ?? 0 ) ) );
	update_post_meta( $post_id, 'wporg_ce_start_utc', get_event_admin_utc_value( (string) ( $fields['start'] ?? '' ) ) );
	update_post_meta( $post_id, 'wporg_ce_end_utc', get_event_admin_utc_value( (string) ( $fields['end'] ?? '' ) ) );
	update_post_meta( $post_id, 'wporg_ce_timezone', sanitize_text_field( (string) ( $fields['timezone'] ?? '' ) ) );
	update_post_meta( $post_id, 'wporg_ce_capacity', max( 0, absint( $fields['capacity'] ?? 0 ) ) );
	update_post_meta( $post_id, 'wporg_ce_online_url', esc_url_raw( (string) ( $fields['online_url'] ?? '' ) ) );
	update_post_meta( $post_id, 'wporg_ce_rsvp_policy', get_allowed_value( sanitize_key( (string) ( $fields['rsvp_policy'] ?? '' ) ), array( 'open', 'closed' ), 'open' ) );

	if ( '' === (string) get_post_meta( $post_id, 'wporg_ce_approval_status', true ) ) {
		update_post_meta( $post_id, 'wporg_ce_approval_status', EVENT_APPROVAL_STATUS_APPROVED );
	}

	if ( $group_id !== (int) $post->post_parent ) {
		remove_action( 'save_post_' . POST_TYPE_EVENT, __NAMESPACE__ . '\save_event_admin_meta', 10 );
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_parent' => $group_id,
			)
		);
		add_action( 'save_post_' . POST_TYPE_EVENT, __NAMESPACE__ . '\save_event_admin_meta', 10, 2 );
	}
}

/**
 * Get selectable public groups for the event editor.
 *
 * @return \WP_Post[]
 */
function get_event_admin_group_options(): array {
	return get_posts(
		array(
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'post_status'            => 'publish',
			'post_type'              => POST_TYPE_GROUP,
			'posts_per_page'         => 100,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
}

/**
 * Get selectable public venues for the event editor.
 *
 * @return \WP_Post[]
 */
function get_event_admin_venue_options(): array {
	return get_posts(
		array(
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'post_status'            => 'publish',
			'post_type'              => POST_TYPE_VENUE,
			'posts_per_page'         => 100,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
}

/**
 * Convert a stored UTC datetime to a datetime-local input value.
 *
 * @param string $utc_date Date-time in UTC.
 *
 * @return string
 */
function get_event_admin_datetime_local_value( string $utc_date ): string {
	$timestamp = strtotime( $utc_date );

	if ( false === $timestamp ) {
		return '';
	}

	return wp_date( 'Y-m-d\TH:i', $timestamp, wp_timezone() );
}

/**
 * Convert a datetime-local input value from the site timezone to UTC.
 *
 * @param string $local_value Date-time value from the editor field.
 *
 * @return string
 */
function get_event_admin_utc_value( string $local_value ): string {
	$local_value = trim( $local_value );

	if ( '' === $local_value ) {
		return '';
	}

	$date = \DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', $local_value, wp_timezone() );

	if ( ! $date instanceof \DateTimeImmutable ) {
		return '';
	}

	return $date->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' );
}
