<?php
/**
 * Theme bootstrap for WordPress.org Events.
 *
 * @package WordPressdotorg\Events_Theme
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Events_Theme;

defined( 'ABSPATH' ) || exit;

const STORE_NAMESPACE = 'wporg/events';
const REST_NAMESPACE  = 'wporg-community-events/v1';

require_once __DIR__ . '/includes/helpers.php';

add_action( 'after_setup_theme', __NAMESPACE__ . '\setup_theme' );
add_action( 'init', __NAMESPACE__ . '\register_blocks' );
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_assets' );
add_filter( 'wporg_block_navigation_menus', __NAMESPACE__ . '\add_site_navigation_menus' );

/**
 * Set up block theme support.
 */
function setup_theme(): void {
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );

	add_editor_style( 'style.css' );
}

/**
 * Enqueue theme assets for the front end.
 */
function enqueue_assets(): void {
	$asset_path              = __DIR__ . '/style.css';
	$parent_style_path       = get_theme_root() . '/wporg-parent-2021/build/style.css';
	$parent_block_style_path = get_theme_root() . '/wporg-parent-2021/build/block-styles.css';
	$version                 = file_exists( $asset_path ) ? (string) filemtime( $asset_path ) : false;

	wp_enqueue_style(
		'wporg-parent-2021-style',
		get_theme_root_uri() . '/wporg-parent-2021/build/style.css',
		array( 'wporg-global-fonts' ),
		filemtime( $parent_style_path )
	);

	wp_enqueue_style(
		'wporg-parent-2021-block-styles',
		get_theme_root_uri() . '/wporg-parent-2021/build/block-styles.css',
		array( 'wporg-global-fonts' ),
		filemtime( $parent_block_style_path )
	);

	wp_enqueue_style(
		'wporg-events',
		get_stylesheet_uri(),
		array( 'wporg-parent-2021-style', 'wporg-parent-2021-block-styles' ),
		$version
	);
}

/**
 * Provide the local navigation menu consumed by the wporg/navigation block.
 *
 * @param array $menus Registered wporg navigation menus.
 * @return array
 */
function add_site_navigation_menus( array $menus ): array {
	if ( is_admin() ) {
		return $menus;
	}

	$menus['events'] = array(
		array(
			'label' => __( 'Events', 'wporg' ),
			'url'   => get_post_type_archive_url( 'wporg_ce_event', '/events/' ),
		),
		array(
			'label' => __( 'Groups', 'wporg' ),
			'url'   => get_post_type_archive_url( 'wporg_ce_group', '/groups/' ),
		),
		array(
			'label' => __( 'Venues', 'wporg' ),
			'url'   => get_post_type_archive_url( 'wporg_ce_venue', '/venues/' ),
		),
	);

	if ( is_user_logged_in() ) {
		$menus['events'][] = array(
			'className' => 'has-separator',
			'label'     => __( 'My events', 'wporg' ),
			'url'       => home_url( '/#my-events' ),
		);
	} else {
		global $wp;

		$menus['events'][] = array(
			'className' => 'has-separator',
			'label'     => __( 'Log in', 'wporg' ),
			'url'       => wp_login_url( home_url( $wp->request ) ),
		);
	}

	return $menus;
}

/**
 * Get a post type archive URL with a stable fallback for local bootstrap.
 *
 * @param string $post_type     Post type key.
 * @param string $fallback_path Fallback path.
 * @return string
 */
function get_post_type_archive_url( string $post_type, string $fallback_path ): string {
	$archive_url = get_post_type_archive_link( $post_type );

	if ( ! $archive_url ) {
		return home_url( $fallback_path );
	}

	return $archive_url;
}

/**
 * Register dynamic blocks and the shared Interactivity API module.
 */
function register_blocks(): void {
	$asset_path = __DIR__ . '/assets/js/interactivity.js';
	$version    = file_exists( $asset_path ) ? (string) filemtime( $asset_path ) : false;

	wp_register_script_module(
		'@wporg/events-theme',
		get_theme_file_uri( 'assets/js/interactivity.js' ),
		array( '@wordpress/interactivity' ),
		$version
	);

	foreach (
		array(
			'event-directory',
			'event-detail',
			'group-directory',
			'group-detail',
			'member-dashboard',
			'page-header',
			'venue-detail',
			'venue-directory',
		) as $block_name
	) {
		register_block_type( __DIR__ . "/blocks/{$block_name}" );
	}
}
