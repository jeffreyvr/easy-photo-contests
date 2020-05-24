<?php
class EPC_Entry_Form {
  private static $instance = null;
  public $entry_form;

  /**
   * Get Instance
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
    $contests = epc_get_contests_select();

    $this->entry_form = new EPC_Form();

    $this->entry_form->form['login_required'] = epc_is_entry_login_required();
    $this->entry_form->form['callback'] = 'epc_submit_entry';
    $this->entry_form->form['button_txt'] = __( 'Submit entry', EPC_TEXT_DOMAIN );
    //$this->entry_form->form['action'] = get_permalink();

    if ( empty( $contests ) ) {
      $this->entry_form->form['disabled_msg'] = __( 'There are no contests active at this moment.', EPC_TEXT_DOMAIN );
    }

    $this->entry_form->set_field(
      array(
        'name' => 'contest_id',
        'label' => __( 'Contest' ),
        'type' => 'dropdown',
        'options' => $contests,
        'required' => true,
      )
    );

    if ( !is_user_logged_in() ) {
      $this->entry_form->set_field(
        array(
          'name' => 'epc_name',
          'label' => __( 'Your name', EPC_TEXT_DOMAIN ),
          'type' => 'text',
          'required' => true,
        )
      );

      $this->entry_form->set_field(
        array(
          'name' => 'epc_email',
          'label' => __( 'Your email address', EPC_TEXT_DOMAIN ),
          'type' => 'email',
          'required' => true,
        )
      );
    }

    $this->entry_form->set_field(
      array(
        'name' => 'entry_name',
        'label' => __( 'Name of your entry', EPC_TEXT_DOMAIN ),
        'type' => 'text',
        'required' => true,
      )
    );

    $this->entry_form->set_field(
      array(
        'name' => 'entry_desc',
        'label' => __( 'Description of your entry', EPC_TEXT_DOMAIN ),
        'type' => 'textarea',
        'required' => false,
      )
    );

    $this->entry_form->set_field(
      array(
        'name' => 'entry_file',
        'label' => __( 'Select your photo', EPC_TEXT_DOMAIN ),
        'type' => 'file',
        'required' => true,
      )
    );

    if ( epc_is_entry_terms_required() ) {
      $this->entry_form->set_field(
        array(
          'name' => 'terms',
          'label' => epc_get_terms_text(),
          'type' => 'checkbox',
          'required' => true,
        )
      );
    }

    add_shortcode( 'epc_entry_form', array( $this, 'form_output' ) );
    add_action( 'template_redirect', array( $this, 'submit' ) );
  }

  /**
   * Form output
   */
  public function form_output() {
    return apply_filters( 'epc_entry_form', $this->entry_form->form_output() );
  }

  /**
   * Submit
   */
  public function submit() {
    if ( ! isset( $_POST ) || ! isset( $_POST['epc_submit_entry_nonce'] ) )
      return;

    if ( wp_verify_nonce( $_POST['epc_submit_entry_nonce'], 'epc_submit_entry' ) == false )
      return;

    $contest_id   = filter_input( INPUT_POST, 'contest_id', FILTER_SANITIZE_NUMBER_INT );
    $name         = filter_input( INPUT_POST, 'epc_name', FILTER_SANITIZE_STRING );
    $email        = filter_input( INPUT_POST, 'epc_email', FILTER_SANITIZE_EMAIL );
    $entry_name   = filter_input( INPUT_POST, 'entry_name', FILTER_SANITIZE_STRING );
    $entry_desc   = filter_input( INPUT_POST, 'entry_desc', FILTER_SANITIZE_STRING );
    $file         = ( !empty( $_FILES['entry_file'] ) ? $_FILES['entry_file'] : null );
    $terms        = filter_input( INPUT_POST, 'terms', FILTER_SANITIZE_NUMBER_INT );

    if ( !empty( $_POST['hfirstname'] ) || !empty( $_POST['hlastname'] ) ) {
      $this->entry_form->get_errors()->add( 'general', __( 'You appear to be a spambot.', EPC_TEXT_DOMAIN ) );

    } else {

      $login_required = epc_is_entry_login_required();
      $user_id = '';

      if ( $login_required || is_user_logged_in() ) {
        $user_id = get_current_user_id();
      }

      // If login required, name and email should already be known
      // @todo maybe check in future release if they are set
      if ( $login_required && empty( $user_id ) ) {
        if( empty( $name ) ) {
          $this->entry_form->get_errors()->add( 'epc_name', __( 'Please fill in your name.', EPC_TEXT_DOMAIN ) );
        }
      }

      if ( $login_required && empty( $user_id ) ) {
        if ( empty( $email ) ) {
          $this->entry_form->get_errors()->add( 'epc_email', __( 'Please fill in your email address.', EPC_TEXT_DOMAIN ) );
        }  elseif (! is_email( $email ) ) {
          $this->entry_form->get_errors()->add( 'epc_email', __( 'Your email address is invalid.', EPC_TEXT_DOMAIN ) );
        }
      }

      if ( empty( $contest_id ) ) {
        $this->entry_form->get_errors()->add( 'contest_id', __( 'Please select a contest.', EPC_TEXT_DOMAIN ) );
      } else {
        if ( epc_entry_enter_only_once() && epc_contestant_submitted_in_contest( $contest_id, $user_id, $email ) ) {
          $this->entry_form->get_errors()->add( 'contest_id', __( 'You already participate in this contest.', EPC_TEXT_DOMAIN ) );
        }
      }


      if ( epc_is_entry_terms_required() && ( empty( $terms ) || $terms === 0 ) )
        $this->entry_form->get_errors()->add( 'terms', __( 'In order to submit your entry you have to agree to the terms.', EPC_TEXT_DOMAIN ) );

      if ( empty( $entry_name ) )
        $this->entry_form->get_errors()->add( 'entry_name', __( 'Please provide your entry with a name (short description).', EPC_TEXT_DOMAIN ) );

      if ( empty( $file ) || $file['size'] == 0 ) {
        $this->entry_form->get_errors()->add( 'entry_file', __( 'Please select a photo.', EPC_TEXT_DOMAIN ) );

      } else {
        $file_type = wp_check_filetype( $file['name'] );

        if ( !in_array( $file_type['ext'], array( 'jpg', 'jpeg', 'JPG', 'png', 'PNG' ) ) )
          $this->entry_form->get_errors()->add( 'file', __( 'Your photo must be in one of the following formats: jpg (jpeg), png.', EPC_TEXT_DOMAIN ) );
      }
    }

    if ( 1 > count( $this->entry_form->get_errors()->get_error_messages() ) ) {
      $contestant = array();

      if ( !empty( $user_id ) ) {
        $contestant['user_id'] = $user_id;
      } else {
        $contestant = array(
          'name' => $name,
          'epc_email' => $email
        );
      }

      $entry_id = epc_insert_entry(
        $contest_id,
        $contestant,
        array(
          'name' => $entry_name,
          'description' => $entry_desc
        ),
        'entry_file'
      );

      if ( ! empty( $entry_id ) ) {

        do_action( 'epc_after_entry_submit', $entry_id );

        if ( ! epc_is_entry_approval_required() ) {
          wp_safe_redirect( epc_get_entry_url( $entry_id, $contest_id ) );
        } else {
          wp_safe_redirect( add_query_arg( 'epc_msg', 'waiting_approval', get_permalink( $contest_id ) ) );
        }

        exit;

      } else {
        wp_die( 'Something went wrong with your submission.', EPC_TEXT_DOMAIN );
      }

    } else {
      $this->entry_form->get_errors()->add( 'general', __( 'Something went wrong with submitting your entry. Please check the form for validation messages.', EPC_TEXT_DOMAIN ) );
    }

  }

}
