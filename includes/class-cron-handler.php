<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ELF_Cron_Handler {

    /** Separate hook for the debounced single-event regen, so it never gets
     *  starved by the recurring cron event already being scheduled. */
    private const REGEN_HOOK = 'elf_debounced_regen_cron';

    public static function init(): void {
        add_action( 'elf_generate_feeds_cron', [ __CLASS__, 'run_feeds' ] );
        add_action( self::REGEN_HOOK,           [ __CLASS__, 'run_feeds' ] );
        add_action( 'elf_fetch_taxonomy',       [ 'ELF_Category_Mapper', 'fetch_taxonomy' ] );

        // Re-schedule if the saved schedule differs from the active cron event.
        // Only checked on admin_init (not every front-end request) since it's
        // also called explicitly right after settings are saved/imported.
        add_action( 'admin_init', [ __CLASS__, 'sync_schedule' ] );

        // Prime taxonomy cache in background if missing (runs on admin page loads only)
        if ( is_admin() ) {
            add_action( 'admin_init', [ 'ELF_Category_Mapper', 'maybe_schedule_fetch' ] );
        }

        // Async regeneration on product save (debounced via single event)
        add_action( 'save_post_product', [ __CLASS__, 'schedule_single_regen' ] );
        add_action( 'woocommerce_update_product', [ __CLASS__, 'schedule_single_regen' ] );
    }

    public static function run_feeds(): void {
        ELF_Logger::info( 'Starting scheduled feed generation', 'cron_run' );
        
        try {
            $feed_ok = ( new ELF_Unified_Feed() )->generate();
            $sitemap_ok = ( new ELF_Image_Sitemap() )->generate();
            
            if ( $feed_ok ) {
                ELF_Logger::info( 'Scheduled feed generation completed successfully', 'cron_run', [
                    'feed_ok' => $feed_ok,
                    'sitemap_ok' => $sitemap_ok
                ]);
            } else {
                ELF_Logger::error( 'Scheduled feed generation failed', 'cron_run', [
                    'feed_ok' => $feed_ok,
                    'sitemap_ok' => $sitemap_ok
                ]);
            }
        } catch ( Exception $e ) {
            ELF_Logger::error( 'Exception during scheduled feed generation', 'cron_run', [
                'exception_message' => $e->getMessage(),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine()
            ]);
        }
    }

    /** Called on product save — schedules a one-off regen 60 s later, on its
     *  own hook so it isn't blocked by the recurring cron event. */
    public static function schedule_single_regen( int $post_id ): void {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        if ( ! wp_next_scheduled( self::REGEN_HOOK ) ) {
            wp_schedule_single_event( time() + 60, self::REGEN_HOOK );
        }
    }

    public static function sync_schedule(): void {
        $settings = get_option( 'elf_settings', [] );
        $wanted   = $settings['schedule'] ?? 'daily';
        if ( ! array_key_exists( $wanted, wp_get_schedules() ) ) {
            $wanted = 'daily';
        }
        $existing = wp_get_schedule( 'elf_generate_feeds_cron' );

        if ( $existing !== $wanted ) {
            $timestamp = wp_next_scheduled( 'elf_generate_feeds_cron' );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, 'elf_generate_feeds_cron' );
            }
            wp_schedule_event( time(), $wanted, 'elf_generate_feeds_cron' );
        }
    }
}
