<?php

use Comment_Notifications\Comment;

class CommentApprovedNotify {

	protected function __construct() {
		add_action( 'admin_menu', array( $this, 'add_default_settings' ) );
		add_action( 'transition_comment_status', array( $this, 'approve_comment_callback' ), 10, 3 );
		add_action( 'comment_form', array( $this, 'approve_comment_optin' ), 10, 1 );
		add_action( 'wp_insert_comment', array( $this, 'approve_comment_posted' ), 10, 2 );
		add_filter( 'edit_comment_misc_actions', array( $this, 'comment_notify_status' ), 10, 2 );
	}

	public static function instance() {

		static $instance;

		if ( ! isset( $instance ) ) {
			$instance = new self();
		}

		return $instance;

	}

	private function get_approved_email_message() {
		$message = get_option( 'comment_approved_message' );

		if ( ! empty( $message ) ) {
			return $message;
		}

		return __( "Hi [name],\n\nThanks for your comment! It has been approved. To view the post, look at the link below.\n\n[permalink]", 'comment-approved-notify' );
	}

	private function get_approved_email_subject() {
		$subject = get_option( 'comment_approved_subject' );

		if ( ! empty( $subject ) ) {
			return $subject;
		}

		return sprintf(
			'[%s] %s',
			get_bloginfo( 'name' ),
			__( 'Your comment has been approved', 'comment-approved-notify' )
		);
	}

	private function is_approve_email_enabled(): bool {
		return (bool) get_option( 'comment_approved_enable', 1 );
	}

	private function is_approve_email_by_default(): bool {
		return (bool) get_option( 'comment_approved_default', 0 );
	}

	public function add_default_settings() {

		// @todo Move to settings API
		add_options_page(
			__( 'Comment Notifications', 'comment-approved-notify' ),
			__( 'Comment Notifications', 'comment-approved-notify' ),
			'manage_options',
			'comment_approved-settings',
			array( $this, 'settings' ),
		);

	}

	public function settings() {

		$updated = false;

		if ( isset( $_POST['comment_approved_settings'] ) && ! wp_verify_nonce( $_POST['_wpnonce'], 'comment_approved_settings' ) ) {
			wp_die( 'Could not verify nonce' );
		}

		if ( isset( $_POST['comment_approved_settings'] ) ) {

			$message = esc_html( $_POST['comment_approved_message'] );
			$subject = esc_html( $_POST['comment_approved_subject'] );

			update_option( 'comment_approved_message', $message );
			update_option( 'comment_approved_subject', $subject );

			if ( isset( $_POST['comment_approved_enable'] ) ) {
				update_option( 'comment_approved_enable', 1 );
			} else {
				update_option( 'comment_approved_enable', 0 );
			}

			if ( isset( $_POST['comment_approved_default'] ) ) {
				update_option( 'comment_approved_default', 1 );
			} else {
				update_option( 'comment_approved_default', 0 );
			}

			$updated = true;

		}

		$message = $this->get_approved_email_message();
		$subject = $this->get_approved_email_subject();
		$enable = $this->is_approve_email_enabled();
		$default = $this->is_approve_email_by_default();

		?>
		<div class="wrap">

			<?php if ( $updated ) : ?>
			<div id="message" class="updated fade">
				<p><?php esc_html_e( 'Options saved', 'comment-approved-notify' ) ?></p>
			</div>
			<?php endif; ?>

			<h1><?php esc_html_e( 'Comment Notifications', 'comment-approved-notify' ); ?></h1>
			<p><?php esc_html_e( 'Configure notifications sent to comment authors.', 'comment-approved-notify' ); ?></p>

			<form method="post">
				<?php wp_nonce_field( 'comment_approved_settings' ); ?>

				<table class="form-table" id="wp-comment-approved-settings">
					<tr class="default-row">
						<th><label><?php esc_html_e( 'Approval Notifications', 'comment-approved-notify' ); ?></label></th>
						<td>
							<label>
								<input type="checkbox" name="comment_approved_enable" value="1" <?php checked( $enable ); ?> />
								<?php esc_html_e( 'Allow users to opt-in to notifications when a comment is approved', 'comment-approved-notify' ); ?>
							</label>
						</td>
					</tr>
					<tr class="default-row">
						<th><label><?php esc_html_e( 'Default Setting', 'comment-approved-notify' ); ?></label></th>
						<td>
							<label>
								<input type="checkbox" name="comment_approved_default" value="1" <?php checked( $default ); ?> />
								<?php esc_html_e( 'Make the checkbox checked by default on the comment form', 'comment-approved-notify' ); ?>
							</label>
						</td>
					</tr>
					<tr class="default-row">
						<th><label><?php esc_html_e( 'Subject', 'comment-approved-notify' ); ?></label></th>
						<td>
							<input type="text" name="comment_approved_subject" class="large-text" value="<?php echo esc_attr( $subject ); ?>" />
						</td>
					</tr>
					<tr class="default-row">
						<th><label><?php esc_html_e( 'Message', 'comment-approved-notify' ); ?></label></th>
						<td>
							<textarea cols="50" rows="10" class="large-text" name="comment_approved_message"><?php echo esc_textarea( $message ); ?></textarea>
							<p class="help">
								<?php esc_html_e( 'Available shortcodes:', 'comment-approved-notify' ); ?>
								<code>[permalink]</code>, 
								<code>[name]</code>,
								<code>[post_title]</code>
								<code>[post_permalink]</code>
							</p>
						</td>
					</tr>
					<tr class="default-row">
						<th></th>
						<td>
							<input type="submit" class="button submit" name="comment_approved_settings" value="<?php esc_attr_e( 'Save', 'comment-approved-notify' ); ?>" />
						</td>
					</tr>
				</table>
			</form>

		</div>
		<?php

	}

	public function approve_comment_callback( string $new_status, string $old_status, WP_Comment $comment ) {
		// Notify only if the comment is approved
		if ( $old_status === $new_status || 'approved' !== $new_status ) {
			return;
		}

		$comment_notify = new Comment( $comment );
		$notify_me = $comment_notify->should_notify_approve();

		// Jetpack comments doesn't allow authors to opt-in so we do it automatically.
		if ( class_exists( Jetpack::class ) && Jetpack::is_module_active( 'comments' ) ) {
			$notify_me = true;
		}

		// Ensure that we can actually notify the comment author.
		if ( empty( $notify_me ) ) {
			return;
		}

		$template_values = array(
			'name' => $comment->comment_author,
			'permalink' => get_comment_link( $comment ),
			'post_title' => get_the_title( $comment->comment_post_ID ),
			'post_permalink' => get_permalink( $comment->comment_post_ID ),
		);

		$map_fields = [];
		foreach ( $template_values as $key => $key_value ) {
			$map_fields[ sprintf( '[%s]', $key ) ] = $key_value;
			$map_fields[ sprintf( '%%%s%%', $key ) ] = $key_value;
		}
		
		// Replace the shortcodes.
		$notification = $this->replace_shortcodes( $this->get_approved_email_message(), $map_fields );
		$subject = $this->replace_shortcodes( $this->get_approved_email_subject(), $map_fields );

		if ( $notification && $subject ) {
			$comment_notify->notify_approve( $notification, $subject );
		}
	}

	private function replace_shortcodes( string $text, array $shortcodes ): string {
		return str_replace( array_keys( $shortcodes ), array_values( $shortcodes ), $text );
	}

	public function approve_comment_optin( $post_id ) {
		if ( ! $this->is_approve_email_enabled() ) {
			return;
		}

		printf(
			'<p class="comment-form-notify-me">
				<label>
					<input type="checkbox" %s name="comment-approved_notify-me" value="1" />
					%s
				</label>
			</p>',
			checked( $this->is_approve_email_by_default(), true, false ),
			esc_html__( 'Notify me by email when the comment gets approved.', 'comment-approved-notify' )
		);
	}

	public function approve_comment_posted( $comment_id, $comment_object ) {

		if ( isset( $_POST['comment-approved_notify-me'] ) ) {
			add_comment_meta( $comment_id, 'notify_me', time() );
		}

	}

	public function comment_notify_status( $html, $comment ) {
		$comment_notify = new Comment( $comment );

		$notify_me = $comment_notify->is_notify_approve_enabled();
		$notify_sent = $comment_notify->get_approve_notified_timestamp();

		if ( ! empty( $notify_me ) && ! empty( $notify_sent ) ) {
			$status = sprintf(
				__( 'Author was notified of the comment approval on %s at %s.', 'comment-approved-notify' ),
				date_i18n( get_option( 'date_format' ), $notify_sent, false ),
				date_i18n( get_option( 'time_format' ), $notify_sent, false )
			);
		} elseif ( ! empty( $notify_me ) ) {
			$status = __( 'Author will be notified of the comment approval.', 'comment-approved-notify' );
		} else {
			$status = __( 'Author did not choose to be notified of the comment approval.', 'comment-approved-notify' );
		}

		$html .= sprintf(
			'<div class="misc-pub-section">
				<p>%s</p>
			</div>',
			esc_html( $status )
		);

		return $html;

	}

}
