<?php
class EPC_Admin_Meta_Box {
  private static $instance = null;

  public static function get_instance() {
    if ( null == self::$instance ) {
      self::$instance = new self;
    }

    return self::$instance;
  }

  private function __construct() {
    add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
    add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );
  }

  /**
   * Meta box.
   */
  public function register_meta_box() {
    add_meta_box( 'epc-meta-box', __( 'Contest settings', EPC_TEXT_DOMAIN ), array( $this, 'display_meta_box' ), 'easy_photo_contest' );
  }

  public function display_meta_box( $post ) {
    $start_date         = get_post_meta( $post->ID, "_epc_start_date", true );
    $end_date           = get_post_meta( $post->ID, "_epc_end_date", true );
    $active             = get_post_meta( $post->ID, "_epc_is_active", true );
    ?>
    <table class="form-table">
      <tbody>
      <tr>
        <td>
          <div>
            <label for="dtpc-pc-status"><strong><?php _e( 'Active', EPC_TEXT_DOMAIN ); ?></strong></label><br>
            <p>
              <input type="radio" name="_epc_is_active" value="1" <?php checked( $active, "1", true ); ?>> <?php _e( 'Yes', EPC_TEXT_DOMAIN ); ?>
              <input type="radio" name="_epc_is_active" value="0" <?php checked( $active, ( $active == "0" ? "0" : null ), true ); ?>> <?php _e( 'No', EPC_TEXT_DOMAIN ); ?>
            </p>
          </div>
        </td>
      </tr>
      <?php if ( epc_is_contest_active_check_enabled() ) : ?>
      <tr>
        <td>
          <div>
            <label for="dtpc-pc-start-date"><strong><?php _e( 'Start date', EPC_TEXT_DOMAIN ); ?></strong></label><br>
            <p><input name="_epc_start_date" id="dtpc-pc-start-date" type="date" value="<?php echo (! empty( $start_date ) ? $start_date : current_time('Y-m-d') ); ?>"></p>
            <i><?php _e( 'The contest will be automatically set active on this date.', EPC_TEXT_DOMAIN ); ?></i>
          </div>
        </td>
      </tr>
      <tr>
        <td>
          <div>
            <label for="dtpc-pc-end-date"><strong><?php _e( 'End date', EPC_TEXT_DOMAIN ); ?></strong></label><br>
            <p><input name="_epc_end_date" id="dtpc-pc-end-date" type="date" value="<?php echo (! empty( $end_date ) ? $end_date : null ); ?>"></p>
            <i><?php _e( 'The contest will be automatically set not active on this date.', EPC_TEXT_DOMAIN ); ?></i>
          </div>
        </td>
      </tr>
      <?php endif; ?>
      <?php if ( isset ( $post->status ) && $post->status !== 'auto-draft' ) : ?>
        <tr>
          <td>
            <a href="<?php echo admin_url( "edit.php?post_type=easy_photo_contest&page=easy_photo_contest_entries&contest_id=$post->ID" ); ?>"><?php _e( '&raquo; View contest entries', EPC_TEXT_DOMAIN ); ?></a>
          </td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>

    <?php wp_nonce_field( 'epc_save_post_meta_box', 'epc_save_post_meta_box_nonce' ); ?>

    <?php
  }

  /**
   * Save post
   *
   * @param  int $post_id
   * @param  object $post
   * @return int|null
   */
  public function save_post( $post_id, $post ) {
    if ( !isset( $_POST['epc_save_post_meta_box_nonce'] ) || !wp_verify_nonce( $_POST['epc_save_post_meta_box_nonce'], 'epc_save_post_meta_box' ) )
      return $post_id;

    if ( ! current_user_can( 'edit_others_posts' ) )
      return $post_id;

    if ( $start_date = filter_input( INPUT_POST, '_epc_start_date', FILTER_SANITIZE_STRING ) ) {
      update_post_meta( $post->ID, '_epc_start_date', date( 'Y-m-d', strtotime( $start_date ) ) );
    }

    if ( $end_date = filter_input( INPUT_POST, '_epc_end_date', FILTER_SANITIZE_STRING ) ) {
      update_post_meta( $post->ID, '_epc_end_date', date( 'Y-m-d', strtotime( $end_date ) ) );
    }

    if ( $active = filter_input( INPUT_POST, '_epc_is_active', FILTER_SANITIZE_NUMBER_INT ) ) {
      update_post_meta( $post->ID, '_epc_is_active', $active );
    } else {
      delete_post_meta( $post->ID, '_epc_is_active' );
    }

    do_action( 'epc_after_contest_save', $post_id );

  }

}
