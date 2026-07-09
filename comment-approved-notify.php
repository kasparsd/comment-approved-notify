<?php
/**
 * Plugin Name: Comment Notifications
 * Plugin URI:
 * Description: Notify comment authors when their comments are approved or receive replies.
 * Version: 2.0.0
 * Requires at least: 3.0
 * Author: Kaspars Dambis
 * Author URI: https://kaspars.net
 * Text Domain: comment-approved-notify
 */

use Comment_Notifications\Plugin;

require_once __DIR__ . '/classes/settings/class-field.php';
require_once __DIR__ . '/classes/settings/class-field-checkbox.php';
require_once __DIR__ . '/classes/settings/class-field-text.php';
require_once __DIR__ . '/classes/settings/class-field-textarea.php';
require_once __DIR__ . '/classes/settings/class-store.php';
require_once __DIR__ . '/classes/settings/class-store-option.php';
require_once __DIR__ . '/classes/class-comment.php';
require_once __DIR__ . '/classes/class-plugin.php';

$comment_notifications_plugin = new Plugin( __FILE__ );

add_action( 'plugins_loaded', [ $comment_notifications_plugin, 'init' ] );
