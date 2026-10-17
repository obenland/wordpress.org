<?php
/**
 * Adds the plugin ID to the index of review emails.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\WPOrgPluginReview\Jobs\IndexPluginIds;

/**
 * Which plugin each review email is about, from the `P0TDX{plugin ID}HGN` of its Review ID line, so a plugin's review
 * conversations can be looked up without reading emails.
 */
class AddPluginIdToWporgpluginreviewReviews extends Migration {

	/**
	 * Runs the migration.
	 *
	 * @return void
	 */
	public function up(): void {
		if ( ! Schema::hasTable( 'wporgpluginreview_reviews' ) || Schema::hasColumn( 'wporgpluginreview_reviews', 'plugin_id' ) ) {
			return;
		}

		// Null until the email is read again; 0 when its Review ID line names no plugin.
		Schema::table(
			'wporgpluginreview_reviews',
			static function ( Blueprint $table ): void {
				$table->unsignedInteger( 'plugin_id' )->nullable();
				$table->index( array( 'plugin_id', 'conversation_id' ) );
			}
		);

		// The review emails indexed already are read again on the queue, in batches; new ones get it as they're indexed.
		try {
			IndexPluginIds::dispatch();
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgPluginReview] Could not queue indexing the plugin IDs of the review emails: ' . $e->getMessage() );
		}
	}

	/**
	 * Reverts the migration.
	 *
	 * @return void
	 */
	public function down(): void {
		if ( ! Schema::hasTable( 'wporgpluginreview_reviews' ) || ! Schema::hasColumn( 'wporgpluginreview_reviews', 'plugin_id' ) ) {
			return;
		}

		Schema::table(
			'wporgpluginreview_reviews',
			static function ( Blueprint $table ): void {
				$table->dropIndex( array( 'plugin_id', 'conversation_id' ) );
				$table->dropColumn( 'plugin_id' );
			}
		);
	}
}
