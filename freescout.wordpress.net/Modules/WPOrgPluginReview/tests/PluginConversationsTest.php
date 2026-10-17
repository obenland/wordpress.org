<?php
/**
 * Tests for looking up a plugin's review conversations by the plugin IDs of their review emails.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Tests;

use App\Conversation;
use App\Mailbox;
use App\Thread;
use App\User;
use Illuminate\Http\Request;
use Modules\WPOrgPluginReview\Jobs\IndexPluginIds;
use Modules\WPOrgPluginReview\Providers\WPOrgPluginReviewServiceProvider;
use Modules\WPOrgPluginReview\Services\PluginConversations;
use Modules\WPOrgPluginReview\Services\Review;
use Symfony\Component\HttpKernel\Exception\HttpException;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers the plugin IDs of the index of review emails, filling them in for the rows indexed before them, and the
 * `reviewPluginId` parameter of the API's conversation list.
 *
 * The API & Webhooks module is premium, so it isn't in the test environment: the filter is run on the query the module
 * would build, the way the module runs it.
 */
final class PluginConversationsTest extends TestCase {

	/**
	 * The plugins team's mailbox.
	 *
	 * @var Mailbox
	 */
	private $mailbox;

	/**
	 * Reviewer.
	 *
	 * @var User
	 */
	private $reviewer;

	/**
	 * Registers the module.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgPluginReviewServiceProvider::class );
		$this->reviewer = $this->create_user();
		$this->mailbox  = $this->create_mailbox( 'Plugins', 'plugins@wordpress.test' );
	}

	/**
	 * Review emails are indexed with the plugin ID of their Review ID line, and 0 when it names none.
	 *
	 * @return void
	 */
	public function test_indexes_the_plugin_id_of_review_emails(): void {
		$with    = $this->review( 42 );
		$without = $this->create_thread( $this->conversation(), Thread::TYPE_MESSAGE, '<p>Review ID: R my-plugin/jane 8Aug26/4.3</p>', $this->reviewer, '2026-08-08 10:00:00' );

		$this->assertSame( 42, $this->plugin_id( $with ) );
		$this->assertSame( 0, $this->plugin_id( $without ) );
	}

	/**
	 * The rows indexed before the plugin IDs get theirs; the ones that aren't review emails any more, or whose thread
	 * is gone, leave the index; and doing it again changes nothing.
	 *
	 * @return void
	 */
	public function test_indexes_the_plugin_ids_of_rows_indexed_before_them(): void {
		$review  = $this->review( 42 );
		$other   = $this->review( 43 );
		$changed = $this->review( 44 );
		$kept    = $this->review( 45 );

		// As the rows were before the column, with a thread changed and another deleted without their hooks.
		\DB::table( Review::TABLE )->where( 'thread_id', '!=', $kept->id )->update( array( 'plugin_id' => null ) );
		\DB::table( 'threads' )->where( 'id', $changed->id )->update( array( 'body' => '<p>Thanks for the update.</p>' ) );
		\DB::table( 'threads' )->where( 'id', $other->id )->delete();

		$this->assertNull( ( new IndexPluginIds() )->index_batch() );

		$this->assertSame( 42, $this->plugin_id( $review ) );
		$this->assertSame( 45, $this->plugin_id( $kept ) );
		$this->assertFalse( \DB::table( Review::TABLE )->where( 'thread_id', $other->id )->exists() );
		$this->assertFalse( \DB::table( Review::TABLE )->where( 'thread_id', $changed->id )->exists() );

		\DB::table( Review::TABLE )->update( array( 'plugin_id' => null ) );
		\Artisan::call(
			'wporgpluginreview:index',
			array(
				'--plugin-ids' => true,
				'--now'        => true,
			)
		);

		$this->assertSame( array( 42, 45 ), \DB::table( Review::TABLE )->orderBy( 'plugin_id' )->pluck( 'plugin_id' )->map( 'intval' )->all() );
	}

	/**
	 * A batch starts after the last row the previous one read, so rows it can't read don't stop the rest.
	 *
	 * @return void
	 */
	public function test_continues_after_the_rows_of_the_previous_batch(): void {
		$first  = $this->review( 42 );
		$second = $this->review( 43 );
		\DB::table( Review::TABLE )->update( array( 'plugin_id' => null ) );

		$after = (int) \DB::table( Review::TABLE )->where( 'thread_id', $first->id )->value( 'id' );
		$this->assertNull( ( new IndexPluginIds( $after ) )->index_batch() );

		$this->assertNull( \DB::table( Review::TABLE )->where( 'thread_id', $first->id )->value( 'plugin_id' ) );
		$this->assertSame( 43, $this->plugin_id( $second ) );
	}

	/**
	 * The parameter lists every conversation with a review email about the plugin, open or closed, and combines with
	 * the module's other parameters.
	 *
	 * @return void
	 */
	public function test_lists_the_conversations_of_a_plugin(): void {
		$open   = $this->review( 42 )->conversation;
		$closed = $this->review( 42 )->conversation;
		$closed->update( array( 'status' => Conversation::STATUS_CLOSED ) );
		$this->review( 43 );
		$this->create_thread( $this->conversation(), Thread::TYPE_CUSTOMER, '<p>Review ID: R my-plugin/jane 8Aug26/4.3 (P0TDX42HGN)</p>', null, '2026-08-08 11:00:00' );

		$this->assertSame( array( $open->id, $closed->id ), $this->search( '42' ) );
		$this->assertSame( array( $open->id ), $this->search( '42', fn( $query ) => $query->where( 'status', Conversation::STATUS_ACTIVE ) ) );
		$this->assertSame( array(), $this->search( '41' ) );
		$this->assertCount( 4, $this->search( null ) );
	}

	/**
	 * A value that isn't a plugin ID is refused rather than ignored, which would list every conversation as the plugin's.
	 *
	 * @return void
	 */
	public function test_refuses_what_isnt_a_plugin_id(): void {
		foreach ( array( 'abc', '-3', '0', '4.2', array( 42 ) ) as $value ) {
			try {
				$this->search( $value );
				$this->fail( 'Not refused: ' . json_encode( $value ) );
			} catch ( HttpException $e ) {
				$this->assertSame( 400, $e->getStatusCode() );
			}
		}
	}

	/**
	 * The module's provider adds the filter the API & Webhooks module runs.
	 *
	 * @return void
	 */
	public function test_is_added_to_the_api_query(): void {
		$review = $this->review( 42 );
		$this->review( 43 );

		$query = \Eventy::filter( 'api.conversations.query', $this->base_query(), Request::create( '/api/conversations', 'GET', array( PluginConversations::PARAMETER => '42' ) ) );

		$this->assertSame( array( (int) $review->conversation_id ), array_map( 'intval', $query->pluck( 'conversations.id' )->all() ) );
	}

	/**
	 * A conversation in the plugins team's mailbox.
	 *
	 * @return Conversation
	 */
	private function conversation(): Conversation {
		return $this->create_conversation( $this->mailbox, $this->create_sender() );
	}

	/**
	 * A review email about a plugin, in a conversation of its own.
	 *
	 * @param int $plugin_id Plugin ID.
	 * @return Thread
	 */
	private function review( int $plugin_id ): Thread {
		return $this->create_thread( $this->conversation(), Thread::TYPE_MESSAGE, '<p>Review ID: R my-plugin/jane/8Aug26/T1 8Aug26/4.3 (P0TDX' . $plugin_id . 'HGN)</p>', $this->reviewer, '2026-08-08 10:00:00' );
	}

	/**
	 * The plugin ID a thread is indexed with.
	 *
	 * @param Thread $thread Thread.
	 * @return int|null
	 */
	private function plugin_id( Thread $thread ): ?int {
		$plugin_id = \DB::table( Review::TABLE )->where( 'thread_id', $thread->id )->value( 'plugin_id' );

		return null === $plugin_id ? null : (int) $plugin_id;
	}

	/**
	 * Runs the filter on the conversation list query.
	 *
	 * @param mixed         $plugin_id Value of the parameter; null leaves it out.
	 * @param callable|null $more      Adds the module's other parameters to the query.
	 * @return int[] IDs of the conversations listed.
	 */
	private function search( mixed $plugin_id, ?callable $more = null ): array {
		$query = $this->base_query();
		if ( $more ) {
			$more( $query );
		}
		$parameters = null === $plugin_id ? array() : array( PluginConversations::PARAMETER => $plugin_id );
		$query      = PluginConversations::filter( $query, Request::create( '/api/conversations', 'GET', $parameters ) );

		return array_map( 'intval', $query->pluck( 'conversations.id' )->all() );
	}

	/**
	 * The query the module starts from.
	 *
	 * @return mixed
	 */
	private function base_query(): mixed {
		return Conversation::select( array( 'conversations.*' ) )->where( 'mailbox_id', $this->mailbox->id )->orderBy( 'conversations.id', 'asc' );
	}
}
