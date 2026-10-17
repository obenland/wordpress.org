<?php
/**
 * Tests for looking up conversations by the text of their threads.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Tests;

use App\Conversation;
use App\Thread;
use Illuminate\Http\Request;
use Modules\WPOrgSite\Providers\WPOrgSiteServiceProvider;
use Modules\WPOrgSite\Services\ConversationTextSearch;
use Symfony\Component\HttpKernel\Exception\HttpException;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers the `body` parameter of the API's conversation list.
 *
 * The API & Webhooks module is premium, so it isn't in the test environment: the filter is run on the query
 * the module would build, the way the module runs it.
 */
final class ConversationTextSearchTest extends TestCase {

	/**
	 * Registers the module.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgSiteServiceProvider::class );
	}

	/**
	 * Only conversations with a published email, reply, or note containing the text are listed.
	 *
	 * @return void
	 */
	public function test_lists_conversations_whose_threads_contain_the_text(): void {
		$mailbox = $this->create_mailbox();
		$agent   = $this->create_user();

		$in_reply = $this->create_conversation( $mailbox, $this->create_sender() );
		$this->create_thread( $in_reply, Thread::TYPE_MESSAGE, '<p>Review ID: R my-plugin/me/1Oct26/T1 1Oct26/1.0 (P0TDX123HGN)</p>', $agent, '2026-10-01 10:00:00' );

		$in_note = $this->create_conversation( $mailbox, $this->create_sender() );
		$this->create_thread( $in_note, Thread::TYPE_NOTE, 'See P0TDX123HGN', $agent, '2026-10-01 10:00:00' );

		$in_draft = $this->create_conversation( $mailbox, $this->create_sender() );
		$draft    = $this->create_thread( $in_draft, Thread::TYPE_MESSAGE, 'P0TDX123HGN', $agent, '2026-10-01 10:00:00' );

		$draft->state = Thread::STATE_DRAFT;
		$draft->save();

		$other = $this->create_conversation( $mailbox, $this->create_sender() );
		$this->create_thread( $other, Thread::TYPE_MESSAGE, 'P0TDX1234HGN', $agent, '2026-10-01 10:00:00' );

		$this->assertSame( array( $in_reply->id, $in_note->id ), $this->search( 'P0TDX123HGN' ) );
	}

	/**
	 * The text is matched literally, so LIKE's wildcards in it don't match anything else.
	 *
	 * @return void
	 */
	public function test_matches_wildcards_literally(): void {
		$mailbox = $this->create_mailbox();
		$agent   = $this->create_user();

		$percent = $this->create_conversation( $mailbox, $this->create_sender() );
		$this->create_thread( $percent, Thread::TYPE_MESSAGE, 'A 100% sure thing', $agent, '2026-10-01 10:00:00' );

		$other = $this->create_conversation( $mailbox, $this->create_sender() );
		$this->create_thread( $other, Thread::TYPE_MESSAGE, 'A 1000 sure things', $agent, '2026-10-01 10:00:00' );

		$this->assertSame( array( $percent->id ), $this->search( '100%' ) );
		$this->assertSame( array(), $this->search( '1_0' ) );
		$this->assertSame( array(), $this->search( '1\\0' ) );
	}

	/**
	 * Only the thread types asked for are read.
	 *
	 * @return void
	 */
	public function test_reads_only_the_thread_types_asked_for(): void {
		$mailbox = $this->create_mailbox();
		$agent   = $this->create_user();

		$in_reply = $this->create_conversation( $mailbox, $this->create_sender() );
		$this->create_thread( $in_reply, Thread::TYPE_MESSAGE, 'P0TDX123HGN', $agent, '2026-10-01 10:00:00' );

		$in_quote = $this->create_conversation( $mailbox, $this->create_sender() );
		$this->create_thread( $in_quote, Thread::TYPE_CUSTOMER, '<blockquote>P0TDX123HGN</blockquote>', null, '2026-10-01 10:00:00' );

		$this->assertSame( array( $in_reply->id, $in_quote->id ), $this->search( 'P0TDX123HGN' ) );
		$this->assertSame( array( $in_reply->id ), $this->search( 'P0TDX123HGN', 'message' ) );
		$this->assertSame( array( $in_reply->id, $in_quote->id ), $this->search( 'P0TDX123HGN', 'Message, customer' ) );
		$this->assert_refused( 400, 'P0TDX123HGN', 'lineitem' );
	}

	/**
	 * Without the parameter the list is left alone, and a search that can't be made is refused rather than
	 * listing nothing, which would read as nothing matching.
	 *
	 * @return void
	 */
	public function test_leaves_other_lists_alone_and_refuses_what_it_cannot_search(): void {
		$mailbox      = $this->create_mailbox();
		$conversation = $this->create_conversation( $mailbox, $this->create_sender() );
		$this->create_thread( $conversation, Thread::TYPE_CUSTOMER, 'Hello there', null, '2026-10-01 10:00:00' );

		$this->assertSame( array( $conversation->id ), $this->search( null ) );
		$this->assertSame( array( $conversation->id ), $this->search( '  ' ) );
		$this->assert_refused( 400, 'He' );
		$this->assert_refused( 400, array( 'Hello' ) );
	}

	/**
	 * Searches covering more conversations than the limit are refused, before any thread is read.
	 *
	 * @return void
	 */
	public function test_refuses_searches_over_too_many_conversations(): void {
		$mailbox = $this->create_mailbox();
		foreach ( array( 1, 2, 3 ) as $i ) {
			$conversation = $this->create_conversation( $mailbox, $this->create_sender() );
			$this->create_thread( $conversation, Thread::TYPE_CUSTOMER, 'Hello there ' . $i, null, '2026-10-01 10:00:00' );
		}

		\Eventy::addFilter( 'wporgsite.conversation_text_search.max_conversations', static fn(): int => 2 );

		try {
			$this->assert_refused( 400, 'Hello' );
		} finally {
			\Eventy::removeAllFilters( 'wporgsite.conversation_text_search.max_conversations' );
		}

		$this->assertCount( 3, $this->search( 'Hello' ) );
	}

	/**
	 * Searches that would read more threads than the limit are refused, counting only the threads they read.
	 *
	 * @return void
	 */
	public function test_refuses_searches_over_too_many_threads(): void {
		$mailbox      = $this->create_mailbox();
		$agent        = $this->create_user();
		$conversation = $this->create_conversation( $mailbox, $this->create_sender() );
		$this->create_thread( $conversation, Thread::TYPE_CUSTOMER, 'Hello there', null, '2026-10-01 10:00:00' );
		$this->create_thread( $conversation, Thread::TYPE_CUSTOMER, 'Hello again', null, '2026-10-02 10:00:00' );
		$this->create_thread( $conversation, Thread::TYPE_MESSAGE, 'Hello, P0TDX123HGN', $agent, '2026-10-03 10:00:00' );

		\Eventy::addFilter( 'wporgsite.conversation_text_search.max_threads', static fn(): int => 2 );

		try {
			$this->assert_refused( 400, 'Hello' );
			$this->assertSame( array( $conversation->id ), $this->search( 'Hello', 'message' ) );
			$this->assertSame( array( $conversation->id ), $this->search( 'Hello', null, array( ConversationTextSearch::SINCE_PARAMETER => '2026-10-02T00:00:00Z' ) ) );
		} finally {
			\Eventy::removeAllFilters( 'wporgsite.conversation_text_search.max_threads' );
		}
	}

	/**
	 * Only the threads created since and before the dates asked for are read.
	 *
	 * @return void
	 */
	public function test_reads_only_the_threads_of_the_dates_asked_for(): void {
		$mailbox = $this->create_mailbox();
		$agent   = $this->create_user();

		$september = $this->create_conversation( $mailbox, $this->create_sender() );
		$this->create_thread( $september, Thread::TYPE_MESSAGE, 'P0TDX123HGN', $agent, '2026-09-15 10:00:00' );

		$october = $this->create_conversation( $mailbox, $this->create_sender() );
		$this->create_thread( $october, Thread::TYPE_MESSAGE, 'P0TDX123HGN', $agent, '2026-10-15 10:00:00' );

		$this->assertSame( array( $september->id, $october->id ), $this->search( 'P0TDX123HGN' ) );
		$this->assertSame( array( $october->id ), $this->search( 'P0TDX123HGN', null, array( ConversationTextSearch::SINCE_PARAMETER => '2026-10-01T00:00:00Z' ) ) );
		$this->assertSame( array( $september->id ), $this->search( 'P0TDX123HGN', null, array( ConversationTextSearch::BEFORE_PARAMETER => '2026-10-01T00:00:00Z' ) ) );
		$this->assertSame(
			array(),
			$this->search(
				'P0TDX123HGN',
				null,
				array(
					ConversationTextSearch::SINCE_PARAMETER  => '2026-09-20T00:00:00Z',
					ConversationTextSearch::BEFORE_PARAMETER => '2026-10-10T00:00:00Z',
				)
			)
		);
		$this->assert_refused( 400, 'P0TDX123HGN', null, array( ConversationTextSearch::SINCE_PARAMETER => 'last tuesday-ish' ) );
	}

	/**
	 * The module's provider adds the filter the API & Webhooks module runs.
	 *
	 * @return void
	 */
	public function test_is_added_to_the_api_query(): void {
		$mailbox      = $this->create_mailbox();
		$conversation = $this->create_conversation( $mailbox, $this->create_sender() );
		$this->create_thread( $conversation, Thread::TYPE_CUSTOMER, 'Hello there', null, '2026-10-01 10:00:00' );

		$query = \Eventy::filter( 'api.conversations.query', $this->base_query(), Request::create( '/api/conversations', 'GET', array( 'body' => 'Nothing like it' ) ) );

		$this->assertSame( array(), $query->pluck( 'conversations.id' )->all() );
	}

	/**
	 * Runs the filter on the conversation list query.
	 *
	 * @param mixed       $text  Value of the parameter; null leaves it out.
	 * @param string|null $types Value of the thread type parameter; null leaves it out.
	 * @param array       $more  More parameters.
	 * @return int[] IDs of the conversations listed.
	 */
	private function search( mixed $text, ?string $types = null, array $more = array() ): array {
		$parameters = array_filter(
			array_merge(
				array(
					ConversationTextSearch::PARAMETER => $text,
					ConversationTextSearch::THREAD_TYPE_PARAMETER => $types,
				),
				$more
			),
			static fn( $value ): bool => null !== $value
		);
		$query      = ConversationTextSearch::filter( $this->base_query(), Request::create( '/api/conversations', 'GET', $parameters ) );

		return array_map( 'intval', $query->pluck( 'conversations.id' )->all() );
	}

	/**
	 * Asserts that a search is answered with an error.
	 *
	 * @param int         $status HTTP status expected.
	 * @param mixed       $text   Value of the parameter.
	 * @param string|null $types  Value of the thread type parameter; null leaves it out.
	 * @param array       $more   More parameters.
	 * @return void
	 */
	private function assert_refused( int $status, mixed $text, ?string $types = null, array $more = array() ): void {
		try {
			$this->search( $text, $types, $more );
		} catch ( HttpException $e ) {
			$this->assertSame( $status, $e->getStatusCode() );
			$this->assertNotSame( '', $e->getMessage() );
			return;
		}

		$this->fail( 'The search was not refused.' );
	}

	/**
	 * The query the module starts from.
	 *
	 * @return mixed
	 */
	private function base_query(): mixed {
		return Conversation::select( array( 'conversations.*' ) )->orderBy( 'conversations.id', 'asc' );
	}
}
