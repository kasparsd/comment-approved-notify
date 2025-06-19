<?php
/*
Plugin Name: Comment Approved Notify
Plugin URI:
Description: Notify comment authors when their comments are approved.
Version: 1.4-dev
Requires at least: 3.0
Author: Kaspars Dambis
Author URI: http://kaspars.net
Text Domain: comment-approved-notify
Domain Path: /languages/
*/

require_once __DIR__ . '/classes/main.php';

add_action( 'plugins_loaded', [ CommentApprovedNotify::class, 'instance' ] );
