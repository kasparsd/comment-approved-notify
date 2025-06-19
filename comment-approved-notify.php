<?php
/*
Plugin Name: Comment Notifications
Plugin URI:
Description: Notify comment authors when their comments are approved or receive replies.
Version: 1.4-dev
Requires at least: 3.0
Author: Kaspars Dambis
Author URI: https://kaspars.net
Text Domain: comment-approved-notify
Domain Path: /languages/
*/

use Comment_Notifications\Plugin;

require_once __DIR__ . '/classes/class-comment.php';
require_once __DIR__ . '/classes/class-plugin.php';

$comment_notifications_plugin = new Plugin( __FILE__ );

add_action( 'plugins_loaded', [ $comment_notifications_plugin, 'init' ] );
