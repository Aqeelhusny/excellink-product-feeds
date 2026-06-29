<?php
/**
 * Plugin Name: Excellink Product Feeds
 * Plugin URI:  https://excellink.com
 * Description: Generate Google Shopping & Facebook Catalog XML feeds from WooCommerce products.
 * Version:     1.0.0
 * Author:      Excellink
 * Text Domain: excellink-feeds
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce
 * WC requires at least: 7.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ELF_VERSION',    '1.0.0' );
define( 'ELF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ELF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ELF_TEXT_DOMAIN', 'excellink-feeds' );

register_activation_hook( __FILE__, 'elf_activate' );
register_deactivation_hook( __FILE__, 'elf_deactivate' );

function elf_activate(): void {
    if ( ! class_exists( 'WooCommerce' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die(
            '<p>' . esc_html__( 'Excellink Product Feeds requires WooCommerce to be installed and active. Please activate WooCommerce first.', 'excellink-feeds' ) . '</p>',
            esc_html__( 'Plugin activation failed', 'excellink-feeds' ),
            [ 'back_link' => true ]
        );
    }

    $upload   = wp_upload_dir();
    $feed_dir = trailingslashit( $upload['basedir'] ) . 'excellink-feeds/';

    if ( ! is_dir( $feed_dir ) ) {
        wp_mkdir_p( $feed_dir );
    }
    if ( ! file_exists( $feed_dir . '.htaccess' ) ) {
        file_put_contents( $feed_dir . '.htaccess', 'Options -Indexes' . PHP_EOL );
    }

    add_option( 'elf_settings', [
        'schedule'             => 'daily',
        'batch_size'           => 200,
        'include_out_of_stock' => 'no',
        'condition'            => 'new',
        'brand_fallback'       => get_bloginfo( 'name' ),
        'enable_logging'       => 'no',
    ]);

    if ( ! wp_next_scheduled( 'elf_generate_feeds_cron' ) ) {
        wp_schedule_event( time(), 'daily', 'elf_generate_feeds_cron' );
    }
}

function elf_deactivate(): void {
    $timestamp = wp_next_scheduled( 'elf_generate_feeds_cron' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'elf_generate_feeds_cron' );
    }
}

add_action( 'plugins_loaded', 'elf_init' );
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'elf_plugin_action_links' );

function elf_plugin_action_links( array $links ): array {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        admin_url( 'admin.php?page=elf-product-feeds' ),
        esc_html__( 'Settings', 'excellink-feeds' )
    );
    
    $feeds_link = sprintf(
        '<a href="%s">%s</a>',
        admin_url( 'admin.php?page=elf-category-map' ),
        esc_html__( 'Category Map', 'excellink-feeds' )
    );
    
    array_unshift( $links, $settings_link );
    array_push( $links, $feeds_link );
    
    return $links;
}

function elf_init(): void {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', static function () {
            echo '<div class="notice notice-error"><p>'
                . esc_html__( 'Excellink Product Feeds requires WooCommerce to be active.', 'excellink-feeds' )
                . '</p></div>';
        });
        return;
    }

    require_once ELF_PLUGIN_DIR . 'includes/class-logger.php';
    require_once ELF_PLUGIN_DIR . 'includes/class-rate-limiter.php';
    require_once ELF_PLUGIN_DIR . 'includes/class-feed-validator.php';
    require_once ELF_PLUGIN_DIR . 'includes/class-image-helper.php';
    require_once ELF_PLUGIN_DIR . 'includes/class-health-monitor.php';
    require_once ELF_PLUGIN_DIR . 'includes/class-category-mapper.php';
    require_once ELF_PLUGIN_DIR . 'includes/class-feed-generator.php';
    require_once ELF_PLUGIN_DIR . 'includes/class-unified-feed.php';
    require_once ELF_PLUGIN_DIR . 'includes/class-image-sitemap.php';
    require_once ELF_PLUGIN_DIR . 'includes/class-cron-handler.php';

    ELF_Cron_Handler::init();
    ELF_Health_Monitor::init();

    if ( is_admin() ) {
        require_once ELF_PLUGIN_DIR . 'admin/class-admin-page.php';
        ELF_Admin_Page::init();
    }
}
