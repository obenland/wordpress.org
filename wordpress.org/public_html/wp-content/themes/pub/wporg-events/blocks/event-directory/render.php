<?php
/**
 * Server render callback for the Event Directory block.
 *
 * @package WordPressdotorg\Events_Theme
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Events_Theme;

configure_interactivity_store();

$events   = get_events(
	array(
		'timeframe' => 'upcoming',
	)
);
$now      = time();
$rsvp_map = get_current_user_rsvp_map();

$country_terms      = get_taxonomy_term_options( 'wporg_ce_country' );
$event_format_terms = get_taxonomy_term_options( 'wporg_ce_event_format' );
$event_type_terms   = get_taxonomy_term_options( 'wporg_ce_event_type' );
$language_terms     = get_taxonomy_term_options( 'wporg_ce_language' );
$topic_terms        = get_taxonomy_term_options( 'wporg_ce_topic' );

ob_start();
?>
<section <?php echo get_block_wrapper_attributes( array( 'class' => 'wporg-events-section wporg-events-event-directory' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-wp-interactive="<?php echo esc_attr( STORE_NAMESPACE ); ?>">
	<div class="wporg-events-section__header">
		<div>
			<h2><?php esc_html_e( 'Upcoming events', 'wporg' ); ?></h2>
			<p><?php esc_html_e( 'Find WordPress meetups, workshops, contributor sessions, and community events.', 'wporg' ); ?></p>
		</div>
		<div class="wporg-events-segmented" role="group" aria-label="<?php esc_attr_e( 'Event timeframe', 'wporg' ); ?>">
			<button type="button" <?php context_attribute( array( 'targetTimeframe' => 'upcoming' ) ); ?> data-wp-on--click="actions.setEventTimeframe" data-wp-class--is-active="state.isSelectedEventTimeframe"><?php esc_html_e( 'Upcoming', 'wporg' ); ?></button>
			<button type="button" <?php context_attribute( array( 'targetTimeframe' => 'past' ) ); ?> data-wp-on--click="actions.setEventTimeframe" data-wp-class--is-active="state.isSelectedEventTimeframe"><?php esc_html_e( 'Past', 'wporg' ); ?></button>
			<button type="button" <?php context_attribute( array( 'targetTimeframe' => 'all' ) ); ?> data-wp-on--click="actions.setEventTimeframe" data-wp-class--is-active="state.isSelectedEventTimeframe"><?php esc_html_e( 'All', 'wporg' ); ?></button>
		</div>
	</div>

	<div class="wporg-events-filter-bar" aria-label="<?php esc_attr_e( 'Filter events', 'wporg' ); ?>">
		<label>
			<span class="screen-reader-text"><?php esc_html_e( 'Search events', 'wporg' ); ?></span>
			<input class="wporg-events-input" type="search" placeholder="<?php esc_attr_e( 'Search events', 'wporg' ); ?>" data-wp-on--input="actions.updateEventQuery" />
		</label>
		<?php render_taxonomy_filter_select( 'eventCountry', __( 'Filter events by country', 'wporg' ), __( 'All countries', 'wporg' ), $country_terms, 'actions.updateEventFilter' ); ?>
		<?php render_taxonomy_filter_select( 'eventType', __( 'Filter events by type', 'wporg' ), __( 'All event types', 'wporg' ), $event_type_terms, 'actions.updateEventFilter' ); ?>
		<?php render_taxonomy_filter_select( 'eventFormat', __( 'Filter events by format', 'wporg' ), __( 'All formats', 'wporg' ), $event_format_terms, 'actions.updateEventFilter' ); ?>
		<?php render_taxonomy_filter_select( 'eventLanguage', __( 'Filter events by language', 'wporg' ), __( 'All languages', 'wporg' ), $language_terms, 'actions.updateEventFilter' ); ?>
		<?php render_taxonomy_filter_select( 'eventTopic', __( 'Filter events by topic', 'wporg' ), __( 'All topics', 'wporg' ), $topic_terms, 'actions.updateEventFilter' ); ?>
	</div>

	<?php if ( $events ) : ?>
		<ul class="wporg-events-grid">
			<?php foreach ( $events as $event ) : ?>
				<?php
				$event_id        = (int) ( $event['id'] ?? 0 );
				$start_utc       = (string) ( $event['start_utc'] ?? '' );
				$start_timestamp = get_event_start_timestamp( $start_utc );
				$location        = get_event_location_label( $event );
				$classification  = get_event_classification_label( $event );
				$search_text     = get_search_text(
					array(
						$event['title'] ?? '',
						$event['excerpt'] ?? '',
						$classification,
						get_event_taxonomy_search_text( $event ),
						get_event_approval_status_label( (string) ( $event['approval_status'] ?? '' ) ),
						$location,
					)
				);
				$is_past         = $start_timestamp && $start_timestamp < $now;
				$is_canceled     = is_event_canceled( $event );
				$has_questions   = ! empty( $event['rsvp_questions'] );
				$event_context   = array_merge(
					get_event_rsvp_context( $event_id, __( 'RSVP', 'wporg' ), $rsvp_map[ $event_id ] ?? array(), $event ),
					array(
						'eventCountries' => get_event_term_slugs( $event, 'countries' ),
						'eventFormats'   => get_event_term_slugs( $event, 'event_formats' ),
						'eventLanguages' => get_event_term_slugs( $event, 'languages' ),
						'eventTopics'    => get_event_term_slugs( $event, 'topics' ),
						'eventTypes'     => get_event_term_slugs( $event, 'event_types' ),
						'searchText'     => $search_text,
						'startTimestamp' => $start_timestamp,
					)
				);
				?>
				<li class="wporg-events-card wporg-events-card--event" <?php context_attribute( $event_context ); ?> data-wp-bind--hidden="state.isEventHidden" <?php echo $is_past ? 'hidden' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<div class="wporg-events-card__layout">
						<?php if ( $start_timestamp ) : ?>
							<time class="wporg-events-date-badge" datetime="<?php echo esc_attr( gmdate( 'c', $start_timestamp ) ); ?>">
								<span class="wporg-events-date-badge__month"><?php echo esc_html( wp_date( 'M', $start_timestamp ) ); ?></span>
								<span class="wporg-events-date-badge__day"><?php echo esc_html( wp_date( 'j', $start_timestamp ) ); ?></span>
							</time>
						<?php endif; ?>
						<div class="wporg-events-card__body">
							<h3><a href="<?php echo esc_url( get_permalink( $event_id ) ); ?>"><?php echo esc_html( (string) ( $event['title'] ?? '' ) ); ?></a></h3>
							<ul class="wporg-events-meta">
								<?php if ( $start_utc ) : ?>
									<li><?php echo esc_html( get_event_start_label( $start_utc ) ); ?></li>
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
							<?php if ( ! empty( $event['excerpt'] ) ) : ?>
								<p><?php echo esc_html( (string) $event['excerpt'] ); ?></p>
							<?php endif; ?>
							<?php if ( $has_questions && empty( $event_context['isEventRsvped'] ) ) : ?>
								<a class="wporg-events-button" href="<?php echo esc_url( get_permalink( $event_id ) ); ?>"><?php esc_html_e( 'RSVP', 'wporg' ); ?></a>
							<?php else : ?>
								<button class="wporg-events-button" type="button" data-wp-on--click="actions.rsvp" data-wp-bind--disabled="state.isRsvpButtonDisabled" data-wp-text="context.rsvpButton">
									<?php echo esc_html( (string) $event_context['rsvpButton'] ); ?>
								</button>
							<?php endif; ?>
							<p class="wporg-events-status" data-wp-text="context.rsvpMessage"></p>
						</div>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<p class="wporg-events-empty"><?php esc_html_e( 'No events are available yet.', 'wporg' ); ?></p>
	<?php endif; ?>
</section>
<?php
echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block output is escaped while rendering.
