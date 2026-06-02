<?php
/**
 * Seed local development content for WordPress.org Events.
 *
 * @package WordPressdotorg\Events_Theme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'WordPressdotorg\Community_Events\join_group' ) ) {
	if ( class_exists( 'WP_CLI' ) ) {
		WP_CLI::error( 'The WordPress.org Community Events plugin must be active before seeding.' );
	}

	return;
}

update_option( 'blogname', 'Events' );
update_option( 'blogdescription', 'Official WordPress community events' );
update_option( 'permalink_structure', '/%postname%/' );

if ( wp_get_theme( 'wporg-events' )->exists() ) {
	switch_theme( 'wporg-events' );
}

$organizer_id = wporg_events_seed_user( 'wporg-organizer', 'organizer@example.org', 'WordPress Organizer' );
$cohost_id    = wporg_events_seed_user( 'wporg-cohost', 'cohost@example.org', 'Community Co-Host' );
$member_id    = wporg_events_seed_user( 'wporg-member', 'member@example.org', 'Community Member' );

$meetup_type_slug       = wporg_events_seed_term( WordPressdotorg\Community_Events\TAXONOMY_EVENT_TYPE, 'Meetup', 'meetup' );
$workshop_type_slug     = wporg_events_seed_term( WordPressdotorg\Community_Events\TAXONOMY_EVENT_TYPE, 'Workshop', 'workshop' );
$in_person_format_slug  = wporg_events_seed_term( WordPressdotorg\Community_Events\TAXONOMY_EVENT_FORMAT, 'In person', 'in-person' );
$online_format_slug     = wporg_events_seed_term( WordPressdotorg\Community_Events\TAXONOMY_EVENT_FORMAT, 'Online', 'online' );
$local_group_type_slug  = wporg_events_seed_term( WordPressdotorg\Community_Events\TAXONOMY_GROUP_TYPE, 'Local', 'local' );
$online_group_type_slug = wporg_events_seed_term( WordPressdotorg\Community_Events\TAXONOMY_GROUP_TYPE, 'Online', 'online' );
$switzerland_slug       = wporg_events_seed_term( WordPressdotorg\Community_Events\TAXONOMY_COUNTRY, 'Switzerland', 'switzerland' );
$english_slug           = wporg_events_seed_term( WordPressdotorg\Community_Events\TAXONOMY_LANGUAGE, 'English', 'en' );
$german_slug            = wporg_events_seed_term( WordPressdotorg\Community_Events\TAXONOMY_LANGUAGE, 'German', 'de' );
$contributor_topic_slug = wporg_events_seed_term( WordPressdotorg\Community_Events\TAXONOMY_TOPIC, 'Contributor events', 'contributor-events' );

$zurich_group_id    = wporg_events_seed_group(
	'wordpress-zurich',
	'WordPress Zurich',
	'Local WordPress community events in Zurich, Switzerland.',
	array(
		'wporg_ce_city'           => 'Zurich',
		'wporg_ce_event_count'    => 2,
		'wporg_ce_location_label' => 'Zurich, Switzerland',
		'wporg_ce_member_count'   => 128,
		'wporg_ce_region'         => 'ZH',
		'wporg_ce_timezone'       => 'Europe/Zurich',
		'wporg_ce_website_url'    => 'https://wpzurich.example.com/',
	)
);
$online_group_id    = wporg_events_seed_group(
	'wordpress-online-contributors',
	'WordPress Online Contributors',
	'Online contributor sessions for WordPress open source projects.',
	array(
		'wporg_ce_event_count'    => 1,
		'wporg_ce_location_label' => 'Online',
		'wporg_ce_member_count'   => 342,
		'wporg_ce_timezone'       => 'UTC',
		'wporg_ce_website_url'    => 'https://make.wordpress.org/',
	)
);
$zurich_group_terms = array(
	WordPressdotorg\Community_Events\TAXONOMY_COUNTRY    => array( $switzerland_slug ),
	WordPressdotorg\Community_Events\TAXONOMY_GROUP_TYPE => array( $local_group_type_slug ),
	WordPressdotorg\Community_Events\TAXONOMY_LANGUAGE   => array( $english_slug, $german_slug ),
	WordPressdotorg\Community_Events\TAXONOMY_TOPIC      => array( $contributor_topic_slug ),
);
$online_group_terms = array(
	WordPressdotorg\Community_Events\TAXONOMY_GROUP_TYPE => array( $online_group_type_slug ),
	WordPressdotorg\Community_Events\TAXONOMY_LANGUAGE   => array( $english_slug ),
	WordPressdotorg\Community_Events\TAXONOMY_TOPIC      => array( $contributor_topic_slug ),
);
wporg_events_set_object_terms( $zurich_group_id, $zurich_group_terms );
wporg_events_set_object_terms( $online_group_id, $online_group_terms );

$venue_id = wporg_events_seed_venue(
	'zurich-community-space',
	'Zurich Community Space',
	array(
		'wporg_ce_address'     => 'Limmatstrasse 123',
		'wporg_ce_city'        => 'Zurich',
		'wporg_ce_latitude'    => 47.384,
		'wporg_ce_longitude'   => 8.532,
		'wporg_ce_postal_code' => '8005',
		'wporg_ce_region'      => 'ZH',
	)
);
wporg_events_set_object_terms(
	$venue_id,
	array(
		WordPressdotorg\Community_Events\TAXONOMY_COUNTRY => array( $switzerland_slug ),
	)
);

$zurich_event_id = wporg_events_seed_event(
	'zurich-contributor-evening',
	'Zurich Contributor Evening',
	$zurich_group_id,
	array(
		'post_content' => 'An evening for contributing to WordPress core, design, documentation, and community projects.',
		'post_excerpt' => 'A local contributor evening for WordPress projects.',
		'meta_input'   => array(
			'wporg_ce_capacity'        => 30,
			'wporg_ce_approval_status' => WordPressdotorg\Community_Events\EVENT_APPROVAL_STATUS_APPROVED,
			'wporg_ce_end_utc'         => gmdate( 'Y-m-d\TH:i:s\Z', time() + ( 10 * DAY_IN_SECONDS ) + ( 2 * HOUR_IN_SECONDS ) ),
			'wporg_ce_host_user_id'    => $organizer_id,
			'wporg_ce_host_user_ids'   => array( $organizer_id, $cohost_id ),
			'wporg_ce_rsvp_policy'     => 'open',
			'wporg_ce_start_utc'       => gmdate( 'Y-m-d\TH:i:s\Z', time() + ( 10 * DAY_IN_SECONDS ) ),
			'wporg_ce_timezone'        => 'Europe/Zurich',
			'wporg_ce_venue_id'        => $venue_id,
		),
		'post_author'  => $organizer_id,
		'terms'        => array(
			'countries'     => array( $switzerland_slug ),
			'event_formats' => array( $in_person_format_slug ),
			'event_types'   => array( $meetup_type_slug ),
			'languages'     => array( $english_slug, $german_slug ),
			'topics'        => array( $contributor_topic_slug ),
		),
	)
);
wporg_events_seed_event(
	'online-training-workshop',
	'Online Training Workshop',
	$online_group_id,
	array(
		'post_content' => 'A remote workshop for creating and reviewing WordPress learning material.',
		'post_excerpt' => 'A remote workshop for WordPress training contributors.',
		'meta_input'   => array(
			'wporg_ce_approval_status' => WordPressdotorg\Community_Events\EVENT_APPROVAL_STATUS_APPROVED,
			'wporg_ce_host_user_id'    => $organizer_id,
			'wporg_ce_host_user_ids'   => array( $organizer_id ),
			'wporg_ce_online_url'      => 'https://example.org/online-workshop/',
			'wporg_ce_rsvp_policy'     => 'open',
			'wporg_ce_start_utc'       => gmdate( 'Y-m-d\TH:i:s\Z', time() + ( 14 * DAY_IN_SECONDS ) ),
			'wporg_ce_timezone'        => 'UTC',
		),
		'post_author'  => $organizer_id,
		'terms'        => array(
			'event_formats' => array( $online_format_slug ),
			'event_types'   => array( $workshop_type_slug ),
			'languages'     => array( $english_slug ),
			'topics'        => array( $contributor_topic_slug ),
		),
	)
);
$past_event_id = wporg_events_seed_event(
	'past-zurich-meetup',
	'Past Zurich Meetup',
	$zurich_group_id,
	array(
		'post_content' => 'A previous meetup kept visible for archive testing.',
		'post_excerpt' => 'A previous WordPress Zurich meetup.',
		'meta_input'   => array(
			'wporg_ce_approval_status' => WordPressdotorg\Community_Events\EVENT_APPROVAL_STATUS_APPROVED,
			'wporg_ce_host_user_id'    => $organizer_id,
			'wporg_ce_host_user_ids'   => array( $organizer_id, $cohost_id ),
			'wporg_ce_rsvp_policy'     => 'open',
			'wporg_ce_start_utc'       => gmdate( 'Y-m-d\TH:i:s\Z', time() - ( 20 * DAY_IN_SECONDS ) ),
			'wporg_ce_timezone'        => 'Europe/Zurich',
			'wporg_ce_venue_id'        => $venue_id,
		),
		'post_author'  => $organizer_id,
		'terms'        => array(
			'countries'     => array( $switzerland_slug ),
			'event_formats' => array( $in_person_format_slug ),
			'event_types'   => array( $meetup_type_slug ),
			'languages'     => array( $english_slug, $german_slug ),
		),
	)
);

WordPressdotorg\Community_Events\join_group(
	$zurich_group_id,
	$organizer_id,
	array(
		'role' => WordPressdotorg\Community_Events\MEMBERSHIP_ROLE_ORGANIZER,
	)
);
WordPressdotorg\Community_Events\join_group(
	$zurich_group_id,
	$cohost_id,
	array(
		'role' => WordPressdotorg\Community_Events\MEMBERSHIP_ROLE_HOST,
	)
);
WordPressdotorg\Community_Events\join_group( $zurich_group_id, $member_id );
WordPressdotorg\Community_Events\rsvp_to_event( $zurich_event_id, $member_id );
WordPressdotorg\Community_Events\rsvp_to_event( $past_event_id, $member_id );
WordPressdotorg\Community_Events\submit_event_feedback(
	$past_event_id,
	$member_id,
	array(
		'rating' => 5,
		'review' => 'Useful contributor time with a welcoming group.',
	)
);

flush_rewrite_rules();

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::success( 'Seeded Events theme content.' );
}

/**
 * Create or get a seed user.
 *
 * @param string $login        User login.
 * @param string $email        User email.
 * @param string $display_name Display name.
 *
 * @return int
 */
function wporg_events_seed_user( string $login, string $email, string $display_name ): int {
	$user_id = username_exists( $login );

	if ( $user_id ) {
		return (int) $user_id;
	}

	return (int) wp_insert_user(
		array(
			'display_name'  => $display_name,
			'user_email'    => $email,
			'user_login'    => $login,
			'user_nicename' => $login,
			'user_pass'     => wp_generate_password( 24, true ),
			'role'          => 'subscriber',
		)
	);
}

/**
 * Create or get a seed term.
 *
 * @param string $taxonomy Taxonomy key.
 * @param string $name     Term name.
 * @param string $slug     Term slug.
 *
 * @return string
 */
function wporg_events_seed_term( string $taxonomy, string $name, string $slug ): string {
	$term = get_term_by( 'slug', $slug, $taxonomy );

	if ( $term instanceof \WP_Term ) {
		return $term->slug;
	}

	wp_insert_term(
		$name,
		$taxonomy,
		array(
			'slug' => $slug,
		)
	);

	return $slug;
}

/**
 * Set seed taxonomy terms on an object.
 *
 * @param int   $post_id  Post ID.
 * @param array $term_map Taxonomy to term slugs map.
 */
function wporg_events_set_object_terms( int $post_id, array $term_map ): void {
	foreach ( $term_map as $taxonomy => $terms ) {
		wp_set_object_terms( $post_id, array_map( 'sanitize_title', (array) $terms ), (string) $taxonomy, false );
	}
}

/**
 * Create or update a seed group.
 *
 * @param string $slug    Group slug.
 * @param string $title   Group title.
 * @param string $excerpt Group excerpt.
 * @param array  $meta    Group meta.
 *
 * @return int
 */
function wporg_events_seed_group( string $slug, string $title, string $excerpt, array $meta ): int {
	$post_id = wporg_events_seed_post(
		WordPressdotorg\Community_Events\POST_TYPE_GROUP,
		$slug,
		array(
			'post_content' => $excerpt,
			'post_excerpt' => $excerpt,
			'post_title'   => $title,
		)
	);

	wporg_events_update_meta( $post_id, $meta );

	return $post_id;
}

/**
 * Create or update a seed venue.
 *
 * @param string $slug  Venue slug.
 * @param string $title Venue title.
 * @param array  $meta  Venue meta.
 *
 * @return int
 */
function wporg_events_seed_venue( string $slug, string $title, array $meta ): int {
	$post_id = wporg_events_seed_post(
		WordPressdotorg\Community_Events\POST_TYPE_VENUE,
		$slug,
		array(
			'post_title' => $title,
		)
	);

	wporg_events_update_meta( $post_id, $meta );

	return $post_id;
}

/**
 * Create or update a seed event.
 *
 * @param string $slug     Event slug.
 * @param string $title    Event title.
 * @param int    $group_id Group post ID.
 * @param array  $args     Event arguments.
 *
 * @return int
 */
function wporg_events_seed_event( string $slug, string $title, int $group_id, array $args ): int {
	$post_id = wporg_events_seed_post(
		WordPressdotorg\Community_Events\POST_TYPE_EVENT,
		$slug,
		array(
			'comment_status' => 'open',
			'post_author'    => $args['post_author'] ?? 0,
			'post_content'   => $args['post_content'] ?? '',
			'post_excerpt'   => $args['post_excerpt'] ?? '',
			'post_parent'    => $group_id,
			'post_title'     => $title,
		)
	);

	wporg_events_update_meta(
		$post_id,
		array_merge(
			array(
				'wporg_ce_group_id'       => $group_id,
				'wporg_ce_rsvp_count'     => 0,
				'wporg_ce_waitlist_count' => 0,
			),
			$args['meta_input'] ?? array()
		)
	);

	foreach ( $args['terms'] ?? array() as $request_key => $terms ) {
		$taxonomy = WordPressdotorg\Community_Events\get_event_taxonomy_request_map()[ $request_key ] ?? '';

		if ( $taxonomy ) {
			wp_set_object_terms( $post_id, array_map( 'sanitize_title', (array) $terms ), $taxonomy, false );
		}
	}

	return $post_id;
}

/**
 * Create or update a seed post.
 *
 * @param string $post_type Post type.
 * @param string $slug      Post slug.
 * @param array  $args      Post arguments.
 *
 * @return int
 */
function wporg_events_seed_post( string $post_type, string $slug, array $args ): int {
	$existing = wporg_events_get_seed_post( $post_type, $slug );
	$postarr  = wp_parse_args(
		$args,
		array(
			'post_name'   => $slug,
			'post_status' => 'publish',
			'post_type'   => $post_type,
		)
	);

	if ( $existing ) {
		$postarr['ID'] = $existing->ID;
		wp_update_post( wp_slash( $postarr ) );
		wporg_events_delete_duplicate_seed_posts( $post_type, $slug, (int) $existing->ID );

		return (int) $existing->ID;
	}

	$post_id = (int) wp_insert_post( wp_slash( $postarr ) );
	wporg_events_delete_duplicate_seed_posts( $post_type, $slug, $post_id );

	return $post_id;
}

/**
 * Get a seed post by its canonical slug.
 *
 * @param string $post_type Post type.
 * @param string $slug      Post slug.
 *
 * @return WP_Post|null
 */
function wporg_events_get_seed_post( string $post_type, string $slug ): ?\WP_Post {
	$posts = get_posts(
		array(
			'fields'           => 'all',
			'name'             => $slug,
			'no_found_rows'    => true,
			'post_status'      => 'any',
			'post_type'        => $post_type,
			'posts_per_page'   => 1,
			'suppress_filters' => false,
		)
	);

	if ( empty( $posts ) || ! $posts[0] instanceof \WP_Post ) {
		return null;
	}

	return $posts[0];
}

/**
 * Delete duplicate posts created by earlier seed runs.
 *
 * @param string $post_type    Post type.
 * @param string $slug         Canonical post slug.
 * @param int    $canonical_id Canonical post ID.
 */
function wporg_events_delete_duplicate_seed_posts( string $post_type, string $slug, int $canonical_id ): void {
	$post_names = array_map(
		static function ( int $index ) use ( $slug ): string {
			return sprintf( '%s-%d', $slug, $index );
		},
		range( 2, 100 )
	);
	$post_ids   = get_posts(
		array(
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'post__not_in'     => array( $canonical_id ),
			'post_name__in'    => $post_names,
			'post_status'      => 'any',
			'post_type'        => $post_type,
			'posts_per_page'   => -1,
			'suppress_filters' => false,
		)
	);

	foreach ( $post_ids as $post_id ) {
		wporg_events_delete_seed_relationships( $post_type, (int) $post_id );
		wp_delete_post( (int) $post_id, true );
	}
}

/**
 * Delete relationship posts that point at a duplicate seed post.
 *
 * @param string $post_type Post type.
 * @param int    $post_id   Duplicate post ID.
 */
function wporg_events_delete_seed_relationships( string $post_type, int $post_id ): void {
	$relationships = array();

	if ( WordPressdotorg\Community_Events\POST_TYPE_GROUP === $post_type ) {
		$relationships = array(
			WordPressdotorg\Community_Events\POST_TYPE_MEMBERSHIP => 'wporg_ce_group_id',
			WordPressdotorg\Community_Events\POST_TYPE_RSVP       => 'wporg_ce_group_id',
		);
	} elseif ( WordPressdotorg\Community_Events\POST_TYPE_EVENT === $post_type ) {
		$relationships = array(
			WordPressdotorg\Community_Events\POST_TYPE_RSVP => 'wporg_ce_event_id',
		);
	}

	foreach ( $relationships as $relationship_post_type => $meta_key ) {
		$relationship_ids = get_posts(
			array(
				'fields'           => 'ids',
				'meta_key'         => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Dev seed cleanup targets one known meta key.
				'meta_value'       => $post_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Dev seed cleanup targets one known duplicate ID.
				'no_found_rows'    => true,
				'post_status'      => 'any',
				'post_type'        => $relationship_post_type,
				'posts_per_page'   => -1,
				'suppress_filters' => false,
			)
		);

		foreach ( $relationship_ids as $relationship_id ) {
			wp_delete_post( (int) $relationship_id, true );
		}
	}
}

/**
 * Update post meta fields.
 *
 * @param int   $post_id Post ID.
 * @param array $meta    Meta fields.
 */
function wporg_events_update_meta( int $post_id, array $meta ): void {
	foreach ( $meta as $key => $value ) {
		update_post_meta( $post_id, $key, $value );
	}
}
