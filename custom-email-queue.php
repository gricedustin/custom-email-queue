<?php
/*
Plugin Name: Custom Email Queue Manager
Plugin URI: https://example.com
Description: Creates a custom post type "Custom Email Queue" to schedule and send emails automatically or manually, with optional WooCommerce template support, multi-send history, queue run logs, and more.
Version: 1.1.0
Author: Your Name
Author URI: https://example.com
License: GPL2
Text Domain: custom-email-queue
*/

/**
 * SECURITY CHECK
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PLUGIN CONSTANTS
 */
define( 'CEU_PLUGIN_VERSION', '1.1.0' );
define( 'CEU_PLUGIN_SLUG', 'custom-email-queue' ); // For references in settings, etc.

/**
 * ACTIVATION & DEACTIVATION
 */
register_activation_hook( __FILE__, 'ceu_activate' );
register_deactivation_hook( __FILE__, 'ceu_deactivate' );

/**
 * SCHEDULE/UNSCHEDULE CRON
 */
function ceu_activate() {
	if ( ! wp_next_scheduled( 'ceu_cron_hook' ) ) {
		wp_schedule_event( time(), 'hourly', 'ceu_cron_hook' );
	}
}

function ceu_deactivate() {
	wp_clear_scheduled_hook( 'ceu_cron_hook' );
}

add_action( 'ceu_cron_hook', 'ceu_cron_run_queue' );

/**
 * DEFAULT PLUGIN SETTINGS
 */
function ceu_default_settings() {
	return array(
		'start_hour'        => 7,
		'end_hour'          => 19,
		'default_from_name' => '',
		'default_from_email'=> '',
		'cc_all_emails'     => '',
		'bcc_all_emails'    => '',
		'run_footer_daily'  => 0, // "Run Queue via Homepage Footer Every 24 Hours"
		'run_footer_hourly' => 0, // "Run Queue via Homepage Footer Every Hour"
		'queue_last_ran'    => '',
	);
}

/**
 * GET PLUGIN SETTINGS
 */
function ceu_get_settings() {
	$saved    = get_option( 'ceu_settings', array() );
	$defaults = ceu_default_settings();
	$final    = wp_parse_args( $saved, $defaults );
	return $final;
}

/**
 * UPDATE PLUGIN SETTINGS
 */
function ceu_update_settings( $new_settings ) {
	$defaults = ceu_default_settings();
	$merged   = wp_parse_args( $new_settings, $defaults );
	update_option( 'ceu_settings', $merged );
}

/**
 * REGISTER POST TYPE: custom_email_queue
 */
add_action( 'init', 'ceu_register_post_type' );
function ceu_register_post_type() {
	$labels = array(
		'name'               => __( 'Email Queue', 'custom-email-queue' ),
		'singular_name'      => __( 'Email Queue', 'custom-email-queue' ),
		'menu_name'          => __( 'Email Queue', 'custom-email-queue' ),
		'name_admin_bar'     => __( 'Email Queue', 'custom-email-queue' ),
		'add_new'            => __( 'Add New', 'custom-email-queue' ),
		'add_new_item'       => __( 'Add New Email Queue', 'custom-email-queue' ),
		'new_item'           => __( 'New Email Queue', 'custom-email-queue' ),
		'edit_item'          => __( 'Edit Email Queue', 'custom-email-queue' ),
		'view_item'          => __( 'View Email Queue', 'custom-email-queue' ),
		'all_items'          => __( 'All Emails', 'custom-email-queue' ),
		'search_items'       => __( 'Search Email Queue', 'custom-email-queue' ),
		'parent_item_colon'  => __( 'Parent Email Queue:', 'custom-email-queue' ),
		'not_found'          => __( 'No Emails found.', 'custom-email-queue' ),
		'not_found_in_trash' => __( 'No Emails found in Trash.', 'custom-email-queue' ),
	);

	$args = array(
		'labels'              => $labels,
		'public'              => false,
		'publicly_queryable'  => false,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'query_var'           => false,
		'rewrite'             => false,
		'capability_type'     => 'post',
		'hierarchical'        => false,
		'menu_position'       => 25,
		'menu_icon'           => 'dashicons-email-alt',
		'supports'            => array( 'title', 'editor' ),
	);

	register_post_type( 'custom_email_queue', $args );
}

/**
 * REGISTER META BOXES
 */
add_action( 'add_meta_boxes', 'ceu_register_meta_boxes' );
function ceu_register_meta_boxes() {
	add_meta_box(
		'ceu_details_metabox',
		__( 'Email Details', 'custom-email-queue' ),
		'ceu_details_metabox_callback',
		'custom_email_queue',
		'normal',
		'default'
	);
}

/**
 * META BOX CALLBACK
 */
function ceu_details_metabox_callback( $post ) {
	wp_nonce_field( 'ceu_save_meta', 'ceu_save_meta_nonce' );

	$email_to       = get_post_meta( $post->ID, '_ceu_email_to', true );
	$email_cc       = get_post_meta( $post->ID, '_ceu_email_cc', true );
	$email_bcc      = get_post_meta( $post->ID, '_ceu_email_bcc', true );
	$attachment_url = get_post_meta( $post->ID, '_ceu_attachment_url', true );
	$from_name      = get_post_meta( $post->ID, '_ceu_from_name', true );
	$from_email     = get_post_meta( $post->ID, '_ceu_from_email', true );
	$email_headline = get_post_meta( $post->ID, '_ceu_email_headline', true );
	$send_x_hours   = get_post_meta( $post->ID, '_ceu_send_x_hours', true );

	// We now store multiple sends in _ceu_sent_history, so no single "sent_timestamp" field.

	// Display
	?>
	<table class="form-table">
		<tr>
			<th><label for="ceu_email_to"><?php esc_html_e( 'To Address', 'custom-email-queue' ); ?></label></th>
			<td>
				<input type="text" name="ceu_email_to" id="ceu_email_to" value="<?php echo esc_attr( $email_to ); ?>" class="regular-text" />
			</td>
		</tr>
		<tr>
			<th><label for="ceu_email_cc"><?php esc_html_e( 'CC', 'custom-email-queue' ); ?></label></th>
			<td>
				<input type="text" name="ceu_email_cc" id="ceu_email_cc" value="<?php echo esc_attr( $email_cc ); ?>" class="regular-text" />
			</td>
		</tr>
		<tr>
			<th><label for="ceu_email_bcc"><?php esc_html_e( 'BCC', 'custom-email-queue' ); ?></label></th>
			<td>
				<input type="text" name="ceu_email_bcc" id="ceu_email_bcc" value="<?php echo esc_attr( $email_bcc ); ?>" class="regular-text" />
			</td>
		</tr>
		<tr>
			<th><label for="ceu_attachment_url"><?php esc_html_e( 'File Attachment URL', 'custom-email-queue' ); ?></label></th>
			<td>
				<input type="text" name="ceu_attachment_url" id="ceu_attachment_url" value="<?php echo esc_attr( $attachment_url ); ?>" class="regular-text" />
			</td>
		</tr>
		<tr>
			<th><label for="ceu_from_name"><?php esc_html_e( 'From Name', 'custom-email-queue' ); ?></label></th>
			<td>
				<input type="text" name="ceu_from_name" id="ceu_from_name" value="<?php echo esc_attr( $from_name ); ?>" class="regular-text" />
			</td>
		</tr>
		<tr>
			<th><label for="ceu_from_email"><?php esc_html_e( 'From Email', 'custom-email-queue' ); ?></label></th>
			<td>
				<input type="text" name="ceu_from_email" id="ceu_from_email" value="<?php echo esc_attr( $from_email ); ?>" class="regular-text" />
			</td>
		</tr>
		<tr>
			<th><label for="ceu_email_headline"><?php esc_html_e( 'Email Headline', 'custom-email-queue' ); ?></label></th>
			<td>
				<input type="text" name="ceu_email_headline" id="ceu_email_headline" value="<?php echo esc_attr( $email_headline ); ?>" class="regular-text" />
			</td>
		</tr>
		<tr>
			<th><label for="ceu_send_x_hours"><?php esc_html_e( 'Send X Hours After Created', 'custom-email-queue' ); ?></label></th>
			<td>
				<input type="number" name="ceu_send_x_hours" id="ceu_send_x_hours" value="<?php echo esc_attr( $send_x_hours ); ?>" class="small-text" />
				<span class="description"><?php esc_html_e( '0 or empty to send immediately.', 'custom-email-queue' ); ?></span>
			</td>
		</tr>
	</table>
	<?php
	/**
	 * "SEND NOW" button. Always visible. Re-sends with the current (latest) data if it was previously sent.
	 */
	$send_url = add_query_arg(
		array(
			'post'     => $post->ID,
			'action'   => 'ceu_send_now',
			'_wpnonce' => wp_create_nonce( 'ceu_send_now_' . $post->ID ),
		),
		admin_url( 'post.php' )
	);
	?>
	<p>
		<a href="#"
		   class="button button-primary"
		   onclick="if(confirm('<?php echo esc_js( __( 'Are you sure you want to send (or re-send) this email NOW using the latest data?', 'custom-email-queue' ) ); ?>')){window.location='<?php echo esc_url( $send_url ); ?>';} return false;">
		   <?php esc_html_e( 'SEND NOW', 'custom-email-queue' ); ?>
		</a>
	</p>
	<?php
}

/**
 * SAVE META BOX DATA
 */
add_action( 'save_post', 'ceu_save_meta_data' );
function ceu_save_meta_data( $post_id ) {
	// Check nonce
	if ( ! isset( $_POST['ceu_save_meta_nonce'] ) || ! wp_verify_nonce( $_POST['ceu_save_meta_nonce'], 'ceu_save_meta' ) ) {
		return;
	}
	// Check auto-save
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	// Check user capability
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$fields = array(
		'_ceu_email_to'       => isset( $_POST['ceu_email_to'] ) ? sanitize_text_field( $_POST['ceu_email_to'] ) : '',
		'_ceu_email_cc'       => isset( $_POST['ceu_email_cc'] ) ? sanitize_text_field( $_POST['ceu_email_cc'] ) : '',
		'_ceu_email_bcc'      => isset( $_POST['ceu_email_bcc'] ) ? sanitize_text_field( $_POST['ceu_email_bcc'] ) : '',
		'_ceu_attachment_url' => isset( $_POST['ceu_attachment_url'] ) ? esc_url_raw( $_POST['ceu_attachment_url'] ) : '',
		'_ceu_from_name'      => isset( $_POST['ceu_from_name'] ) ? sanitize_text_field( $_POST['ceu_from_name'] ) : '',
		'_ceu_from_email'     => isset( $_POST['ceu_from_email'] ) ? sanitize_text_field( $_POST['ceu_from_email'] ) : '',
		'_ceu_email_headline' => isset( $_POST['ceu_email_headline'] ) ? sanitize_text_field( $_POST['ceu_email_headline'] ) : '',
		'_ceu_send_x_hours'   => isset( $_POST['ceu_send_x_hours'] ) ? intval( $_POST['ceu_send_x_hours'] ) : 0,
	);

	foreach ( $fields as $key => $value ) {
		update_post_meta( $post_id, $key, $value );
	}
}

/**
 * PROCESS "SEND NOW" ACTION FROM POST EDIT
 */
add_action( 'load-post.php', 'ceu_process_send_now' );
function ceu_process_send_now() {
	if ( isset( $_GET['action'] ) && $_GET['action'] === 'ceu_send_now' && isset( $_GET['post'] ) ) {
		$post_id = intval( $_GET['post'] );
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'ceu_send_now_' . $post_id ) ) {
			return;
		}

		// "SEND NOW" uses the latest data from the post meta
		ceu_send_single_email_from_post_meta( $post_id, 'Manual Post Edit' );

		// Redirect back to post edit page
		wp_safe_redirect( admin_url( 'post.php?post=' . $post_id . '&action=edit' ) );
		exit;
	}
}

/*-------------------------------------------------------
 * DEVELOPER FUNCTIONS
 *------------------------------------------------------*/

/**
 * custom_email_queue_insert()
 * Inserts a new post in the queue with the specified data.
 *
 * @param array $args {
 *   @type string subject
 *   @type string body (HTML)
 *   @type string email_to
 *   @type string cc
 *   @type string bcc
 *   @type string attachment_url
 *   @type string from_name
 *   @type string from_email
 *   @type string email_headline
 *   @type int    send_x_hours
 *   @type string post_date (optional)
 * }
 * @return int|WP_Error
 */
function custom_email_queue_insert( $args = array() ) {
	$defaults = array(
		'subject'        => '',
		'body'           => '',
		'email_to'       => '',
		'cc'             => '',
		'bcc'            => '',
		'attachment_url' => '',
		'from_name'      => '',
		'from_email'     => '',
		'email_headline' => '',
		'send_x_hours'   => 0,
	);
	$data = wp_parse_args( $args, $defaults );

	$post_arr = array(
		'post_type'    => 'custom_email_queue',
		'post_status'  => 'publish',
		'post_title'   => $data['subject'],
		'post_content' => $data['body'],
	);
	if ( ! empty( $data['post_date'] ) ) {
		$post_arr['post_date'] = $data['post_date'];
		$time = strtotime( $data['post_date'] );
		if ( $time ) {
			$post_arr['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $time );
		}
	}

	$post_id = wp_insert_post( $post_arr );
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	update_post_meta( $post_id, '_ceu_email_to',       sanitize_text_field( $data['email_to'] ) );
	update_post_meta( $post_id, '_ceu_email_cc',       sanitize_text_field( $data['cc'] ) );
	update_post_meta( $post_id, '_ceu_email_bcc',      sanitize_text_field( $data['bcc'] ) );
	update_post_meta( $post_id, '_ceu_attachment_url', esc_url_raw( $data['attachment_url'] ) );
	update_post_meta( $post_id, '_ceu_from_name',      sanitize_text_field( $data['from_name'] ) );
	update_post_meta( $post_id, '_ceu_from_email',     sanitize_text_field( $data['from_email'] ) );
	update_post_meta( $post_id, '_ceu_email_headline', sanitize_text_field( $data['email_headline'] ) );
	update_post_meta( $post_id, '_ceu_send_x_hours',   intval( $data['send_x_hours'] ) );

	// Initialize an empty sent history array for new posts
	if ( ! get_post_meta( $post_id, '_ceu_sent_history', true ) ) {
		update_post_meta( $post_id, '_ceu_sent_history', array() );
	}

	return $post_id;
}

/**
 * custom_email_queue_run()
 * Main function to process unsent emails in the queue.
 *
 * @param string $run_method A label indicating how the queue is run (e.g. 'WP_Cron', 'Manual (Settings Page)', 'Footer 24 Hour', 'Footer Hourly', etc.)
 */
function custom_email_queue_run( $run_method = 'WP_Cron' ) {
	// Update "Queue Last Ran Timestamp"
	$settings = ceu_get_settings();
	$settings['queue_last_ran'] = current_time( 'mysql' );
	ceu_update_settings( $settings );

	// Query unsent: unsent means _ceu_sent_history is empty or no successful send yet
	// But we only consider "unsent" if _ceu_sent_history is an empty array.
	$args = array(
		'post_type'   => 'custom_email_queue',
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'fields'      => 'ids',
		'meta_query'  => array(
			array(
				'key'     => '_ceu_sent_history',
				'compare' => 'NOT EXISTS',
			),
			// Or if the meta exists but is empty, that's still unsent. We'll handle that logic after we fetch.
		),
	);
	$all_posts = get_posts( $args );

	// Additional check for posts that DO have _ceu_sent_history but it's an empty array
	$args_empty = array(
		'post_type'   => 'custom_email_queue',
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'fields'      => 'ids',
		'meta_query'  => array(
			array(
				'key'     => '_ceu_sent_history',
				'value'   => 'a:0:{}', // serialized empty array
				'compare' => '=',
			),
		),
	);
	$empty_history_posts = get_posts( $args_empty );

	$unsent = array_unique( array_merge( $all_posts, $empty_history_posts ) );

	$sent_count = 0;
	foreach ( $unsent as $post_id ) {
		// Attempt to send if conditions (time, etc.) are met
		$did_send = ceu_send_single_email_from_post_meta( $post_id, 'system' );
		if ( $did_send ) {
			$sent_count++;
		}
	}

	// Log this run
	ceu_log_run( $run_method, $sent_count );
}

/**
 * A wrapper to run the queue specifically from Cron (hourly).
 */
function ceu_cron_run_queue() {
	custom_email_queue_run( 'WP_Cron' );
}

/**
 * LOG QUEUE RUN
 * We store in an option 'ceu_run_logs', an array of runs with timestamp, # emails sent, URL, method, etc.
 */
function ceu_log_run( $method, $emails_sent ) {
	$logs = get_option( 'ceu_run_logs', array() );
	$logs[] = array(
		'timestamp'   => current_time( 'mysql' ),
		'emails_sent' => $emails_sent,
		'url'         => ceu_current_url_guess(),
		'method'      => $method,
	);
	update_option( 'ceu_run_logs', $logs );
}

/**
 * HELPER: Attempt to guess the current URL.
 */
function ceu_current_url_guess() {
	if ( is_admin() ) {
		return isset( $_SERVER['REQUEST_URI'] ) ? 'admin: ' . $_SERVER['REQUEST_URI'] : 'admin';
	} else {
		return home_url( $_SERVER['REQUEST_URI'] );
	}
}

/*-------------------------------------------------------
 * SENDING LOGIC
 *------------------------------------------------------*/

/**
 * Send a single email using the CURRENT post meta data (used by 'SEND NOW', or system runs).
 *
 * @param int    $post_id
 * @param string $trigger E.g. 'system', 'Manual Post Edit', etc.
 *
 * @return bool True if sent, false otherwise.
 */
function ceu_send_single_email_from_post_meta( $post_id, $trigger = 'system' ) {
	// Check time constraints if trigger=system
	// 1) Check if enough hours have passed
	$send_x_hours = (int) get_post_meta( $post_id, '_ceu_send_x_hours', true );
	$created_time = get_post( $post_id )->post_date; // local time
	$created_ts   = strtotime( $created_time );
	$now_ts       = current_time( 'timestamp' );

	if ( 'system' === $trigger ) {
		if ( $send_x_hours > 0 ) {
			$diff_in_hours = ( $now_ts - $created_ts ) / 3600;
			if ( $diff_in_hours < $send_x_hours ) {
				return false; // Not ready
			}
		}
	}

	// 2) Check allowed window (start_hour, end_hour) if system
	$settings     = ceu_get_settings();
	$start_h      = (int) $settings['start_hour'];
	$end_h        = (int) $settings['end_hour'];
	$current_hour = (int) current_time( 'G' );

	if ( 'system' === $trigger ) {
		if ( $current_hour < $start_h || $current_hour >= $end_h ) {
			return false; // out of window
		}
	}

	// Gather meta data
	$subject        = get_the_title( $post_id );
	$body           = get_post( $post_id )->post_content;
	$email_to       = get_post_meta( $post_id, '_ceu_email_to', true );
	$email_cc       = get_post_meta( $post_id, '_ceu_email_cc', true );
	$email_bcc      = get_post_meta( $post_id, '_ceu_email_bcc', true );
	$attachment_url = get_post_meta( $post_id, '_ceu_attachment_url', true );
	$from_name      = get_post_meta( $post_id, '_ceu_from_name', true );
	$from_email     = get_post_meta( $post_id, '_ceu_from_email', true );
	$headline       = get_post_meta( $post_id, '_ceu_email_headline', true );

	// Fallback to plugin defaults
	if ( empty( $from_name ) ) {
		$from_name = $settings['default_from_name'];
	}
	if ( empty( $from_email ) ) {
		$from_email = $settings['default_from_email'];
	}

	// Additional CC/BCC from settings
	$global_cc  = $settings['cc_all_emails'];
	$global_bcc = $settings['bcc_all_emails'];

	// Build the final email
	$send_success = ceu_send_email_with_data( array(
		'subject'       => $subject,
		'body'          => $body,
		'email_to'      => $email_to,
		'email_cc'      => $email_cc,
		'email_bcc'     => $email_bcc,
		'global_cc'     => $global_cc,
		'global_bcc'    => $global_bcc,
		'attachment_url'=> $attachment_url,
		'from_name'     => $from_name,
		'from_email'    => $from_email,
		'headline'      => $headline,
		'trigger'       => $trigger,
	) );

	if ( $send_success ) {
		// Record the send in _ceu_sent_history
		$sent_history = get_post_meta( $post_id, '_ceu_sent_history', true );
		if ( ! is_array( $sent_history ) ) {
			$sent_history = array();
		}

		$new_record = array(
			'sent_id'       => uniqid( 'ceu_', true ),
			'timestamp'     => current_time( 'mysql' ),
			'subject'       => $subject,
			'body'          => $body,
			'email_to'      => $email_to,
			'email_cc'      => $email_cc,
			'email_bcc'     => $email_bcc,
			'attachment_url'=> $attachment_url,
			'from_name'     => $from_name,
			'from_email'    => $from_email,
			'email_headline'=> $headline,
			'how_sent'      => ( 'system' === $trigger ) ? 'system' : ceu_current_url_guess(),
			'sent_by'       => ( 'system' === $trigger ) ? 'system' : ( is_user_logged_in() ? get_current_user_id() : 'system' ),
		);

		$sent_history[] = $new_record;
		update_post_meta( $post_id, '_ceu_sent_history', $sent_history );
		return true;
	}

	return false;
}

/**
 * Send an email EXACTLY as recorded in a previous "sent event".
 * This is used when "Resend" is clicked from the Emails Sent page. 
 *
 * @param int    $post_id
 * @param array  $sent_record The previously stored array of data for the email
 * @return bool
 */
function ceu_resend_email_from_history( $post_id, $sent_record ) {
	// We replicate EXACT same data except we might update "how_sent" to "resend" or a manual URL
	// But all other fields are the same.
	$send_success = ceu_send_email_with_data( array(
		'subject'       => $sent_record['subject'],
		'body'          => $sent_record['body'],
		'email_to'      => $sent_record['email_to'],
		'email_cc'      => $sent_record['email_cc'],
		'email_bcc'     => $sent_record['email_bcc'],
		'global_cc'     => '', // We do NOT add the global CC/BCC automatically if we want EXACT same email. 
							   // The requirement states "the EXACT SAME email with the same settings." 
							   // So let's skip global CC/BCC for re-sends.
		'global_bcc'    => '',
		'attachment_url'=> $sent_record['attachment_url'],
		'from_name'     => $sent_record['from_name'],
		'from_email'    => $sent_record['from_email'],
		'headline'      => $sent_record['email_headline'],
		'trigger'       => 'Resend from Emails Sent History',
	) );

	if ( $send_success ) {
		// Add a new record in the same post's _ceu_sent_history
		$sent_history = get_post_meta( $post_id, '_ceu_sent_history', true );
		if ( ! is_array( $sent_history ) ) {
			$sent_history = array();
		}

		$resend_record = $sent_record; // copy
		$resend_record['sent_id']   = uniqid( 'ceu_', true );
		$resend_record['timestamp'] = current_time( 'mysql' );
		$resend_record['how_sent']  = 'Resend from Emails Sent History';
		$resend_record['sent_by']   = is_user_logged_in() ? get_current_user_id() : 'system';

		$sent_history[] = $resend_record;
		update_post_meta( $post_id, '_ceu_sent_history', $sent_history );
		return true;
	}

	return false;
}

/**
 * CORE: Actually build and send the email with or without WooCommerce styles.
 * 
 * @param array $data {
 *   @type string subject
 *   @type string body
 *   @type string email_to
 *   @type string email_cc
 *   @type string email_bcc
 *   @type string global_cc   (for system sends)
 *   @type string global_bcc  (for system sends)
 *   @type string attachment_url
 *   @type string from_name
 *   @type string from_email
 *   @type string headline
 *   @type string trigger
 * }
 * @return bool True if wp_mail() succeeded, false otherwise
 */
function ceu_send_email_with_data( $data ) {
	// Parse addresses
	$to_array = array_map( 'trim', explode( ',', $data['email_to'] ) );
	$to_array = array_filter( $to_array );
	if ( empty( $to_array ) ) {
		return false;
	}

	// Build headers
	$headers = array();
	if ( ! empty( $data['from_email'] ) ) {
		$headers[] = 'From: ' . esc_attr( $data['from_name'] ) . ' <' . esc_attr( $data['from_email'] ) . '>';
	}
	if ( ! empty( $data['email_cc'] ) ) {
		$headers[] = 'Cc: ' . esc_attr( $data['email_cc'] );
	}
	// Only add global cc/bcc if we're sending from "current" data, not re-sends
	// but the instructions mention EXACT SAME email for re-send, so we skip them on re-send.
	if ( ! empty( $data['global_cc'] ) && $data['trigger'] !== 'Resend from Emails Sent History' ) {
		$headers[] = 'Cc: ' . esc_attr( $data['global_cc'] );
	}
	if ( ! empty( $data['email_bcc'] ) ) {
		$headers[] = 'Bcc: ' . esc_attr( $data['email_bcc'] );
	}
	if ( ! empty( $data['global_bcc'] ) && $data['trigger'] !== 'Resend from Emails Sent History' ) {
		$headers[] = 'Bcc: ' . esc_attr( $data['global_bcc'] );
	}
	// Always HTML
	$headers[] = 'Content-Type: text/html; charset=UTF-8';

	// Attachments
	$attachments = array();
	if ( ! empty( $data['attachment_url'] ) ) {
		// If it's a local file, convert to path
		$maybe_local_path = str_replace( home_url(), ABSPATH, $data['attachment_url'] );
		if ( file_exists( $maybe_local_path ) ) {
			$attachments[] = $maybe_local_path;
		}
	}

	// Build final HTML content using WooCommerce if available
	$final_body = '';

	if ( class_exists( 'WC' ) ) {
		// We can use the WC mailer
		$mailer = WC()->mailer();
		// Wrap message using the woo template
		$wrapped_message = $mailer->wrap_message( 
			$data['headline'] ? $data['headline'] : '', 
			$data['body']
		);
		// Inline styles
		$wc_email     = new WC_Email();
		$final_body   = $wc_email->style_inline( $wrapped_message );
	} else {
		// Basic fallback
		$final_body = ceu_basic_html_wrapper( $data['body'], $data['headline'] );
	}

	// Send
	$sent = wp_mail( $to_array, $data['subject'], $final_body, $headers, $attachments );
	return $sent;
}

/**
 * Basic HTML fallback if WooCommerce is not available.
 */
function ceu_basic_html_wrapper( $body, $headline = '' ) {
	ob_start();
	?>
	<html>
	<head><meta charset="utf-8"></head>
	<body style="font-family: Arial, sans-serif;">
		<?php if ( $headline ) : ?>
			<h2><?php echo esc_html( $headline ); ?></h2>
		<?php endif; ?>
		<div><?php echo wp_kses_post( $body ); ?></div>
	</body>
	</html>
	<?php
	return ob_get_clean();
}

/*-------------------------------------------------------
 * FOOTER CHECKS FOR RUNNING QUEUE
 *------------------------------------------------------*/
add_action( 'wp_footer', 'ceu_maybe_run_queue_footer' );
function ceu_maybe_run_queue_footer() {
	if ( ! is_front_page() ) {
		return;
	}
	$settings = ceu_get_settings();
	$last_ran = $settings['queue_last_ran'];

	// If never ran, do so if either box is checked, within window.
	if ( empty( $last_ran ) ) {
		if ( $settings['run_footer_daily'] || $settings['run_footer_hourly'] ) {
			// Check time window
			$start_h      = (int) $settings['start_hour'];
			$end_h        = (int) $settings['end_hour'];
			$current_hour = (int) current_time( 'G' );
			if ( $current_hour >= $start_h && $current_hour < $end_h ) {
				custom_email_queue_run( 'Footer (Uninitialized Last Ran)' );
			}
		}
		return;
	}

	$last_ran_ts = strtotime( $last_ran );
	$now_ts      = current_time( 'timestamp' );

	// Daily check
	if ( $settings['run_footer_daily'] ) {
		if ( ( $now_ts - $last_ran_ts ) >= 86400 ) { // 24h
			$start_h      = (int) $settings['start_hour'];
			$end_h        = (int) $settings['end_hour'];
			$current_hour = (int) current_time( 'G' );
			if ( $current_hour >= $start_h && $current_hour < $end_h ) {
				custom_email_queue_run( 'Footer 24 Hour' );
				return;
			}
		}
	}

	// Hourly check
	if ( $settings['run_footer_hourly'] ) {
		if ( ( $now_ts - $last_ran_ts ) >= 3600 ) {
			$start_h      = (int) $settings['start_hour'];
			$end_h        = (int) $settings['end_hour'];
			$current_hour = (int) current_time( 'G' );
			if ( $current_hour >= $start_h && $current_hour < $end_h ) {
				custom_email_queue_run( 'Footer Hourly' );
				return;
			}
		}
	}
}

/*-------------------------------------------------------
 * ADMIN MENU & PAGES
 *------------------------------------------------------*/
add_action( 'admin_menu', 'ceu_add_admin_menu' );
function ceu_add_admin_menu() {
	// SETTINGS
	add_submenu_page(
		'edit.php?post_type=custom_email_queue',
		__( 'Email Queue Settings', 'custom-email-queue' ),
		__( 'Settings', 'custom-email-queue' ),
		'manage_options',
		'ceu_settings',
		'ceu_settings_page_callback'
	);

	// EMAILS SENT HISTORY
	add_submenu_page(
		'edit.php?post_type=custom_email_queue',
		__( 'Emails Sent History', 'custom-email-queue' ),
		__( 'Emails Sent', 'custom-email-queue' ),
		'manage_options',
		'ceu_emails_sent',
		'ceu_emails_sent_page_callback'
	);

	// QUEUE HISTORY (Runs)
	add_submenu_page(
		'edit.php?post_type=custom_email_queue',
		__( 'Queue History', 'custom-email-queue' ),
		__( 'Queue History', 'custom-email-queue' ),
		'manage_options',
		'ceu_queue_history',
		'ceu_queue_history_page_callback'
	);

	// CHANGELOG
	add_submenu_page(
		'edit.php?post_type=custom_email_queue',
		__( 'Plugin Changelog', 'custom-email-queue' ),
		__( 'Changelog', 'custom-email-queue' ),
		'manage_options',
		'ceu_changelog',
		'ceu_changelog_page_callback'
	);
}

/*-------------------------------------------------------
 * SETTINGS PAGE
 *------------------------------------------------------*/
function ceu_settings_page_callback() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	// Save form
	if ( isset( $_POST['ceu_settings_nonce'] ) && wp_verify_nonce( $_POST['ceu_settings_nonce'], 'ceu_settings' ) ) {
		$new_settings = array(
			'start_hour'         => isset( $_POST['start_hour'] ) ? intval( $_POST['start_hour'] ) : 7,
			'end_hour'           => isset( $_POST['end_hour'] ) ? intval( $_POST['end_hour'] ) : 19,
			'default_from_name'  => isset( $_POST['default_from_name'] ) ? sanitize_text_field( $_POST['default_from_name'] ) : '',
			'default_from_email' => isset( $_POST['default_from_email'] ) ? sanitize_text_field( $_POST['default_from_email'] ) : '',
			'cc_all_emails'      => isset( $_POST['cc_all_emails'] ) ? sanitize_text_field( $_POST['cc_all_emails'] ) : '',
			'bcc_all_emails'     => isset( $_POST['bcc_all_emails'] ) ? sanitize_text_field( $_POST['bcc_all_emails'] ) : '',
			'run_footer_daily'   => isset( $_POST['run_footer_daily'] ) ? 1 : 0,
			'run_footer_hourly'  => isset( $_POST['run_footer_hourly'] ) ? 1 : 0,
		);
		$old = ceu_get_settings();
		// Keep the last_ran
		$new_settings['queue_last_ran'] = $old['queue_last_ran'];

		ceu_update_settings( $new_settings );

		echo '<div class="updated"><p>' . esc_html__( 'Settings updated.', 'custom-email-queue' ) . '</p></div>';
	}

	$settings = ceu_get_settings();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Email Queue Settings', 'custom-email-queue' ); ?></h1>
		<form method="post" action="">
			<?php wp_nonce_field( 'ceu_settings', 'ceu_settings_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row"><label for="start_hour"><?php esc_html_e( 'Allowed Send Start Hour (0-23)', 'custom-email-queue' ); ?></label></th>
					<td>
						<input type="number" name="start_hour" id="start_hour" value="<?php echo esc_attr( $settings['start_hour'] ); ?>" min="0" max="23" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="end_hour"><?php esc_html_e( 'Allowed Send End Hour (0-23)', 'custom-email-queue' ); ?></label></th>
					<td>
						<input type="number" name="end_hour" id="end_hour" value="<?php echo esc_attr( $settings['end_hour'] ); ?>" min="0" max="23" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="default_from_name"><?php esc_html_e( 'Default From Name', 'custom-email-queue' ); ?></label></th>
					<td>
						<input type="text" name="default_from_name" id="default_from_name" value="<?php echo esc_attr( $settings['default_from_name'] ); ?>" class="regular-text" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="default_from_email"><?php esc_html_e( 'Default From Email', 'custom-email-queue' ); ?></label></th>
					<td>
						<input type="text" name="default_from_email" id="default_from_email" value="<?php echo esc_attr( $settings['default_from_email'] ); ?>" class="regular-text" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="cc_all_emails"><?php esc_html_e( 'CC all emails', 'custom-email-queue' ); ?></label></th>
					<td>
						<input type="text" name="cc_all_emails" id="cc_all_emails" value="<?php echo esc_attr( $settings['cc_all_emails'] ); ?>" class="regular-text" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bcc_all_emails"><?php esc_html_e( 'BCC all emails', 'custom-email-queue' ); ?></label></th>
					<td>
						<input type="text" name="bcc_all_emails" id="bcc_all_emails" value="<?php echo esc_attr( $settings['bcc_all_emails'] ); ?>" class="regular-text" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="run_footer_daily"><?php esc_html_e( 'Run Queue via Homepage Footer Every 24 Hours', 'custom-email-queue' ); ?></label></th>
					<td>
						<label>
							<input type="checkbox" name="run_footer_daily" id="run_footer_daily" value="1" <?php checked( $settings['run_footer_daily'], 1 ); ?> />
							<?php esc_html_e( 'Enabled', 'custom-email-queue' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="run_footer_hourly"><?php esc_html_e( 'Run Queue via Homepage Footer Every Hour', 'custom-email-queue' ); ?></label></th>
					<td>
						<label>
							<input type="checkbox" name="run_footer_hourly" id="run_footer_hourly" value="1" <?php checked( $settings['run_footer_hourly'], 1 ); ?> />
							<?php esc_html_e( 'Enabled', 'custom-email-queue' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Queue Last Ran Timestamp', 'custom-email-queue' ); ?></th>
					<td>
						<input type="text" readonly value="<?php echo esc_attr( $settings['queue_last_ran'] ); ?>" class="regular-text" />
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save Settings', 'custom-email-queue' ) ); ?>
		</form>
		<hr>
		<h2><?php esc_html_e( 'Manual Run', 'custom-email-queue' ); ?></h2>
		<?php
		// "Run Queue Now" link
		$run_now_url = add_query_arg(
			array(
				'page'      => 'ceu_settings',
				'ceu_run_now' => 1,
				'_wpnonce'  => wp_create_nonce( 'ceu_run_now' ),
			),
			admin_url( 'edit.php?post_type=custom_email_queue' )
		);
		?>
		<p>
			<a href="<?php echo esc_url( $run_now_url ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Run Queue Now', 'custom-email-queue' ); ?>
			</a>
		</p>
	</div>
	<?php
}

/**
 * CHECK IF "RUN QUEUE NOW" FROM SETTINGS
 */
add_action( 'admin_init', 'ceu_maybe_run_now' );
function ceu_maybe_run_now() {
	if ( isset( $_GET['ceu_run_now'] ) && $_GET['ceu_run_now'] == 1 ) {
		if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'ceu_run_now' ) ) {
			custom_email_queue_run( 'Manual (Settings Page)' );
			wp_safe_redirect( remove_query_arg( array( 'ceu_run_now', '_wpnonce' ) ) );
			exit;
		}
	}
}

/*-------------------------------------------------------
 * EMAILS SENT PAGE
 *------------------------------------------------------*/

/**
 * Emails Sent page: shows each individual sending event from all posts.
 * Each row has a Resend link that replicates EXACT same email data.
 */
function ceu_emails_sent_page_callback() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Check for resend action
	if ( isset( $_GET['ceu_resend'] ) && isset( $_GET['post_id'] ) && isset( $_GET['sent_id'] ) ) {
		$post_id = (int) $_GET['post_id'];
		$sent_id = sanitize_text_field( $_GET['sent_id'] );

		if ( wp_verify_nonce( $_GET['_wpnonce'], 'ceu_resend_' . $post_id . '_' . $sent_id ) ) {
			$sent_history = get_post_meta( $post_id, '_ceu_sent_history', true );
			if ( is_array( $sent_history ) ) {
				foreach ( $sent_history as $record ) {
					if ( isset( $record['sent_id'] ) && $record['sent_id'] === $sent_id ) {
						// Found the record
						$did_resend = ceu_resend_email_from_history( $post_id, $record );
						if ( $did_resend ) {
							echo '<div class="updated"><p>' . esc_html__( 'Email resent successfully.', 'custom-email-queue' ) . '</p></div>';
						} else {
							echo '<div class="error"><p>' . esc_html__( 'Failed to resend email.', 'custom-email-queue' ) . '</p></div>';
						}
						break;
					}
				}
			}
		}
	}

	// Build a combined list of all sending events from all posts
	// We'll do a simple approach: query all 'custom_email_queue' posts, gather each one's _ceu_sent_history
	$args = array(
		'post_type'      => 'custom_email_queue',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
	);
	$all = get_posts( $args );

	// We'll store all events in an array for sorting by date desc
	$all_events = array();

	foreach ( $all as $p ) {
		$pid          = $p->ID;
		$history      = get_post_meta( $pid, '_ceu_sent_history', true );
		if ( is_array( $history ) && ! empty( $history ) ) {
			foreach ( $history as $event ) {
				// Add the post ID for reference
				$event['post_id'] = $pid;
				$all_events[]     = $event;
			}
		}
	}

	// Sort by timestamp desc
	usort( $all_events, function( $a, $b ) {
		return strtotime( $b['timestamp'] ) - strtotime( $a['timestamp'] );
	});

	// Pagination (optional). For simplicity, let's do a small approach
	$paged      = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
	$per_page   = 20;
	$total      = count( $all_events );
	$start      = ( $paged - 1 ) * $per_page;
	$paginated  = array_slice( $all_events, $start, $per_page );
	$total_pages= ceil( $total / $per_page );

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Emails Sent History', 'custom-email-queue' ); ?></h1>

		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Timestamp', 'custom-email-queue' ); ?></th>
					<th><?php esc_html_e( 'Subject', 'custom-email-queue' ); ?></th>
					<th><?php esc_html_e( 'To', 'custom-email-queue' ); ?></th>
					<th><?php esc_html_e( 'Sent By', 'custom-email-queue' ); ?></th>
					<th><?php esc_html_e( 'How Sent', 'custom-email-queue' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'custom-email-queue' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $paginated ) ) : ?>
				<tr>
					<td colspan="6"><?php esc_html_e( 'No emails have been sent yet.', 'custom-email-queue' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $paginated as $event ) : 
					$ts        = $event['timestamp'];
					$subject   = $event['subject'];
					$to        = $event['email_to'];
					$how_sent  = isset( $event['how_sent'] ) ? $event['how_sent'] : '';
					$sent_by   = isset( $event['sent_by'] ) ? $event['sent_by'] : '';
					$post_id   = $event['post_id'];
					$sent_id   = isset( $event['sent_id'] ) ? $event['sent_id'] : '';

					// If numeric user
					if ( is_numeric( $sent_by ) ) {
						$user_info = get_userdata( $sent_by );
						if ( $user_info ) {
							$sent_by_display = '<a href="' . esc_url( get_edit_user_link( $sent_by ) ) . '">' . esc_html( $user_info->user_email ) . '</a>';
						} else {
							$sent_by_display = esc_html( $sent_by );
						}
					} else {
						$sent_by_display = esc_html( $sent_by );
					}

					// Link subject to the original post
					$subject_link = '<a href="' . esc_url( admin_url( 'post.php?post=' . $post_id . '&action=edit' ) ) . '">' 
						. esc_html( $subject ) . '</a>';

					// Resend link
					$resend_url = add_query_arg(
						array(
							'page'       => 'ceu_emails_sent',
							'ceu_resend' => 1,
							'post_id'    => $post_id,
							'sent_id'    => $sent_id,
							'_wpnonce'   => wp_create_nonce( 'ceu_resend_' . $post_id . '_' . $sent_id ),
						),
						menu_page_url( 'ceu_emails_sent', false )
					);
					?>
					<tr>
						<td><?php echo esc_html( $ts ); ?></td>
						<td><?php echo $subject_link; ?></td>
						<td><?php echo esc_html( $to ); ?></td>
						<td><?php echo $sent_by_display; ?></td>
						<td><?php echo esc_html( $how_sent ); ?></td>
						<td>
							<a href="<?php echo esc_url( $resend_url ); ?>"
							   onclick="return confirm('<?php echo esc_js( __( 'Re-send this exact email?', 'custom-email-queue' ) ); ?>');">
							   <?php esc_html_e( 'Resend', 'custom-email-queue' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav">
				<div class="tablenav-pages">
					<?php
					echo paginate_links( array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'current'   => $paged,
						'total'     => $total_pages,
					) );
					?>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/*-------------------------------------------------------
 * QUEUE HISTORY (RUNS) PAGE
 *------------------------------------------------------*/
function ceu_queue_history_page_callback() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$logs = get_option( 'ceu_run_logs', array() );

	// Sort descending by timestamp
	usort( $logs, function( $a, $b ) {
		return strtotime( $b['timestamp'] ) - strtotime( $a['timestamp'] );
	});

	// Simple pagination if large
	$paged    = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
	$per_page = 20;
	$total    = count( $logs );
	$start    = ( $paged - 1 ) * $per_page;
	$page_logs= array_slice( $logs, $start, $per_page );
	$total_pages = ceil( $total / $per_page );

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Queue History', 'custom-email-queue' ); ?></h1>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Timestamp', 'custom-email-queue' ); ?></th>
					<th><?php esc_html_e( 'Number of Emails Sent', 'custom-email-queue' ); ?></th>
					<th><?php esc_html_e( 'URL Ran From', 'custom-email-queue' ); ?></th>
					<th><?php esc_html_e( 'How Queue Was Run', 'custom-email-queue' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $page_logs ) ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'No runs found.', 'custom-email-queue' ); ?></td></tr>
				<?php else : 
					foreach ( $page_logs as $log ) :
						?>
						<tr>
							<td><?php echo esc_html( $log['timestamp'] ); ?></td>
							<td><?php echo esc_html( $log['emails_sent'] ); ?></td>
							<td><?php echo esc_html( $log['url'] ); ?></td>
							<td><?php echo esc_html( $log['method'] ); ?></td>
						</tr>
						<?php
					endforeach;
				endif; ?>
			</tbody>
		</table>
		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav">
				<div class="tablenav-pages">
					<?php
					echo paginate_links( array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'current'   => $paged,
						'total'     => $total_pages,
					) );
					?>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/*-------------------------------------------------------
 * CHANGELOG PAGE
 *------------------------------------------------------*/

/**
 * Hardcoded array of changelog entries. Each time we update the plugin,
 * we add a new entry here with version, timestamp, changes, etc.
 */
function ceu_get_changelog_entries() {
	return array(
		array(
			'version'   => '1.0.0',
			'timestamp' => '2025-03-22 10:00:00',
			'changes'   => "Initial version.\n- Created plugin architecture.\n- Basic email queue features."
		),
		array(
			'version'   => '1.1.0',
			'timestamp' => '2025-03-22 12:00:00',
			'changes'   => "Renamed 'Email Queue History' to 'Emails Sent History'.\n" .
						   "Added multi-sent timestamps with an array of send events.\n" .
						   "Added separate 'Queue History' page to track runs.\n" .
						   "Added new 'Changelog' page.\n" .
						   "Improved WooCommerce email template usage.\n" .
						   "Implemented re-sending exact same email data from history.\n" .
						   "Added manual or system triggers for sending."
		),
	);
}

function ceu_changelog_page_callback() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$entries = ceu_get_changelog_entries();

	// Sort by version descending or keep them in the order we define
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Plugin Changelog', 'custom-email-queue' ); ?></h1>
		<table class="widefat striped">
			<thead>
			<tr>
				<th><?php esc_html_e( 'Version', 'custom-email-queue' ); ?></th>
				<th><?php esc_html_e( 'Timestamp', 'custom-email-queue' ); ?></th>
				<th><?php esc_html_e( 'Changes', 'custom-email-queue' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $entries as $entry ) : ?>
				<tr>
					<td><?php echo esc_html( $entry['version'] ); ?></td>
					<td><?php echo esc_html( $entry['timestamp'] ); ?></td>
					<td><pre style="white-space:pre-wrap;"><?php echo esc_html( $entry['changes'] ); ?></pre></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}
