<?php
/**
 * Looks up a plugin's review conversations through the API.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Services;

use Illuminate\Support\Facades\Schema;

/**
 * Adds a `reviewPluginId` parameter to the API's conversation list: only the conversations with a review email about
 * that plugin are listed, by the plugin ID of their Review ID lines (`P0TDX{plugin ID}HGN`), from the index of review
 * emails. The review tools look up the conversation of the plugin they're reviewing this way, whatever its status,
 * whoever wrote to it, and however many emails the plugins team's mailbox has.
 *
 * It goes through the API & Webhooks module's `api.conversations.query` filter, so the module keeps authenticating the
 * key, limiting it to its mailboxes, combining it with its other parameters (`status`, `tag`…), and paginating; nothing
 * here depends on its classes.
 */
final class PluginConversations {

	/**
	 * The parameter.
	 *
	 * @var string
	 */
	public const PARAMETER = 'reviewPluginId';

	/**
	 * Whether the index has its plugin IDs, checked once per process.
	 *
	 * @var bool|null
	 */
	private static $ready = null;

	/**
	 * Limits the conversation list query to the conversations with a review email about the plugin.
	 *
	 * @param mixed $query   Query for the conversations, with the module's other parameters applied.
	 * @param mixed $request Request.
	 * @return mixed
	 */
	public static function filter( mixed $query, mixed $request ): mixed {
		if ( ! is_object( $query ) || ! method_exists( $query, 'getQuery' ) || ! is_object( $request ) || ! method_exists( $request, 'input' ) ) {
			return $query;
		}

		$plugin_id = $request->input( self::PARAMETER );

		if ( null === $plugin_id || '' === $plugin_id ) {
			return $query;
		}

		// Refused rather than ignored: an ignored filter would list every conversation as the plugin's.
		if ( ! is_scalar( $plugin_id ) || ! ctype_digit( (string) $plugin_id ) || (int) $plugin_id <= 0 ) {
			abort( 400, 'The `' . self::PARAMETER . '` parameter takes a plugin ID, a positive number.' );
		}

		if ( ! self::ready() ) {
			abort( 503, 'The plugin IDs of the review emails aren\'t indexed yet.' );
		}

		$query->whereIn(
			'conversations.id',
			static function ( $reviews ) use ( $plugin_id ): void {
				$reviews->select( 'conversation_id' )->from( Review::TABLE )->where( 'plugin_id', (int) $plugin_id );
			}
		);

		return $query;
	}

	/**
	 * Whether the index has the plugin ID column, which its migration adds.
	 *
	 * @return bool
	 */
	private static function ready(): bool {
		if ( null === self::$ready ) {
			try {
				self::$ready = Schema::hasColumn( Review::TABLE, 'plugin_id' );
			} catch ( \Throwable $e ) {
				\Log::error( '[WPOrgPluginReview] Could not check the index of review emails: ' . $e->getMessage() );

				return false;
			}
		}

		return self::$ready;
	}
}
