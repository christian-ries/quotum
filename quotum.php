<?php
/**
 * Plugin Name: Quotum
 * Description: A simple library for managing and displaying quotations.
 * Version: 0.7.7
 * Author: Christian Ries
 * Author URI: https://profiles.wordpress.org/christian-ries/
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 * Text Domain: quotum
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'QUOTUM_VERSION', '0.7.7' );
define( 'QUOTUM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'QUOTUM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once QUOTUM_PLUGIN_DIR . 'includes/import-export.php';
require_once QUOTUM_PLUGIN_DIR . 'includes/admin.php';
require_once QUOTUM_PLUGIN_DIR . 'includes/functions.php';
require_once QUOTUM_PLUGIN_DIR . 'includes/public.php';
require_once QUOTUM_PLUGIN_DIR . 'includes/shortcode.php';
require_once QUOTUM_PLUGIN_DIR . 'includes/widget.php';

/**
 * Create the dedicated quotations table on first activation.
 * dbDelta() leaves existing quotation records intact.
 */
function quotum_activate() {
    global $wpdb;

    $table   = $wpdb->prefix . 'quotum';
    $charset = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        original_id bigint(20) unsigned DEFAULT NULL,
        quote longtext NOT NULL,
        author_original varchar(255) NOT NULL DEFAULT '',
        source text NULL,
        tags varchar(255) NOT NULL DEFAULT '',
        active tinyint(1) NOT NULL DEFAULT 1,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY original_id (original_id),
        KEY active (active)
    ) $charset;";

    dbDelta( $sql );

    // Track successful schema initialization for updates installed over an active plugin.
    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $table ) ) ) === $table ) {
        update_option( 'quotum_db_version', QUOTUM_VERSION, false );
    }

    // Only offer examples to a new, empty library; never interrupt upgrades.
    if ( false === get_option( 'quotum_samples_choice', false ) ) {
        $existing = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
        if ( 0 === $existing ) {
            add_option( 'quotum_samples_choice', 'offer', '', false );
        } else {
            add_option( 'quotum_samples_choice', 'existing', '', false );
        }
    }
}
register_activation_hook( __FILE__, 'quotum_activate' );

/**
 * Ensure the database exists after in-place plugin replacements.
 * WordPress does not invoke activation hooks when replacing an active plugin ZIP.
 */
function quotum_maybe_upgrade_database() {
    if ( get_option( 'quotum_db_version' ) === QUOTUM_VERSION ) {
        return;
    }
    quotum_activate();
}
add_action( 'plugins_loaded', 'quotum_maybe_upgrade_database', 20 );
