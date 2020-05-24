<?php

/**
 * Check active contests
 *
 * Runs every night with scheduled event. At this moment it
 * will only do something with future ending contests.
 */
function epc_check_active_contests() {
  $current_date = current_time( 'Y-m-d' );

  $contests = get_posts( array(
    'post_type' => 'easy_photo_contest',
    'post_status' => 'publish',
    'showposts' => -1,
    'meta_query' => array(
      'relation' => 'OR',
      array(
        'key'     => '_epc_start_date',
        'compare' => '>=',
        'value'   => $current_date
      ),
      array(
        'key'     => '_epc_end_date',
        'compare' => '<=',
        'value'   => $current_date
      )
    )
  ) );

  foreach ( $contests as $contest ) {

    $current_date = current_time( 'Ymd', strtotime( $current_date ) );
    $is_active  = get_post_meta( $contest->ID, '_epc_is_active', true );
    $start_date = date( 'Ymd', strtotime( get_post_meta( $contest->ID, '_epc_start_date', true ) ) );
    $end_date   = date( 'Ymd', strtotime( get_post_meta( $contest->ID, '_epc_end_date', true ) ) );

    if ( ($end_date <= $current_date) && $is_active == "1" ) {
      update_post_meta( $contest->ID, '_epc_is_active', '0' );
    } elseif ( ($start_date >= $current_date ) && ($is_active == "0" || $is_active == null ) ) {
      update_post_meta( $contest->ID, '_epc_is_active', '1' );
    }

  }

}
add_action( 'epc_check_active_contests', 'epc_check_active_contests', 10, 2 );

/**
 * Add rewrite rules
 */
function epc_add_rewrite_rules() {
  $slug = epc_get_post_type_slug();

  add_rewrite_rule( "{$slug}/([^/]+)/entry/?([0-9]{1,})/?$", 'index.php?easy_photo_contest=$matches[1]&entry_id=$matches[2]','top' );
}
add_action('init', 'epc_add_rewrite_rules');

/**
 * Image sizes
 */
function epc_image_sizes() {
  add_image_size( 'epc_thumbnail', 350, 350, true );
}
add_action( 'init', 'epc_image_sizes' );

/**
 * Load more entries
 *
 * Used as front end paging.
 */
function epc_load_more_entries() {
	$contest_id = (int) $_POST['contest_id'];
  $offset = (int) $_POST['offset'];
  $order = filter_input( INPUT_POST, 'order', FILTER_SANITIZE_STRING );
  $orderby = filter_input( INPUT_POST, 'orderby', FILTER_SANITIZE_STRING );

  $entries = epc_get_contest_entries(
    $contest_id,
    array(
      'order' => $order,
      'orderby' => $orderby,
      'offset' => $offset
    )
  );

  $result = array();

  if ( !empty( $entries ) ) {

    foreach( $entries as $entry ) {
      ob_start();

      include apply_filters( 'epc_entry_item_view', EPC_PATH . 'views/entry-item.php' );

      $result[] = ob_get_clean();
    }

  }

  echo json_encode( $result );

  exit;
}
add_action( 'wp_ajax_nopriv_epc_load_more_entries', 'epc_load_more_entries' );
add_action( 'wp_ajax_epc_load_more_entries', 'epc_load_more_entries' );

/**
 * Is approval notification
 *
 * @return boolean
 */
function epc_is_approval_notification() {
  global $epc_options;

  $enabled = '';

  if ( $epc_options['send_entry_approval_notification'] ) {
    $enabled = true;
  }

  return apply_filters( 'epc_send_entry_approval_notification', $enabled );
}

/**
 * Send entry approval mail
 */
function epc_send_entry_approval_mail( $entry_id ) {
  global $epc_options;

  $entry = epc_get_contest_entry( $entry_id );

  // If the status is not approved at this point
  // it means it will get approved after this
  // action is fired, in that case we do
  // want to send the notification
  if ( $entry->status == 'approved' )
    return;

  $entry_url = epc_get_entry_url( $entry_id, $entry->contest_id );

  $subject = $epc_options['entry_approval_notification_email_subject'];
  $text = wpautop( $epc_options['entry_approval_notification_email_text'] );

  $text = str_replace(
    array( '{entry_url}', '{entry_name}' ),
    array( esc_url( $entry_url ), ( !empty( $entry->name) ? esc_attr( $entry->name ) : null) ),
    $text
  );

  $email = '';

  if ( !empty( $entry->user_id ) ) {
    $user_info = get_userdata( $entry->user_id );
    $email = $user_info->user_email;

  } else {
    $email = epc_get_entry_meta( $entry_id, 'contestant_email' );

  }

  if ( !empty( $email ) && !empty( $subject ) && !empty( $text ) ) {
    epc_mail(
      apply_filters( 'epc_send_entry_approval_mail_email', $email ),
      apply_filters( 'epc_send_entry_approval_mail_subject', $subject ),
      apply_filters( 'epc_send_entry_approval_mail_text', $text )
    );
  }
}
if ( epc_is_approval_notification() ) {
  add_action( 'epc_contest_entry_status_approved', 'epc_send_entry_approval_mail', 10, 1 );
}

/**
 * Send admin entry notification
 *
 * @param  int $entry_id
 */
function epc_send_admin_entry_notification_mail( $entry_id ) {
  if ( ! epc_is_admin_entry_notification_enabled() )
    return;

  $email = get_option( 'admin_email' );

  $subject = __( 'A new contest entry has been submitted', EPC_TEXT_DOMAIN );

  $entry = epc_get_contest_entry( $entry_id );

  $entry_url = epc_get_entry_url( $entry_id, $entry->contest_id );
  $entry_admin_url = epc_get_edit_entry_url( $entry_id );

  $text  = '<p>'. sprintf( __( 'A new contest entry has been submitted. You can view it here: %s', EPC_TEXT_DOMAIN ), $entry_url ).'<br>';
  $text .= sprintf( __( 'You can edit the entry here: %s', EPC_TEXT_DOMAIN ), $entry_admin_url ) . '</p>';

  if ( !empty( $email ) && !empty( $subject ) && !empty( $text ) ) {
    epc_mail(
      apply_filters( 'epc_send_admin_entry_notification_mail_email', $email ),
      apply_filters( 'epc_send_admin_entry_notification_mail_subject', $subject ),
      apply_filters( 'epc_send_admin_entry_notification_mail_text', $text )
    );
  }

}
add_action( 'epc_after_insert_entry', 'epc_send_admin_entry_notification_mail' );
