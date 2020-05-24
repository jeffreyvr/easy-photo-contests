<?php

/**
 * Social sharing
 *
 * @param  string $output
 * @return string
 */
function epc_entry_social_sharing( $output ) {
  global $epc_options;

  if ( empty( $epc_options['entry_social_media_buttons'] ) )
    return;

  return $output;
}
add_filter( 'epc_get_entry_social_sharing', 'epc_entry_social_sharing', 1 );

/**
 * Query vars
 *
 * @param  array $query_vars
 * @return array
 */
function epc_query_vars( $query_vars ){
  $query_vars[] = 'entry_id';
  $query_vars[] = 'contest_id';
  $query_vars[] = 'entries_order';
  $query_vars[] = 'entries_orderby';
  return $query_vars;
}
add_filter( 'query_vars', 'epc_query_vars' );

/**
 * Append entries to entry post
 *
 * @todo Should be a view and too.
 */
function epc_append_entries_to_entry_post( $content ) {
  global $post, $epc_options;

  if ( ! is_singular() )
    return $content;

  if ( $post->post_type != 'easy_photo_contest' || ! empty( epc_get_current_entry_id() ) )
    return $content;

  $content = $content;

  if( epc_is_contest_active( $post->ID ) ) {

    $submit_entry_link = epc_get_submit_entry_page_link();

    if ( !empty( $submit_entry_link ) ) {
      $content .= $submit_entry_link;
    }

  } else {

    $content .= '<div class="epc-msg">' . __( 'This contest is not active at this moment.', EPC_TEXT_DOMAIN ) . '</div>';

  }

  if ( isset( $_GET['epc_msg'] ) && $_GET['epc_msg'] == 'waiting_approval' ) {
    $content .= '<div class="epc-msg epc-success-msg">' . __( 'Your entry is submitted and awaiting approval from the moderators.', EPC_TEXT_DOMAIN ) . '</div>';
  }

  $order = ( !empty( get_query_var( 'entries_order' ) ) ? get_query_var( 'entries_order' ) : 'desc' );
  $orderby = ( !empty( get_query_var( 'entries_orderby' ) ) ? get_query_var( 'entries_orderby' ) : 'date' );

  $entries = epc_get_contest_entries(
    $post->ID,
    array(
      'order' => $order,
      'orderby' => $orderby
    )
  );

  if ( ! empty( $entries ) ) {

    $content .= '<h2 class="epc-heading">' . __( 'Entries', EPC_TEXT_DOMAIN ) . '</h2>';

    $content .= '<div class="epc-order-options">';
      $content .= '<div><span>' . __( 'Order by:', EPC_TEXT_DOMAIN ) . '</span>';
      $content .= ' <a href="' . get_permalink() . '" class="' . ( empty(get_query_var('entries_orderby')) ? 'epc-current-order':'') . '">' . __( 'Date', EPC_TEXT_DOMAIN ) . '</a>';
      $content .= ' <a href="' . add_query_arg( 'entries_orderby', 'votes', get_permalink() ) . '" class="' . ( get_query_var('entries_orderby')=='votes' ? 'epc-current-order':'') . '">' . __( 'Votes', EPC_TEXT_DOMAIN ) . '</a></div>';
      $content .= '<div><span>' . __( 'Order:', EPC_TEXT_DOMAIN ) . '</span>';
      $content .= ' <a href="' . add_query_arg( 'entries_order', 'desc', basename( $_SERVER['REQUEST_URI'] ) ) . '" class="' . ( get_query_var('entries_order')=='desc'||empty(get_query_var('entries_order'))?'epc-current-order':'') . '">' . __( 'Descending', EPC_TEXT_DOMAIN ) . '</a>';
      $content .= ' <a href="' . add_query_arg( 'entries_order', 'asc', basename( $_SERVER['REQUEST_URI'] ) ) . '" class="' . ( get_query_var('entries_order')=='asc'?'epc-current-order':'') . '">' . __( 'Ascending', EPC_TEXT_DOMAIN ) . '</a></div>';
    $content .= '</div>';

    $columns = ( !empty( $epc_options['columns_per_row'] ) ? $epc_options['columns_per_row'] : 3 );

    $content .= '<div class="epc-entry-wrapper grid-container epc-'.esc_attr( $columns ).'-cols">';

    ob_start();

    foreach( $entries as $entry ) {
      include EPC_PATH . 'views/entry-item.php';
    }

    $content .= ob_get_clean();

    $content .= '</div>';

    if ( count( $entries ) == epc_get_entries_per_page() ) {
      $content .= '<button class="epc-load-more-entries button" data-contest-id="' . esc_attr( $post->ID ) . '" data-order="' . esc_attr( $order ) . '" data-orderby="' . esc_attr( $orderby ) . '">Load more</button>';
    }

  } else {

    $content .= '<div class="epc-entry-msg">';
      $content .= __( 'There are no entries yet, be the first!', EPC_TEXT_DOMAIN );
    $content .= '</div>';

  }

  return apply_filters( 'epc_entries_overview', $content );
}
add_filter( 'the_content', 'epc_append_entries_to_entry_post' );

/**
 * Override views
 *
 * Checks if views are available in theme.
 *
 * @param  string $original_view
 * @return string
 */
function epc_override_views( $original_view ) {
  if ( strpos( $original_view, 'entry.php' ) !== false ) {
    $new_view = locate_template( 'epc/entry.php' );

  } elseif ( strpos( $original_view, 'entry-item.php' ) !== false ) {
    $new_view = locate_template( 'epc/entry-item.php' );

  } elseif ( strpos( $original_view, 'entry-form.php' ) !== false ) {
    $new_view = locate_template( 'epc/entry-form.php' );

  } elseif ( strpos( $original_view, 'voting-form.php' ) !== false ) {
    $new_view = locate_template( 'epc/voting-form.php' );

  }

  if ( !empty( $new_view ) ) {
    return $new_view;
  }

  return $original_view;
}
add_filter( 'epc_entry_item_view', 'epc_override_views' );
add_filter( 'epc_entry_view', 'epc_override_views' );
add_filter( 'epc_voting_form_view', 'epc_override_views' );
add_filter( 'epc_entry_form_view', 'epc_override_views' );

/**
 * Filter media library
 *
 * This function will filter all contest entry media.
 *
 * @param object $query
 * @return object
 */
function epc_filter_media_library( $query ) {
  if( ! is_admin() && ! in_array( $query->get( 'post_type' ), array( 'attachment' ) ) )
    return $query;

  $query->set( 'meta_query', array(
    'relation' => 'AND',
    array(
      'key' => '_epc_media',
      'compare' => 'NOT EXISTS'
    )
  ) );

  return $query;
}
if ( epc_is_media_library_filter_enabled() ) {
  add_filter( 'pre_get_posts', 'epc_filter_media_library' );
}
