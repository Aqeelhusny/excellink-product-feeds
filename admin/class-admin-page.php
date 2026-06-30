<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ELF_Admin_Page {

    private const MENU_SLUG = 'elf-product-feeds';
    private const NONCE     = 'elf_admin_nonce';

    public static function init(): void {
        add_action( 'admin_menu',            [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'wp_ajax_elf_save_settings',     [ __CLASS__, 'ajax_save_settings' ] );
        add_action( 'wp_ajax_elf_regenerate_feeds',  [ __CLASS__, 'ajax_regenerate_feeds' ] );
        add_action( 'wp_ajax_elf_save_category_map', [ __CLASS__, 'ajax_save_category_map' ] );
        add_action( 'wp_ajax_elf_search_taxonomy',   [ __CLASS__, 'ajax_search_taxonomy' ] );
        add_action( 'wp_ajax_elf_export_settings',   [ __CLASS__, 'ajax_export_settings' ] );
        add_action( 'wp_ajax_elf_import_settings',   [ __CLASS__, 'ajax_import_settings' ] );
        add_action( 'wp_ajax_elf_clear_logs',        [ __CLASS__, 'ajax_clear_logs' ] );
    }

    public static function register_menu(): void {
        add_menu_page(
            __( 'Product Feeds', 'excellink-product-feeds' ),
            __( 'Product Feeds', 'excellink-product-feeds' ),
            'manage_options',
            self::MENU_SLUG,
            [ __CLASS__, 'render_settings' ],
            'dashicons-rss',
            58
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Settings', 'excellink-product-feeds' ),
            __( 'Settings', 'excellink-product-feeds' ),
            'manage_options',
            self::MENU_SLUG,
            [ __CLASS__, 'render_settings' ]
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Category Mapping', 'excellink-product-feeds' ),
            __( 'Category Mapping', 'excellink-product-feeds' ),
            'manage_options',
            'elf-category-map',
            [ __CLASS__, 'render_category_map' ]
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Logs', 'excellink-product-feeds' ),
            __( 'Logs', 'excellink-product-feeds' ),
            'manage_options',
            'elf-logs',
            [ __CLASS__, 'render_logs' ]
        );
    }

    public static function enqueue_assets( string $hook ): void {
        if ( ! str_contains( $hook, self::MENU_SLUG ) && ! str_contains( $hook, 'elf-' ) ) {
            return;
        }

        wp_enqueue_style(
            'elf-admin',
            ELF_PLUGIN_URL . 'assets/css/admin.css',
            [],
            ELF_VERSION
        );

        wp_enqueue_script(
            'elf-admin',
            ELF_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            ELF_VERSION,
            true
        );

        wp_localize_script( 'elf-admin', 'elfData', [
            'nonce'    => wp_create_nonce( self::NONCE ),
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'i18n'     => [
                'saving'       => __( 'Saving…', 'excellink-product-feeds' ),
                'saved'        => __( 'Saved!', 'excellink-product-feeds' ),
                'generating'   => __( 'Generating feeds…', 'excellink-product-feeds' ),
                'generated'    => __( 'Feeds generated!', 'excellink-product-feeds' ),
                'error'        => __( 'An error occurred. Please try again.', 'excellink-product-feeds' ),
                'loading'      => __( 'Loading taxonomy…', 'excellink-product-feeds' ),
                'exporting'    => __( 'Exporting…', 'excellink-product-feeds' ),
                'importing'    => __( 'Importing…', 'excellink-product-feeds' ),
                'imported'     => __( 'Settings imported!', 'excellink-product-feeds' ),
                'exported'     => __( 'Settings exported!', 'excellink-product-feeds' ),
            ],
        ]);
    }

    public static function render_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'excellink-product-feeds' ) );
        }
        require ELF_PLUGIN_DIR . 'admin/views/settings.php';
    }

    public static function render_category_map(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'excellink-product-feeds' ) );
        }
        require ELF_PLUGIN_DIR . 'admin/views/category-mapper.php';
    }

    public static function render_logs(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'excellink-product-feeds' ) );
        }
        require ELF_PLUGIN_DIR . 'admin/views/logs.php';
    }

    // ── AJAX handlers ────────────────────────────────────────────────────────

    public static function ajax_save_settings(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'excellink-product-feeds' ) ] );
        }

        // Rate limit: 30 requests per minute
        ELF_Rate_Limiter::check_ajax_limit( 'elf_save_settings', 30, MINUTE_IN_SECONDS );

        $settings = [
            'schedule'             => sanitize_key( wp_unslash( $_POST['schedule'] ?? 'daily' ) ),
            'batch_size'           => absint( wp_unslash( $_POST['batch_size'] ?? 200 ) ),
            'include_out_of_stock' => sanitize_key( wp_unslash( $_POST['include_out_of_stock'] ?? 'no' ) ),
            'condition'            => sanitize_text_field( wp_unslash( $_POST['condition'] ?? 'new' ) ),
            'brand_fallback'       => sanitize_text_field( wp_unslash( $_POST['brand_fallback'] ?? get_bloginfo( 'name' ) ) ),
            'enable_logging'       => sanitize_key( wp_unslash( $_POST['enable_logging'] ?? 'no' ) ),
        ];

        // Clamp batch size
        $settings['batch_size'] = min( 1000, max( 50, $settings['batch_size'] ) );

        update_option( 'elf_settings', $settings );

        // Re-sync cron with new schedule
        ELF_Cron_Handler::sync_schedule();

        wp_send_json_success( [ 'message' => __( 'Settings saved.', 'excellink-product-feeds' ) ] );
    }

    public static function ajax_regenerate_feeds(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'excellink-product-feeds' ) ] );
        }

        // Rate limit: 10 requests per minute
        ELF_Rate_Limiter::check_ajax_limit( 'elf_regenerate_feeds', 10, MINUTE_IN_SECONDS );

        // Set higher time limit for large catalogs — intentional use of set_time_limit.
        set_time_limit( 300 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged

        ELF_Logger::info( 'Starting manual feed regeneration via AJAX', 'ajax_regen' );

        $start_time = microtime( true );
        
        // Generate product feed
        $feed_ok = ( new ELF_Unified_Feed() )->generate();
        
        // Generate image sitemap (non-fatal)
        $sitemap_ok = ( new ELF_Image_Sitemap() )->generate();
        
        $end_time = microtime( true );
        $duration = round( $end_time - $start_time, 2 );

        if ( $feed_ok ) {
            ELF_Logger::info( 'Manual feed regeneration completed successfully', 'ajax_regen', [
                'duration' => $duration,
                'sitemap_ok' => $sitemap_ok
            ]);
            
            wp_send_json_success( [
                'message' => sprintf(
                    // translators: %s is the number of seconds the operation took.
                    __( 'Feed and image sitemap regenerated successfully in %s seconds.', 'excellink-product-feeds' ),
                    $duration
                ),
                'duration' => $duration,
            ]);
        } else {
            ELF_Logger::error( 'Manual feed regeneration failed', 'ajax_regen', [
                'duration' => $duration,
                'feed_ok' => $feed_ok,
                'sitemap_ok' => $sitemap_ok
            ]);
            
            wp_send_json_error( [ 'message' => __( 'Feed generation failed. Check that products exist and are published.', 'excellink-product-feeds' ) ] );
        }
    }

    public static function ajax_save_category_map(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'excellink-product-feeds' ) ] );
        }

        // Rate limit: 20 requests per minute
        ELF_Rate_Limiter::check_ajax_limit( 'elf_save_category_map', 20, MINUTE_IN_SECONDS );

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value is run through absint() below.
        $raw_map = isset( $_POST['category_map'] ) ? wp_unslash( $_POST['category_map'] ) : [];

        if ( ! is_array( $raw_map ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid data.', 'excellink-product-feeds' ) ] );
        }

        $clean = [];
        foreach ( $raw_map as $term_id => $google_id ) {
            $clean[ absint( $term_id ) ] = absint( $google_id );
        }

        ELF_Category_Mapper::save_map( $clean );

        wp_send_json_success( [ 'message' => __( 'Category mapping saved.', 'excellink-product-feeds' ) ] );
    }

    public static function ajax_search_taxonomy(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'excellink-product-feeds' ) ] );
        }

        // Rate limit: 60 requests per minute (more permissive for search)
        ELF_Rate_Limiter::check_ajax_limit( 'elf_search_taxonomy', 60, MINUTE_IN_SECONDS );

        $q = strtolower( sanitize_text_field( wp_unslash( $_POST['q'] ?? '' ) ) );

        $taxonomy = ELF_Category_Mapper::get_google_taxonomy();

        if ( empty( $taxonomy ) ) {
            wp_send_json_error( [ 'message' => __( 'Could not fetch Google taxonomy. Check your internet connection.', 'excellink-product-feeds' ) ] );
        }

        $results = [];
        foreach ( $taxonomy as $id => $label ) {
            if ( $q === '' || str_contains( strtolower( $label ), $q ) ) {
                $results[] = [ 'id' => $id, 'text' => $label ];
                if ( count( $results ) >= 50 ) break;
            }
        }

        wp_send_json_success( [ 'results' => $results ] );
    }

    public static function ajax_export_settings(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'excellink-product-feeds' ) ] );
        }

        ELF_Rate_Limiter::check_ajax_limit( 'elf_export_settings', 10, MINUTE_IN_SECONDS );

        $settings = [
            'elf_settings'     => get_option( 'elf_settings', [] ),
            'elf_category_map' => ELF_Category_Mapper::get_map(),
            'export_date'      => current_time( 'mysql' ),
            'plugin_version'   => ELF_VERSION,
        ];

        wp_send_json_success( [ 'settings' => $settings ] );
    }

    public static function ajax_import_settings(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'excellink-product-feeds' ) ] );
        }

        ELF_Rate_Limiter::check_ajax_limit( 'elf_import_settings', 10, MINUTE_IN_SECONDS );

        $settings_json = isset( $_POST['settings'] ) ? sanitize_textarea_field( wp_unslash( $_POST['settings'] ) ) : '';
        
        if ( empty( $settings_json ) ) {
            wp_send_json_error( [ 'message' => __( 'No settings data provided.', 'excellink-product-feeds' ) ] );
        }

        $settings = json_decode( $settings_json, true );
        
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( [ 'message' => __( 'Invalid settings data format.', 'excellink-product-feeds' ) ] );
        }

        // Import main settings
        if ( isset( $settings['elf_settings'] ) && is_array( $settings['elf_settings'] ) ) {
            $imported_settings = [
                'schedule'             => sanitize_key( $settings['elf_settings']['schedule'] ?? 'daily' ),
                'batch_size'           => absint( $settings['elf_settings']['batch_size'] ?? 200 ),
                'include_out_of_stock' => sanitize_key( $settings['elf_settings']['include_out_of_stock'] ?? 'no' ),
                'condition'            => sanitize_text_field( $settings['elf_settings']['condition'] ?? 'new' ),
                'brand_fallback'       => sanitize_text_field( $settings['elf_settings']['brand_fallback'] ?? get_bloginfo( 'name' ) ),
                'enable_logging'       => sanitize_key( $settings['elf_settings']['enable_logging'] ?? 'no' ),
            ];
            
            // Clamp batch size
            $imported_settings['batch_size'] = min( 1000, max( 50, $imported_settings['batch_size'] ) );
            
            update_option( 'elf_settings', $imported_settings );
        }

        // Import category map
        if ( isset( $settings['elf_category_map'] ) && is_array( $settings['elf_category_map'] ) ) {
            $clean_map = [];
            foreach ( $settings['elf_category_map'] as $term_id => $google_id ) {
                $clean_map[ absint( $term_id ) ] = absint( $google_id );
            }
            ELF_Category_Mapper::save_map( $clean_map );
        }

        // Re-sync cron schedule
        ELF_Cron_Handler::sync_schedule();

        ELF_Logger::info( 'Settings imported successfully', 'settings_import', [
            'version' => $settings['plugin_version'] ?? 'unknown',
            'export_date' => $settings['export_date'] ?? 'unknown'
        ]);

        wp_send_json_success( [ 'message' => __( 'Settings imported successfully.', 'excellink-product-feeds' ) ] );
    }

    public static function ajax_clear_logs(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'excellink-product-feeds' ) ] );
        }

        ELF_Rate_Limiter::check_ajax_limit( 'elf_clear_logs', 5, MINUTE_IN_SECONDS );

        ELF_Logger::clear_logs();
        
        ELF_Logger::info( 'Logs cleared by admin', 'log_management' );

        wp_send_json_success( [ 'message' => __( 'Logs cleared successfully.', 'excellink-product-feeds' ) ] );
    }
}
