<?php
class EPC_Install {
  private $wpdb;
  private $plugin_options;

  public function __construct() {
    global $wpdb;

    $this->wpdb = $wpdb;
    $this->plugin_options = get_option( 'epc_settings' );

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
  }

  public function run() {
    add_option( 'epc_activation_time', time() );

    $charset_collate = $this->wpdb->get_charset_collate();

    self::create_table_entries();
    self::create_table_entry_meta();
    self::create_table_votes();
    self::create_pages();
    self::set_default_options();

    add_option( 'epc_version', EPC_VERSION );

    if ( empty( get_option( 'epc_welcomed' ) ) ) {
      add_option( 'epc_welcomed', '0' );
    }

    flush_rewrite_rules( false );
  }

  /**
   * Create table entries
   */
  public function create_table_entries() {
    $table_name = $this->wpdb->prefix . 'epc_entries';

    if ( !$this->table_exists( $table_name ) ) {
      $sql = "CREATE TABLE $table_name (
      entry_id mediumint(9) NOT NULL AUTO_INCREMENT,
      date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
      name tinytext NOT NULL,
      description text,
      contest_id mediumint(9) NOT NULL,
      media_id mediumint(9),
      user_id mediumint(9),
      status varchar(255),
      UNIQUE KEY entry_id (entry_id)
      ) $charset_collate;";

      dbDelta( $sql );
    }
  }

  /**
   * Create table entry_meta
   */
  public function create_table_entry_meta() {
    $table_name = $this->wpdb->prefix . 'epc_entry_meta';

    if ( !$this->table_exists( $table_name ) ) {
      $sql = "CREATE TABLE $table_name (
      meta_id mediumint(9) NOT NULL AUTO_INCREMENT,
      entry_id mediumint(9) NOT NULL,
      meta_key varchar(255) NOT NULL,
      meta_value text,
      UNIQUE KEY meta_id (meta_id)
      ) $charset_collate;";

      dbDelta( $sql );
    }
  }

  /**
   * Create table votes
   */
  public function create_table_votes() {
    $table_name = $this->wpdb->prefix . 'epc_votes';

    if ( !$this->table_exists( $table_name ) ) {
      $sql = "CREATE TABLE $table_name (
      vote_id mediumint(9) NOT NULL AUTO_INCREMENT,
      contest_id mediumint(9) NOT NULL,
      entry_id mediumint(9) NOT NULL,
      user_id mediumint(9),
      fingerprint tinytext,
      email varchar(255),
      status varchar(255),
      token varchar(255),
      date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
      UNIQUE KEY vote_id (vote_id)
      ) $charset_collate;";

      dbDelta( $sql );
    }
  }

  /**
   * Table exists
   */
  public function table_exists( $table_name ) {
    if ( $this->wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name )
      return true;
  }

  public function create_pages() {
    if ( empty( $this->plugin_options['submit_entry_page_id'] ) ) {
      $submit_entry_page_args = array(
        'post_title'    => __( 'Submit entry', EPC_TEXT_DOMAIN ),
        'post_content'  => '[epc_entry_form]',
        'post_status'   => 'publish',
        'post_type'     => 'page'
      );

      $submit_entry_page_id = wp_insert_post( $submit_entry_page_args );

      if ( !empty( $submit_entry_page_id ) ) {
        $this->plugin_options['submit_entry_page_id'] = $submit_entry_page_id;
        update_option( 'epc_settings', $this->plugin_options );
      }
    }
  }

  /**
   * Default options
   */
  public function set_default_options() {
    if ( empty( $this->plugin_options['post_type_slug'] ) ) {
      $this->plugin_options['post_type_slug'] = 'easy-photo-contest';
    }

    if ( empty( $this->plugin_options['entries_per_page'] ) ) {
      $this->plugin_options['entries_per_page'] = 12;
    }

    if ( empty( $this->plugin_options['columns_per_row'] ) ) {
      $this->plugin_options['columns_per_row'] = 3;
    }

    if ( empty( $this->plugin_options['vote_confirmation_email_subject'] ) ) {
      $this->plugin_options['vote_confirmation_email_subject'] = __ ( 'Confirm your vote', EPC_TEXT_DOMAIN );
    }

    if ( empty( $this->plugin_options['vote_confirmation_email_text'] ) ) {
      $this->plugin_options['vote_confirmation_email_text'] = __ ( 'Please confirm your vote by click this URL: {confirmation_url}', EPC_TEXT_DOMAIN );
    }

    if ( empty( $this->plugin_options['approval_notification_email_subject'] ) ) {
      $this->plugin_options['approval_notification_email_subject'] = __ ( 'Your entry has been approved', EPC_TEXT_DOMAIN );
    }

    if ( empty( $this->plugin_options['entry_approval_notification_email_text'] ) ) {
      $this->plugin_options['entry_approval_notification_email_text'] = __ ( 'Your entry has been approved. You can view at this URL: {entry_url}', EPC_TEXT_DOMAIN );
    }

    update_option( 'epc_settings', $this->plugin_options );
  }

}
