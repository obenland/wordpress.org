<?php
/**
 * Looks up conversations by the text of their threads through the API.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Services;

use App\Thread;
use Carbon\Carbon;

/**
 * Adds a `body` parameter to the API's conversation list: only conversations with an email, reply, or note
 * containing that text are listed, like HelpScout's `body:` search, which the plugins team's review tools
 * relied on. Three more parameters narrow which threads are read:
 *
 * - `bodyThreadType`: comma-separated `customer`, `message`, `note`; all three when it's left out.
 * - `bodySince`, `bodyBefore`: only threads created since, or before, a date (ISO 8601, like `createdSince`).
 *
 * The API & Webhooks module lists conversations by sender, subject, tag, and dates, but not by what they say.
 * It goes through the module's `api.conversations.query` filter rather than a route of our own, so the module
 * keeps authenticating the key, limiting it to its mailboxes, paginating, and formatting the list, and nothing
 * here depends on its classes.
 *
 * Thread bodies can't be indexed for this (a `LIKE '%…%'`, and core's tables aren't ours to change), so every
 * search reads the body of every thread it covers, and that's what it costs: about 0.1 ms per email of 20 KB.
 * It's kept to a bounded amount of them:
 *
 * - The module's other parameters (`status`, `tag`, `createdSince`, `customerId`…) pick the conversations
 *   first, from their indexes, and only their threads are read, through the threads' conversation index.
 *   A correlated `EXISTS` would leave that to the optimizer, which rewrites it into a scan of every thread
 *   in the helpdesk when the other filters look weak.
 * - The threads it would read are counted first, from that index, and searches over MAX_THREADS of them, or
 *   over MAX_CONVERSATIONS conversations, are refused: the answer asks for narrower filters.
 * - The matches go back to the module's query as IDs, so its count and its page don't search again.
 *
 * Refusals and failures answer an error rather than an empty list: an empty list would read as "no
 * conversation says that", and the review tools act on that answer. Only requests with `body` are affected.
 */
final class ConversationTextSearch {

	/**
	 * The parameter, named after HelpScout's search field.
	 *
	 * @var string
	 */
	public const PARAMETER = 'body';

	/**
	 * The parameter narrowing which threads are read by their type.
	 *
	 * @var string
	 */
	public const THREAD_TYPE_PARAMETER = 'bodyThreadType';

	/**
	 * The parameter reading only the threads created since a date.
	 *
	 * @var string
	 */
	public const SINCE_PARAMETER = 'bodySince';

	/**
	 * The parameter reading only the threads created before a date.
	 *
	 * @var string
	 */
	public const BEFORE_PARAMETER = 'bodyBefore';

	/**
	 * Shorter texts match too much to be worth reading every thread for.
	 *
	 * @var int
	 */
	public const MIN_LENGTH = 3;

	/**
	 * The most threads one search reads, what a search costs: about 1.5 seconds when they're the plugins team's
	 * review emails, of 20 KB on average. Finding a plugin's review among the active ones reads about 2,000 of
	 * them; every review email of 6 months is about 100,000, at 1,200 new reviews a week.
	 *
	 * Can be changed with the `wporgsite.conversation_text_search.max_threads` filter.
	 *
	 * @var int
	 */
	public const MAX_THREADS = 15000;

	/**
	 * The most conversations whose threads one search counts. Only their IDs and their threads' index are read,
	 * about 0.1 seconds for 30,000, but it keeps a search with no other filter from listing the whole helpdesk
	 * before refusing it. 3 months of approved reviews are about 10,000 conversations.
	 *
	 * Can be changed with the `wporgsite.conversation_text_search.max_conversations` filter.
	 *
	 * @var int
	 */
	public const MAX_CONVERSATIONS = 25000;

	/**
	 * Conversations whose threads are counted or read per query.
	 *
	 * @var int
	 */
	private const CHUNK = 500;

	/**
	 * Thread types that can be searched, by the names the API uses.
	 *
	 * @var array<string, int>
	 */
	private const THREAD_TYPES = array(
		'customer' => Thread::TYPE_CUSTOMER,
		'message'  => Thread::TYPE_MESSAGE,
		'note'     => Thread::TYPE_NOTE,
	);

	/**
	 * Limits the conversation list query to conversations with a thread containing the text.
	 *
	 * @param mixed $query   Query for the conversations, with the module's other parameters applied.
	 * @param mixed $request Request.
	 * @return mixed
	 */
	public static function filter( mixed $query, mixed $request ): mixed {
		if ( ! is_object( $query ) || ! method_exists( $query, 'getQuery' ) || ! is_object( $request ) || ! method_exists( $request, 'input' ) ) {
			return $query;
		}

		$text = $request->input( self::PARAMETER );

		if ( null === $text || ( is_string( $text ) && '' === trim( $text ) ) ) {
			return $query;
		}

		if ( ! is_string( $text ) || mb_strlen( trim( $text ) ) < self::MIN_LENGTH ) {
			self::refuse( 400, 'The `' . self::PARAMETER . '` text needs ' . self::MIN_LENGTH . ' characters or more.' );
		}

		$threads = array(
			'types'  => self::thread_types( $request->input( self::THREAD_TYPE_PARAMETER ) ),
			'since'  => self::date( self::SINCE_PARAMETER, $request->input( self::SINCE_PARAMETER ) ),
			'before' => self::date( self::BEFORE_PARAMETER, $request->input( self::BEFORE_PARAMETER ) ),
		);

		$max_conversations = (int) \Eventy::filter( 'wporgsite.conversation_text_search.max_conversations', self::MAX_CONVERSATIONS );
		$max_threads       = (int) \Eventy::filter( 'wporgsite.conversation_text_search.max_threads', self::MAX_THREADS );

		try {
			$candidates = self::candidate_ids( $query, $max_conversations );
			$to_read    = count( $candidates ) > $max_conversations ? 0 : self::count_threads( $query, $candidates, $threads );
		} catch ( \Throwable $e ) {
			\Log::error( 'WPOrgSite: conversation text search: ' . $e->getMessage() );
			self::refuse( 500, 'The conversations could not be searched.' );
		}

		if ( count( $candidates ) > $max_conversations ) {
			self::refuse( 400, 'The `' . self::PARAMETER . '` search reads the threads of ' . $max_conversations . ' conversations at most, and these filters match more. Narrow them with status, tag, createdSince, customerId or customerEmail.' );
		}

		if ( $to_read > $max_threads ) {
			self::refuse( 400, 'The `' . self::PARAMETER . '` search would read ' . $to_read . ' threads, and it reads ' . $max_threads . ' at most. Narrow it with ' . self::THREAD_TYPE_PARAMETER . ', ' . self::SINCE_PARAMETER . ' and ' . self::BEFORE_PARAMETER . ', or the conversations with status, tag, createdSince, customerId or customerEmail.' );
		}

		try {
			$matches = $to_read > 0 ? self::matching_ids( $query, $candidates, trim( $text ), $threads ) : array();
		} catch ( \Throwable $e ) {
			\Log::error( 'WPOrgSite: conversation text search: ' . $e->getMessage() );
			self::refuse( 500, 'The conversations could not be searched.' );
		}

		if ( empty( $matches ) ) {
			$query->whereRaw( '1 = 0' );
		} else {
			$query->whereIn( 'conversations.id', $matches );
		}

		return $query;
	}

	/**
	 * The thread types of the parameter, every searchable one when it names none.
	 *
	 * @param mixed $value Value of the parameter.
	 * @return int[]
	 */
	private static function thread_types( mixed $value ): array {
		if ( null === $value || '' === $value ) {
			return array_values( self::THREAD_TYPES );
		}

		$types = array();
		foreach ( explode( ',', is_string( $value ) ? $value : '' ) as $name ) {
			$name = strtolower( trim( $name ) );
			if ( ! isset( self::THREAD_TYPES[ $name ] ) ) {
				self::refuse( 400, 'The `' . self::THREAD_TYPE_PARAMETER . '` parameter takes customer, message, or note, separated by commas.' );
			}
			$types[] = self::THREAD_TYPES[ $name ];
		}

		return array_values( array_unique( $types ) );
	}

	/**
	 * A date parameter in the database's time zone, like the module turns `createdSince` into, or null.
	 *
	 * @param string $parameter Name of the parameter, for the error.
	 * @param mixed  $value     Value of the parameter.
	 * @return string|null
	 * @throws \InvalidArgumentException If the value isn't a string; caught, so it's refused like any other value that isn't a date.
	 */
	private static function date( string $parameter, mixed $value ): ?string {
		if ( null === $value || '' === $value ) {
			return null;
		}

		try {
			if ( ! is_string( $value ) ) {
				throw new \InvalidArgumentException( 'Not a string' );
			}

			return Carbon::parse( $value )->setTimezone( (string) config( 'app.timezone' ) )->format( 'Y-m-d H:i:s' );
		} catch ( \Throwable $e ) {
			self::refuse( 400, 'The `' . $parameter . '` parameter takes a date, like 2026-04-01T00:00:00Z.' );
		}
	}

	/**
	 * IDs of the conversations the module's other parameters select, one more than the limit at most.
	 *
	 * @param mixed $query Query for the conversations.
	 * @param int   $max   The most conversations a search covers.
	 * @return int[]
	 */
	private static function candidate_ids( mixed $query, int $max ): array {
		$candidates = ( clone $query )->getQuery();

		// Only the IDs: without the module's columns, order, and page.
		$candidates->columns = null;
		$candidates->orders  = null;
		$candidates->offset  = null;
		$candidates->limit   = null;

		$ids = $candidates->limit( $max + 1 )->pluck( 'conversations.id' )->all();

		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	/**
	 * The threads of the candidates a search would read, counted from the threads' indexes.
	 *
	 * Drafts are counted too: the state isn't in those indexes, and they're few.
	 *
	 * @param mixed $query      Query for the conversations, for its connection.
	 * @param int[] $candidates Conversation IDs.
	 * @param array $threads    Which threads: `types`, `since`, `before`.
	 * @return int
	 */
	private static function count_threads( mixed $query, array $candidates, array $threads ): int {
		$count = 0;
		foreach ( array_chunk( $candidates, self::CHUNK ) as $chunk ) {
			$count += (int) self::threads_query( $query, $chunk, $threads )->count();
		}

		return $count;
	}

	/**
	 * IDs of the conversations, among the candidates, with a published thread containing the text.
	 *
	 * @param mixed  $query      Query for the conversations, for its connection.
	 * @param int[]  $candidates Conversation IDs.
	 * @param string $text       Text to search for, matched literally.
	 * @param array  $threads    Which threads: `types`, `since`, `before`.
	 * @return int[]
	 */
	private static function matching_ids( mixed $query, array $candidates, string $text, array $threads ): array {
		$like     = '%' . addcslashes( $text, '\\%_' ) . '%';
		$operator = \Helper::isPgSql() ? 'ilike' : 'like';
		$matches  = array();

		foreach ( array_chunk( $candidates, self::CHUNK ) as $chunk ) {
			$found = self::threads_query( $query, $chunk, $threads )
				->where( 'state', Thread::STATE_PUBLISHED )
				->where( 'body', $operator, $like )
				->distinct()
				->pluck( 'conversation_id' )
				->all();

			array_push( $matches, ...array_map( 'intval', $found ) );
		}

		return array_values( array_unique( $matches ) );
	}

	/**
	 * The threads of some conversations a search covers.
	 *
	 * @param mixed $query   Query for the conversations, for its connection.
	 * @param int[] $chunk   Conversation IDs.
	 * @param array $threads Which threads: `types`, `since`, `before`.
	 * @return mixed
	 */
	private static function threads_query( mixed $query, array $chunk, array $threads ): mixed {
		$builder = $query->getQuery()->getConnection()->table( 'threads' )
			->whereIn( 'conversation_id', $chunk )
			->whereIn( 'type', $threads['types'] );

		if ( null !== $threads['since'] ) {
			$builder->where( 'created_at', '>=', $threads['since'] );
		}
		if ( null !== $threads['before'] ) {
			$builder->where( 'created_at', '<', $threads['before'] );
		}

		return $builder;
	}

	/**
	 * Answers the request with an error, which the module's API turns into JSON.
	 *
	 * @param int    $status  HTTP status.
	 * @param string $message Why.
	 * @return never
	 */
	private static function refuse( int $status, string $message ): never {
		abort( $status, $message );
	}
}
