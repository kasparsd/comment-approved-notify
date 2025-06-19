<?php

namespace Comment_Notifications;

use Comment_Notifications\Settings\Field;
use Comment_Notifications\Settings\Field_Checkbox;
use Comment_Notifications\Settings\Field_Text;
use Comment_Notifications\Settings\Field_Textarea;
use Comment_Notifications\Settings\Store_Option;
use WP_Comment;

class Plugin {
	private string $plugin_file;

	private const SETTINGS_SLUG = 'comment_notifications';

	private const SETTINGS_SECTION_APPROVE = 'comment_notifications__approve';

	public function __construct( string $plugin_file ) {
		$this->plugin_file = $plugin_file;
	}

	public function init() {
		add_action( 'admin_menu', array( $this, 'action_register_settings' ) );
		add_action( 'transition_comment_status', array( $this, 'approve_comment_callback' ), 10, 3 );
		add_action( 'comment_form', array( $this, 'approve_comment_optin' ), 10, 1 );
		add_action( 'wp_insert_comment', array( $this, 'approve_comment_posted' ), 10, 2 );
		add_filter( 'edit_comment_misc_actions', array( $this, 'comment_notify_status' ), 10, 2 );
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

	public function action_register_settings() {
		$hook = add_options_page(
			__( 'Comment Notifications', 'comment-approved-notify' ),
			__( 'Comment Notifications', 'comment-approved-notify' ),
			'manage_options',
			self::SETTINGS_SLUG,
			array( $this, 'settings' ),
		);

		add_settings_section(
			self::SETTINGS_SECTION_APPROVE,
			__( 'Comment Approval', 'comment-approved-notify' ),
			null,
			self::SETTINGS_SLUG
		);

		$this->add_settings_field(
			new Field_Checkbox(
				new Store_Option( 'comment_approved_enable' ),
				[
					'title' => __( 'Approval Notifications', 'comment-approved-notify' ),
					'label' => __( 'Allow users to opt-in to notifications when a comment is approved', 'comment-approved-notify' ),
				]
			),
			self::SETTINGS_SECTION_APPROVE
		);

		$this->add_settings_field(
			new Field_Checkbox(
				new Store_Option( 'comment_approved_default' ),
				[
					'title' => __( 'Default Setting', 'comment-approved-notify' ),
					'label' => __( 'Make the checkbox checked by default on the comment form', 'comment-approved-notify' ),
				]
			),
			self::SETTINGS_SECTION_APPROVE
		);

		$this->add_settings_field(
			new Field_Text(
				new Store_Option( 'comment_approved_subject' ),
				[
					'title' => __( 'Subject', 'comment-approved-notify' ),
					'input_classes' => 'large-text',
				]
			),
			self::SETTINGS_SECTION_APPROVE
		);

		$this->add_settings_field(
			new Field_Textarea(
				new Store_Option( 'comment_approved_message' ),
				[
					'title' => __( 'Message', 'comment-approved-notify' ),
					'input_classes' => 'large-text',
					'rows' => 10,
					'help' => sprintf(
						/* translators: %s is a list of available shortcodes */
						__( 'Available shortcodes: %s', 'comment-approved-notify' ),
						'<code>[permalink]</code>, <code>[name]</code>, <code>[post_title]</code>, <code>[post_permalink]</code>'
					),
				]
			),
			self::SETTINGS_SECTION_APPROVE
		);

		// add_action( 'load-' . $hook, [ $this, 'action_render_settings' ] );
	}

	protected function add_settings_field( Field $field, string $section ) {
		register_setting(
			self::SETTINGS_SLUG,
			$field->id(),
			[ 'sanitize_callback' => [ $field, 'sanitize' ] ]
		);

		add_settings_field(
			$field->id(),
			$field->title(),
			function () use ( $field ) {
				foreach ( $field->get_errors() as $error ) {
					$error_type = 'notice';
					$error_data = $error->get_error_data();

					if ( isset( $error_data['type'] ) ) {
						$error_type = $error_data['type'];
					}

					printf(
						'<div class="notice notice-%s inline"><p>%s</p></div>',
						esc_attr( sanitize_key( $error_type ) ),
						esc_html( $error->get_error_message() )
					);
				}

				echo wp_kses_post( (string) $field->setting( 'before' ) );
				echo $field->render();
				echo wp_kses_post( (string) $field->setting( 'after' ) );
			},
			self::SETTINGS_SLUG,
			$section,
			[
				'label_for' => $field->id(),
			]
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
			<h1><?php esc_html_e( 'Comment Notifications', 'comment-approved-notify' ); ?></h1>
			<form method="post" action="options.php">
				<?php
					settings_fields( self::SETTINGS_SLUG );
					do_settings_sections( self::SETTINGS_SLUG );
					submit_button();
				?>
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
		if ( class_exists( \Jetpack::class ) && \Jetpack::is_module_active( 'comments' ) ) {
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
