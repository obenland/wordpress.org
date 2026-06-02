<?php
/**
 * Server render callback for the Page Header block.
 *
 * @package WordPressdotorg\Events_Theme
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Events_Theme;

$variant = is_array( $attributes ?? null ) ? (string) ( $attributes['variant'] ?? 'front' ) : 'front';
$headers = array(
	'events' => array(
		'description' => __( 'Find local meetups, online workshops, contributor sessions, and official WordPress community events.', 'wporg' ),
		'title'       => __( 'Events', 'wporg' ),
	),
	'front'  => array(
		'description' => __( 'Find local meetups, online workshops, contributor sessions, and official WordPress community groups.', 'wporg' ),
		'title'       => __( 'Events', 'wporg' ),
	),
	'groups' => array(
		'description' => __( 'Browse local chapters, online groups, and topic communities.', 'wporg' ),
		'title'       => __( 'Groups', 'wporg' ),
	),
	'venues' => array(
		'description' => __( 'Find reusable physical and online spaces for WordPress community events.', 'wporg' ),
		'title'       => __( 'Venues', 'wporg' ),
	),
);
$header  = $headers[ $variant ] ?? $headers['front'];

?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'wporg-events-hero' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<h1 class="wp-block-heading"><?php echo esc_html( $header['title'] ); ?></h1>
	<p><?php echo esc_html( $header['description'] ); ?></p>
</div>
