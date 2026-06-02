<?php
/**
 * Server render callback for the Venue Directory block.
 *
 * @package WordPressdotorg\Events_Theme
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Events_Theme;

configure_interactivity_store();

$venues = get_venues();

ob_start();
?>
<section <?php echo get_block_wrapper_attributes( array( 'class' => 'wporg-events-section wporg-events-venue-directory' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-wp-interactive="<?php echo esc_attr( STORE_NAMESPACE ); ?>">
	<div class="wporg-events-section__header">
		<div>
			<h2><?php esc_html_e( 'Venues', 'wporg' ); ?></h2>
			<p><?php esc_html_e( 'Browse physical and online spaces used by WordPress community organizers.', 'wporg' ); ?></p>
		</div>
		<label>
			<span class="screen-reader-text"><?php esc_html_e( 'Search venues', 'wporg' ); ?></span>
			<input class="wporg-events-input" type="search" placeholder="<?php esc_attr_e( 'Search venues', 'wporg' ); ?>" data-wp-on--input="actions.updateVenueQuery" />
		</label>
	</div>

	<?php if ( $venues ) : ?>
		<ul class="wporg-events-grid">
			<?php foreach ( $venues as $venue ) : ?>
				<?php
				$venue_id      = (int) ( $venue['id'] ?? 0 );
				$location      = get_venue_location_label( $venue );
				$address       = get_venue_address_label( $venue );
				$search_text   = get_search_text(
					array(
						$venue['title'] ?? '',
						$venue['description'] ?? '',
						$location,
						$address,
					)
				);
				$venue_url     = (string) ( $venue['url'] ?? get_permalink( $venue_id ) );
				$online_url    = (string) ( $venue['online_url'] ?? '' );
				$accessibility = (string) ( $venue['accessibility_notes'] ?? '' );
				?>
				<li class="wporg-events-card" <?php context_attribute( array( 'searchText' => $search_text ) ); ?> data-wp-bind--hidden="state.isVenueHidden">
					<h3><a href="<?php echo esc_url( $venue_url ); ?>"><?php echo esc_html( (string) ( $venue['title'] ?? '' ) ); ?></a></h3>
					<?php if ( $location || $address || $online_url ) : ?>
						<ul class="wporg-events-meta">
							<?php if ( $location ) : ?>
								<li><?php echo esc_html( $location ); ?></li>
							<?php endif; ?>
							<?php if ( $address ) : ?>
								<li><?php echo esc_html( $address ); ?></li>
							<?php endif; ?>
							<?php if ( $online_url ) : ?>
								<li><?php esc_html_e( 'Online venue', 'wporg' ); ?></li>
							<?php endif; ?>
						</ul>
					<?php endif; ?>
					<?php if ( ! empty( $venue['description'] ) ) : ?>
						<p><?php echo esc_html( (string) $venue['description'] ); ?></p>
					<?php endif; ?>
					<?php if ( $accessibility ) : ?>
						<p class="wporg-events-meta"><?php echo esc_html( $accessibility ); ?></p>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<p class="wporg-events-empty"><?php esc_html_e( 'No venues are available yet.', 'wporg' ); ?></p>
	<?php endif; ?>
</section>
<?php
echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block output is escaped while rendering.
