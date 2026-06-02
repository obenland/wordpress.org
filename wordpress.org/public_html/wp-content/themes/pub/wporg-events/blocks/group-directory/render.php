<?php
/**
 * Server render callback for the Group Directory block.
 *
 * @package WordPressdotorg\Events_Theme
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Events_Theme;

configure_interactivity_store();

$groups                   = get_groups();
$group_suggestion_context = get_group_suggestion_form_context();
$can_review_suggestions   = can_review_group_suggestions();
$group_suggestions        = $can_review_suggestions ? get_group_suggestions_for_review() : array();

$country_terms    = get_taxonomy_term_options( 'wporg_ce_country' );
$group_type_terms = get_taxonomy_term_options( 'wporg_ce_group_type' );
$language_terms   = get_taxonomy_term_options( 'wporg_ce_language' );
$topic_terms      = get_taxonomy_term_options( 'wporg_ce_topic' );

ob_start();
?>
<section <?php echo get_block_wrapper_attributes( array( 'class' => 'wporg-events-section wporg-events-group-directory' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-wp-interactive="<?php echo esc_attr( STORE_NAMESPACE ); ?>">
	<div class="wporg-events-section__header">
		<div>
			<h2><?php esc_html_e( 'Groups', 'wporg' ); ?></h2>
			<p><?php esc_html_e( 'Browse local chapters, online groups, and topic communities.', 'wporg' ); ?></p>
		</div>
	</div>

	<div class="wporg-events-filter-bar" aria-label="<?php esc_attr_e( 'Filter groups', 'wporg' ); ?>">
		<label>
			<span class="screen-reader-text"><?php esc_html_e( 'Search groups', 'wporg' ); ?></span>
			<input class="wporg-events-input" type="search" placeholder="<?php esc_attr_e( 'Search groups', 'wporg' ); ?>" data-wp-on--input="actions.updateGroupQuery" />
		</label>
		<?php render_taxonomy_filter_select( 'groupCountry', __( 'Filter groups by country', 'wporg' ), __( 'All countries', 'wporg' ), $country_terms, 'actions.updateGroupFilter' ); ?>
		<?php render_taxonomy_filter_select( 'groupType', __( 'Filter groups by type', 'wporg' ), __( 'All group types', 'wporg' ), $group_type_terms, 'actions.updateGroupFilter' ); ?>
		<?php render_taxonomy_filter_select( 'groupLanguage', __( 'Filter groups by language', 'wporg' ), __( 'All languages', 'wporg' ), $language_terms, 'actions.updateGroupFilter' ); ?>
		<?php render_taxonomy_filter_select( 'groupTopic', __( 'Filter groups by topic', 'wporg' ), __( 'All topics', 'wporg' ), $topic_terms, 'actions.updateGroupFilter' ); ?>
	</div>

	<?php if ( $groups ) : ?>
		<ul class="wporg-events-grid">
			<?php foreach ( $groups as $group ) : ?>
				<?php
				$group_id      = (int) ( $group['id'] ?? 0 );
				$location      = get_group_location_label( $group );
				$website_url   = (string) ( $group['website_url'] ?? '' );
				$search_text   = get_search_text(
					array(
						$group['title'] ?? '',
						$group['excerpt'] ?? '',
						$location,
						get_group_taxonomy_search_text( $group ),
					)
				);
				$group_context = array(
					'groupCountries' => get_group_term_slugs( $group, 'countries' ),
					'groupLanguages' => get_group_term_slugs( $group, 'languages' ),
					'groupTopics'    => get_group_term_slugs( $group, 'topics' ),
					'groupTypes'     => get_group_term_slugs( $group, 'group_types' ),
					'searchText'     => $search_text,
				);
				?>
				<li class="wporg-events-card" <?php context_attribute( $group_context ); ?> data-wp-bind--hidden="state.isGroupHidden">
					<h3><a href="<?php echo esc_url( get_permalink( $group_id ) ); ?>"><?php echo esc_html( (string) ( $group['title'] ?? '' ) ); ?></a></h3>
					<ul class="wporg-events-meta">
						<?php if ( $location ) : ?>
							<li><?php echo esc_html( $location ); ?></li>
						<?php endif; ?>
						<li>
							<?php
							printf(
								/* translators: %d: number of members. */
								esc_html( _n( '%d member', '%d members', (int) ( $group['member_count'] ?? 0 ), 'wporg' ) ),
								(int) ( $group['member_count'] ?? 0 )
							);
							?>
						</li>
						<li>
							<?php
							printf(
								/* translators: %d: number of events. */
								esc_html( _n( '%d event', '%d events', (int) ( $group['event_count'] ?? 0 ), 'wporg' ) ),
								(int) ( $group['event_count'] ?? 0 )
							);
							?>
						</li>
						<?php if ( $website_url ) : ?>
							<li><a href="<?php echo esc_url( $website_url ); ?>" rel="external nofollow"><?php esc_html_e( 'Website', 'wporg' ); ?></a></li>
						<?php endif; ?>
					</ul>
					<?php if ( ! empty( $group['excerpt'] ) ) : ?>
						<p><?php echo esc_html( (string) $group['excerpt'] ); ?></p>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<p class="wporg-events-empty"><?php esc_html_e( 'No groups are available yet.', 'wporg' ); ?></p>
	<?php endif; ?>

	<div class="wporg-events-panel wporg-events-suggestion" <?php context_attribute( $group_suggestion_context ); ?>>
		<div class="wporg-events-suggestion__header">
			<div>
				<h3><?php esc_html_e( 'Suggest a new group', 'wporg' ); ?></h3>
				<p><?php esc_html_e( 'Know a WordPress community that should be listed here?', 'wporg' ); ?></p>
			</div>
			<button class="wporg-events-button" type="button" data-wp-on--click="actions.toggleGroupSuggestionForm">
				<?php esc_html_e( 'Suggest a group', 'wporg' ); ?>
			</button>
		</div>

		<form class="wporg-events-form" hidden data-wp-on--submit="actions.submitGroupSuggestion" data-wp-bind--hidden="state.isGroupSuggestionFormHidden">
			<label>
				<span><?php esc_html_e( 'Group name', 'wporg' ); ?></span>
				<input class="wporg-events-input" type="text" name="title" required />
			</label>
			<label>
				<span><?php esc_html_e( 'Location', 'wporg' ); ?></span>
				<input class="wporg-events-input" type="text" name="location_label" required />
			</label>
			<label>
				<span><?php esc_html_e( 'City', 'wporg' ); ?></span>
				<input class="wporg-events-input" type="text" name="city" />
			</label>
			<label>
				<span><?php esc_html_e( 'Region', 'wporg' ); ?></span>
				<input class="wporg-events-input" type="text" name="region" />
			</label>
			<label>
				<span><?php esc_html_e( 'Timezone', 'wporg' ); ?></span>
				<input class="wporg-events-input" type="text" name="timezone" />
			</label>
			<label>
				<span><?php esc_html_e( 'Website URL', 'wporg' ); ?></span>
				<input class="wporg-events-input" type="url" name="website_url" />
			</label>
			<label>
				<span><?php esc_html_e( 'Short summary', 'wporg' ); ?></span>
				<input class="wporg-events-input" type="text" name="excerpt" />
			</label>
			<label>
				<span><?php esc_html_e( 'Description', 'wporg' ); ?></span>
				<textarea class="wporg-events-input" name="description" rows="4"></textarea>
			</label>
			<button class="wporg-events-button" type="submit" data-wp-bind--disabled="context.groupSuggestionBusy" data-wp-text="context.groupSuggestionButton">
				<?php echo esc_html( (string) $group_suggestion_context['groupSuggestionButton'] ); ?>
			</button>
		</form>
		<p class="wporg-events-status" aria-live="polite" data-wp-text="context.groupSuggestionMessage"></p>
	</div>

	<?php if ( $can_review_suggestions ) : ?>
		<div class="wporg-events-review-queue">
			<div class="wporg-events-section__header">
				<div>
					<h2><?php esc_html_e( 'Review group suggestions', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'Approve new community groups or ask the submitter for more details.', 'wporg' ); ?></p>
				</div>
			</div>

			<?php if ( $group_suggestions ) : ?>
				<ul class="wporg-events-review-list">
					<?php foreach ( $group_suggestions as $suggestion ) : ?>
						<?php
						$suggestion_id      = (int) ( $suggestion['id'] ?? 0 );
						$submitter          = is_array( $suggestion['submitter'] ?? null ) ? $suggestion['submitter'] : array();
						$submitter_name     = (string) ( $submitter['name'] ?? __( 'Unknown submitter', 'wporg' ) );
						$submitter_url      = (string) ( $submitter['profile_url'] ?? '' );
						$review_status      = (string) ( $suggestion['review_status'] ?? 'pending' );
						$created_group_id   = (int) ( $suggestion['created_group_id'] ?? 0 );
						$duplicate_group_id = (int) ( $suggestion['duplicate_group_id'] ?? 0 );
						?>
						<li class="wporg-events-panel wporg-events-review-item" <?php context_attribute( get_group_suggestion_review_context( $suggestion ) ); ?>>
							<div class="wporg-events-review-item__summary">
								<div>
									<h3><?php echo esc_html( (string) ( $suggestion['title'] ?? '' ) ); ?></h3>
									<ul class="wporg-events-meta">
										<li>
											<?php if ( $submitter_url ) : ?>
												<a href="<?php echo esc_url( $submitter_url ); ?>"><?php echo esc_html( $submitter_name ); ?></a>
											<?php else : ?>
												<?php echo esc_html( $submitter_name ); ?>
											<?php endif; ?>
										</li>
										<?php if ( ! empty( $suggestion['location_label'] ) ) : ?>
											<li><?php echo esc_html( (string) $suggestion['location_label'] ); ?></li>
										<?php endif; ?>
										<?php if ( ! empty( $suggestion['website_url'] ) ) : ?>
											<li><a href="<?php echo esc_url( (string) $suggestion['website_url'] ); ?>" rel="external nofollow"><?php esc_html_e( 'Website', 'wporg' ); ?></a></li>
										<?php endif; ?>
										<li data-wp-text="context.groupSuggestionReviewStatusLabel"><?php echo esc_html( get_group_suggestion_review_status_label( $review_status ) ); ?></li>
									</ul>
								</div>
								<?php if ( $suggestion_id ) : ?>
									<span class="wporg-events-meta">
										<?php
										printf(
											/* translators: %s: group suggestion ID. */
											esc_html__( 'Suggestion #%s', 'wporg' ),
											esc_html( (string) $suggestion_id )
										);
										?>
									</span>
								<?php endif; ?>
							</div>

							<?php if ( ! empty( $suggestion['excerpt'] ) ) : ?>
								<p><?php echo esc_html( (string) $suggestion['excerpt'] ); ?></p>
							<?php endif; ?>

							<?php if ( ! empty( $suggestion['description'] ) ) : ?>
								<div class="wporg-events-review-item__description">
									<?php echo wp_kses_post( wpautop( (string) $suggestion['description'] ) ); ?>
								</div>
							<?php endif; ?>

							<form class="wporg-events-form wporg-events-review-form" data-wp-on--submit="actions.submitGroupSuggestionReview">
								<input type="hidden" name="suggestion_id" value="<?php echo esc_attr( (string) $suggestion_id ); ?>" />
								<label>
									<span><?php esc_html_e( 'Review status', 'wporg' ); ?></span>
									<select class="wporg-events-input" name="review_status" required>
										<option value="pending" <?php selected( 'pending', $review_status ); ?>><?php esc_html_e( 'Pending review', 'wporg' ); ?></option>
										<option value="needs_info" <?php selected( 'needs_info', $review_status ); ?>><?php esc_html_e( 'Needs more information', 'wporg' ); ?></option>
										<option value="approved" <?php selected( 'approved', $review_status ); ?>><?php esc_html_e( 'Approve and create group', 'wporg' ); ?></option>
										<option value="declined" <?php selected( 'declined', $review_status ); ?>><?php esc_html_e( 'Decline', 'wporg' ); ?></option>
									</select>
								</label>
								<label>
									<span><?php esc_html_e( 'Duplicate group ID', 'wporg' ); ?></span>
									<input class="wporg-events-input" type="number" name="duplicate_group_id" min="0" step="1" value="<?php echo esc_attr( (string) $duplicate_group_id ); ?>" />
								</label>
								<label>
									<span><?php esc_html_e( 'Review note', 'wporg' ); ?></span>
									<textarea class="wporg-events-input" name="review_note" rows="3"><?php echo esc_textarea( (string) ( $suggestion['review_note'] ?? '' ) ); ?></textarea>
								</label>
								<button class="wporg-events-button" type="submit" data-wp-bind--disabled="context.groupSuggestionReviewBusy" data-wp-text="context.groupSuggestionReviewButton">
									<?php esc_html_e( 'Save review', 'wporg' ); ?>
								</button>
							</form>
							<p class="wporg-events-status" aria-live="polite" data-wp-text="context.groupSuggestionReviewMessage"></p>
								<p class="wporg-events-meta" data-wp-bind--hidden="state.isCreatedGroupNoticeHidden"<?php echo $created_group_id ? '' : ' hidden'; ?>>
								<?php esc_html_e( 'Created group ID:', 'wporg' ); ?>
								<span data-wp-text="context.groupSuggestionCreatedGroupId"><?php echo esc_html( (string) $created_group_id ); ?></span>
							</p>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="wporg-events-empty"><?php esc_html_e( 'No group suggestions are waiting for review.', 'wporg' ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</section>
<?php
echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block output is escaped while rendering.
