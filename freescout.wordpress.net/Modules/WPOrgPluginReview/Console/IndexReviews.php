<?php
/**
 * Indexes the review emails again.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Console;

use Illuminate\Console\Command;
use Modules\WPOrgPluginReview\Jobs\IndexPluginIds as IndexPluginIdsJob;
use Modules\WPOrgPluginReview\Jobs\IndexReviews as IndexReviewsJob;

/**
 * Brings the index of review emails up to date with every reply of the plugins team's mailbox, like after the module
 * was switched off for a while, when their threads' hooks didn't run. With `--plugin-ids`, only the review emails indexed
 * before the index had their plugin IDs are read again.
 */
final class IndexReviews extends Command {

	/**
	 * Command signature.
	 *
	 * @var string
	 */
	protected $signature = 'wporgpluginreview:index {--now : Index in this process instead of on the queue} {--plugin-ids : Only read again the review emails indexed without their plugin ID}';

	/**
	 * Command description.
	 *
	 * @var string
	 */
	protected $description = 'Indexes the plugin review emails FreeScout already has, on the queue unless asked to do it now.';

	/**
	 * Runs the command.
	 *
	 * @return int Exit code.
	 */
	public function handle(): int {
		$plugin_ids = (bool) $this->option( 'plugin-ids' );
		$job        = $plugin_ids ? IndexPluginIdsJob::class : IndexReviewsJob::class;

		if ( ! $this->option( 'now' ) ) {
			$job::dispatch();
			$this->line( $plugin_ids ? 'Indexing the plugin IDs of the review emails on the queue.' : 'Indexing the review emails on the queue.' );

			return 0;
		}

		$last = 0;
		do {
			$last = ( new $job( $last ) )->index_batch();
		} while ( null !== $last );

		$this->line( $plugin_ids ? 'Indexed the plugin IDs of the review emails.' : 'Indexed the review emails.' );

		return 0;
	}
}
