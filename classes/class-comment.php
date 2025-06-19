<?php

namespace Comment_Notifications;

use WP_Comment;

class Comment {
	private WP_Comment $comment;

	private const META_KEY_NOTIFY_APPROVE = 'notify_me';

	private const META_KEY_NOTIFY_APPROVE_SENT = 'comment_approve_notify_sent';

	public function __construct( WP_Comment $comment ) {
		$this->comment = $comment;
	}

	public static function from_comment_id( int $comment_id ): self {
		return new self( get_comment( $comment_id ) );
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

	public function notify_approve( string $message, string $subject ): bool {
		if ( ! is_email( $this->comment->comment_author_email ) && $this->should_notify_approve() ) {
			wp_mail( $this->comment->comment_author_email, $subject, $message );

			$this->set_approve_notified();

			return true;
		}

		return false;
	}
}