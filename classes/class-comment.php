<?php

namespace Comment_Notifications;

use WP_Comment;

class Comment {
	private WP_Comment $comment;

	private const META_KEY_NOTIFY_APPROVE = 'notify_me';

	private const META_KEY_NOTIFY_APPROVE_SENT = 'comment_approve_notify_sent';

	private const META_KEY_NOTIFY_REPLIES = 'comment_notifications__notify_replies';

	private const META_KEY_NOTIFY_ALL = 'comment_notifications__notify_all';

	private const META_KEY_TOKEN_UNSUBSCRIBE = 'comment_notifications__token_unsubscribe';

	public function __construct( WP_Comment $comment ) {
		$this->comment = $comment;
	}

	public static function from_comment_id( int $comment_id ): self {
		return new self( get_comment( $comment_id ) );
	}

	public function get_id(): int {
		return (int) $this->comment->comment_ID;
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

	private function get_meta( string $meta_key ): ?string {
		$value = get_comment_meta( $this->comment->comment_ID, $meta_key, true );

		if ( is_string( $value ) ) {
			return $value;
		}

		return null;
	}

	private function set_meta( string $meta_key, string $value ): bool {
		return (bool) update_comment_meta( $this->comment->comment_ID, $meta_key, $value );
	}

	public function enable_notify_approve() {
		return $this->set_meta( self::META_KEY_NOTIFY_APPROVE, time() );
	}

	public function enable_notify_replies(): bool {
		return $this->set_meta( self::META_KEY_NOTIFY_REPLIES, time() );
	}

	public function is_notify_replies_enabled(): bool {
		return (bool) $this->get_meta( self::META_KEY_NOTIFY_REPLIES );
	}

	public function enable_notify_all_comments(): bool {
		return $this->set_meta( self::META_KEY_NOTIFY_ALL, time() );
	}

	public function is_notify_all_comments_enabled(): bool {
		return (bool) $this->get_meta( self::META_KEY_NOTIFY_ALL );
	}

	public function is_notify_approve_enabled(): bool {
		return (bool) $this->get_meta( self::META_KEY_NOTIFY_APPROVE );
	}

	public function has_notified_approve(): bool {
		return (bool) $this->get_approve_notified_timestamp();
	}

	public function get_approve_notified_timestamp(): int {
		return (int) $this->get_meta( self::META_KEY_NOTIFY_APPROVE_SENT );
	}

	public function set_approve_notified(): bool {
		return $this->set_meta( self::META_KEY_NOTIFY_APPROVE_SENT, time() );
	}

	public function should_notify_approve(): bool {
		return $this->is_notify_approve_enabled() && ! $this->has_notified_approve();
	}

	public function get_unsubscribe_token(): string {
		$token = $this->get_meta( self::META_KEY_TOKEN_UNSUBSCRIBE );

		if ( empty( $token ) ) {
			$this->set_meta( self::META_KEY_TOKEN_UNSUBSCRIBE, sha1( wp_generate_password( 32, false ) ) );
		}

		return $token;
	}

	public function is_unsubscribe_token_valid( string $token ): bool {
		$stored_token = $this->get_unsubscribe_token();
		
		if ( ! empty( $stored_token ) && hash_equals( $stored_token, trim( $token ) ) ) {
			return true;
		}

		return false;
	}

	public function notify_disable(): void {
		delete_comment_meta( $this->comment->comment_ID, self::META_KEY_NOTIFY_REPLIES );
		delete_comment_meta( $this->comment->comment_ID, self::META_KEY_NOTIFY_ALL );
	}

	public function notify( string $message, string $subject ): bool {
		$email_to = $this->get_email();

		if ( $email_to ) {
			return wp_mail( $email_to, $subject, $message );
		}

		return false;
	}
}