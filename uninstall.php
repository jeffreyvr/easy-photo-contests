<?php
/**
 * This part always scares the living daylights out of me.
 */

/* If uninstall is not called from WordPress exit. */
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit ();
}

// Remove the plugin's settings & installation log.
$epc_options = get_option('epc_settings');
$delete_options = array('epc_settings','epc_license_key','epc_license_status','epc_version', 'epc_welcomed');
foreach ( $delete_options as $option ) {
	delete_option( $option );
}

// Remove the database tables.
$epc_wpdb = $GLOBALS['wpdb'];

if( isset( $epc_wpdb ) ) { /** @var wpdb $epc_wpdb */
	// EXTERMINATE!
	$epc_wpdb->query( "DROP TABLE IF EXISTS {$epc_wpdb->prefix}epc_entries, {$epc_wpdb->prefix}epc_votes, {$epc_wpdb->prefix}epc_entry_meta" );
}

wp_delete_post( $epc_options['submit_entry_page_id'], true ); // delete entry submit page

foreach ( get_posts( array( 'post_type' => 'easy_photo_contest' ) ) as $post ) { // delete all contests
	wp_delete_post( $post->ID, true );
}
