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
 * Adds a `body` text search to the API & Webhooks module's conversation list, like HelpScout's.
 *
 * Thread bodies can't be indexed, so the module's other parameters pick the conversations first, and searches over
 * MAX_CONVERSATIONS or MAX_THREADS are refused.
 */
final class ConversationTextSearch {

	/**
	 * The parameter, named after HelpScout's search field.
	 *
	 * @var string
	 */
	public const PARAMETER = 'body';

	/**
	 * Thread types to read: `customer`, `message`, `note`, comma-separated; all of them by default.
	 *
	 * @var string
	 */
	public const THREAD_TYPE_PARAMETER = 'bodyThreadType';

	/**
	 * Only threads created since this date.
	 *
	 * @var string
	 */
	public const SINCE_PARAMETER = 'bodySince';

	/**
	 * Only threads created before this date.
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
	 * The most threads one search reads, about 1.5 seconds of review emails.
	 *
	 * @var int
	 */
	public const MAX_THREADS = 15000;

	/**
	 * The most conversations one search covers, so one without other filters is refused before listing them all.
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
			// IDs, not a correlated EXISTS, which MariaDB can turn into a scan of every thread.
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
	 * @throws \InvalidArgumentException Caught, for a value that isn't a string.
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

		// Without the module's columns, order, and page.
		$candidates->columns = null;
		$candidates->orders  = null;
		$candidates->offset  = null;
		$candidates->limit   = null;

		$ids = $candidates->limit( $max + 1 )->pluck( 'conversations.id' )->all();

		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	/**
	 * The threads of the candidates a search would read, drafts included, as their state isn't indexed.
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
	 * Answers with an error, as an empty list would read as no match.
	 *
	 * @param int    $status  HTTP status.
	 * @param string $message Why.
	 * @return never
	 */
	private static function refuse( int $status, string $message ): never {
		abort( $status, $message );
	}
}
