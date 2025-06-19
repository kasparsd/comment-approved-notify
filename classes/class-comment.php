<?php

namespace Comment_Notifications;

use WP_Comment;

class Comment {
	private WP_Comment $comment;

	private const META_KEY_NOTIFY_APPROVE = 'notify_me';

	private const META_KEY_NOTIFY_APPROVE_SENT = 'comment_approve_notify_sent';

	private const META_KEY_NOTIFY_REPLIES = 'comment_notifications__notify_replies';

	private const META_KEY_NOTIFY_ALL = 'comment_notifications__notify_all';

	public function __construct( WP_Comment $comment ) {
		$this->comment = $comment;
	}

	public static function from_comment_id( int $comment_id ): self {
		return new self( get_comment( $comment_id ) );
	}

	public function is_approved(): bool {
		return 'approve' === $this->comment->comment_approved;
	}

	public function get_email(): ?string {
		if ( ! empty( $this->comment->comment_author_email ) && is_email( $this->comment->comment_author_email ) ) {
			return $this->comment->comment_author_email;
		}

		return null;
	}

	public function enable_notify_approve() {
		return (bool) update_comment_meta( $this->comment->comment_ID, self::META_KEY_NOTIFY_APPROVE, time() );
	}

	public function enable_notify_replies(): bool {
		return (bool) update_comment_meta( $this->comment->comment_ID, self::META_KEY_NOTIFY_REPLIES, time() );
	}

	public function is_notify_replies_enabled(): bool {
		return (bool) get_comment_meta( $this->comment->comment_ID, self::META_KEY_NOTIFY_REPLIES, true );
	}

	public function enable_notify_all_comments(): bool {
		return (bool) update_comment_meta( $this->comment->comment_ID, self::META_KEY_NOTIFY_ALL, time() );
	}

	public function is_notify_all_comments_enabled(): bool {
		return (bool) get_comment_meta( $this->comment->comment_ID, self::META_KEY_NOTIFY_ALL, true );
	}

	public function is_notify_approve_enabled(): bool {
		return (bool) get_comment_meta( $this->comment->comment_ID, self::META_KEY_NOTIFY_APPROVE, true );
	}

	public function is_approve_notified(): bool {
		return (bool) $this->get_approve_notified_timestamp();
	}

	public function get_approve_notified_timestamp(): int {
		return (int) get_comment_meta( $this->comment->comment_ID, self::META_KEY_NOTIFY_APPROVE_SENT, true );
	}

	public function set_approve_notified(): void {
		update_comment_meta( $this->comment->comment_ID, self::META_KEY_NOTIFY_APPROVE_SENT, 1 );
	}

	public function should_notify_approve(): bool {
		return $this->is_notify_approve_enabled() && ! $this->is_approve_notified();
	}

	public function notify( string $message, string $subject ): bool {
		$email_to = $this->get_email();

		if ( $email_to ) {
			return wp_mail( $email_to, $subject, $message );
		}

		return false;
	}
}