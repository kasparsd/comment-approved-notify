<?php

namespace Comment_Notifications;

use WP_Comment;

class Comment {
	private WP_Comment $comment;

	private const META_KEY_NOTIFY_APPROVED = 'notify_me';

	private const META_KEY_NOTIFY_APPROVED_SENT = 'comment_approve_notify_sent';

	public function __construct( WP_Comment $comment ) {
		$this->comment = $comment;
	}

	public static function from_comment_id( int $comment_id ): self {
		return new self( get_comment( $comment_id ) );
	}

	public function is_notify_approve_enabled(): bool {
		return (bool) get_comment_meta( $this->comment->comment_ID, self::META_KEY_NOTIFY_APPROVED, true );
	}

	public function is_approve_notified(): bool {
		return (bool) get_comment_meta( $this->comment->comment_ID, self::META_KEY_NOTIFY_APPROVED_SENT, true );
	}

	public function set_approve_notified(): void {
		update_comment_meta( $this->comment->comment_ID, self::META_KEY_NOTIFY_APPROVED_SENT, 1 );
	}

	public function should_notify_approve(): bool {
		return $this->is_notify_approve_enabled() && ! $this->is_approve_notified();
	}
}