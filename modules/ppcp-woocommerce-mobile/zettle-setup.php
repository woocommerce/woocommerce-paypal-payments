<?php
/**
 * Quick setup script for Zettle OAuth configuration
 * 
 * Usage: Add this to your wp-config.php or run via WP-CLI
 */

// Only run this if accessed directly or via WP-CLI
if ( ! defined( 'WP_CLI' ) && ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Set up Zettle OAuth configuration
 * 
 * Replace YOUR_ZETTLE_CLIENT_ID with the actual client ID from https://developer.zettle.com/
 */
function setup_zettle_oauth() {
    // Set your Zettle client ID here
    $zettle_client_id = 'f41c0f30-abe3-40e3-bff9-9171aef77e64'; // Replace with your actual client ID
    
    // Update the option
    update_option( 'zettle_oauth_client_id', $zettle_client_id );
    
    // Log success
    error_log( "Zettle OAuth: Client ID configured successfully: $zettle_client_id" );
    
    if ( defined( 'WP_CLI' ) ) {
        WP_CLI::success( "Zettle OAuth client ID configured: $zettle_client_id" );
    } else {
        echo "Zettle OAuth client ID configured: $zettle_client_id\n";
    }
}

// Auto-run if this file is included
if ( function_exists( 'add_action' ) ) {
    add_action( 'init', 'setup_zettle_oauth' );
} else {
    setup_zettle_oauth();
}