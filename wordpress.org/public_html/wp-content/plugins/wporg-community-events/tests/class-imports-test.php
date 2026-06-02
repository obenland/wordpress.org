<?php
/**
 * Tests for Community Events import mappings.
 *
 * @package WordPressdotorg\Community_Events
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Community_Events\Tests;

use WP_UnitTestCase;

use const WordPressdotorg\Community_Events\IMPORT_STATUS_FAILED;
use const WordPressdotorg\Community_Events\IMPORT_STATUS_IMPORTED;
use const WordPressdotorg\Community_Events\POST_TYPE_GROUP;
use const WordPressdotorg\Community_Events\POST_TYPE_IMPORT;
use const WordPressdotorg\Community_Events\POST_TYPE_VENUE;

/**
 * Tests for source-to-local import reconciliation records.
 */
class Imports_Test extends WP_UnitTestCase {
	/**
	 * Register the plugin data model for each isolated test.
	 */
	public function set_up(): void {
		parent::set_up();

		\WordPressdotorg\Community_Events\register_post_types();
		\WordPressdotorg\Community_Events\register_meta_fields();
	}

	/**
	 * Import mappings should create private source records for local targets.
	 */
	public function test_upsert_import_record_creates_source_mapping(): void {
		$group_id = $this->create_group();

		$import_id = \WordPressdotorg\Community_Events\upsert_import_record(
			'meetup',
			'123456',
			POST_TYPE_GROUP,
			$group_id,
			array(
				'import_status' => IMPORT_STATUS_IMPORTED,
				'imported_at'   => '2026-06-01 12:00:00',
				'source_url'    => 'https://www.meetup.com/wordpress-zurich/',
			)
		);

		$this->assertNotWPError( $import_id );

		$import = get_post( $import_id );

		$this->assertSame( POST_TYPE_IMPORT, $import->post_type );
		$this->assertSame( $group_id, (int) $import->post_parent );
		$this->assertSame( 'meetup', get_post_meta( $import_id, 'wporg_ce_source', true ) );
		$this->assertSame( '123456', get_post_meta( $import_id, 'wporg_ce_source_id', true ) );
		$this->assertSame( 'https://www.meetup.com/wordpress-zurich/', get_post_meta( $import_id, 'wporg_ce_source_url', true ) );
		$this->assertSame( POST_TYPE_GROUP, get_post_meta( $import_id, 'wporg_ce_target_type', true ) );
		$this->assertSame( $group_id, (int) get_post_meta( $import_id, 'wporg_ce_target_id', true ) );
		$this->assertSame( IMPORT_STATUS_IMPORTED, get_post_meta( $import_id, 'wporg_ce_import_status', true ) );
		$this->assertSame( '2026-06-01 12:00:00', get_post_meta( $import_id, 'wporg_ce_imported_at', true ) );
		$this->assertSame(
			$import_id,
			\WordPressdotorg\Community_Events\get_import_record_id( 'meetup', '123456', POST_TYPE_GROUP )
		);
		$this->assertSame(
			$group_id,
			\WordPressdotorg\Community_Events\get_import_target_id( 'meetup', '123456', POST_TYPE_GROUP )
		);
	}

	/**
	 * Re-importing the same source object should update the existing mapping.
	 */
	public function test_upsert_import_record_updates_existing_source_mapping(): void {
		$old_group_id = $this->create_group();
		$new_group_id = $this->create_group();

		$import_id  = \WordPressdotorg\Community_Events\upsert_import_record(
			'meetup',
			'wp-zurich',
			POST_TYPE_GROUP,
			$old_group_id
		);
		$updated_id = \WordPressdotorg\Community_Events\upsert_import_record(
			'meetup',
			'wp-zurich',
			POST_TYPE_GROUP,
			$new_group_id,
			array(
				'import_status' => IMPORT_STATUS_FAILED,
				'source_url'    => 'https://www.meetup.com/wordpress-zurich/events/',
			)
		);

		$this->assertNotWPError( $import_id );
		$this->assertNotWPError( $updated_id );
		$this->assertSame( $import_id, $updated_id );
		$this->assertSame( $new_group_id, (int) get_post( $updated_id )->post_parent );
		$this->assertSame( $new_group_id, (int) get_post_meta( $updated_id, 'wporg_ce_target_id', true ) );
		$this->assertSame( IMPORT_STATUS_FAILED, get_post_meta( $updated_id, 'wporg_ce_import_status', true ) );
		$this->assertSame( 'https://www.meetup.com/wordpress-zurich/events/', get_post_meta( $updated_id, 'wporg_ce_source_url', true ) );
	}

	/**
	 * Import mappings should reject missing source identity and mismatched targets.
	 */
	public function test_upsert_import_record_rejects_invalid_source_or_target(): void {
		$group_id = $this->create_group();
		$venue_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Venue',
				'post_type'   => POST_TYPE_VENUE,
			)
		);

		$missing_source = \WordPressdotorg\Community_Events\upsert_import_record( '', '123456', POST_TYPE_GROUP, $group_id );
		$wrong_type     = \WordPressdotorg\Community_Events\upsert_import_record( 'meetup', '123456', POST_TYPE_GROUP, $venue_id );

		$this->assertWPError( $missing_source );
		$this->assertSame( 'wporg_ce_import_source_required', $missing_source->get_error_code() );
		$this->assertWPError( $wrong_type );
		$this->assertSame( 'wporg_ce_invalid_import_target', $wrong_type->get_error_code() );
	}

	/**
	 * Create a published group for import tests.
	 *
	 * @return int Group post ID.
	 */
	private function create_group(): int {
		return self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'WordPress Zurich',
				'post_type'   => POST_TYPE_GROUP,
			)
		);
	}
}
