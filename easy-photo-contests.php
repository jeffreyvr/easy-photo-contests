<?php
/*
Plugin Name: Easy Photo Contests
Plugin URI: https://doubletakepigeon.com/easy-photo-contests
Description: With the Easy Photo Contest plugin from Double Take Pigeon, you'll be able to quickly set up and organize photo contests.
Version: 1.0
Author: Double Take Pigeon
Author URI: https://www.doubletakepigeon.com
Text Domain: easy-photo-contests
Domain Path: /languages
License: GPL-2.0+

------------------------------------------------------------------------
Copyright 2018 Double Take Pigeon, The Netherlands.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

For the GNU General Public License, see http://www.gnu.org/licenses.
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Easy_Photo_Contests' ) ) {

class Easy_Photo_Contests {
  private static $instance = null;
  private $plugin_path;
  private $plugin_url;
  private $plugin_name = 'Easy Photo Contests';
  private $plugin_version = '1.0';
  private $text_domain = 'easy-photo-contests';

  /**
   * Creates or returns an instance of this class.
   */
  public static function get_instance() {
    if ( null == self::$instance ) {
      self::$instance = new self;
    }

    return self::$instance;
  }

  /**
   * Initializes the plugin by setting localization, hooks, filters, and administrative functions.
   */
  private function __construct() {
    spl_autoload_register( array( &$this, 'autoload' ) );

    $this->plugin_path = plugin_dir_path( __FILE__ );
    $this->plugin_url  = plugin_dir_url( __FILE__ );

    add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

    add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts' ) );
    add_action( 'admin_enqueue_scripts', array( $this, 'register_styles' ) );

    add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
    add_action( 'wp_enqueue_scripts', array( $this, 'register_styles' ) );

    register_activation_hook( __FILE__, array( $this, 'activation' ) );
    //register_deactivation_hook( __FILE__, array( $this, 'deactivation' ) );

    require_once( $this->get_plugin_path() . 'includes/epc-functions.php' );

    $this->define_constants();

    require_once( $this->get_plugin_path() . 'includes/epc-actions.php' );
    require_once( $this->get_plugin_path() . 'includes/epc-filters.php' );

    $this->run_plugin();
  }

  public function get_plugin_url() {
    return $this->plugin_url;
  }

  public function get_plugin_path() {
    return $this->plugin_path;
  }

  /**
   * Place code that runs at plugin activation here.
   */
  public function activation() {
    require_once( $this->get_plugin_path() . 'includes/classes/class-epc-install.php' );
    $install = new EPC_Install();
    $install->run();
  }

  /**
   * Place code that runs at plugin deactivation here.
   */
  public function deactivation() {}

  /**
   * Enqueue and register JavaScript files here.
   */
  public function register_scripts() {
    if ( is_admin() ) {
      wp_enqueue_script( 'epc-admin', $this->get_plugin_url() . 'assets/js/epc-admin.js', array( 'jquery', 'chosen' ), $this->plugin_version );
      wp_enqueue_script( 'chosen', $this->get_plugin_url() . 'assets/chosen/chosen.jquery.min.js', array( 'jquery' ), '1.8.7' );
      wp_localize_script( 'epc-admin', 'epc_admin', array(
        'confirm_text' => __( 'Are you sure you want to do this?', EPC_TEXT_DOMAIN )
      ) );
    } else {
      wp_enqueue_script( 'epc', $this->get_plugin_url() . 'assets/js/easy-photo-contests.js', array( 'jquery' ), $this->plugin_version );

      wp_localize_script( 'epc', 'dtp_pc', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'copy_clipboard' => __( 'The URL has been copied to your clipboard.', EPC_TEXT_DOMAIN )
      ) );

      if ( epc_is_lightbox_enabled() )
        wp_enqueue_script( 'simplelightbox', $this->get_plugin_url() . 'assets/simplelightbox/simple-lightbox.min.js', array( 'jquery' ), '1.13.0' );
        wp_add_inline_script( 'simplelightbox', 'jQuery(document).ready(function(){ jQuery("a.epc-lightbox").simpleLightbox(); });' );
    }
  }

  /**
   * Enqueue and register CSS files here.
   */
  public function register_styles() {
    if ( is_admin() ) {
      wp_enqueue_style( 'epc-admin', $this->get_plugin_url() . 'assets/css/epc-admin.css', null, $this->plugin_version );
      wp_enqueue_style( 'chosen', $this->get_plugin_url() . 'assets/chosen/chosen.min.css', null, '1.8.7' );
    } else {
      $load_css = apply_filters( 'epc_load_css', true );

      if ( $load_css ) {
        wp_enqueue_style( 'epc', $this->get_plugin_url() . 'assets/css/easy-photo-contests.css', null, $this->plugin_version );
      }

      wp_enqueue_style( 'fontawesome', $this->get_plugin_url() . 'assets/fontawesome/css/all.min.css', null, '5.1.1' );

      if ( epc_is_lightbox_enabled() )
        wp_enqueue_style( 'simplelightbox', $this->get_plugin_url() . 'assets/simplelightbox/simplelightbox.min.css', null, '1.13.0' );
    }
  }

  /**
   * Place code for your plugin's functionality here.
   */
  private function run_plugin() {
    if ( is_admin() ) {
      add_action( 'init', array( 'EPC_Admin_Entries', 'get_instance' ) );
      add_action( 'init', array( 'EPC_Admin_Settings', 'get_instance' ) );
      add_action( 'init', array( 'EPC_Admin_Meta_Box', 'get_instance' ) );
    }

    add_action( 'wp', array( 'EPC_Entry', 'get_instance' ) );
    add_action( 'wp', array( 'EPC_Entry_Form', 'get_instance' ) );
    add_action( 'wp', array( 'EPC_Vote_Form', 'get_instance' ) );
    add_action( 'plugins_loaded', array( 'EPC_Post_Type', 'get_instance' ) );
  }

  /**
   * Load textdomain
   */
  public function load_textdomain() {
    $mofile = sprintf( '%1$s-%2$s.mo', EPC_TEXT_DOMAIN, get_locale() );

    // Check wp-content/languages/plugins/easy-photo-contests
    $mofile_global = WP_LANG_DIR . '/plugins/easy-photo-contests/' . $mofile;

    if ( file_exists( $mofile_global ) ) {
      load_textdomain( EPC_TEXT_DOMAIN, $mofile_global );
    } else {
      load_plugin_textdomain( EPC_TEXT_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

  }

  /**
	 * Auto-load Plugin Name classes on demand to reduce memory consumption.
	 *
	 * @access public
	 * @param mixed $class
	 * @return void
	 */
   public function autoload( $class ) {
     $path  = null;
     $class = strtolower( $class );
     $file = 'class-' . str_replace( '_', '-', $class ) . '.php';
     $class = strtolower( $class );
     if ( strpos( $class, 'epc_admin_' ) === 0 ) {
       $path = $this->get_plugin_path() . 'includes/classes/admin/';
     } else if ( strpos( $class, 'epc_' ) === 0 ) {
       $path = $this->get_plugin_path() . 'includes/classes/';
     }
     //echo $path.$file."\n";
     if ( $path && is_readable( $path . $file ) ) {
       include_once( $path . $file );
       return;
     }
     // Fallback
     if ( strpos( $class, 'epc_' ) === 0 ) {
       $path = $this->get_plugin_path() . 'includes/';
     }
     if ( $path && is_readable( $path . $file ) ) {
       include_once( $path . $file );
       return;
     }

  }

  /**
   * Define Constants
   */
  private function define_constants() {
    if ( ! defined( 'EPC_NAME' ) ) define( 'EPC_NAME', $this->plugin_name );
    if ( ! defined( 'EPC_PATH' ) ) define( 'EPC_PATH', $this->get_plugin_path() );
    if ( ! defined( 'EPC_URL' ) ) define( 'EPC_URL', $this->get_plugin_url() );
    if ( ! defined( 'EPC_VERSION' ) ) define( 'EPC_VERSION', $this->plugin_version );
    if ( ! defined( 'EPC_TEXT_DOMAIN' ) ) define( 'EPC_TEXT_DOMAIN', $this->text_domain );
  }

  public function settings() {
    return epc_get_settings();
  }

}

$epc = Easy_Photo_Contests::get_instance();
$epc_options = $epc->settings();

}
