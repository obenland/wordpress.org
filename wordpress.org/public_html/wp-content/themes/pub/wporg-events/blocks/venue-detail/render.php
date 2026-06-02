<?php
/**
 * Server render callback for the Venue Detail block.
 *
 * @package WordPressdotorg\Events_Theme
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Events_Theme;

$venue_id         = get_the_ID();
$venue            = $venue_id ? get_venue( $venue_id ) : array();
$location         = $venue ? get_venue_location_label( $venue ) : '';
$address          = $venue ? get_venue_address_label( $venue ) : '';
$map_embed_url    = $venue ? get_venue_map_embed_url( $venue ) : '';
$map_external_url = $venue ? get_venue_external_map_url( $venue ) : '';
$map_title        = $venue ? sprintf(
	/* translators: %s: venue name. */
	__( 'Map showing %s', 'wporg' ),
	(string) ( $venue['title'] ?? __( 'venue location', 'wporg' ) )
) : '';

ob_start();
?>
<section <?php echo get_block_wrapper_attributes( array( 'class' => 'wporg-events-detail wporg-events-venue-detail' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( $venue ) : ?>
		<h1><?php echo esc_html( (string) ( $venue['title'] ?? '' ) ); ?></h1>

		<?php if ( $location || $address || ! empty( $venue['online_url'] ) ) : ?>
			<ul class="wporg-events-meta">
				<?php if ( $location ) : ?>
					<li><?php echo esc_html( $location ); ?></li>
				<?php endif; ?>
				<?php if ( $address ) : ?>
					<li><?php echo esc_html( $address ); ?></li>
				<?php endif; ?>
				<?php if ( ! empty( $venue['online_url'] ) ) : ?>
					<li><a href="<?php echo esc_url( (string) $venue['online_url'] ); ?>"><?php esc_html_e( 'Online venue', 'wporg' ); ?></a></li>
				<?php endif; ?>
			</ul>
		<?php endif; ?>

		<div class="wporg-events-detail__body">
			<div>
				<?php if ( ! empty( $venue['description'] ) ) : ?>
					<div><?php echo wp_kses_post( wpautop( (string) $venue['description'] ) ); ?></div>
				<?php endif; ?>
			</div>

			<aside class="wporg-events-panel">
				<h2><?php esc_html_e( 'Venue information', 'wporg' ); ?></h2>
				<?php if ( ! empty( $venue['accessibility_notes'] ) ) : ?>
					<h3><?php esc_html_e( 'Accessibility', 'wporg' ); ?></h3>
					<p><?php echo esc_html( (string) $venue['accessibility_notes'] ); ?></p>
				<?php endif; ?>
				<?php if ( $map_embed_url && $map_external_url ) : ?>
					<h3><?php esc_html_e( 'Map', 'wporg' ); ?></h3>
					<div class="wporg-events-map">
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
			</aside>
		</div>
	<?php else : ?>
		<p class="wporg-events-empty"><?php esc_html_e( 'Venue not found.', 'wporg' ); ?></p>
	<?php endif; ?>
</section>
<?php
echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block output is escaped while rendering.
