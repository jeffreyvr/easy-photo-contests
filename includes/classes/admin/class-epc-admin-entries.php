<?php
class EPC_Admin_Entries {
  private static $instance = null;
  public $entry_id;

  public static function get_instance() {
    if ( null == self::$instance ) {
      self::$instance = new self;
    }

    return self::$instance;
  }

  private function __construct() {
    if ( isset( $_GET['entry_id'] ) ) {
      $this->entry_id = $_GET['entry_id'];
      $this->entry = epc_get_contest_entry( $this->entry_id );
    }

    add_action( 'admin_init', array( $this, 'edit_entry' ) );
    add_action( 'admin_init', array( $this, 'delete_entry' ) );
    add_action( 'admin_init', array( $this, 'delete_vote' ) );
    add_action( 'admin_init', array( $this, 'bulk_entries' ) );
    add_action( 'admin_menu', array( $this, 'add_settings_menu_page' ) );

    if ( !empty( $this->entry ) ) {
      add_action( 'admin_bar_menu', array( $this, 'add_view_toolbar' ), 999 );
    }
  }

  public function add_settings_menu_page() {
    add_submenu_page( 'edit.php?post_type=easy_photo_contest', __( 'Entries', EPC_TEXT_DOMAIN ), __( 'Entries', EPC_TEXT_DOMAIN ), 'manage_options', 'easy_photo_contest_entries', array( $this, 'page' ) );
  }

  /**
   * Add view toolbar
   * @param  [type] $wp_admin_bar [description]
   * @return [type]               [description]
   */
  function add_view_toolbar( $wp_admin_bar ) {
  	$args = array(
  		'id'    => 'epc_view_entry',
  		'title' => __( 'View entry', EPC_TEXT_DOMAIN ),
  		'href'  => epc_get_entry_url( $this->entry->entry_id, $this->entry->contest_id )
  	);
  	$wp_admin_bar->add_node( $args );
  }

  /**
   * Page
   *
   * Render pages: entries (overview) and entry detail.
   */
  public function page() {

    if ( ! empty( $this->entry ) ) {

      $entry = $this->entry;

      include 'views/entry.php';

    } else {

      if ( isset ($_GET['contest_id'] ) ) {
        $args = array();
        if ( !empty( $_GET['search'] ) ) {
          $args['search'] = esc_attr( $_GET['search'] );
        }
        if ( !empty( $_GET['paging'] ) ) {
          $offset = (int) ( $_GET['paging'] - 1 ) * epc_get_entries_per_page();
          $args['offset'] = $offset;
        }

        $args['status'] = '';

        $entries = epc_get_contest_entries( (int) $_GET['contest_id'], $args );
      }

      include 'views/entries.php';
    }
  }

  /**
   * Edit entry
   */
  public function edit_entry() {
    if ( isset( $_POST['edit_entry'] ) && wp_verify_nonce( $_POST['epc_edit_entry_nonce'], 'epc_edit_entry' ) ) {

      $entry_id = (int) $_POST['entry_id'];

      epc_update_contest_entry( $entry_id, array(
        'name' => filter_input( INPUT_POST, 'entry_name', FILTER_SANITIZE_STRING ),
        'description' => filter_input( INPUT_POST, 'entry_description', FILTER_SANITIZE_STRING ),
        'status' => filter_input( INPUT_POST, 'entry_status', FILTER_SANITIZE_STRING )
      ) );

    }
  }

  /**
   * Delete vote
   */
  public function delete_vote() {
    if ( isset( $_GET['_wpnonce'] ) && ! empty( $_GET['vote_id'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'epc_delete_vote_nonce' ) ) {

      $vote_id  = (int) $_GET['vote_id'];
      $entry_id = (int) $_GET['entry_id'];

      $entry = epc_get_contest_entry( $entry_id );

      epc_delete_vote( $vote_id );

      wp_redirect( epc_get_edit_entry_url( $entry->entry_id ) ); exit;
    }
  }

  /**
   * Delete entry
   */
  public function delete_entry() {
    if ( isset( $_GET['_wpnonce'] ) && ! empty( $_GET['entry_id'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'epc_delete_entry_nonce' ) ) {

      $entry_id = (int) $_GET['entry_id'];

      $entry = epc_get_contest_entry( $entry_id );

      epc_delete_contest_entry( $entry_id );

      wp_redirect( epc_get_admin_entries_url( $entry->contest_id ) ); exit;
    }
  }

  /**
   * Delete entries
   */
  public function bulk_entries() {
    if ( isset( $_GET['epc_bulk_entries_nonce'] ) && wp_verify_nonce( $_GET['epc_bulk_entries_nonce'], 'epc_bulk_entries' ) ) {

      // Delete entries in bulk
      if ( !empty( $_GET['entry_id'] ) && is_array( $_GET['entry_id'] ) && $_GET['action'] != '-1' ) {

        if( $_GET['action'] == 'epc_delete_entries' ) {

          foreach ( $_GET['entry_id'] as $entry_id ) {
            epc_delete_contest_entry( (int) $entry_id );
          }

        } elseif ( $_GET['action'] == 'epc_approve_entries' ) {

          foreach ( $_GET['entry_id'] as $entry_id ) {
            epc_update_contest_entry( (int) $entry_id, array( 'status' => 'approved' ) );
          }

        } elseif ( $_GET['action'] == 'epc_not_approve_entries' ) {

          foreach ( $_GET['entry_id'] as $entry_id ) {
            epc_update_contest_entry( (int) $entry_id, array( 'status' => 'not_approved' ) );
          }

        }

        $redirect_url = add_query_arg( 'contest_id', (int) $_GET['contest_id'], epc_get_admin_entries_url() );

        wp_redirect( $redirect_url ); exit;

      }
    }

  }


}
