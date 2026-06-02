<?php
/**
 * Comments template for event discussions.
 *
 * @package WordPressdotorg\Events_Theme
 */

declare( strict_types = 1 );

$requires_login = 'wporg_ce_event' === get_post_type() && ! is_user_logged_in();

if ( post_password_required() ) {
	return;
}
?>

<section id="comments" class="wporg-events-comments__inner">
	<?php if ( have_comments() ) : ?>
		<h2>
			<?php
			printf(
				/* translators: %d: number of comments. */
				esc_html( _n( '%d comment', '%d comments', get_comments_number(), 'wporg' ) ),
				(int) get_comments_number()
			);
			?>
		</h2>

		<ol class="wporg-events-comment-list">
			<?php
			wp_list_comments(
				array(
					'avatar_size' => 40,
					'short_ping'  => true,
					'style'       => 'ol',
				)
			);
			?>
		</ol>

		<?php the_comments_navigation(); ?>
	<?php endif; ?>

	<?php
	if ( comments_open() ) {
		$comment_form_args = array(
			'class_form'          => 'wporg-events-form wporg-events-comment-form',
			'comment_notes_after' => '',
			'title_reply'         => __( 'Join the discussion', 'wporg' ),
			'title_reply_before'  => '<h2 id="reply-title" class="comment-reply-title">',
			'title_reply_after'   => '</h2>',
		);

		if ( $requires_login ) {
			$login_link = sprintf(
				'<a href="%s">%s</a>',
				esc_url( wp_login_url( get_permalink() ) ),
				esc_html__( 'Log in', 'wporg' )
			);

			$comment_form_args = array_merge(
				$comment_form_args,
				array(
					'must_log_in'   => sprintf(
						'<p class="must-log-in">%s</p>',
						sprintf(
							/* translators: %s: Log in link. */
							esc_html__( '%s with your WordPress.org account to join the discussion.', 'wporg' ),
							$login_link
						)
					),
					'logged_in_as'  => '',
					'comment_field' => '',
				)
			);

			add_filter( 'pre_option_comment_registration', '__return_true' );
		}

		comment_form(
			$comment_form_args
		);

		if ( $requires_login ) {
			remove_filter( 'pre_option_comment_registration', '__return_true' );
		}
	} elseif ( get_comments_number() ) {
		?>
		<p class="wporg-events-empty"><?php esc_html_e( 'Comments are closed.', 'wporg' ); ?></p>
		<?php
	}
	?>
</section>
