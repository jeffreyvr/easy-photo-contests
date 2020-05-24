<?php
class EPC_Entry {
  private static $instance = null;
  public $entry_id;
  public $entry;

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

    if ( !empty( $this->entry ) ) {
      add_filter( 'the_content', array( $this, 'display_entry' ), 1, 10 );
      add_filter( 'document_title_parts', array( $this, 'set_document_title_parts' ), 10, 1 );
      add_action( 'wpseo_add_opengraph_images', array( $this, 'wpseo_entry_opengraph_image' ) );
      add_filter( 'wpseo_opengraph_desc', array( $this, 'wpseo_entry_opengraph_descr' ) );
      add_filter( 'wpseo_opengraph_url', array( $this, 'wpseo_entry_opengraph_url' ) );
      add_filter( 'wpseo_metadesc', array( $this, 'filter_wpseo_entry_desc' ), 10, 1 );
      add_filter( 'wpseo_twitter_description', array( $this, 'filter_wpseo_entry_desc' ), 10, 1 );
      add_filter( 'wpseo_title', array( $this, 'filter_wpseo_entry_title' ), 10, 1 );
      add_filter( 'the_title', array( $this, 'epc_set_entry_title' ), 10, 1 );
      add_filter( 'body_class', array( $this, 'body_class' ), 10, 1 );
      add_action( 'template_redirect', array( $this, 'access' ), 10, 1 );
      add_filter( 'get_edit_post_link', array( $this, 'edit_entry_link' ), 10, 1 );
      add_action( 'wp_before_admin_bar_render', array( $this, 'admin_bar' ), 10 );
    }

    if ( !empty( $this->entry_id ) && empty( $this->entry ) ) {
      global $post;

      wp_redirect( get_permalink( $post ) );
      exit;
    }
  }

  /**
   * Access
   *
   * Check if the visitor can view the entry.
   */
  public function access( $template ) {
    global $post;

    if ( $this->entry->status == 'not_approved' && ! current_user_can( 'edit_others_posts' ) ) {
      wp_redirect( get_permalink( $post ) );
      exit;
    }

    return $template;
  }

  /**
   * Edit entry link
   * @param  string $link
   * @return string
   */
  public function edit_entry_link( $link ) {
    return epc_get_edit_entry_url( $this->entry_id );
  }

  /**
   * Admin bar
   *
   * Replace the contest edit url with the entry edit url.
   */
  public function admin_bar() {
    global $wp_admin_bar;

    $wp_admin_bar->remove_node('edit');

    $args = array(
      'id' => 'edit',
      'title' => __( 'Edit entry', EPC_TEXT_DOMAIN ),
      'href' => epc_get_edit_entry_url( $this->entry_id )
    );

    $wp_admin_bar->add_node( $args );
  }

  /**
   * Display entry
   *
   * @return string
   */
  public function display_entry( $content ) {
    global $post;

    ob_start();

    remove_filter( 'the_content', 'wpautop' );

    $entry = $this->entry;

    include apply_filters( 'epc_entry_view', EPC_PATH . 'views/entry.php' );

    return apply_filters( 'epc_entry', ob_get_clean() );
  }

  /**
   * Set entry title
   *
   * @param string $title
   *
   * @return string
   */
  function epc_set_entry_title( $title ) {
    global $post;

    if ( $title == $post->post_title ) {
      return esc_attr( $post->post_title ) . ' - ' . esc_attr( $this->entry->name );
    }

    return $title;
  }

  /**
   * Set title
   * @param [type] $title [description]
   */
  function set_document_title_parts( $title ) {
    if ( $new_title = $this->get_document_title( 'array' ) ) {
      return $new_title;
    }

    return $title;
  }

  /**
   * Get document title
   *
   * @param string $return [description]
   */
  function get_document_title( $return = 'array' ) {
    global $wp_query, $post;

    $title_array = array( $this->entry->name, $post->post_title, get_bloginfo( 'name' ) );

    if ( $return == 'array' ) {
      return $title_array;

    } else {

      if ( class_exists( 'WPSEO_Utils' ) ) { // if WPSEO (Yoast) is active, get configured seperator
        $seperator = WPSEO_Utils::get_title_separator();
      } else {
        $seprator = ' - '; // WP default
      }

      return implode( " $seperator ", $title_array );

    }

    return;

  }

  /**
   * WPSEO entry title
   */
  public function filter_wpseo_entry_title( $title ) {
    if ( $new_title = $this->get_document_title( 'string' ) ) {
      return $new_title;
    }

    return $title;
  }

  /**
   * WPSEO entry desc
   *
   * @param  string $wpseo_replace_vars
   * @return string
   */
  public function filter_wpseo_entry_desc( $desc ) {
    global $post;

    return sprintf( __( 'This is the entry "%s" for the %s contest.', EPC_TEXT_DOMAIN ), esc_attr( $this->entry->name ), esc_attr( $post->post_title ) );
  }


  /**
   * WPSEO Opengraph URL
   *
   * @param  string $url
   * @return string
   */
  public function wpseo_entry_opengraph_url( $url ) {
    return epc_get_entry_url( $this->entry->entry_id );
  }

  /**
   * WPSEO Opengraph image
   *
   * @param  string   $image
   * @return string
   */
  public function wpseo_entry_opengraph_image( $image ) {
    $image->add_image( esc_attr( epc_get_entry_image_url( $this->entry ) ) );
  }

  /**
   * WPSEO Opengraph desc
   *
   * @param string $desc
   * @return string
   */
  public function wpseo_entry_opengraph_descr( $desc ) {
    global $post;

    return sprintf( __( 'This is the entry "%s" for the %s contest.', EPC_TEXT_DOMAIN ), esc_attr( $this->entry->name ), esc_attr( $post->post_title ) );
  }

  /**
   * Add body class
   *
   * @param  array $classes
   * @return array
   */
  public function body_class( $classes ) {
    if ( ! epc_is_vote_email_required() ) {
      $classes[] = 'epc-entry-no-vote-fields';
    }

    return $classes;
  }

}
