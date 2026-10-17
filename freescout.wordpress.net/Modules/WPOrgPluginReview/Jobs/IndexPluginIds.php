<?php
/**
 * Queued indexing of the plugin IDs of the review emails indexed before the index had them.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Jobs;

use App\Thread;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\WPOrgPluginReview\Services\Review;

/**
 * Reads again the review emails whose rows have no plugin ID yet, and writes it.
 *
 * Only rows of the index are read, which are review emails already: the replies without a Review ID line aren't in it,
 * so they cost nothing here. A review email is indexed again as a whole, the way a change to it is: one that isn't a
 * review email any more, or whose thread is gone, leaves the index.
 *
 * The module's migration queues it once, as it adds the column; `php artisan wporgpluginreview:index --plugin-ids` runs
 * it again. Each job reads CHUNKS_PER_JOB batches of CHUNK rows, in the order of the index, and queues itself for the
 * rows after them, so no job runs long; running it again is harmless.
 */
final class IndexPluginIds implements ShouldQueue {

	use Dispatchable;
	use InteractsWithQueue;
	use Queueable;

	/**
	 * How many rows are read at a time.
	 *
	 * @var int
	 */
	private const CHUNK = 200;

	/**
	 * How many chunks a job reads before queuing the next.
	 *
	 * @var int
	 */
	private const CHUNKS_PER_JOB = 25;

	/**
	 * ID of the last index row read before this job.
	 *
	 * @var int
	 */
	public $after_id;

	/**
	 * Constructor.
	 *
	 * @param int $after_id ID of the last index row read before this job.
	 */
	public function __construct( int $after_id = 0 ) {
		$this->after_id = $after_id;
	}

	/**
	 * Indexes the next batch of rows, and queues the one after.
	 *
	 * @return void
	 */
	public function handle(): void {
		$last = $this->index_batch();
		if ( null !== $last ) {
			self::dispatch( $last );
		}
	}

	/**
	 * Indexes the next batch of rows without a plugin ID.
	 *
	 * The rows are walked by their ID rather than picked again by their missing plugin ID, so a row that can't be read
	 * doesn't stop the ones after it.
	 *
	 * @return int|null ID of the last row read, to start the next batch after; null once there are no more.
	 */
	public function index_batch(): ?int {
		$last = $this->after_id;

		for ( $chunk = 0; $chunk < self::CHUNKS_PER_JOB; $chunk++ ) {
			$rows = \DB::table( Review::TABLE )
				->where( 'id', '>', $last )
				->whereNull( 'plugin_id' )
				->orderBy( 'id' )
				->limit( self::CHUNK )
				->get( array( 'id', 'thread_id' ) );

			if ( $rows->isEmpty() ) {
				return null;
			}

			$threads = Thread::query()->whereIn( 'id', $rows->pluck( 'thread_id' )->map( 'intval' )->all() )->get()->keyBy( 'id' );

			foreach ( $rows as $row ) {
				$thread = $threads->get( (int) $row->thread_id );
				if ( $thread ) {
					Review::index_thread( $thread );
				} else {
					// Its thread is gone without its hooks, like core deleting a conversation's threads with a query.
					\DB::table( Review::TABLE )->where( 'id', $row->id )->delete();
				}
				$last = (int) $row->id;
			}

			if ( $rows->count() < self::CHUNK ) {
				return null;
			}
		}

		return $last;
	}
}
