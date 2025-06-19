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

	private const SETTINGS_SECTION_REPLY = 'comment_notifications__reply';

	private const SETTINGS_SECTION_ALL_COMMENTS = 'comment_notifications__all_comments';

	private Store_Option $option_approve_enable;
	private Store_Option $option_approve_default;
	private Store_Option $option_approve_subject;
	private Store_Option $option_approve_message;

	private ?string $notify_approve_subject_default;
	private ?string $notify_approve_message_default;

	private Store_Option $option_reply_enable;
	private Store_Option $option_reply_default;
	private Store_Option $option_reply_subject;
	private Store_Option $option_reply_message;

	private Store_Option $option_all_comments_enable;
	private Store_Option $option_all_comments_default;
	private Store_Option $option_all_comments_subject;
	private Store_Option $option_all_comments_message;

	public function __construct( string $plugin_file ) {
		$this->plugin_file = $plugin_file;

		$this->option_approve_enable = new Store_Option( 'comment_approved_enable' );
		$this->option_approve_default = new Store_Option( 'comment_approved_default' );
		$this->option_approve_subject = new Store_Option( 'comment_approved_subject' );
		$this->option_approve_message = new Store_Option( 'comment_approved_message' );

		$this->option_reply_enable = new Store_Option( 'comment_reply_enable' );
		$this->option_reply_default = new Store_Option( 'comment_reply_default' );
		$this->option_reply_subject = new Store_Option( 'comment_reply_subject' );
		$this->option_reply_message = new Store_Option( 'comment_reply_message' );

		$this->option_all_comments_enable = new Store_Option( 'comment_all_comments_enable' );
		$this->option_all_comments_default = new Store_Option( 'comment_all_comments_default' );
		$this->option_all_comments_subject = new Store_Option( 'comment_all_comments_subject' );
		$this->option_all_comments_message = new Store_Option( 'comment_all_comments_message' );
	}

	public function init() {
		add_action( 'init', [ $this, 'action_populate_defaults' ], 0 ); // Translations can be loaded only during init or later.
		add_action( 'admin_menu', array( $this, 'action_register_settings' ) );
		add_action( 'transition_comment_status', array( $this, 'approve_comment_callback' ), 10, 3 );
		add_action( 'wp_insert_comment', array( $this, 'approve_comment_posted' ), 10, 2 );
		add_filter( 'comment_form_fields', [ $this, 'filter_comment_form_fields' ], 20 );
		add_action( 'add_meta_boxes', [ $this, 'action_add_meta_boxes' ] );
	}

	public function filter_comment_form_fields( array $fields ): array {
		if ( ! empty( $fields ) ) {
			$fields = array_merge( $fields, $this->get_comment_fields() );
		}
		
		return $fields;
	}

	public function action_populate_defaults() {
		$this->notify_approve_message_default = __( "Hi [name],\n\nThanks for your comment! It has been approved. To view the post, look at the link below.\n\n[permalink]", 'comment-approved-notify' );
		$this->notify_approve_subject_default = sprintf(
			'[%s] %s',
			get_bloginfo( 'name' ),
			__( 'Your comment has been approved', 'comment-approved-notify' )
		);
	}

	private function is_comment_replies_enabled(): bool {
		return (bool) get_option( 'thread_comments' );
	}

	private function is_comment_moderation_enabled(): bool {
		return (bool) get_option( 'comment_previously_approved' ) || (bool) get_option( 'comment_moderation' );
	}

	private function get_approve_email_message() {
		$message = $this->option_approve_message->get();

		if ( ! empty( $message ) ) {
			return $message;
		}

		return $this->notify_approve_subject_default;
	}

	private function get_approve_email_subject() {
		$subject = $this->option_approve_subject->get();

		if ( ! empty( $subject ) ) {
			return $subject;
		}

		return $this->notify_approve_message_default;
	}

	private function is_approve_email_enabled(): bool {
		return (bool) $this->option_approve_enable->get() ?? true;
	}

	private function is_approve_email_by_default(): bool {
		return (bool) $this->option_approve_default->get();
	}

	private function is_replies_email_enabled(): bool {
		return (bool) $this->option_approve_enable->get();
	}

	private function is_all_comments_email_enabled(): bool {
		return (bool) $this->option_all_comments_enable->get();
	}

	private function get_settings_url(): string {
		return admin_url( 'options-general.php?page=' . self::SETTINGS_SLUG );
	}

	public function action_register_settings() {
		$hook = add_options_page(
			__( 'Comment Notifications', 'comment-approved-notify' ),
			__( 'Comment Notifications', 'comment-approved-notify' ),
			'manage_options',
			self::SETTINGS_SLUG,
			array( $this, 'settings' ),
		);

		$shortcodes_available = [
			'permalink',
			'name',
			'post_title',
			'post_permalink',
			'comment_content',
			'comment_permalink',
			'comment_author',
		];

		$shortcodes = implode( 
			', ', 
			array_map(
				fn ( $shortcode ) => sprintf( '<code>[%s]</code>', $shortcode ),
				$shortcodes_available
			)
		);

		/**
		 * Comment Approvals.
		 */

		add_settings_section(
			self::SETTINGS_SECTION_APPROVE,
			__( 'Comment Approval', 'comment-approved-notify' ),
			null,
			self::SETTINGS_SLUG
		);

		$this->add_settings_field(
			new Field_Checkbox(
				$this->option_approve_enable,
				[
					'title' => __( 'Approval Notifications', 'comment-approved-notify' ),
					'label' => __( 'Enable notifications for comment approvals', 'comment-approved-notify' ),
					'help' => __( 'Enable email notification to the comment author when their comment is approved.', 'comment-approved-notify' ),
				]
			),
			self::SETTINGS_SECTION_APPROVE
		);

		$this->add_settings_field(
			new Field_Checkbox(
				$this->option_approve_default,
				[
					'title' => __( 'Default Setting', 'comment-approved-notify' ),
					'label' => __( 'Enable by default', 'comment-approved-notify' ),
					'help' => __( 'Set the comment approval notificaions as enabled by default in the comment form.', 'comment-approved-notify' ),
				]
			),
			self::SETTINGS_SECTION_APPROVE
		);

		$this->add_settings_field(
			new Field_Text(
				$this->option_approve_subject,
				[
					'title' => __( 'Email Subject', 'comment-approved-notify' ),
					'input_classes' => 'large-text',
					'placeholder' => $this->notify_approve_subject_default,
				]
			),
			self::SETTINGS_SECTION_APPROVE
		);

		$this->add_settings_field(
			new Field_Textarea(
				$this->option_approve_message,
				[
					'title' => __( 'Email Message', 'comment-approved-notify' ),
					'input_classes' => 'large-text',
					'placeholder' => $this->notify_approve_message_default,
					'rows' => 8,
					'help' => sprintf(
						/* translators: %s is a list of available shortcodes */
						__( 'Available shortcodes: %s', 'comment-approved-notify' ),
						$shortcodes
					),
				]
			),
			self::SETTINGS_SECTION_APPROVE
		);

		/**
		 * Comment Replies.
		 */

		add_settings_section(
			self::SETTINGS_SECTION_REPLY,
			__( 'Comment Replies', 'comment-approved-notify' ),
			null,
			self::SETTINGS_SLUG
		);

		$this->add_settings_field(
			new Field_Checkbox(
				$this->option_reply_enable,
				[
					'title' => __( 'Reply Notifications', 'comment-approved-notify' ),
					'label' => __( 'Enable notifications of replies to user comments', 'comment-approved-notify' ),
					'help' => __( 'Enable email notification to the comment author when someone replies to their comment.', 'comment-approved-notify' ),
				]
			),
			self::SETTINGS_SECTION_REPLY
		);

		$this->add_settings_field(
			new Field_Checkbox(
				$this->option_reply_default,
				[
					'title' => __( 'Default Setting', 'comment-approved-notify' ),
					'label' => __( 'Enable by default', 'comment-approved-notify' ),
					'help' => __( 'Set the comment reply notificaions as enabled by default in the comment form.', 'comment-approved-notify' ),
				]
			),
			self::SETTINGS_SECTION_REPLY
		);

		$this->add_settings_field(
			new Field_Text(
				$this->option_reply_subject,
				[
					'title' => __( 'Email Subject', 'comment-approved-notify' ),
					'input_classes' => 'large-text',
				]
			),
			self::SETTINGS_SECTION_REPLY
		);

		$this->add_settings_field(
			new Field_Textarea(
				$this->option_reply_message,
				[
					'title' => __( 'Email Message', 'comment-approved-notify' ),
					'input_classes' => 'large-text',
					'rows' => 8,
					'help' => sprintf(
						/* translators: %s is a list of available shortcodes */
						__( 'Available shortcodes: %s', 'comment-approved-notify' ),
						$shortcodes
					),
				]
			),
			self::SETTINGS_SECTION_REPLY
		);

		/**
		 * All Post Comments.
		 */

		add_settings_section(
			self::SETTINGS_SECTION_ALL_COMMENTS,
			__( 'All Post Comments', 'comment-approved-notify' ),
			null,
			self::SETTINGS_SLUG
		);

		$this->add_settings_field(
			new Field_Checkbox(
				$this->option_all_comments_enable,
				[
					'title' => __( 'Comment Notifications', 'comment-approved-notify' ),
					'label' => __( 'Enable notifications of all new comments on the same post', 'comment-approved-notify' ),
					'help' => __( 'Enable email notification to the comment author of all future comments on the same post.', 'comment-approved-notify' ),
				]
			),
			self::SETTINGS_SECTION_ALL_COMMENTS
		);

		$this->add_settings_field(
			new Field_Checkbox(
				$this->option_all_comments_default,
				[
					'title' => __( 'Default Setting', 'comment-approved-notify' ),
					'label' => __( 'Enable by default', 'comment-approved-notify' ),
					'help' => __( 'Set the all comments notificaions as enabled by default in the comment form.', 'comment-approved-notify' ),
				]
			),
			self::SETTINGS_SECTION_ALL_COMMENTS
		);

		$this->add_settings_field(
			new Field_Text(
				$this->option_all_comments_subject,
				[
					'title' => __( 'Email Subject', 'comment-approved-notify' ),
					'input_classes' => 'large-text',
				]
			),
			self::SETTINGS_SECTION_ALL_COMMENTS
		);

		$this->add_settings_field(
			new Field_Textarea(
				$this->option_all_comments_message,
				[
					'title' => __( 'Email Message', 'comment-approved-notify' ),
					'input_classes' => 'large-text',
					'rows' => 8,
					'help' => sprintf(
						/* translators: %s is a list of available shortcodes */
						__( 'Available shortcodes: %s', 'comment-approved-notify' ),
						$shortcodes
					),
				]
			),
			self::SETTINGS_SECTION_ALL_COMMENTS
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

	public function action_add_meta_boxes() {
		add_meta_box(
			self::SETTINGS_SLUG,
			__( 'Comment Notifications', 'comment-approved-notify' ),
			array( $this, 'action_meta_box_comment_notify_status' ),
			'comment',
			'normal'
		);
	}

	public function action_meta_box_comment_notify_status( WP_Comment $comment ) {
		$comment_notify = new Comment( $comment );

		// TODO: show the last notification timestamp for each.
		$fields = [
			self::SETTINGS_SECTION_APPROVE => [
				'label' => __( 'comment is approved', 'comment-approved-notify' ),
				'checked' => $comment_notify->is_notify_approve_enabled(),
			],
			self::SETTINGS_SECTION_REPLY => [
				'label' => __( 'comment has replies', 'comment-approved-notify' ),
				'checked' => $comment_notify->is_notify_replies_enabled(),
			],
			self::SETTINGS_SECTION_ALL_COMMENTS => [
				'label' => __( 'all new comments on the same post', 'comment-approved-notify' ),
				'checked' => $comment_notify->is_notify_all_comments_enabled(),
			],
		];

		foreach ( $fields as $section => $field ) {
			$fields[ $section ] = sprintf(
				'<li class="%1$s">
					<label>
						<input type="checkbox" name="%1$s" %2$s />
						%3$s
					</label>
				</li>',
				esc_attr( $section ),
				checked( $field['checked'], true, false ),
				esc_html( $field['label'] )
			);
		}

		printf( 
			'<p>%s</p><ul>%s</ul><p><a class="button" href="%s">%s</a>',
			esc_html__( 'Comment author enabled email notifications when:', 'comment-approved-notify' ),
			implode( '', $fields ),
			esc_url( $this->get_settings_url() ),
			esc_html__( 'Notification Settings', 'comment-approved-notify' )
		);
	}

	public function approve_comment_callback( string $new_status, string $old_status, WP_Comment $comment ) {
		// Notify only if the comment is approved
		if ( $old_status === $new_status || 'approved' !== $new_status ) {
			return;
		}

		$comment_notify = new Comment( $comment );
		$notify_me = $comment_notify->should_notify_approve();

		// Ensure that we can actually notify the comment author.
		if ( empty( $notify_me ) ) {
			return;
		}

		$template_values = array(
			'name' => $comment->comment_author,
			'permalink' => get_comment_link( $comment ),
			'post_title' => get_the_title( $comment->comment_post_ID ),
			'post_permalink' => get_permalink( $comment->comment_post_ID ),
			'comment_content' => $comment->comment_content,
			'comment_permalink' => get_comment_link( $comment ),
			'comment_author' => $comment->comment_author,
		);

		$map_fields = [];
		foreach ( $template_values as $key => $key_value ) {
			$map_fields[ sprintf( '[%s]', $key ) ] = $key_value;
			$map_fields[ sprintf( '%%%s%%', $key ) ] = $key_value;
		}
		
		// Replace the shortcodes.
		$notification = $this->replace_shortcodes( $this->get_approve_email_message(), $map_fields );
		$subject = $this->replace_shortcodes( $this->get_approve_email_subject(), $map_fields );

		if ( $notification && $subject ) {
			$notified = $comment_notify->notify( $notification, $subject );

			if ( $notified ) {
				$comment_notify->set_approve_notified();
			}
		}
	}

	private function replace_shortcodes( string $text, array $shortcodes ): string {
		return str_replace( array_keys( $shortcodes ), array_values( $shortcodes ), $text );
	}

	private function get_comment_fields(): array {
		$fields = [];

		if ( $this->is_comment_moderation_enabled() && $this->is_approve_email_enabled() ) {
			$fields[ self::SETTINGS_SECTION_APPROVE ] = sprintf(
				'<p class="%1$s">
					<label>
						<input type="checkbox" name="%1$s" %2$s value="1" />
						%3$s
					</label>
				</p>',
				esc_attr( self::SETTINGS_SECTION_APPROVE ),
				checked( $this->is_approve_email_by_default(), true, false ),
				esc_html__( 'Email me when my comment is approved.', 'comment-approved-notify' )
			);
		}

		if ( $this->is_comment_replies_enabled() && $this->is_replies_email_enabled() ) {
			$fields[ self::SETTINGS_SECTION_REPLY ] = sprintf(
				'<p class="%1$s">
					<label>
						<input type="checkbox" name="%1$s" %2$s value="1" />
						%3$s
					</label>
				</p>',
				esc_attr( self::SETTINGS_SECTION_REPLY ),
				checked( false, true, false ),
				esc_html__( 'Email me when someone replies to my comment.', 'comment-approved-notify' )
			);
		}

		if ( $this->is_all_comments_email_enabled() ) {
			$fields[ self::SETTINGS_SECTION_ALL_COMMENTS ] = sprintf(
				'<p class="%1$s">
					<label>
						<input type="checkbox" name="%1$s" %2$s value="1" />
						%3$s
					</label>
				</p>',
				esc_attr( self::SETTINGS_SECTION_ALL_COMMENTS ),
				checked( false, true, false ),
				esc_html__( 'Email me all new comments.', 'comment-approved-notify' )
			);
		}

		return $fields;
	}

	public function approve_comment_posted( $comment_id, $comment_object ) {
		$comment_notify = new Comment( $comment_object );
		
		if ( $this->is_approve_email_enabled() && ! empty( $_POST[ SELF::SETTINGS_SECTION_REPLY ] ) ) {
			$comment_notify->enable_notify_approve();
		}

		if ( $this->is_replies_email_enabled() && ! empty( $_POST[ SELF::SETTINGS_SECTION_REPLY ] ) ) {
			$comment_notify->enable_notify_replies();
		}

		if ( $this->is_all_comments_email_enabled() && ! empty( $_POST[ SELF::SETTINGS_SECTION_ALL_COMMENTS ] ) ) {
			$comment_notify->enable_notify_all_comments();
		}
	}
}
