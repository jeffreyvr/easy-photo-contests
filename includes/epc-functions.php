<?php

/**
 * Get settings
 *
 * Retrieves all the plugin settings.
 *
 * @return array
 */
function epc_get_settings() {
  $settings = is_array( get_option( 'epc_settings' ) ) ? get_option( 'epc_settings' ) : array();

  return apply_filters( 'epc_get_settings', $settings );
}

/**
 * Get contests
 *
 * @return array
 */
function epc_get_contests($include_not_active=false) {
  $args = array(
    'post_type' => 'easy_photo_contest',
    'showposts' => -1, // all
    'post_status' => 'publish'
  );

  if ( $include_not_active == false ) {
    $args['meta_key'] = '_epc_is_active';
    $args['meta_value'] = '1';
  }

  return get_posts( $args );
}

/**
 * Get contests select
 *
 * Get id and name from contests to use for example in a selectbox.
 *
 * @return array
 */
function epc_get_contests_select($include_not_active=false) {
  $result = array();
  $contests = epc_get_contests($include_not_active);
  foreach ( $contests as $contest ){
    $result[$contest->ID] = $contest->post_title;
  }
  return $result;
}

/**
 * Get max upload size
 *
 * @return int
 */
function epc_get_max_upload_size() {
  global $epc_options;

  $max_upload_size = 0;
  $wp_max_upload_size = wp_max_upload_size();

  if ( !empty( $epc_options['max_file_size'] ) ) {
    $max_upload_size = ( $wp_max_upload_size < $epc_options['max_file_size'] ? $wp_max_upload_size : $epc_options['max_file_size'] );
  }

  return apply_filters('epc_max_upload_size', $max_upload_size );
}

/**
 * Insert Entry
 *
 * @param  int $contest_id
 * @param  array $contestant
 * @param  array $entry
 * @param  string $file
 * @param  string $approved
 * @return int
 */
function epc_insert_entry( $contest_id, $contestant, $entry, $file, $force_approved = false ) {
  global $wpdb;

  require_once( ABSPATH . 'wp-admin/includes/image.php' );
  require_once( ABSPATH . 'wp-admin/includes/file.php' );
  require_once( ABSPATH . 'wp-admin/includes/media.php' );

  $media_id = media_handle_upload( $file, $contest_id );

  if ( is_wp_error( $media_id ) ) {
    wp_die( __( 'Could not upload the file.', EPC_TEXT_DOMAIN ) );
  }

  update_post_meta( $media_id, '_epc_media', '1' );

  $set_meta = false;

  $table = $wpdb->prefix . 'epc_entries';

  $data = array(
    'date' => current_time( 'Y-m-d H:i:s' ),
    'name' => $entry['name'],
    'description' => $entry['description'],
    'contest_id' => $contest_id,
    'media_id' => $media_id
  );

  if ( epc_is_entry_approval_required() && ! $force_approved ) {
    $data['status'] = 'waiting_approval';
  } else {
    $data['status'] = 'approved';
  }

  if ( !empty( $contestant['user_id'] ) ) {
    $data['user_id'] = $contestant['user_id'];
  } else {
    $set_meta = true;
  }

  $wpdb->insert( $table, $data );

  $entry_id = $wpdb->insert_id;

  if ( $set_meta ) {
    epc_update_entry_meta( $entry_id, 'contestant_name', $contestant['name'] );
    epc_update_entry_meta( $entry_id, 'contestant_email', $contestant['email'] );
  }

  if ( !empty( $entry_id ) ) {
    do_action( 'epc_after_insert_entry', $entry_id );
  }

  return $entry_id;
}

/**
 * Update Entry Meta
 *
 * @param  int $entry_id
 * @param  string $key
 * @param  string $value
 * @return int
 */
function epc_update_entry_meta( $entry_id, $key, $value ) {
  global $wpdb;

  $table = $wpdb->prefix . 'epc_entry_meta';

  $data = array(
    'entry_id' => $entry_id,
    'meta_key' => $key,
    'meta_value' => $value
  );

  $wpdb->insert( $table, $data );

  return $wpdb->insert_id;
}

/**
 * Get entry meta
 *
 * @param  int $entry_id
 * @param  string $key
 * @return string
 */
function epc_get_entry_meta( $entry_id, $key ) {
  global $wpdb;

  $result = $wpdb->get_row(
    $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}epc_entry_meta WHERE entry_id=%d AND meta_key=%s", (int) $entry_id, esc_attr( $key ) )
  );

  if ( !empty( $result->meta_value ) )
    return $result->meta_value;

  return;
}

/**
 * Get contest entries
 *
 * @param  int  $contest_id
 * @param  string  $order
 * @param  string  $orderby
 * @param  integer $offset
 * @param  int  $limit
 * @return array
 */
function epc_get_contest_entries( $contest_id, $args = array() ) {
  global $wpdb;

  $default_args = array(
    'order' => 'DESC',
    'orderby' => 'date',
    'offset' => 0,
    'limit' => epc_get_entries_per_page(),
    'status' => 'approved'
  );

  $args = array_merge( $default_args, $args );

  $placeholders['contest_id'] = (int) $contest_id;

  $votes_table    = $wpdb->prefix . 'epc_votes';
  $entries_table  = $wpdb->prefix . 'epc_entries';

  if ( ! in_array( $args['orderby'], array( 'votes', 'date' ) ) ) $args['orderby'] = 'date';

  $select = "$entries_table.*";
  $join   = "";
  $group  = "";

  if ( $args['orderby'] == 'votes' ) {
    $select  .= ", count(vote_id) as number_of_votes ";
    $join     = " LEFT JOIN $votes_table ON $entries_table.entry_id = $votes_table.entry_id ";
    $group    = " GROUP BY $entries_table.entry_id ";
    $orderby  = " count(vote_id) ";
  }

  $statement  = "SELECT $select FROM {$wpdb->prefix}epc_entries ";
  $statement .= $join;
  $statement .= " WHERE $entries_table.contest_id=%d ";

  if ( !empty( $args['status'] ) ) {
    $placeholders['status'] = esc_attr( $args['status'] );
    $statement .= " AND $entries_table.status=%s ";
  }

  if ( !empty( $args['search'] ) ) {
    $placeholders['search'] = esc_attr( $args['search'] );
    $statement .= " AND $entries_table.name LIKE '%%%s%%' ";
  }

  $statement .= " $group ";
  if ( $args['orderby'] == 'votes' ) {
    $statement .= " ORDER BY number_of_votes {$args['order']} ";
  } else {
    $statement .= " ORDER BY {$args['orderby']} {$args['order']} ";
  }

  if ( !empty( $args['offset'] ) || !empty( $args['limit'] ) ) {
    $statement .= " LIMIT {$args['offset']}, {$args['limit']} ";
  }

  $results = $wpdb->get_results( $wpdb->prepare( $statement, $placeholders ) );

  return $results;
}

/**
 * Get total entries
 *
 * @param  int $contest_id
 * @param  string $search_term
 * @return int
 */
function epc_get_total_entries( $contest_id, $search_term ) {
  global $wpdb;

  $entries_table  = $wpdb->prefix . 'epc_entries';

  $placeholders['contest_id'] = $contest_id;

  $statement  = "SELECT COUNT(1) FROM {$entries_table} ";
  $statement .= " WHERE $entries_table.contest_id=%d ";

  if ( !empty( $search_term ) ) {
    $placeholders['search'] = $search_term;
    $statement .= " AND $entries_table.name LIKE '%%%s%%' ";
  }

  $result = $wpdb->get_var(
    $wpdb->prepare( $statement, $placeholders )
  );

  return $result;
}

/**
 * Get contest entry
 *
 * @param  int $entry_id
 * @return object
 */
function epc_get_contest_entry( $entry_id ) {
  global $wpdb;

  $results = $wpdb->get_row(
    $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}epc_entries WHERE entry_id=%d", (int) $entry_id)
  );

  return $results;
}

/**
 * Update contest entry
 *
 * @param  int $entry_id
 * @param  array $entry
 * @return int
 */
function epc_update_contest_entry( $entry_id, $entry_input ) {
  global $wpdb;

  // some filtering
  if ( ! is_array( $entry_input ) ) return;

  if ( isset( $entry_input['name'] ) )
    $entry['name'] = $entry_input['name'];

  if ( isset( $entry_input['description'] ) )
    $entry['description'] = $entry_input['description'];

  if ( isset( $entry_input['status'] ) ) {
    $entry['status'] = $entry_input['status'];

    if ( $entry_input['status'] == 'approved' ) {
      do_action( 'epc_contest_entry_status_approved', $entry_id );
    } elseif ( $entry_input['status'] == 'not_approved' ) {
      do_action( 'epc_contest_entry_status_not_approved', $entry_id );
    }
  }

  if ( !empty( $entry ) ) {
    $wpdb->update(
      $wpdb->prefix . 'epc_entries',
      $entry,
      array( 'entry_id' => $entry_id )
    );
  }

  return $entry_id;
}

/**
 * Get edit entry url
 *
 * @param  int $entry_id
 * @return string
 */
function epc_get_edit_entry_url( $entry_id ) {
  return admin_url( "edit.php?post_type=easy_photo_contest&page=easy_photo_contest_entries&entry_id=$entry_id" );
}

/**
 * Get delete vote url
 * @param  int $vote_id
 * @return string
 */
function epc_get_delete_vote_url( $entry_id, $vote_id ) {
  return admin_url( "edit.php?post_type=easy_photo_contest&page=easy_photo_contest_entries&entry_id=$entry_id&vote_id=$vote_id" );
}

/**
 * Delete vote
 *
 * @param  int $vote_id
 */
function epc_delete_vote( $vote_id ) {
  global $wpdb;

  $wpdb->delete( $wpdb->prefix . 'epc_votes', array(
    'vote_id' => $vote_id
  ) );
}

/**
 * Get admin entries per page
 *
 * @todo Implement custom function to set entries per admin/front end.
 *
 * @return int
 */
function epc_get_entries_per_page() {
  global $epc_options;

  return ( !empty( $epc_options['entries_per_page'] ) ? (int) $epc_options['entries_per_page'] : 12 );
}

/**
 * Get admin entries url
 *
 * @param  int|null $contest_id
 * @return string
 */
function epc_get_admin_entries_url( $contest_id = null ) {
  $query = array( 'post_type' => 'easy_photo_contest', 'page' => 'easy_photo_contest_entries' );

  if ( !empty( $contest_id ) ) {
    $query['contest_id'] = (int) $contest_id;
  }

  return add_query_arg( $query, admin_url( "edit.php" ) );
}

/**
 * Delete contest entry
 *
 * @param  int $entry_id
 */
function epc_delete_contest_entry( $entry_id ) {
  global $wpdb;

  $entry = epc_get_contest_entry( $entry_id );

  if ( !empty( $entry ) && ! empty( $entry->media_id ) ) {
    wp_delete_attachment( (int) $entry->media_id, true );
  }

  $wpdb->delete( $wpdb->prefix . 'epc_entries', array(
    'entry_id' => $entry_id
  ) );

  $wpdb->delete( $wpdb->prefix . 'epc_entry_meta', array(
    'entry_id' => $entry_id
  ) );

  do_action( 'epc_after_delete_contest_entry' );

}

/**
 * Entry enter only once
 *
 * @return boolean
 */
function epc_entry_enter_only_once() {
  global $epc_options;

  $only_once = '';

  $only_once = (!empty($epc_options['entry_requirements']['enter_once']) ? true : false );

  return apply_filters( 'epc_entry_enter_only_once', $only_once );
}

/**
 * Contestant has submitted in contest
 *
 * Check if contestant has already entered in contest.
 *
 * @param  int  $contest_id
 * @param  int  $user_id
 * @param  string  $email
 * @return boolean
 */
function epc_contestant_submitted_in_contest( $contest_id, $user_id = null, $email = null ) {
  global $wpdb;

  $result = false;

  if ( ! empty( $user_id ) ) {
    $result = $wpdb->get_row(
      $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}epc_entries WHERE user_id=%d AND contest_id=%d", (int) $user_id, (int) $contest_id )
    );
  } elseif ( !empty( $email ) ) {

    // There should be a better way...

    $entries = $wpdb->get_results(
      $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}epc_entries WHERE contest_id=%d", (int) $contest_id )
    );

    if (!empty( $entries ) ) {
      foreach ( $entries as $entry ) {

        $result = $wpdb->get_row(
          $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}epc_entry_meta WHERE meta_key='contestant_email' AND meta_value='%s' AND entry_id=%d",
           $email,
           $entry->entry_id
          )
        );

        if ( !empty( $result ) ) {
          break;
        }

      }
    }

  }

  return !empty( $result ) ? true : false;
}

/**
 * Get vote
 *
 * @param  int $vote_id
 * @return array
 */
function epc_get_vote( $vote_id ) {
  global $wpdb;

  $results = $wpdb->get_row(
    $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}epc_votes WHERE vote_id=%d", (int) $vote_id)
  );

  return $results;
}

/**
 * Get entry votes
 *
 * @param  int $entry_id
 * @return array
 */
function epc_get_entry_votes( $entry_id ) {
  global $wpdb;

  $results = $wpdb->get_results(
    $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}epc_votes WHERE entry_id=%d", (int) $entry_id)
  );

  return $results;
}

/**
 * Get Vote by token
 *
 * @param  string $token
 * @return object
 */
function epc_get_vote_by_token( $token ) {
  global $wpdb;

  $results = $wpdb->get_row(
    $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}epc_votes WHERE token=%d", esc_attr( $token ) )
  );

  return $results;
}

/**
 * Get current entry id
 * @return int|null
 */
function epc_get_current_entry_id() {
  return get_query_var( 'entry_id' );
}

/**
 * Is entry terms required
 *
 * @return boolean
 */
function epc_is_entry_terms_required() {
  global $epc_options;

  $required = (!empty( $epc_options['entry_require_terms'] ) ? true : false );

  return apply_filters( 'epc_entry_require_terms', $required );
}

/**
 * Get Entry url
 *
 * @param  int $entry_id
 * @param  int $contest_id
 * @return string
 */
function epc_get_entry_url( $entry_id, $contest_id = null ) {
  if ( empty( $contest_id ) ) {
    $contest_id = get_the_ID();
  }

  $url = '';

  if ( epc_permalinks_is_enabled() ) {
    $url = get_permalink( $contest_id ) . "entry/" . $entry_id . '/';
  } else {
    $url = add_query_arg( 'entry_id', $entry_id, get_permalink( $contest_id ) );
  }

  return $url;
}

/**
 * Get entry image
 *
 * @todo Should probably accept the entry_id only as well.
 *
 * @param  object   $entry
 * @param  string   $size
 * @return string
 */
function epc_get_entry_image( $entry, $size = 'epc_thumbnail' ) {
  return wp_get_attachment_image( $entry->media_id, $size );
}

/**
 * Get entry image url
 *
 * @todo Should probably accept the entry_id only as well.
 *
 * @param  object $entry
 * @param  string $size
 * @return string
 */
function epc_get_entry_image_url( $entry, $size = 'epc_thumbnail' ) {
  return wp_get_attachment_image_url( $entry->media_id, $size );
}

/**
 * Append entry
 */
function epc_append_entry() {
  global $post;

  $entry_id = get_query_var( 'entry_id' );

  $entry = epc_get_contest_entry( $entry_id );

  $content = '';

  if ( empty( $entry ) ) {
    $content .= '<p>' . __( 'This entry does not exist.', EPC_TEXT_DOMAIN ) . '</p>';

  } else {

    if ( epc_is_lightbox_enabled() ) {
      $content .= '<a href="' . wp_get_attachment_image_url( $entry->media_id, 'large' ) . '" title="' . esc_attr( $entry->name ) . '" class="epc-lightbox">';
        $content .= wp_get_attachment_image( $entry->media_id, 'large' );
      $content .= '</a>';
    } else {
      $content .= wp_get_attachment_image( $entry->media_id, 'large' );
    }

    $content .= '<p class="epc-entry-description">' . $entry->description . '</p>';

    $content .= '<div class="epc-entry-meta">';
      $content .= '<div class="epc-entry-date">'. sprintf( __( "Posted: %s ago", EPC_TEXT_DOMAIN ), human_time_diff( current_time('U', strtotime( $entry->date ) ) ) ) . '</div>';
      $content .= '<div class="epc-contestant-name"><span> ' . __('Contestant:', EPC_TEXT_DOMAIN ) . ' ' . epc_get_entry_contestant_name( $entry_id ) . '</span></div>';
      $content .= '<div class="epc-entry-votes"><span>' . __( 'Votes:', EPC_TEXT_DOMAIN ) . '</span>' . ' ' . dtp_get_total_votes_entry( $entry_id ) . '</div>';
    $content .= '</div>';

    if ( epc_is_contest_active( $post->ID ) ) {
      $content .= do_shortcode( '[epc_vote_form]' );
    } else {
      $content .= '<p>' . __( 'This contest is not active.', EPC_TEXT_DOMAIN ) . '</p>';
    }

  }

  $content .= '<a href="' . get_permalink( $entry->contest_id ) . '">' . __( 'Back to contest page', EPC_TEXT_DOMAIN ) . '</a>';

  return $content;
}

/**
 * Get contestant name
 *
 * @param  int|object $entry
 * @return string
 */
function epc_get_entry_contestant_name( $entry ) {
  if ( is_int( $entry ) ) {
    $entry = epc_get_contest_entry( $entry );
  }

  if ( !empty( $entry->user_id ) ) {
    return epc_get_user_display_name( $entry->user_id );
  }

  $name = epc_get_entry_meta( $entry->entry_id, 'contestant_name' );

  if ( !empty( $name ) ) {
    return $name;
  }

  return;
}

/**
 * Get user display name
 *
 * @param  int $user_id
 * @return string
 */
function epc_get_user_display_name( $user_id ) {
  $userdata = get_userdata( $user_id );

  return $userdata->display_name;
}

/**
 * Get voter
 *
 * @param  object $vote
 * @return string
 */
function epc_get_voter( $vote ) {
  if ( !empty( $vote->user_id ) ) {

    $user_edit_url = get_edit_user_link( $vote->user_id );

    $voter = '<a href="' . $user_edit_url . '">' . epc_get_user_display_name( $vote->user_id ) . '</a>';

  } elseif ( !empty( $vote->email ) ) {
    $voter = $vote->email;

  } else {
    $voter = __( 'Fingerprint only', EPC_TEXT_DOMAIN );

  }

  return $voter;
}

/**
 * Is contest active
 *
 * @param  int  $contest_id
 * @return boolean
 */
function epc_is_contest_active( $contest_id ) {
  return ( get_post_meta( $contest_id, '_epc_is_active', true ) ? true : false );
}

/**
 * Do vote
 *
 * @param  int $entry_id
 * @param  string $status
 * @param  string $email
 *
 * @return int
 */
function epc_do_vote( $entry_id, $status = 'unconfirmed', $email = '' ) {
  global $wpdb, $post;

  if ( !epc_is_contest_active( $post->ID ) )
    return;

  if ( !in_array( $status, array( 'confirmed', 'unconfirmed') ) )
    return;

  $table = $wpdb->prefix . 'epc_votes';

  $data = array(
    'entry_id' => $entry_id,
    'contest_id' => $post->ID,
    'status' => $status,
    'fingerprint' => epc_make_visitor_fingerprint(),
    'user_id' => ( !empty( get_current_user_id() ) ? get_current_user_id() : null ),
    'email' => ( !empty( $email ) ? $email : null ),
    'token' => epc_make_token(),
    'date' => date_i18n( 'Y-m-d H:i:s' )
  );

  $wpdb->insert( $table, $data );

  if ( $status == 'unconfirmed' ) {
    epc_send_vote_confirmation_mail( $entry_id, $wpdb->insert_id );
  }

  if ( !empty( $wpdb->insert_id ) ) {
    do_action( 'epc_after_do_vote', $wpdb->insert_id );
  }

  return $wpdb->insert_id;
}

/**
 * Update vote status
 *
 * @param  int $vote_id
 * @param  string $status
 * @return int
 */
function epc_update_vote_status( $vote_id, $status ) {
  global $wpdb;

  $wpdb->update(
  	$wpdb->prefix . 'epc_votes',
  	array(
  		'status' => $status,
  	),
  	array( 'vote_id' => $vote_id ),
  	array(
  		'%s',
  		'%d'
  	)
  );

  do_action( 'epc_after_update_vote_status', $vote_id, $status );

  return $vote_id;
}

/**
 * Make token
 *
 * @return String
 */
function epc_make_token() {
  return sha1( uniqid() );
}

/**
 * Make vote confirmation url
 *
 * @param  string $token
 * @return string
 */
function epc_vote_confirmation_url( $token ) {
  $confirm_url = add_query_arg( array(
    'epc-confirm-vote' => '1',
    'token' => $token,
  ), get_bloginfo('url') );

  return apply_filters( 'epc_vote_confirmation_url', $confirm_url );
}

/**
 * Send vote confirmation mail
 */
function epc_send_vote_confirmation_mail( $entry_id, $vote_id ) {
  global $epc_options;

  $vote = epc_get_vote( $vote_id );

  $confirm_url = epc_vote_confirmation_url( $vote->token );
  $entry = epc_get_contest_entry( $vote->entry_id );

  $subject = $epc_options['vote_confirmation_email_subject'];
  $text = wpautop( $epc_options['vote_confirmation_email_text'] );

  $text = str_replace(
    array( '{confirmation_url}', '{entry_name}', '{entry_url}' ),
    array( $confirm_url, $entry->name, epc_get_entry_url( $entry->entry_id ) ),
    $text
  );

  $email = '';

  if ( !empty( $vote->email ) ) {
    $email = $vote->email;
  } elseif( !empty( $vote->user_id ) ) {
    $user = get_userdata( (int) $vote->user_id );
    $email = $user->user_email;
  }

  if ( !empty( $email ) && !empty( $subject ) && !empty( $text ) ) {
    epc_mail(
      apply_filters( 'epc_send_vote_confirmation_mail_email', $email ),
      apply_filters( 'epc_send_vote_confirmation_mail_subject', $subject ),
      apply_filters( 'epc_send_vote_confirmation_mail_text', $text )
    );
  }
}

/**
 * Mail
 */
function epc_mail( $to, $subject, $body ) {
  if ( is_wp_error( wp_mail( $to, $subject, $body, array('Content-Type: text/html; charset=UTF-8') ) ) ) {
    return false;
  }
  return true;
}

/**
 * Make visitor fingerprint
 *
 * @return String
 */
function epc_make_visitor_fingerprint() {
  $user_agent = $_SERVER['HTTP_USER_AGENT'];
  $ip_address = epc_get_visitor_ip_address();

  return base64_encode( $user_agent . '|' . $ip_address );
}

/**
 * Get visitor IP Address
 *
 * @return String
 */
function epc_get_visitor_ip_address() {
  $client  = @$_SERVER['HTTP_CLIENT_IP'];
  $forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
  $remote  = $_SERVER['REMOTE_ADDR'];

  if( filter_var( $client, FILTER_VALIDATE_IP ) ) {
    $ip = $client;
  } elseif( filter_var( $forward, FILTER_VALIDATE_IP ) ) {
    $ip = $forward;
  } else {
    $ip = $remote;
  }

  return $ip;
}

/**
 * Is fingerprint unique
 *
 * Checks if user with fingerprint has already voted.
 *
 * @param  int  $entry_id
 * @param  int  $contest_id
 * @param  String  $fingerprint
 * @return boolean
 */
function dtpc_is_fingerprint_unique( $contest_id ) {
  global $wpdb;

  $fingerprint = epc_make_visitor_fingerprint();

  $result = $wpdb->get_row(
    $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}epc_votes WHERE contest_id=%d AND fingerprint=%s", (int) $contest_id, esc_attr( $fingerprint ) )
  );

  if ( empty( $result ) )
    return true;

  return false;
}

/**
 * Get total votes entry
 *
 * @param  int $entry_id
 * @return int
 */
function dtp_get_total_votes_entry( $entry_id ) {
  global $wpdb;

  $vote_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}epc_votes WHERE entry_id=%d AND status='confirmed'", (int) $entry_id ) );

  if ( empty( $vote_count ) ) {
    $vote_count = 0;
  }

  return apply_filters( 'dtp_get_total_votes_entry', $vote_count );
}

/**
 * Readable entry status
 *
 * @param  string $entry_status
 * @return string
 */
function epc_get_entry_status_readable( $entry_status ) {
  $status = '';

  if ( $entry_status == 'approved' ) {
    $status = __( 'Approved', EPC_TEXT_DOMAIN );
  } elseif ( $entry_status == 'not_approved' ) {
    $status = __( 'Not approved', EPC_TEXT_DOMAIN );
  } elseif ( $entry_status == 'waiting_approval' ) {
    $status = __( 'Awaiting approval', EPC_TEXT_DOMAIN );
  }

  return apply_filters( 'epc_get_entry_status_readable', $status );
}

/**
 * Get submit entry page id
 *
 * @return int
 */
function epc_get_submit_entry_page_id() {
  global $epc_options;

  $page_id = null;

  if ( !empty( $epc_options['submit_entry_page_id'] ) )
    $page_id = $epc_options['submit_entry_page_id'];

  return apply_filters( 'epc_get_submit_entry_page_id', $page_id );
}

/**
 * Get submit entry page URL
 *
 * @return string
 */
function epc_get_submit_entry_page_url() {
  $page_id = epc_get_submit_entry_page_id();

  if ( empty( $page_id ) )
    return;

  return apply_filters( 'epc_get_submit_entry_page_url', get_permalink( $page_id ) );
}

/**
 * Get submit entry page URL
 *
 * @return string
 */
function epc_get_submit_entry_page_link( $label = null ) {
  $url = epc_get_submit_entry_page_url();

  if ( empty ( $url ) )
    return;

  if ( empty( $label ) )
    $label = __( 'Enter contest', EPC_TEXT_DOMAIN );

  return '<a href="' . $url . '" class="btn button">' . $label . '</a>';
}

/**
 * Is permalink enabled
 *
 * @return boolean
 */
function epc_permalinks_is_enabled() {
  return ( get_option('permalink_structure') ? true : false );
}

/**
 * Get terms text
 *
 * @return string
 */
function epc_get_terms_text() {
  global $epc_options;

  $text = '';

  if ( !empty( $epc_options['entry_terms_text'] ) )
    $text = $epc_options['entry_terms_text'];

  return apply_filters( 'epc_get_terms_text', $text );
}

/**
 * Get post type slug
 *
 * @return string
 */
function epc_get_post_type_slug() {
  global $epc_options;

  $slug = 'easy-photo-contest';

  if ( !empty( $epc_options['post_type_slug'] ) )
    $slug = $epc_options['post_type_slug'];

  return apply_filters( 'epc_get_post_type_slug', $slug );
}

/**
 * Is vote login required
 *
 * @return boolean
 */
function epc_is_vote_login_required() {
  global $epc_options;

  $login_required = ( !empty( $epc_options['voting_requirements']['login'] ) ? true : false );

  return apply_filters( 'epc_is_vote_login_required', $login_required );
}

/**
 * Can user vote
 *
 * @return boolean
 */
function epc_can_user_vote() {
  $can_vote= true;

  if ( epc_is_vote_login_required() && ! is_user_logged_in() ) {
    $can_vote = false;
  }

  return apply_filters( 'epc_can_user_vote', $can_vote );
}

/**
 * Is vote email required
 *
 * @return boolean
 */
function epc_is_vote_email_required() {
  global $epc_options;

  $email_required = ( !empty( $epc_options['voting_requirements']['email'] ) ? true : false );

  if ( is_user_logged_in() ) {
    $email_required = false;
  }

  return apply_filters( 'epc_is_vote_email_required', $email_required );
}

/**
 * Is vote confirmation required
 *
 * @return boolean
 */
function epc_is_vote_confirmation_required() {
  global $epc_options;

  $confirmation_required = ( !empty( $epc_options['voting_requirements']['confirmation'] ) ? true : false );

  return apply_filters( 'epc_is_vote_confirmation_required', $confirmation_required );
}

/**
 * Is admin entry notification enabled
 *
 * @return boolean
 */
function epc_is_admin_entry_notification_enabled() {
  global $epc_options;

  $noficiation_enabled = ( !empty( $epc_options['send_admin_entry_notification'] ) ? true : false );

  return apply_filters( 'epc_is_admin_entry_notification_enabled', $noficiation_enabled );
}

/**
 * Is entry login required
 *
 * @return boolean
 */
function epc_is_entry_login_required() {
  global $epc_options;

  $login_required = ( !empty( $epc_options['entry_requirements']['login'] ) ? true : false );

  return apply_filters( 'epc_is_entry_login_required', $login_required );
}

/**
 * Is entry approval required
 *
 * @return boolean
 */
function epc_is_entry_approval_required() {
  global $epc_options;

  $approval_required = ( !empty( $epc_options['entry_requirements']['approval'] ) ? true : false );

  return apply_filters( 'epc_is_entry_approval_required', $approval_required );
}

/**
 * Is lightbox enabled
 *
 * @return boolean
 */
function epc_is_lightbox_enabled() {
  global $epc_options;

  $lightbox_enabled = ( !empty( $epc_options['use_lightbox'] ) ? true : false );

  return apply_filters( 'epc_is_lightbox_enabled', $lightbox_enabled );
}

/**
 * Is media library filter enabled
 *
 * @return boolean
 */
function epc_is_media_library_filter_enabled() {
  global $epc_options;

  $filter_enabled = ( !empty( $epc_options['filter_media_library'] ) ? true : false );

  return apply_filters( 'epc_is_media_library_filter_enabled', $filter_enabled );
}

/**
 * Get current entry
 *
 * @return object
 */
function epc_get_current_entry() {
  $entry_id = (int) get_query_var( 'entry_id' );

  if ( empty( $entry_id ) )
    return;

  return epc_get_contest_entry( $entry_id );
}

/**
 * Is entry singular
 *
 * Checks if we are on a entry page. Function name is a little odd.
 *
 * @return boolean
 */
function epc_is_entry_singular() {
  global $wp_query;

  if ( is_singular() && is_main_query() && $wp_query->get( 'post_type' ) == 'easy_photo_contest' )
    return true;

  return false;
}

/**
 * Get entry social sharing
 *
 * @return string
 */
function epc_get_entry_social_sharing( $entry = null ) {
  global $post;

  if ( empty( $entry ) ) {
    $entry = epc_get_current_entry();
  }

  ob_start();

  $medias = array(
    array(
      'name' => 'Twitter',
      'share_url' => 'https://twitter.com/share?text=<TITLE>&amp;url=<URL>',
      'icon' => '<i class="fab fa-twitter"></i>'
    ),
    array(
      'name' => 'Facebook',
      'share_url' => 'https://www.facebook.com/sharer.php?u=<URL>&amp;p[title]=<TITLE>',
      'icon' => '<i class="fab fa-facebook-f"></i>'
    ),
    array(
      'name' => 'Google+',
      'share_url' => 'https://plus.google.com/share?url=<URL>&amp;text=<TITLE>',
      'icon' => '<i class="fab fa-google-plus-g"></i>'
    ),
    array(
      'name' => 'Pinterest',
      'share_url' => 'https://pinterest.com/pin/create/button/?url=<URL>&amp;description=<TITLE>&amp;media=<IMAGE>',
      'icon' => '<i class="fab fa-pinterest-p"></i>'
    ),
    array(
      'name' => 'Reddit',
      'share_url' => 'http://www.reddit.com/submit?url=<URL>&amp;title=<TITLE>',
      'icon' => '<i class="fab fa-reddit-alien"></i>'
    ),
    array(
      'name' => 'WhatsApp',
      'share_url' => 'whatsapp://send?text=<TITLE> - <DESC> - <URL>',
      'icon' => '<i class="fab fa-whatsapp"></i>'
    ),
    array(
      'name' => 'Tumblr',
      'share_url' => 'http://www.tumblr.com/share/link?url=<URL>&amp;name=<TITLE>&amp;description=<DESC>',
      'icon' => '<i class="fab fa-tumblr"></i>'
    ),
    array(
      'name' => 'LinkedIn',
      'share_url' => 'https://www.linkedin.com/shareArticle?mini=true&amp;url=<URL>&amp;title=<TITLE>&amp;summary=<DESC>',
      'icon' => '<i class="fab fa-linkedin-in"></i>'
    ),
    array(
      'name' => 'Email',
      'share_url' => 'mailto:?subject=<TITLE>&amp;body=<TITLE> - <DESC> - <URL>',
      'icon' => '<i class="far fa-envelope"></i>'
    ),
    array(
      'name' => 'URL',
      'share_url' => '<URL>',
      'icon' => '<i class="fas fa-link"></i>',
      'class' => 'epc-copy-clipboard'
    )
  );

  $medias = apply_filters( 'epc_social_media_channels', $medias );

  ?>

  <div class="epc-sharing">

    <div class="epc-social-icons">
      <?php foreach ( $medias as $media ) {

        $share_url = str_replace(
          array( '<TITLE>', '<DESC>', '<URL>', '<IMAGE>' ),
          array( esc_attr( $entry->name ), esc_attr( $entry->description ), epc_get_entry_url( $entry->entry_id ), esc_attr( epc_get_entry_image_url( $entry, 'large' ) ) ),
          $media['share_url']
        );

      ?>
      <a href="<?php echo esc_url( $share_url ); ?>" class="epc-social-icon <?php echo (!empty($media['class'])?esc_attr($media['class']):null); ?> epc-social-icon-<?php echo sanitize_title( $media['name'] ); ?>"><?php echo $media['icon']; ?></a>
      <?php } ?>
    </div>

  </div>

  <?php
  $output = ob_get_contents();

  ob_end_clean();

  return apply_filters( 'epc_get_entry_social_sharing', $output );
}

/**
 * Is contest active check enabled
 *
 * @return boolean
 */
function epc_is_contest_active_check_enabled() {
  global $epc_options;

  $enabled = (!empty($epc_options['check_contest_active_state']) ? true : false );

  return apply_filters( 'epc_is_contest_active_check_enabled', $enabled );
}

/**
 * Set schedule event
 *
 * Enable or disable schedule event.
 *
 * @param string $state
 */
function epc_set_schedule_event( $state = 'on' ) {
  if ( $state == 'on' && ! wp_next_scheduled( 'epc_check_active_contests' ) ) {
    wp_schedule_event( strtotime('00:00:00'), 'daily', 'epc_check_active_contests' );
  } elseif ( $state == 'off' ) {
    wp_clear_scheduled_hook( 'epc_check_active_contests' );
  }
}

/**
 * Get message
 *
 * @param  string $msg
 * @return string
 */
function epc_get_message( $msg ) {
  $html = '';

  switch ( $msg ) {
    case 'vote_confirm':
      $html .= '<strong>' . __( 'Thank you for voting!', EPC_TEXT_DOMAIN ) . '</strong>';
      $html .= '<p>' . __( 'We have send you an email with which you can confirm your vote.', EPC_TEXT_DOMAIN ) . '</p>';
      break;

    case 'vote_confirmed':
      $html .= '<strong>' . __( 'Thank you!', EPC_TEXT_DOMAIN ) . '</strong>';
      $html .= '<p>' . __( 'Your vote has been confirmed.', EPC_TEXT_DOMAIN ) . '</p>';
      break;

    case 'vote_is_confirmed':
      $html .= '<strong>' . __( 'Your vote has been confirmed already!', EPC_TEXT_DOMAIN ) . '</strong>';
      break;

    default:
      break;
  }

  return apply_filters( 'epc_get_message', $html );
}

/**
 * Display vote confirmation message
 */
function epc_display_vote_confirmation_message() {
  $msg = isset( $_GET['epc_msg'] ) ? esc_attr( $_GET['epc_msg'] ) : '';

  if ( !empty( $msg ) ) {
    echo '<div class="epc-msg epc-success-msg">';
    echo epc_get_message( $msg );
    echo '</div>';
  }

}
add_action( 'epc_before_entry_description', 'epc_display_vote_confirmation_message' );
