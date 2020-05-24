<?php
class EPC_Admin_Settings {
  private static $instance = null;

  /**
   * Instance
   */
  public static function get_instance() {
    if ( null == self::$instance ) {
      self::$instance = new self;
    }

    return self::$instance;
  }

  /**
   * Construct
   */
  private function __construct() {
    add_action( 'admin_init', array( $this, 'initialize_plugin_settings' ) );
    add_action( 'admin_menu', array( $this, 'add_settings_menu_page' ) );
    add_action( 'admin_notices', array( $this, 'admin_notices' ) );
  }

  /**
   * Admin notices
   */
  public function admin_notices() {
    if ( isset( $_GET['page'] ) && $_GET['page'] == 'epc_settings' ) {
      $is_welcomed = get_option('epc_welcomed' );
      if ( $is_welcomed == '0' ) {
        ?>
        <div class="epc-msg">
          <strong class="epc-msg-heading"><?php _e( 'Thank you for using this plugin!', EPC_TEXT_DOMAIN ); ?></strong>
          <p><?php printf(
            __( 'If you need any help configuring this plugin, please check the <a href="%s">documenation</a> or <a href="%s">email us</a>.', EPC_TEXT_DOMAIN ),
            'https://www.doubletakepigeon.com/easy-photo-contest/docs',
            'https://www.doubletakepigeon.com/support'
          ); ?></p>
          <p><strong>- Double Take Pigeon</strong></p>
        </div>
        <?php
        update_option( 'epc_welcomed', '1' ); // seen
      }
    }
  }

  /**
   * Add settings menu page
   */
  public function add_settings_menu_page() {
    add_submenu_page(
      'edit.php?post_type=easy_photo_contest',
      __( 'Photo Contest Settings', EPC_TEXT_DOMAIN ),
      __( 'Settings', EPC_TEXT_DOMAIN ),
      'manage_options',
      'epc_settings',
      array( $this, 'settings_display' )
    );
  }

  /**
   * Sections
   *
   * @return array
   */
  public function sections() {
    $sections = array( 'general', 'voting', 'entry', 'email' );

    return apply_filters( 'epc_settings_sections', $sections );
  }

  /**
   * Init plugin settings
   */
  public function initialize_plugin_settings() {
    $sections = $this->sections();
    $settings = $this->settings();

    foreach ( $this->sections() as $section ) {
      add_settings_section(
        'epc_settings_' . $section,
        __return_null(),
        '__return_false',
        'epc_settings_' . $section
      );
    }

    $active_section = isset( $_GET['tab'] ) ? esc_attr( $_GET['tab'] ) : 'general';

    if ( empty( $settings[ $active_section ] ) )
      return;

    foreach ( $settings[ $active_section ] as $option ) {

        add_settings_field(
          'epc_settings[' . $option['id'] . ']',
          $option['name'],
          array( $this, 'render_' . $option['type'] ),
          'epc_settings_' . $active_section,
          'epc_settings_' . $active_section,
          array(
            'id' => $option['id'],
            'desc' => $option['desc'],
            'name' => $option['name'],
            'section' => 'general',
            'size' => isset( $option['size'] ) ? $option['size'] : null,
            'options' => isset( $option['options'] ) ? $option['options'] : '',
            'input_class' => isset( $option['input_class'] ) ? $option['input_class'] : ''
          )
      );

    }

    register_setting( 'epc_settings', 'epc_settings', array( $this, 'settings_callback' ) );
  }

  /**
   * Settings callback
   */
  public function settings_callback( $input ) {
    global $epc_options; // get current settings

    parse_str( $_POST['_wp_http_referer'], $referrer );

    $input = $input ? $input : array();
    $tab   = isset( $referrer['tab'] ) ? $referrer['tab'] : 'general';

    $all_settings = $this->settings();
    $tab_settings = $all_settings[ $tab ];

    foreach ( $tab_settings as $setting ) {

      if ( empty( $input[ $setting['id'] ] ) ) { // setting has no value anymore, so we unset it

        unset( $epc_options[ $setting['id'] ] );

        // Custom callback for disable cron
        if ( $setting['id'] == 'check_contest_active_state' ) {
          epc_set_schedule_event( 'off' );
        }

      } else {

        // Here follows some data filtering

        if ( $setting['type'] == 'rich_editor' ) {
          $epc_options[ $setting['id'] ] = wp_filter_post_kses( $input[ $setting['id'] ] );

        } elseif ( in_array( $setting['type'], array( 'checkbox', 'multi_checkbox' ) ) ) {

          if ( is_array( $input[ $setting['id'] ] ) ) {
            $checked_array = array();

            foreach ( $input[ $setting['id'] ] as $key => $value ) {
              $checked_array[ $key ] = filter_var( $value, FILTER_SANITIZE_NUMBER_INT );
            }

            $epc_options[ $setting['id'] ] = $checked_array;

          } else {
            $epc_options[ $setting['id'] ] = filter_var( $input[ $setting['id'] ], FILTER_SANITIZE_NUMBER_INT );
          }

        } else {
          $epc_options[ $setting['id'] ] = filter_var( $input[ $setting['id'] ], FILTER_SANITIZE_STRING );

        }

        // Custom callback for enable cron
        if ( $setting['id'] == 'check_contest_active_state' ) {
          epc_set_schedule_event( 'on' );
        }

      }

    }

    return $epc_options;
  }

  /**
   * Settings dislay
   */
  function settings_display() {

    $active_tab = isset( $_GET['tab'] ) ? esc_attr( $_GET['tab'] ) : 'general';

    ?>
    <div class="wrap">

      <h2><?php _e( 'Photo Contest Settings', EPC_TEXT_DOMAIN ); ?></h2>

      <div class="nav-tab-wrapper">
        <a href="<?php echo add_query_arg( 'tab', 'general' ); ?>" class="nav-tab <?php echo ( $active_tab == 'general' ? 'nav-tab-active' : '' ); ?>"><?php _e( 'General', EPC_TEXT_DOMAIN ); ?></a>
        <a href="<?php echo add_query_arg( 'tab', 'voting' ); ?>" class="nav-tab <?php echo ( $active_tab == 'voting' ? 'nav-tab-active' : '' ); ?>"><?php _e( 'Voting', EPC_TEXT_DOMAIN ); ?></a>
        <a href="<?php echo add_query_arg( 'tab', 'entry' ); ?>" class="nav-tab <?php echo ( $active_tab == 'entry' ? 'nav-tab-active' : '' ); ?>"><?php _e( 'Entry', EPC_TEXT_DOMAIN ); ?></a>
        <a href="<?php echo add_query_arg( 'tab', 'email' ); ?>" class="nav-tab <?php echo ( $active_tab == 'email' ? 'nav-tab-active' : '' ); ?>"><?php _e( 'Email', EPC_TEXT_DOMAIN ); ?></a>
      </div>

      <div class="epc-help-nav">
        <a href="https://www.doubletakepigeon.com/support" class="epc-help-link"><?php _e( 'Support', EPC_TEXT_DOMAIN ); ?></a>
        <a href="https://www.doubletakepigeon.com/easy-photo-contest/docs" class="epc-help-link"><?php _e( 'Documentation', EPC_TEXT_DOMAIN ); ?></a>
      </div>

      <div id="tab_container">

        <?php settings_errors(); ?>

        <form method="post" action="options.php">

          <?php
            settings_fields( 'epc_settings' );

            do_settings_sections( 'epc_settings_' . $active_tab );

            submit_button();
          ?>

        </form>

      </div>

    </div><!-- /.wrap -->
  <?php
  }

  /**
   * Byte options
   *
   * @return array
   */
  public function byte_options() {
    $bytes = array( '209715', '419430', '629145', '838860', '1048576', '2097152', '3145728', '4194304', '5242880', '6291456', '7340032', '8388608' );

    $epc_options = array( '' => __( 'Server default', EPC_TEXT_DOMAIN ) );

    foreach ( $bytes as $byte ) {
      $epc_options[$byte] = size_format( $byte );
    }

    return $epc_options;
  }

  /**
   * Page options
   *
   * @return array
   */
  public function page_options() {
    $pages = get_posts( array( 'post_type' => 'page', 'showposts' => -1, 'post_status' => 'publish' ) );

    $epc_options = array( '' => __( 'Select a page', EPC_TEXT_DOMAIN ) );

    foreach ( $pages as $page ) {
      $epc_options[$page->ID] = $page->post_title;
    }

    return $epc_options;
  }

  /**
   * Settings
   */
  public function settings() {
    $settings = array(
      /* General */
      'general' => apply_filters( 'epc_settings_general',
        array(
          array(
            'id' => 'post_type_slug',
            'name' => __( 'Post Type Slug', EPC_TEXT_DOMAIN ),
            'desc' => __( 'When using permalinks use the following slug in the URL. Default: easy-photo-contest. You might have to update your permalink settings after changing this.', EPC_TEXT_DOMAIN ),
            'type' => 'text'
          ),
          array(
            'id' => 'max_file_size',
            'name' => __( 'Maximum file size', EPC_TEXT_DOMAIN ),
            'desc' => sprintf( __( 'The maximum upload file size of a photo. Your server limit is %s.', EPC_TEXT_DOMAIN ), size_format( wp_max_upload_size() ) ),
            'type' => 'dropdown',
            'options' => $this->byte_options()
          ),
          array(
            'id' => 'use_lightbox',
            'name' => __( 'Use lightbox', EPC_TEXT_DOMAIN ),
            'desc' => sprintf( __( 'Will load <a href="%s">SimpleLightbox</a> (by Andre Rinas) script to enable image enlargement.', EPC_TEXT_DOMAIN ), 'https://github.com/andreknieriem/simplelightbox' ),
            'type' => 'checkbox'
          ),
          array(
            'id' => 'filter_media_library',
            'name' => __( 'Filter media library', EPC_TEXT_DOMAIN ),
            'desc' => __( 'When enabled the contest media will not show up in the admin media library.', EPC_TEXT_DOMAIN ),
            'type' => 'checkbox'
          ),
          array(
            'id' => 'entries_per_page',
            'name' => __( 'Entries per page', EPC_TEXT_DOMAIN ),
            'desc' => __( 'The amount of entries you would like to show per page. Default is 12.', EPC_TEXT_DOMAIN ),
            'type' => 'number'
          ),
          array(
            'id' => 'columns_per_row',
            'name' => __( 'Columns per row', EPC_TEXT_DOMAIN ),
            'desc' => __( 'The amount of columns you would like to show per row. Default is 3.', EPC_TEXT_DOMAIN ),
            'type' => 'dropdown',
            'options' => array( 3 => '3', 4 => '4', 6 => '6' )
          ),
          array(
            'id' => 'check_contest_active_state',
            'name' => __( 'Check contest active state', EPC_TEXT_DOMAIN ),
            'desc' => __( 'When enabled you will be able to set an start en end date for your contest that will be automatically checked daily.', EPC_TEXT_DOMAIN ),
            'type' => 'checkbox'
          )
        )
      ),
      /* Voting */
      'voting' => apply_filters( 'epc_settings_voting',
        array(
          array(
            'id' => 'voting_requirements',
            'name' => __( 'Voting requirements', EPC_TEXT_DOMAIN ),
            'desc' => __( 'With these options you specify requirements that the voter needs to fulfill.', EPC_TEXT_DOMAIN ),
            'type' => 'multi_checkbox',
            'options' => array(
              'login' => __( 'Voter must be logged in to vote.', EPC_TEXT_DOMAIN ),
              'email' => __( 'Voter needs to leave an email address to vote.', EPC_TEXT_DOMAIN ),
              'confirmation' => __( 'Voter needs to confirm the vote via email.', EPC_TEXT_DOMAIN )
            )
          )
        )
      ),
      /* Entry */
      'entry' => apply_filters( 'epc_settings_entry',
        array(
          array(
            'id' => 'entry_requirements',
            'name' => __( 'Entry requirements', EPC_TEXT_DOMAIN ),
            'desc' => __( 'With these options you specify requirements that the contestant needs to fulfill.', EPC_TEXT_DOMAIN ),
            'type' => 'multi_checkbox',
            'options' => array(
              'login' => __( 'The contestant must be logged in to submit an entry.', EPC_TEXT_DOMAIN ),
              'enter_once' => __( 'The contestant can only submit one photo per contest.', EPC_TEXT_DOMAIN ),
              'approval' => __( 'The entry needs to approved before it becomes visible.', EPC_TEXT_DOMAIN ),
            )
          ),
          array(
            'id' => 'submit_entry_page_id',
            'name' => __( 'Submit entry page', EPC_TEXT_DOMAIN ),
            'desc' => __( 'The page from where entries can be submitted.', EPC_TEXT_DOMAIN ),
            'type' => 'dropdown',
            'options' => $this->page_options(),
            'input_class' => 'epc-chosen-select',
          ),
          array(
            'id' => 'entry_social_media_buttons',
            'name' => __( 'Social media buttons', EPC_TEXT_DOMAIN ),
            'desc' => __( 'Check to show social media sharing buttons on entry pages.', EPC_TEXT_DOMAIN ),
            'type' => 'checkbox'
          ),
          array(
            'id' => 'entry_require_terms',
            'name' => __( 'Agree to terms', EPC_TEXT_DOMAIN ),
            'desc' => __( 'Check to have contestants agree to the terms.', EPC_TEXT_DOMAIN ),
            'type' => 'checkbox'
          ),
          array(
            'id' => 'entry_terms_text',
            'name' => __( 'Terms text', EPC_TEXT_DOMAIN ),
            'desc' => __( 'If Agree to Terms is checked, enter the agreement terms here.', EPC_TEXT_DOMAIN ),
            'type' => 'rich_editor'
          )
        )
      ),
      /* Email */
      'email' => apply_filters( 'epc_settings_email',
        array(
          array(
            'id' => 'vote_confirmation_email_subject',
            'name' => __( 'Vote confirmation subject', EPC_TEXT_DOMAIN ),
            'desc' => __( 'Subject of the voting confirmation email.', EPC_TEXT_DOMAIN ),
            'type' => 'text'
          ),
          array(
            'id' => 'vote_confirmation_email_text',
            'name' => __( 'Vote confirmation text', EPC_TEXT_DOMAIN ),
            'desc' => __( 'Text of the voting confirmation email. HTML is accepted. Available tags:', EPC_TEXT_DOMAIN ) . ' {confirmation_url}, {entry_url}, {entry_name}',
            'type' => 'rich_editor'
          ),
          array(
            'id' => 'send_entry_approval_notification',
            'name' => __( 'Entry approval notification', EPC_TEXT_DOMAIN ),
            'desc' => __( 'Send a notification to the contestants after approving their entry.', EPC_TEXT_DOMAIN ),
            'type' => 'checkbox'
          ),
          array(
            'id' => 'entry_approval_notification_email_subject',
            'name' => __( 'Entry approval subject', EPC_TEXT_DOMAIN ),
            'desc' => __( 'Subject of the voting confirmation email.', EPC_TEXT_DOMAIN ),
            'type' => 'text'
          ),
          array(
            'id' => 'entry_approval_notification_email_text',
            'name' => __( 'Entry approval text', EPC_TEXT_DOMAIN ),
            'desc' => __( 'Text of the voting confirmation email. HTML is accepted. Available tags:', EPC_TEXT_DOMAIN ) . ' {entry_url}, {entry_name}',
            'type' => 'rich_editor'
          ),
          array(
            'id' => 'send_admin_entry_notification',
            'name' => __( 'Admin entry notification', EPC_TEXT_DOMAIN ),
            'desc' => __( 'Send a notification to the admin when a new entry has been submitted.', EPC_TEXT_DOMAIN ),
            'type' => 'checkbox'
          )
        )
      )
    );

    return $settings;
  }

  /**
   * Render text
   *
   * Renders text option.
   *
   * @param  array $args
   */
  public function render_text( $args ) {
    global $epc_options;

    $value = isset( $epc_options[ $args['id'] ] ) ? $epc_options[ $args['id'] ] : '';

    $html = '<input type="text" id="epc_settings[' . $args['id'] . ']" name="epc_settings[' . $args['id'] . ']" value="' . $value . '" />';
    $html .= '<label for="epc_settings[' . $args['id'] . ']">'  . $args['desc'] . '</label>';

    echo $html;
  }

  /**
   * Render number
   *
   * Renders number option.
   *
   * @param  array $args
   */
  public function render_number( $args ) {
    global $epc_options;

    $value = isset( $epc_options[ $args['id'] ] ) ? $epc_options[ $args['id'] ] : '';

    $html = '<input type="number" id="epc_settings[' . $args['id'] . ']" name="epc_settings[' . $args['id'] . ']" value="' . $value . '" />';
    $html .= '<label for="epc_settings[' . $args['id'] . ']">'  . $args['desc'] . '</label>';

    echo $html;
  }

  /**
   * Render checkbox
   *
   * Renders checkbox.
   *
   * @param  array $args
   */
  public function render_checkbox( $args ) {
    global $epc_options;

    $checked = isset( $epc_options[ $args['id'] ] ) ? checked(1, $epc_options[ $args['id'] ], false ) : '';

    $html = '<input type="checkbox" id="epc_settings[' . $args['id'] . ']" name="epc_settings[' . $args['id'] . ']" value="1" ' . $checked . ' />';
    $html .= '<label for="epc_settings[' . $args['id'] . ']">'  . $args['desc'] . '</label>';

    echo $html;
  }

  /**
   * Render multi checkbox
   *
   * Renders checkboxes.
   *
   * @param  array $args
   */
  public function render_multi_checkbox( $args ) {
    global $epc_options;

    $html = '';

  	if ( ! empty( $args['options'] ) ) {

      foreach( $args['options'] as $key => $option ) {

        if ( isset( $epc_options[ $args['id'] ][ $key ] ) ) {
          $enabled = '1';
        } else {
          $enabled = null;
        }

  			$html .= '<input name="epc_settings[' . $args['id'] . '][' .  $key . ']" id="epc_settings[' . $args['id'] . '][' . $key . ']" type="checkbox" value="1" ' . checked(1, $enabled, false) . '/>&nbsp;';
  			$html .= '<label for="epc_settings[' . $args['id'] . '][' . $key . ']">' . wp_kses_post( $option ) . '</label><br/>';

      }

  		$html .= '<p class="description">' . $args['desc'] . '</p>';
  	}

    echo $html;
  }

  /**
   * Render dropdown
   *
   * @param  array $args
   */
  public function render_dropdown( $args ) {
    global $epc_options;

    $html = '';

    if ( ! empty( $args['options'] ) ) {

      $class = isset( $args['input_class'] ) ? $args['input_class'] : '';

      $html .= '<select name="epc_settings[' . $args['id'] . ']" id="epc_settings[' . $args['id'] . ']" class="' . $class . '">';

  		foreach( $args['options'] as $key => $option ) {
        $selected = isset( $epc_options[ $args['id'] ] ) ? selected( $epc_options[ $args['id'] ], $key, false ) : '';

        $html .= '<option value="' . $key . '" ' . $selected . '>' . $option . '</option>';
      }

      $html .= '</select>';

      $html .= '<p class="description">' . $args['desc'] . '</p>';

    }

    echo $html;
  }

  /**
   * Render rich editor
   *
   * @param  array $args
   */
  public function render_rich_editor( $args ) {
    global $epc_options;

    $editor_args = array(
      'textarea_name' => 'epc_settings[' . $args['id'] . ']',
      'teeny' => isset( $args['teeny'] ) ? $args['teeny'] : true,
      'media_buttons' => isset( $args['media_buttons'] ) ? $args['media_buttons'] : false,
      'textarea_rows' => isset( $args['rows'] ) ? $args['rows'] : 5
    );

    $value = isset( $epc_options[ $args['id'] ] ) ? stripslashes( $epc_options[ $args['id'] ] ) : '';

    wp_editor( $value, 'epc_settings_' . $args['id'], $editor_args );

    echo '<p class="description">' . $args['desc'] . '</p>';
  }

}
