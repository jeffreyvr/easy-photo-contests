<?php
class EPC_Vote_Form {
  private static $instance = null;
  public $entry_form;
  public $entry;
  public $entry_id;

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
    $this->entry_id = epc_get_current_entry_id();

    if ( !empty ( $this->entry_id ) ) {
      $this->entry = epc_get_contest_entry( $this->entry_id );
    }

    if ( ! empty( $this->entry ) ) {
      $this->vote_form = new EPC_Form();

      $this->vote_form->form['login_required'] = epc_is_vote_login_required();
      $this->vote_form->form['callback'] = 'epc_submit_vote';
      $this->vote_form->form['action'] = epc_get_entry_url( $this->entry->entry_id, $this->entry->contest_id );
      $this->vote_form->form['button_txt'] = __( 'Submit vote', EPC_TEXT_DOMAIN );
      $this->vote_form->form['form_class'] = $this->vote_form->form['form_class'] . ' epc-vote-form';

      if ( epc_is_vote_email_required() ) {
        $this->vote_form->set_field(
          array(
            'name' => 'epc_email',
            'label' => __( 'Your email address', EPC_TEXT_DOMAIN ),
            'type' => 'email',
            'required' => true,
          )
        );
      }

      add_shortcode( 'epc_vote_form', array( $this, 'form_output' ) );
      add_action( 'template_redirect', array( $this, 'submit' ) );
    }

    add_action( 'template_redirect', array( $this, 'confirm_vote' ) );
  }

  /**
   * Form output
   */
  public function form_output() {
    return apply_filters( 'epc_voting_form', $this->vote_form->form_output() );
  }

  /**
   * Submit
   */
  public function submit() {
    if ( ! isset( $_POST ) || ! isset( $_POST['epc_submit_vote_nonce'] ) )
      return;

    if ( wp_verify_nonce( $_POST['epc_submit_vote_nonce'], 'epc_submit_vote' ) == false )
      return;

    global $post;

    if ( !empty( $_POST['hfirstname'] ) || !empty( $_PSOT['hlastname'] ) ) {
      $this->vote_form->get_errors()->add( 'general', __( 'You appear to be a spambot.', EPC_TEXT_DOMAIN ) );

    } else {

      $email = filter_input( INPUT_POST, 'epc_email', FILTER_SANITIZE_EMAIL );

      if ( ! epc_can_user_vote() )
        $this->vote_form->get_errors()->add( 'general', __( 'You are not able to vote in this contest.', EPC_TEXT_DOMAIN ) );

      if ( epc_is_vote_email_required() ) {
        if ( empty( $email ) ) {
          $this->vote_form->get_errors()->add( 'epc_email', __( 'Please fill in your email address.', EPC_TEXT_DOMAIN ) );
        } elseif ( !is_email( $email ) ) {
          $this->vote_form->get_errors()->add( 'epc_email', __( 'Your email address is invalid.', EPC_TEXT_DOMAIN ) );
        }
      }

      if ( dtpc_is_fingerprint_unique( $post->ID ) == false )
        $this->vote_form->get_errors()->add( 'general', __( 'You have already voted in this contest.', EPC_TEXT_DOMAIN ) );

      if ( 1 > count( $this->vote_form->get_errors()->get_error_messages() ) ) {
        $status = ( epc_is_vote_confirmation_required() ? 'unconfirmed' : 'confirmed' );

        epc_do_vote( get_query_var( 'entry_id' ), $status, $email );

        if ( $status == 'unconfirmed' ) {
          $msg = 'confirm';
        } else {
          $msg = 'confirmed';
        }

        $this->vote_form->form['success_msg'] = epc_get_message( 'vote_' . $msg );

      }
    }

  }

  /**
   * Confirm vote
   */
   public function confirm_vote() {
     if ( isset( $_GET['epc-confirm-vote'] ) && isset ( $_GET['token'] ) ) {
       $vote = epc_get_vote_by_token( esc_attr( $_GET['token'] ) );

       if ( !empty( $vote ) ) {

         if ( $vote->status == 'unconfirmed' ) {

           epc_update_vote_status( $vote->vote_id, 'confirmed' );

           $redirect_url = add_query_arg( array(
             'epc_msg' => 'vote_confirmed'
           ), epc_get_entry_url( $vote->entry_id, $vote->contest_id ) );

         } elseif (  $vote->status == 'confirmed' ) {

           $this->vote_form->form['success_msg'] = epc_get_message( 'vote_is_confirmed' );

           $redirect_url = add_query_arg( array(
             'epc_msg' => 'vote_is_confirmed'
           ), epc_get_entry_url( $vote->entry_id, $vote->contest_id ) );

         }

         wp_safe_redirect( $redirect_url );

         exit;

       } else {
         wp_die( 'This voting confirmation URL appears to invalid.', EPC_TEXT_DOMAIN );
       }
     }
   }

}
