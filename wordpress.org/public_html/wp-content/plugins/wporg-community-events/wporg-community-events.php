<?php
/**
 * Plugin Name: WordPress.org Community Events
 * Plugin URI:  https://wordpress.org/
 * Description: Provides the data model for WordPress.org community groups, events, RSVPs, and organizer workflows.
 * Version:     0.1.0
 * Author:      WordPress.org
 * Author URI:  https://wordpress.org/
 * Text Domain: wporg
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 * @package WordPressdotorg\Community_Events
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Community_Events;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/relationships.php';
require_once __DIR__ . '/includes/imports.php';
require_once __DIR__ . '/includes/group-suggestions.php';
require_once __DIR__ . '/includes/profiles.php';
require_once __DIR__ . '/includes/rest-api.php';

const POST_TYPE_GROUP            = 'wporg_ce_group';
const POST_TYPE_EVENT            = 'wporg_ce_event';
const POST_TYPE_VENUE            = 'wporg_ce_venue';
const POST_TYPE_GROUP_SUGGESTION = 'wporg_ce_suggest';
const POST_TYPE_MEMBERSHIP       = 'wporg_ce_member';
const POST_TYPE_RSVP             = 'wporg_ce_rsvp';
const POST_TYPE_FEEDBACK         = 'wporg_ce_feedback';
const POST_TYPE_IMPORT           = 'wporg_ce_import';

const TAXONOMY_GROUP_TYPE   = 'wporg_ce_group_type';
const TAXONOMY_EVENT_TYPE   = 'wporg_ce_event_type';
const TAXONOMY_EVENT_FORMAT = 'wporg_ce_event_format';
const TAXONOMY_TOPIC        = 'wporg_ce_topic';
const TAXONOMY_LANGUAGE     = 'wporg_ce_language';
const TAXONOMY_COUNTRY      = 'wporg_ce_country';

require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/comments.php';

add_action( 'init', __NAMESPACE__ . '\register_post_types' );
add_action( 'init', __NAMESPACE__ . '\register_taxonomies' );
add_action( 'init', __NAMESPACE__ . '\register_meta_fields' );
add_action( 'init', __NAMESPACE__ . '\maybe_schedule_event_reminders' );
add_action( 'rest_api_init', __NAMESPACE__ . '\register_rest_routes' );
add_action( EVENT_REMINDER_CRON_HOOK, __NAMESPACE__ . '\send_due_event_reminders' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\clear_event_reminder_schedule' );

/**
 * Register public/editorial and private/operational post types.
 */
function register_post_types(): void {
	$supports = array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'author' );

	register_post_type(
		POST_TYPE_GROUP,
		array(
			'labels'       => get_post_type_labels( 'Community Groups', 'Community Group' ),
			'description'  => 'Official WordPress community groups, local chapters, online groups, and topic communities.',
			'public'       => true,
			'has_archive'  => 'groups',
			'menu_icon'    => 'dashicons-groups',
			'rewrite'      => array( 'slug' => 'groups' ),
			'show_in_rest' => true,
			'supports'     => $supports,
		)
	);

	register_post_type(
		POST_TYPE_GROUP_SUGGESTION,
		array(
			'labels'              => get_post_type_labels( 'Group Suggestions', 'Group Suggestion' ),
			'description'         => 'Community-submitted group suggestions awaiting Community Team review.',
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'exclude_from_search' => true,
			'can_export'          => true,
			'menu_icon'           => 'dashicons-lightbulb',
			'rest_base'           => 'group-suggestions',
			'show_in_rest'        => true,
			'supports'            => array( 'title', 'editor', 'excerpt', 'author', 'revisions', 'custom-fields' ),
		)
	);

	register_post_type(
		POST_TYPE_EVENT,
		array(
			'labels'       => get_post_type_labels( 'Community Events', 'Community Event' ),
			'description'  => 'Meetups, workshops, contributor events, trainings, and other WordPress community events.',
			'public'       => true,
			'has_archive'  => 'events',
			'menu_icon'    => 'dashicons-calendar-alt',
			'rewrite'      => array( 'slug' => 'events' ),
			'show_in_rest' => true,
			'supports'     => array_merge( $supports, array( 'comments' ) ),
		)
	);

	register_post_type(
		POST_TYPE_VENUE,
		array(
			'labels'              => get_post_type_labels( 'Venues', 'Venue' ),
			'description'         => 'Reusable physical and online venues for community events.',
			'public'              => true,
			'exclude_from_search' => true,
			'has_archive'         => 'venues',
			'menu_icon'           => 'dashicons-location-alt',
			'rewrite'             => array( 'slug' => 'venues' ),
			'show_in_rest'        => true,
			'supports'            => array( 'title', 'editor', 'revisions' ),
		)
	);

	register_relationship_post_type(
		POST_TYPE_MEMBERSHIP,
		'Memberships',
		'Membership',
		'Private user-to-group membership records.'
	);

	register_relationship_post_type(
		POST_TYPE_RSVP,
		'RSVPs',
		'RSVP',
		'Private user-to-event RSVP and waitlist records.'
	);

	register_feedback_post_type();

	register_relationship_post_type(
		POST_TYPE_IMPORT,
		'Imports',
		'Import',
		'Private migration records for imported community groups and events.'
	);
}

/**
 * Register private event feedback records.
 */
function register_feedback_post_type(): void {
	register_post_type(
		POST_TYPE_FEEDBACK,
		array(
			'labels'              => get_post_type_labels( 'Event Feedback', 'Event Feedback' ),
			'description'         => 'Private attendee-to-event feedback records.',
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'exclude_from_search' => true,
			'hierarchical'        => false,
			'can_export'          => true,
			'show_in_rest'        => true,
			'supports'            => array( 'title', 'editor', 'author', 'custom-fields' ),
		)
	);
}

/**
 * Register a private operational post type.
 *
 * Relationship objects use post_author for the WordPress.org user and
 * post_parent for the related group or event whenever a parent exists.
 *
 * @param string $post_type   Post type key.
 * @param string $plural      Plural label.
 * @param string $singular    Singular label.
 * @param string $description Post type description.
 */
function register_relationship_post_type( string $post_type, string $plural, string $singular, string $description ): void {
	register_post_type(
		$post_type,
		array(
			'labels'              => get_post_type_labels( $plural, $singular ),
			'description'         => $description,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'exclude_from_search' => true,
			'hierarchical'        => false,
			'can_export'          => true,
			'show_in_rest'        => true,
			'supports'            => array( 'title', 'author', 'custom-fields' ),
		)
	);
}

/**
 * Register taxonomies for discovery and event classification.
 */
function register_taxonomies(): void {
	register_taxonomy(
		TAXONOMY_GROUP_TYPE,
		array( POST_TYPE_GROUP, POST_TYPE_GROUP_SUGGESTION ),
		get_taxonomy_args(
			'Group Types',
			'Group Type',
			array( 'slug' => 'group-type' ),
			false
		)
	);

	register_taxonomy(
		TAXONOMY_EVENT_TYPE,
		array( POST_TYPE_EVENT ),
		get_taxonomy_args(
			'Event Types',
			'Event Type',
			array( 'slug' => 'event-type' ),
			false
		)
	);

	register_taxonomy(
		TAXONOMY_EVENT_FORMAT,
		array( POST_TYPE_EVENT ),
		get_taxonomy_args(
			'Event Formats',
			'Event Format',
			array( 'slug' => 'event-format' ),
			false
		)
	);

	register_taxonomy(
		TAXONOMY_TOPIC,
		array( POST_TYPE_GROUP, POST_TYPE_GROUP_SUGGESTION, POST_TYPE_EVENT ),
		get_taxonomy_args(
			'Community Topics',
			'Community Topic',
			array( 'slug' => 'topic' ),
			false
		)
	);

	register_taxonomy(
		TAXONOMY_LANGUAGE,
		array( POST_TYPE_GROUP, POST_TYPE_GROUP_SUGGESTION, POST_TYPE_EVENT ),
		get_taxonomy_args(
			'Languages',
			'Language',
			array( 'slug' => 'language' ),
			false
		)
	);

	register_taxonomy(
		TAXONOMY_COUNTRY,
		array( POST_TYPE_GROUP, POST_TYPE_GROUP_SUGGESTION, POST_TYPE_EVENT, POST_TYPE_VENUE ),
		get_taxonomy_args(
			'Countries',
			'Country',
			array( 'slug' => 'country' ),
			false
		)
	);
}

/**
 * Register object meta used by the first data model.
 */
function register_meta_fields(): void {
	register_group_meta();
	register_group_suggestion_meta();
	register_event_meta();
	register_venue_meta();
	register_membership_meta();
	register_rsvp_meta();
	register_feedback_meta();
	register_import_meta();
}

/**
 * Register community group metadata.
 */
function register_group_meta(): void {
	foreach (
		array(
			'wporg_ce_timezone'          => 'string',
			'wporg_ce_city'              => 'string',
			'wporg_ce_region'            => 'string',
			'wporg_ce_location_label'    => 'string',
			'wporg_ce_website_url'       => 'string',
			'wporg_ce_official_status'   => 'string',
			'wporg_ce_source_meetup_url' => 'string',
			'wporg_ce_source_meetup_id'  => 'string',
			'wporg_ce_member_count'      => 'integer',
			'wporg_ce_event_count'       => 'integer',
		) as $key => $type
	) {
		register_post_meta_field( POST_TYPE_GROUP, $key, $type );
	}
}

/**
 * Register group suggestion metadata.
 */
function register_group_suggestion_meta(): void {
	foreach (
		array(
			'wporg_ce_timezone'            => 'string',
			'wporg_ce_city'                => 'string',
			'wporg_ce_region'              => 'string',
			'wporg_ce_location_label'      => 'string',
			'wporg_ce_website_url'         => 'string',
			'wporg_ce_reviewed_by_user_id' => 'integer',
			'wporg_ce_reviewed_at_utc'     => 'string',
			'wporg_ce_review_note'         => 'string',
			'wporg_ce_created_group_id'    => 'integer',
			'wporg_ce_duplicate_group_id'  => 'integer',
		) as $key => $type
	) {
		register_post_meta_field( POST_TYPE_GROUP_SUGGESTION, $key, $type );
	}

	register_post_meta(
		POST_TYPE_GROUP_SUGGESTION,
		'wporg_ce_review_status',
		array(
			'auth_callback' => __NAMESPACE__ . '\can_edit_registered_meta',
			'show_in_rest'  => array(
				'schema' => array(
					'context' => array( 'view', 'edit' ),
					'enum'    => get_group_suggestion_review_statuses(),
					'type'    => 'string',
				),
			),
			'single'        => true,
			'type'          => 'string',
		)
	);
}

/**
 * Register community event metadata.
 */
function register_event_meta(): void {
	foreach (
		array(
			'wporg_ce_group_id'                      => 'integer',
			'wporg_ce_venue_id'                      => 'integer',
			'wporg_ce_start_utc'                     => 'string',
			'wporg_ce_end_utc'                       => 'string',
			'wporg_ce_timezone'                      => 'string',
			'wporg_ce_host_user_id'                  => 'integer',
			'wporg_ce_capacity'                      => 'integer',
			'wporg_ce_rsvp_policy'                   => 'string',
			'wporg_ce_rsvp_count'                    => 'integer',
			'wporg_ce_waitlist_count'                => 'integer',
			'wporg_ce_approval_status'               => 'string',
			'wporg_ce_canceled_at_utc'               => 'string',
			'wporg_ce_canceled_by_user_id'           => 'integer',
			'wporg_ce_cancellation_reason'           => 'string',
			'wporg_ce_online_url'                    => 'string',
			'wporg_ce_attendee_reminder_sent_at_utc' => 'string',
			'wporg_ce_attendee_reminder_start_utc'   => 'string',
			'wporg_ce_copied_from_event_id'          => 'integer',
			'wporg_ce_source_meetup_url'             => 'string',
			'wporg_ce_source_meetup_id'              => 'string',
		) as $key => $type
	) {
		register_post_meta_field( POST_TYPE_EVENT, $key, $type );
	}

	register_post_meta(
		POST_TYPE_EVENT,
		'wporg_ce_host_user_ids',
		array(
			'auth_callback' => __NAMESPACE__ . '\can_edit_registered_meta',
			'show_in_rest'  => array(
				'schema' => array(
					'context' => array( 'view', 'edit' ),
					'items'   => array(
						'type' => 'integer',
					),
					'type'    => 'array',
				),
			),
			'single'        => true,
			'type'          => 'array',
		)
	);

	register_post_meta(
		POST_TYPE_EVENT,
		'wporg_ce_rsvp_questions',
		array(
			'auth_callback'     => __NAMESPACE__ . '\can_edit_registered_meta',
			'sanitize_callback' => __NAMESPACE__ . '\sanitize_event_rsvp_questions',
			'show_in_rest'      => array(
				'schema' => get_event_rsvp_questions_schema(),
			),
			'single'            => true,
			'type'              => 'array',
		)
	);
}

/**
 * Register venue metadata.
 */
function register_venue_meta(): void {
	foreach (
		array(
			'wporg_ce_address'             => 'string',
			'wporg_ce_city'                => 'string',
			'wporg_ce_region'              => 'string',
			'wporg_ce_postal_code'         => 'string',
			'wporg_ce_latitude'            => 'number',
			'wporg_ce_longitude'           => 'number',
			'wporg_ce_accessibility_notes' => 'string',
			'wporg_ce_online_url'          => 'string',
		) as $key => $type
	) {
		register_post_meta_field( POST_TYPE_VENUE, $key, $type );
	}
}

/**
 * Register membership metadata.
 */
function register_membership_meta(): void {
	foreach (
		array(
			'wporg_ce_group_id'         => 'integer',
			'wporg_ce_user_id'          => 'integer',
			'wporg_ce_role'             => 'string',
			'wporg_ce_status'           => 'string',
			'wporg_ce_joined_at_utc'    => 'string',
			'wporg_ce_visibility'       => 'string',
			'wporg_ce_source_meetup_id' => 'string',
		) as $key => $type
	) {
		register_post_meta_field( POST_TYPE_MEMBERSHIP, $key, $type );
	}

	register_post_meta(
		POST_TYPE_MEMBERSHIP,
		'wporg_ce_notification_preferences',
		array(
			'auth_callback' => __NAMESPACE__ . '\can_edit_registered_meta',
			'default'       => get_default_notification_preferences(),
			'show_in_rest'  => array(
				'schema' => get_notification_preferences_schema(),
			),
			'single'        => true,
			'type'          => 'object',
		)
	);
}

/**
 * Register RSVP metadata.
 */
function register_rsvp_meta(): void {
	foreach (
		array(
			'wporg_ce_event_id'          => 'integer',
			'wporg_ce_group_id'          => 'integer',
			'wporg_ce_user_id'           => 'integer',
			'wporg_ce_status'            => 'string',
			'wporg_ce_waitlist_position' => 'integer',
			'wporg_ce_guest_count'       => 'integer',
			'wporg_ce_visibility'        => 'string',
			'wporg_ce_attendance_status' => 'string',
			'wporg_ce_attended_at_utc'   => 'string',
			'wporg_ce_attendance_by'     => 'integer',
			'wporg_ce_attendance_at_utc' => 'string',
			'wporg_ce_created_at_utc'    => 'string',
			'wporg_ce_updated_at_utc'    => 'string',
		) as $key => $type
	) {
		register_post_meta_field( POST_TYPE_RSVP, $key, $type );
	}

	register_post_meta(
		POST_TYPE_RSVP,
		'wporg_ce_answers',
		array(
			'auth_callback'     => __NAMESPACE__ . '\can_edit_registered_meta',
			'sanitize_callback' => __NAMESPACE__ . '\sanitize_event_rsvp_answers',
			'show_in_rest'      => array(
				'schema' => get_event_rsvp_answers_schema(),
			),
			'single'            => true,
			'type'              => 'object',
		)
	);
}

/**
 * Register event feedback metadata.
 */
function register_feedback_meta(): void {
	foreach (
		array(
			'wporg_ce_event_id'         => 'integer',
			'wporg_ce_group_id'         => 'integer',
			'wporg_ce_user_id'          => 'integer',
			'wporg_ce_rating'           => 'integer',
			'wporg_ce_created_at_utc'   => 'string',
			'wporg_ce_source_meetup_id' => 'string',
		) as $key => $type
	) {
		register_post_meta_field( POST_TYPE_FEEDBACK, $key, $type );
	}
}

/**
 * Register import metadata.
 */
function register_import_meta(): void {
	foreach (
		array(
			'wporg_ce_source'      => 'string',
			'wporg_ce_source_id'   => 'string',
			'wporg_ce_source_url'  => 'string',
			'wporg_ce_target_type' => 'string',
			'wporg_ce_target_id'   => 'integer',
			'wporg_ce_imported_at' => 'string',
		) as $key => $type
	) {
		register_post_meta_field( POST_TYPE_IMPORT, $key, $type );
	}

	register_post_meta(
		POST_TYPE_IMPORT,
		'wporg_ce_import_status',
		array(
			'auth_callback' => __NAMESPACE__ . '\can_edit_registered_meta',
			'show_in_rest'  => array(
				'schema' => array(
					'context' => array( 'view', 'edit' ),
					'enum'    => get_import_statuses(),
					'type'    => 'string',
				),
			),
			'single'        => true,
			'type'          => 'string',
		)
	);
}

/**
 * Register one post meta field with common defaults.
 *
 * @param string $post_type Post type key.
 * @param string $key       Meta key.
 * @param string $type      REST schema type.
 */
function register_post_meta_field( string $post_type, string $key, string $type ): void {
	$schema = array(
		'type'    => $type,
		'context' => array( 'view', 'edit' ),
	);

	if ( 'object' === $type ) {
		$schema['additionalProperties'] = true;
	}

	if ( 'string' === $type && str_ends_with( $key, '_url' ) ) {
		$schema['format'] = 'uri';
	}

	$args = array(
		'single'        => true,
		'type'          => $type,
		'show_in_rest'  => array(
			'schema' => $schema,
		),
		'auth_callback' => __NAMESPACE__ . '\can_edit_registered_meta',
	);

	register_post_meta( $post_type, $key, $args );
}

/**
 * Restrict registered meta edits to users who can edit the owning post.
 *
 * @param bool   $allowed     Whether editing is allowed.
 * @param string $meta_key    Meta key.
 * @param int    $object_id   Object ID.
 * @param int    $user_id     User ID.
 * @param string $capability  Capability name.
 * @param array  $capabilities Primitive capabilities.
 *
 * @return bool
 */
function can_edit_registered_meta( bool $allowed, string $meta_key, int $object_id, int $user_id, string $capability, array $capabilities ): bool {
	unset( $allowed, $meta_key, $capability, $capabilities );

	return user_can( $user_id, 'edit_post', $object_id );
}

/**
 * Build post type labels.
 *
 * @param string $plural   Plural label.
 * @param string $singular Singular label.
 *
 * @return array
 */
function get_post_type_labels( string $plural, string $singular ): array {
	$lower_plural   = strtolower( $plural );
	$lower_singular = strtolower( $singular );

	/* translators: %s: singular post type label. */
	$add_new_item = sprintf( __( 'Add New %s', 'wporg' ), $singular );
	/* translators: %s: singular post type label. */
	$edit_item = sprintf( __( 'Edit %s', 'wporg' ), $singular );
	/* translators: %s: singular post type label. */
	$new_item = sprintf( __( 'New %s', 'wporg' ), $singular );
	/* translators: %s: singular post type label. */
	$view_item = sprintf( __( 'View %s', 'wporg' ), $singular );
	/* translators: %s: plural post type label. */
	$view_items = sprintf( __( 'View %s', 'wporg' ), $plural );
	/* translators: %s: plural post type label. */
	$search_items = sprintf( __( 'Search %s', 'wporg' ), $plural );
	/* translators: %s: plural post type label in lowercase. */
	$not_found = sprintf( __( 'No %s found', 'wporg' ), $lower_plural );
	/* translators: %s: plural post type label in lowercase. */
	$not_found_in_trash = sprintf( __( 'No %s found in Trash', 'wporg' ), $lower_plural );
	/* translators: %s: plural post type label. */
	$all_items = sprintf( __( 'All %s', 'wporg' ), $plural );
	/* translators: %s: singular post type label. */
	$archives = sprintf( __( '%s Archives', 'wporg' ), $singular );
	/* translators: %s: singular post type label. */
	$attributes = sprintf( __( '%s Attributes', 'wporg' ), $singular );
	/* translators: %s: singular post type label in lowercase. */
	$insert_into_item = sprintf( __( 'Insert into %s', 'wporg' ), $lower_singular );
	/* translators: %s: singular post type label in lowercase. */
	$uploaded_to_this_item = sprintf( __( 'Uploaded to this %s', 'wporg' ), $lower_singular );

	return array(
		'name'                  => $plural,
		'singular_name'         => $singular,
		'add_new'               => __( 'Add New', 'wporg' ),
		'add_new_item'          => $add_new_item,
		'edit_item'             => $edit_item,
		'new_item'              => $new_item,
		'view_item'             => $view_item,
		'view_items'            => $view_items,
		'search_items'          => $search_items,
		'not_found'             => $not_found,
		'not_found_in_trash'    => $not_found_in_trash,
		'all_items'             => $all_items,
		'archives'              => $archives,
		'attributes'            => $attributes,
		'insert_into_item'      => $insert_into_item,
		'uploaded_to_this_item' => $uploaded_to_this_item,
		'menu_name'             => $plural,
	);
}

/**
 * Build taxonomy arguments.
 *
 * @param string $plural       Plural label.
 * @param string $singular     Singular label.
 * @param array  $rewrite      Rewrite arguments.
 * @param bool   $hierarchical Whether the taxonomy is hierarchical.
 *
 * @return array
 */
function get_taxonomy_args( string $plural, string $singular, array $rewrite, bool $hierarchical = true ): array {
	return array(
		'labels'            => get_taxonomy_labels( $plural, $singular ),
		'public'            => true,
		'hierarchical'      => $hierarchical,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'rewrite'           => $rewrite,
	);
}

/**
 * Build taxonomy labels.
 *
 * @param string $plural   Plural label.
 * @param string $singular Singular label.
 *
 * @return array
 */
function get_taxonomy_labels( string $plural, string $singular ): array {
	$lower_plural = strtolower( $plural );

	/* translators: %s: plural taxonomy label. */
	$search_items = sprintf( __( 'Search %s', 'wporg' ), $plural );
	/* translators: %s: plural taxonomy label. */
	$popular_items = sprintf( __( 'Popular %s', 'wporg' ), $plural );
	/* translators: %s: plural taxonomy label. */
	$all_items = sprintf( __( 'All %s', 'wporg' ), $plural );
	/* translators: %s: singular taxonomy label. */
	$edit_item = sprintf( __( 'Edit %s', 'wporg' ), $singular );
	/* translators: %s: singular taxonomy label. */
	$view_item = sprintf( __( 'View %s', 'wporg' ), $singular );
	/* translators: %s: singular taxonomy label. */
	$update_item = sprintf( __( 'Update %s', 'wporg' ), $singular );
	/* translators: %s: singular taxonomy label. */
	$add_new_item = sprintf( __( 'Add New %s', 'wporg' ), $singular );
	/* translators: %s: singular taxonomy label. */
	$new_item_name = sprintf( __( 'New %s Name', 'wporg' ), $singular );
	/* translators: %s: plural taxonomy label in lowercase. */
	$separate_items_with_commas = sprintf( __( 'Separate %s with commas', 'wporg' ), $lower_plural );
	/* translators: %s: plural taxonomy label in lowercase. */
	$add_or_remove_items = sprintf( __( 'Add or remove %s', 'wporg' ), $lower_plural );
	/* translators: %s: plural taxonomy label in lowercase. */
	$choose_from_most_used = sprintf( __( 'Choose from the most used %s', 'wporg' ), $lower_plural );
	/* translators: %s: plural taxonomy label in lowercase. */
	$not_found = sprintf( __( 'No %s found', 'wporg' ), $lower_plural );

	return array(
		'name'                       => $plural,
		'singular_name'              => $singular,
		'search_items'               => $search_items,
		'popular_items'              => $popular_items,
		'all_items'                  => $all_items,
		'edit_item'                  => $edit_item,
		'view_item'                  => $view_item,
		'update_item'                => $update_item,
		'add_new_item'               => $add_new_item,
		'new_item_name'              => $new_item_name,
		'separate_items_with_commas' => $separate_items_with_commas,
		'add_or_remove_items'        => $add_or_remove_items,
		'choose_from_most_used'      => $choose_from_most_used,
		'not_found'                  => $not_found,
		'menu_name'                  => $plural,
	);
}
