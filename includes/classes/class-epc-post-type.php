<?php
class EPC_Post_Type {
  private static $instance = null;

  public static function get_instance() {
    if ( null == self::$instance ) {
      self::$instance = new self;
    }

    return self::$instance;
  }

  /**
   * Construct.
   */
  private function __construct() {
    add_action( 'init', array( $this, 'register' ) );

    add_filter( 'manage_easy_photo_contest_posts_columns', array( $this, 'set_custom_edit_easy_photo_contest_columns' ) );
    add_action( 'manage_easy_photo_contest_posts_custom_column' , array( $this, 'custom_easy_photo_contest_column' ), 10, 2 );

    add_filter( 'post_row_actions', array( $this, 'epc_post_row_actions_filter' ), 10, 2 );
  }

  /**
   * Register post type contest.
   */
  public function register() {
    $labels = array(
  		'name'               => _x( 'Photo Contests', 'post type general name', EPC_TEXT_DOMAIN ),
  		'singular_name'      => _x( 'Contest', 'post type singular name', EPC_TEXT_DOMAIN ),
  		'menu_name'          => _x( 'Photo Contests', 'admin menu', EPC_TEXT_DOMAIN ),
  		'name_admin_bar'     => _x( 'Contest', 'add new on admin bar', EPC_TEXT_DOMAIN ),
  		'add_new'            => _x( 'Add New', 'contest', EPC_TEXT_DOMAIN ),
  		'add_new_item'       => __( 'Add New Contest', EPC_TEXT_DOMAIN ),
  		'new_item'           => __( 'New Contest', EPC_TEXT_DOMAIN ),
  		'edit_item'          => __( 'Edit Contest', EPC_TEXT_DOMAIN ),
  		'view_item'          => __( 'View Contest', EPC_TEXT_DOMAIN ),
  		'all_items'          => __( 'All Photo Contests', EPC_TEXT_DOMAIN ),
  		'search_items'       => __( 'Search Photo Contests', EPC_TEXT_DOMAIN ),
  		'not_found'          => __( 'No Photo Contests found.', EPC_TEXT_DOMAIN ),
  		'not_found_in_trash' => __( 'No Photo Contests found in Trash.', EPC_TEXT_DOMAIN )
  	);

  	$args = array(
  		'labels'             => $labels,
      'description'        => __( 'This post type contains the photo contests.', EPC_TEXT_DOMAIN ),
  		'public'             => true,
  		'publicly_queryable' => true,
  		'show_ui'            => true,
  		'show_in_menu'       => true,
  		'query_var'          => true,
  		'rewrite'            => array( 'slug' => epc_get_post_type_slug() ),
  		'capability_type'    => 'post',
  		'has_archive'        => true,
  		'hierarchical'       => false,
  		'menu_position'      => null,
  		'supports'           => array( 'title', 'editor', 'author', 'thumbnail' ),
      'menu_icon'          => 'dashicons-camera',
  	);

  	register_post_type( 'easy_photo_contest', $args );
  }

  /**
   * Post row actions filter
   *
   * @param  array $actions
   * @param  object $post
   * @return array
   */
  function epc_post_row_actions_filter( $actions, $post ) {
    if ( $post->post_type == 'easy_photo_contest' ) {
      $actions['duplicate'] = '<a href="' . epc_get_admin_entries_url( $post->ID ) . '" title="" rel="permalink">' . __( 'Entries', EPC_TEXT_DOMAIN ) . '</a>';
    }
    return $actions;
  }

  function set_custom_edit_easy_photo_contest_columns($columns) {
      $columns['epc_status'] = __( 'Status', EPC_TEXT_DOMAIN );

      return $columns;
  }

  // Add the data to the custom columns for the contest post type:
  function custom_easy_photo_contest_column( $column, $post_id ) {
    switch ( $column ) {
      case 'epc_status' :

      $active = get_post_meta( $post_id, '_epc_is_active', true );
      echo ( $active=='1'?__('Active',EPC_TEXT_DOMAIN) : __( 'Not active', EPC_TEXT_DOMAIN ) );

      break;
    }
  }

}
