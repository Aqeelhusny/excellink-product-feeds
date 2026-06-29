<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ELF_Category_Mapper {

    private const TAXONOMY_TRANSIENT = 'elf_google_taxonomy';
    private const TAXONOMY_URL       = 'https://www.google.com/basepages/producttype/taxonomy-with-ids.en-US.txt';
    private const MAP_OPTION         = 'elf_category_map';

    /**
     * Return the cached Google taxonomy array.
     * Never fetches inline — call fetch_taxonomy_async() to prime the cache.
     *
     * @return array<int, string>  id => label
     */
    public static function get_google_taxonomy(): array {
        $cached = get_transient( self::TAXONOMY_TRANSIENT );
        return is_array( $cached ) ? $cached : [];
    }

    /**
     * Fetch taxonomy from Google and store as a transient.
     * Called from WP Cron so it never blocks a user request.
     */
    public static function fetch_taxonomy(): void {
        ELF_Logger::info( 'Fetching Google taxonomy', 'taxonomy_fetch' );
        
        $response = wp_remote_get( self::TAXONOMY_URL, [ 'timeout' => 30 ] );

        if ( is_wp_error( $response ) ) {
            ELF_Logger::error( 'Failed to fetch Google taxonomy - WP Error', 'taxonomy_fetch', [
                'error_message' => $response->get_error_message()
            ]);
            return;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $response_code ) {
            ELF_Logger::error( 'Failed to fetch Google taxonomy - HTTP error', 'taxonomy_fetch', [
                'response_code' => $response_code
            ]);
            return;
        }

        $body  = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            ELF_Logger::error( 'Empty response body from Google taxonomy', 'taxonomy_fetch' );
            return;
        }

        $lines = explode( "\n", $body );
        $map   = [];

        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( empty( $line ) || str_starts_with( $line, '#' ) ) {
                continue;
            }
            if ( preg_match( '/^(\d+)\s+-\s+(.+)$/', $line, $m ) ) {
                $map[ (int) $m[1] ] = $m[2];
            }
        }

        if ( ! empty( $map ) ) {
            set_transient( self::TAXONOMY_TRANSIENT, $map, WEEK_IN_SECONDS );
            ELF_Logger::info( 'Google taxonomy fetched successfully', 'taxonomy_fetch', [
                'category_count' => count( $map )
            ]);
        } else {
            ELF_Logger::warning( 'Google taxonomy fetched but no categories parsed', 'taxonomy_fetch' );
        }
    }

    /**
     * Schedule a one-off background fetch if the taxonomy cache is missing.
     * Safe to call on every admin page load — does nothing if already cached.
     */
    public static function maybe_schedule_fetch(): void {
        if ( false !== get_transient( self::TAXONOMY_TRANSIENT ) ) {
            return;
        }
        if ( ! wp_next_scheduled( 'elf_fetch_taxonomy' ) ) {
            wp_schedule_single_event( time(), 'elf_fetch_taxonomy' );
        }
    }

    /** Retrieve stored WC term_id → Google taxonomy ID mappings. */
    public static function get_map(): array {
        $map = get_option( self::MAP_OPTION, [] );
        return is_array( $map ) ? $map : [];
    }

    /** Persist the full mapping array (wc_term_id => google_taxonomy_id). */
    public static function save_map( array $map ): void {
        update_option( self::MAP_OPTION, array_map( 'absint', $map ) );
    }

    /** Resolve a product's Google category ID from its WC categories.
     *  Uses get_the_terms() which reads from the WP object cache primed in load_products(). */
    public static function resolve_google_category( WC_Product $product ): string {
        static $map = null;
        if ( null === $map ) {
            $map = self::get_map();
        }

        $post_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
        $terms   = get_the_terms( $post_id, 'product_cat' );

        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            return '';
        }

        foreach ( $terms as $term ) {
            if ( ! empty( $map[ $term->term_id ] ) ) {
                return (string) absint( $map[ $term->term_id ] );
            }
        }

        return '';
    }

    /** Human-readable category path for product_type field.
     *  get_the_terms() hits the WP object cache — no extra DB query. */
    public static function get_product_type_path( WC_Product $product ): string {
        $post_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
        $terms   = get_the_terms( $post_id, 'product_cat' );
        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            return '';
        }

        $term  = reset( $terms );
        $path  = [ $term->name ];
        $depth = 0;

        while ( $term->parent && $depth < 5 ) {
            $parent = get_term( $term->parent, 'product_cat' );
            if ( is_wp_error( $parent ) || ! $parent ) break;
            array_unshift( $path, $parent->name );
            $term = $parent;
            $depth++;
        }

        return implode( ' > ', $path );
    }
}
