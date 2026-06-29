<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

delete_option( 'elf_settings' );
delete_option( 'elf_category_map' );
delete_option( 'elf_feed_last_generated' );
delete_option( 'elf_feed_product_count' );
delete_option( 'elf_feed_skipped_no_image' );
delete_option( 'elf_feed_skipped_no_price' );
delete_option( 'elf_error_log' );
delete_transient( 'elf_google_taxonomy' );

// Remove generated feed files
$upload   = wp_upload_dir();
$feed_dir = trailingslashit( $upload['basedir'] ) . 'excellink-feeds/';

if ( is_dir( $feed_dir ) ) {
    array_map( 'unlink', glob( $feed_dir . '*.xml' ) ?: [] );
    @unlink( $feed_dir . '.htaccess' );
    @rmdir( $feed_dir );
}

// Clear scheduled cron
$timestamp = wp_next_scheduled( 'elf_generate_feeds_cron' );
if ( $timestamp ) {
    wp_unschedule_event( $timestamp, 'elf_generate_feeds_cron' );
}
