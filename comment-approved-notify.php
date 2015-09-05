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

include dirname( __FILE__ ) . '/classes/main.php';

CommentApprovedNotify::instance();
