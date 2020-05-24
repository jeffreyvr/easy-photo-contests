<?php
class EPC_Form {
  public $fields;
  public $form;
  public $wp_error;
  public $current_url;

  /**
   * Construct
   */
  public function __construct() {
    global $wp;

    $this->wp_error = new WP_Error();

    $this->fields = array();

    $this->current_url = add_query_arg( $_SERVER['QUERY_STRING'], '', home_url( $wp->request ) . '/' );

    $this->form = array(
      'login_required' => false,
      'callback' => '',
      'button_txt' => __( 'Submit', EPC_TEXT_DOMAIN ),
      'fields' => $this->fields,
      'form_class' => 'epc-form',
      'honeypot' => true,
      'action' => $this->current_url
    );
  }

  /**
   * Get form
   *
   * @return array
   */
  public function get_form() {
    return apply_filters( 'epc_get_form', $this->form );
  }

  /**
   * Get fields
   *
   * @return array
   */
  public function get_fields() {
    return apply_filters( 'epc_get_form_fields', $this->fields );
  }

  public function get_errors() {
    return apply_filters( 'epc_get_form_errors', $this->wp_error );
  }

  /**
   * Get form
   *
   * @return string
   */
  public function form_output() {
    global $post;

    if ( ! is_singular() ) {
      return;
    }

    if ( $this->form['login_required'] && ! is_user_logged_in() ) {
      return '<div class="epc-msg">
        ' . sprintf( __( "In order to fill in this form, you must be <a href='%s'>logged in</a>.", EPC_TEXT_DOMAIN ), wp_login_url( $this->current_url ) ) . '
      </div>';
    }

    if ( isset( $this->get_form()['success_msg'] ) ) {
      return '<div class="epc-msg epc-success-msg">
        ' . $this->get_form()['success_msg'] . '
      </div>';
    }

    if ( isset( $this->get_form()['disabled_msg'] ) ) {
      return '<div class="epc-msg">
        ' . $this->get_form()['disabled_msg'] . '
      </div>';
    }

    if ( isset( $_GET['epc_msg'] ) ) {
      return;
    }

    $html = '';

    if ( !empty( $this->get_errors()->get_error_message( 'general' ) ) ) {
      $html .= '<div class="epc-msg epc-error-msg">' . $this->get_errors()->get_error_message( 'general' ) . '</div>';
    }

    $html .= '<form action="' . $this->get_form()['action'] . '" class="' . $this->get_form()['form_class'] . '" method="POST" ' . ( $this->form_has_file() ? 'enctype="multipart/form-data"' : '' ) . '>';

    foreach ( $this->get_fields() as $field ) {

      $html.= $this->{'render_' . $field['type']}( $field );

    }

    if ( $this->get_form()['honeypot'] ) {
      $html .= '<input name="hfirstname" type="text" id="epc-hfirstname" class="epc-hide-robot">';
      $html .= '<input name="hlastname" type="text" id="epc-hlastname" class="epc-hide-robot">';
    }

    ob_start();

    wp_nonce_field( $this->form['callback'], $this->form['callback'] . '_nonce' );

    $html .= ob_get_clean();

    $html .= '<button type="submit" class="epc-button button">' . $this->form['button_txt']  . '</button>';

    $html .= '</form>';

    return $html;
  }

  /**
   * Form has file
   *
   * @return boolean
   */
  public function form_has_file() {
    foreach ( $this->get_fields() as $field ) {
      if ( $field['type'] == 'file' )
        return true;
    }

    return false;
  }

  /**
   * Set field
   *
   * @param array $field
   */
  public function set_field( $field ) {
    $new_field = array(
      'name' => $field['name'],
      'label' => isset( $field['label'] ) ? $field['label'] : '',
      'required' => isset( $field['required'] ) ? $field['required'] : false,
      'type' => isset( $field['type'] ) ? $field['type'] : 'text',
      'desc' => isset( $field['desc'] ) ? $field['desc'] : '',
      'placeholder' => isset( $field['placeholder'] ) ? $field['placeholder'] : '',
      'options' => isset( $field['options'] ) ? $field['options'] : array()
    );

    $this->fields[] = $new_field;
  }

  /**
   * Render text
   *
   * @param  array $field
   * @return string
   */
  public function render_text( $field ) {
    $required = !empty( $field['required'] ) ? 'required' : '';
    $value = isset( $_POST[ $field['name'] ] ) ? esc_attr( $_POST[ $field['name'] ] ) : '';

    $html = '<div class="epc-input-wrap">';

      $html .= '<label class="epc-label" for="epc-contest-' . $field['name'] . '">' . $field['label'] . ' ' . ( $required ? '*' : '' ) . '</label>';

      $html .= '<input type="text" id="epc-contest-' . $field['name'] . '" ' . $required . ' class="epc-input epc-' . $field['type'] . '" name="' . $field['name'] . '" placeholder="' . $field['placeholder'] . '" value="' . $value . '">';

      if ( !empty( $this->get_errors()->get_error_message( $field['name'] ) ) ) {
        $html .= '<strong class="epc-input-invalid">' . $this->get_errors()->get_error_message( $field['name'] ) . '</strong>';
      }

      if ( isset( $field['desc'] ) ) {
        $html .= '<div class="epc-input-desc">' . $field['desc'] . '</div>';
      }

    $html .= '</div>';

    return $html;
  }

  /**
   * Render email
   *
   * @param  array $field
   * @return string
   */
  public function render_email( $field ) {
    $required = !empty( $field['required'] ) ? 'required' : '';
    $value = isset( $_POST[ $field['name'] ] ) ? esc_attr( $_POST[ $field['name'] ] ) : '';

    $html = '<div class="epc-input-wrap">';

      $html .= '<label class="epc-label" for="epc-contest-' . $field['name'] . '">' . $field['label'] . ' ' . ( $required ? '*' : '' ) . '</label>';

      $html .= '<input type="email" id="epc-contest-' . $field['name'] . '" ' . $required . ' class="epc-input epc-' . $field['type'] . '" name="' . $field['name'] . '" placeholder="' . $field['placeholder'] . '" value="' . $value . '">';

      if ( !empty( $this->get_errors()->get_error_message( $field['name'] ) ) ) {
        $html .= '<strong class="epc-input-invalid">' . $this->get_errors()->get_error_message( $field['name'] ) . '</strong>';
      }

      if ( isset( $field['desc'] ) ) {
        $html .= '<div class="epc-input-desc">' . $field['desc'] . '</div>';
      }

    $html .= '</div>';

    return $html;
  }

  /**
   * Render hidden
   *
   * @param  array $field
   * @return string
   */
  public function render_hidden( $field ) {
    $required = !empty( $field['required'] ) ? 'required' : '';
    $value = isset( $field['value'] ) ? esc_attr( $field['value'] ) : '';

    $html = '';

    $html .= '<input type="hidden" id="epc-contest-' . $field['name'] . '" ' . $required . ' name="' . $field['name'] . '" value="' . $value . '">';

    return $html;
  }

  /**
   * Render textarea
   *
   * @param  array $field
   * @return string
   */
  public function render_textarea( $field ) {
    $required = !empty( $field['required'] ) ? 'required' : '';
    $value = isset( $_POST[ $field['name'] ] ) ? esc_attr( $_POST[ $field['name'] ] ) : '';

    $html = '<div class="epc-input-wrap">';

      $html .= '<label class="epc-label" for="epc-contest-' . $field['name'] . '">' . $field['label'] . ' ' . ( $required ? '*' : '' ) . '</label>';

      $html .= '<textarea id="epc-contest-' . $field['name'] . '" ' . $required . ' class="epc-input epc-' . $field['type'] . '" name="' . $field['name'] . '" placeholder="' . $field['placeholder'] . '">' . $value . '</textarea>';

      if ( !empty( $this->get_errors()->get_error_message( $field['name'] ) ) ) {
        $html .= '<strong class="epc-input-invalid">' . $this->get_errors()->get_error_message( $field['name'] ) . '</strong>';
      }

      if ( isset( $field['desc'] ) ) {
        $html .= '<div class="epc-input-desc">' . $field['desc'] . '</div>';
      }

    $html .= '</div>';

    return $html;
  }

  /**
   * Render dropdown
   *
   * @param  array $field
   * @return string
   */
  public function render_dropdown( $field ) {
    $required = !empty( $field['required'] ) ? 'required' : '';
    $value = isset( $_POST[ $field['name'] ] ) ? esc_attr( $_POST[ $field['name'] ] ) : '';

    $html = '<div class="epc-input-wrap">';

      $html .= '<label class="epc-label" for="epc-contest-' . $field['name'] . '">' . $field['label'] . ' ' . ( $required ? '*' : '' ) . '</label>';

      if ( !empty( $field['options'] ) ) {

        $html .= '<select id="epc-contest-' . $field['name'] . '" ' . $required . ' class="epc-input epc-' . $field['type'] . '" name="' . $field['name'] . '">';

        foreach ( $field['options'] as $option_value => $option_label ) {
          $html.= '<option value="' . $option_value . '" ' . selected( $value, $value, false ) . '>' . $option_label . '</option>';
        }

        $html .= '</select>';

        if ( !empty( $this->get_errors()->get_error_message( $field['name'] ) ) ) {
          $html .= '<strong class="epc-input-invalid">' . $this->get_errors()->get_error_message( $field['name'] ) . '</strong>';
        }

        if ( isset( $field['desc'] ) ) {
          $html .= '<div class="epc-input-desc">' . $field['desc'] . '</div>';
        }

      }

    $html .= '</div>';

    return $html;
  }

  /**
   * Render checkbox
   *
   * @param  array $field
   * @return string
   */
  public function render_checkbox( $field ) {
    $required = !empty( $field['required'] ) ? 'required' : '';
    $checked = isset( $_POST[ $field['name'] ] ) ? '1' : '';

    $html = '<div class="epc-input-wrap">';

      $html .= '<label class="epc-label" for="epc-contest-' . $field['name'] . '">';

        $html .= '<input type="checkbox" id="epc-contest-' . $field['name'] . '" ' . $required . ' class="epc-input epc-' . $field['type'] . '" ' . checked( 1, $checked, false ) . ' name="' . $field['name'] . '" value="1"> ';

        $html .=  $field['label'] . ' ' . ( $required ? '*' : '' );

      $html .= '</label>';

      if ( !empty( $this->get_errors()->get_error_message( $field['name'] ) ) ) {
        $html .= '<strong class="epc-input-invalid">' . $this->get_errors()->get_error_message( $field['name'] ) . '</strong>';
      }

      if ( isset( $field['desc'] ) ) {
        $html .= '<div class="epc-input-desc">' . $field['desc'] . '</div>';
      }

    $html .= '</div>';

    return $html;
  }

  /**
   * Render file
   *
   * @param  array $field
   * @return string
   */
  public function render_file( $field ) {
    $required = !empty( $field['required'] ) ? 'required' : '';
    $value = isset( $_POST[ $field['name'] ] ) ? esc_attr( $_POST[ $field['name'] ] ) : '';

    $html = '<div class="epc-input-wrap">';

      $html .= '<label class="epc-label" for="epc-contest-' . $field['name'] . '">' . $field['label'] . ' ' . ( $required ? '*' : '' ) . '</label>';

      $html .= '<input type="file" id="epc-contest-' . $field['name'] . '" ' . $required . ' class="epc-input epc-' . $field['type'] . '" name="' . $field['name'] . '" placeholder="' . $field['placeholder'] . '" value="' . $value . '">';

      if ( !empty( $this->get_errors()->get_error_message( $field['name'] ) ) ) {
        $html .= '<strong class="epc-input-invalid">' . $this->get_errors()->get_error_message( $field['name'] ) . '</strong>';
      }

      if ( isset( $field['desc'] ) ) {
        $html .= '<div class="epc-input-desc">' . $field['desc'] . '</div>';
      }

    $html .= '</div>';

    return $html;
  }

}
