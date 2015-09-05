<?php

/**
 * Title: Comment approved
 * Description: Main functions
 * Company: Media-Enzo
 * @author Niels van Renselaar
 * @version 1.3.2.1
 */
 
class Comment_Approved {

	/**
	 * Constructs and initialize
	 */
	 
	private $default_notification;
	 
	public function __construct() {
	
		global $wp_comment_approved;
		
		add_action('admin_menu', array( &$this, 'add_default_settings' ) );
		add_action('admin_enqueue_scripts', array( &$this, 'load_custom_admin_style' ) );
		add_action('transition_comment_status', array( &$this, 'approve_comment_callback'), 10, 3);
		add_filter('comment_form_default_fields', array( &$this, 'approve_comment_fields'), 10, 1 );
		add_action('wp_insert_comment', array( &$this, 'approve_comment_posted'), 10, 2 );
		
		$this->default_notification = "Hi %name%,\n\nThanks for your comment! It has been approved. To view the post, look at the link below.\n\n%permalink%";
		$this->default_subject = __("Comment approved", "ca");
			
			
	}
	
	static function install() {
	
		/* for future usage */
		
    }

    
    public function add_default_settings() {
	    
	    add_submenu_page( 'options-general.php',  __("Comment approved", 'ca'), __("Comment approved", 'ca'), "manage_options", 'comment_approved-settings', array( &$this, 'settings'), 'dashicons-admin-tools' );
	    
    }
	
    public function load_custom_admin_style() {
	
        wp_register_style( 'comment_approved_admin_css', plugins_url('comment-approved') . '/assets/css/admin.css', false, '1.0.0' );
        wp_enqueue_style( 'comment_approved_admin_css' );
        
	}
	
	public function settings() {
	
		$updated = false;
		
		if( isset($_POST['comment_approved_settings']) && !wp_verify_nonce( $_POST['_wpnonce'], 'comment_approved_settings' ) ) {
		
			die("Could not verify nonce");
			
		} else {
	
			if(isset($_POST['comment_approved_settings'])) {
				
				$message = esc_html( $_POST['comment_approved_message']);
				$subject = esc_html( $_POST['comment_approved_subject']);
				$from_name = esc_html( $_POST['comment_approved_from_name']);
				$from_email = esc_html( $_POST['comment_approved_from_email']);
				
				update_option("comment_approved_message", $message);
				update_option("comment_approved_subject", $subject);
				update_option("comment_approved_from_name", $from_name);
				update_option("comment_approved_from_email", $from_email);
				
				if(isset($_POST['comment_approved_enable'])) {
					update_option("comment_approved_enable", 1);
				} else {
					update_option("comment_approved_enable", 0);
				}
				
				if(isset($_POST['comment_approved_default'])) {
					update_option("comment_approved_default", 1);
				} else {
					update_option("comment_approved_default", 0);
				}
				
				$updated = true;
				
			}
			
			$message = get_option("comment_approved_message");
			$subject = get_option("comment_approved_subject");
		    $enable = get_option("comment_approved_enable");
		    $default = get_option("comment_approved_default");
		    $from_name = get_option("comment_approved_from_name");
		    $from_email = get_option("comment_approved_from_email");
			
			if( empty( $message ) ) {
				
				$message = $this->default_notification;
				
			}
			
			if( empty( $subject ) ) {
				
				$subject = $this->default_subject ;
				
			}
		
		}
		
		?>
		<div class="wrap">
		
			<?php
				if( $updated ) {
					
					echo '<div id="message" class="updated fade"><p>'.__("Options saved", 'ca').'</p></div>';
					
				}
			?>
		
			<h2><?php _e('Comment approved', 'ca'); ?></h2>
			<p><?php _e('This notification is sent to the user that has left the comment, after you approve an comment. The message is not sent to comments that has been approved before.', 'ca'); ?></p>
		
			<blockquote>
				<?php _e("Available shortcodes: %permalink%, %name%"); ?>
			</blockquote>
			
			<form  method="post">
		
				<?php wp_nonce_field('comment_approved_settings'); ?>
				
				<table class="form-table" id="wp-comment-approved-settings">
					
					
					<tr class="default-row">
						<th><label><?php _e("Subject", 'ca'); ?></label></th>
						<td>
							<input type="text" name="comment_approved_subject" value="<?php echo esc_attr($subject); ?>" />
						</td>
					</tr>
					<tr class="default-row">
						<th><label><?php _e("Message", 'ca'); ?></label></th>
						<td>
							<textarea cols="50" rows="10" name="comment_approved_message"><?php echo esc_attr($message); ?></textarea>
						</td>
					</tr>
					<tr class="default-row">
						<th><label><?php _e("Enable", 'ca'); ?></label></th>
						<td>
							<input type="checkbox" name="comment_approved_enable" value="true" <?php echo ($enable == 1) ? "checked='checked'" : ""; ?> /> <?php _e("Enable comment approved message", "ca"); ?>
						</td>
					</tr>
					<tr class="default-row">
						<th><label><?php _e("Default state", 'ca'); ?></label></th>
						<td>
							<input type="checkbox" name="comment_approved_default" value="true" <?php echo ($default == 1) ? "checked='checked'" : ""; ?> /> <?php _e("Make the checkbox checked by default on the comment form", "ca"); ?>
						</td>
					</tr>
					<tr class="default-row">
						<th><label><?php _e("Default from name", 'ca'); ?></label></th>
						<td>
							<input type="text" name="comment_approved_from_name" value="<?php echo esc_attr($from_name); ?>" />
						</td>
					</tr>
					<tr class="default-row">
						<th><label><?php _e("Default from e-mail", 'ca'); ?></label></th>
						<td>
							<input type="text" name="comment_approved_from_email" value="<?php echo esc_attr($from_email); ?>" />
						</td>
					</tr>
					<tr class="default-row">
						<th><label><?php _e("Save", 'ca'); ?></label></th>
						<td>
							<input type="submit" class="button" name="comment_approved_settings" value="<?php _e("Save", "ca"); ?>" />
						</td>
					</tr>
									
				</table>
			</form>
			
			<p><?php _e("Plugin by:", 'ca'); ?> <a href="http://media-enzo.nl">Media-Enzo.nl</a>
		</div>
		<?php
		
	}
	
	public function approve_comment_callback($new_status, $old_status, $comment) {
	
	    if($old_status != $new_status) {
	    
	        if($new_status == 'approved') {
		        
		        $notify_me = get_comment_meta( $comment->comment_ID, "notify_me", true );
		        
				if( !empty( $notify_me ) ) {
					
		        	$comment_author = $comment->comment_author;
		        	$comment_author_email = $comment->comment_author_email;
		        	$comment_post_ID = $comment->comment_post_ID;
		        	
		        	$notification = get_option("comment_approved_message");
		        	$subject = get_option("comment_approved_subject");
		        	$enable = get_option("comment_approved_enable");
		        	$from_name = get_option("comment_approved_from_name");
		        	$from_email = get_option("comment_approved_from_email");
		        	
		        	if( $enable == 1 ) {
		        	
			        	if( empty( $notification ) ) {
					
							$notification = $this->default_notification;
							
						}
		        	
			        	if( empty( $subject ) ) {
					
							$subject = $this->default_subject;
							
						}
						
						$send_mail_from_name = $from_name ? $from_name : get_option('blogname');
						$send_mail_from_email = $from_email ? $from_email : get_option('admin_email');
						
						$headers = 'From: '.$send_mail_from_name.' <'.$send_mail_from_email.'>' . "\r\n";						
						
						$notification = str_replace("%name%", $comment_author, $notification);
						$notification = str_replace("%permalink%", get_permalink( $comment_post_ID ), $notification );
						
						$subject = str_replace("%name%", $comment_author, $subject);
						$subject = str_replace("%permalink%", get_permalink( $comment_post_ID ), $subject );
			        	
			        	wp_mail( $comment_author_email, $subject, $notification, $headers );
			        	
		        	}
	        	
	        	}
	        }
	        
	    }
	    
	}
	
	public function approve_comment_fields( $fields ) {
		    
		$default = get_option("comment_approved_default");
		
		$checked = $default ? "checked='checked'" : "";
		
		$fields['notify_me'] = " <p class='comment-form-notify-me'> <input type='checkbox' ".$checked." name='comment-approved_notify-me' value='yes' /> ".__("Notify me by email when my comment gets approved.", "ca")."</p>";
		
		return $fields;
		
	}
	
	public function approve_comment_posted( $comment_id, $comment_object ) {
		
		$wants_notification = isset( $_POST['comment-approved_notify-me'] ) ? true : false;
		
		
		if( $wants_notification ) {
			
			add_comment_meta( $comment_id, 'notify_me', mktime() );
			
		}
		
	}

}
