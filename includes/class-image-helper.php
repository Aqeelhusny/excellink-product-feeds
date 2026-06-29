<?php
if ( ! defined( 'ABSPATH' ) exit;

/**
 * Image helper functions for feed generation.
 * Provides shared image URL logic across different feed generators.
 */
class ELF_Image_Helper {

    /**
     * Returns a stable, crawlable image URL.
     * 
     * This method ensures consistent image URLs that work well with feed crawlers:
     * - Uses wp_get_original_image_url when available (WordPress 5.3+)
     * - Falls back to full-size image URL
     * - Removes query parameters to prevent cache busting issues
     * - Enforces consistent URL scheme (http/https based on current request)
     * 
     * @param int $attachment_id The attachment ID
     * @return string Stable image URL or empty string on failure
     */
    public static function get_stable_image_url( int $attachment_id ): string {
        $url = function_exists( 'wp_get_original_image_url' )
            ? wp_get_original_image_url( $attachment_id )
            : false;

        if ( ! $url ) {
            $src = wp_get_attachment_image_src( $attachment_id, 'full' );
            $url = $src ? $src[0] : '';
        }

        if ( ! $url ) {
            return '';
        }

        // Remove query parameters to prevent cache busting
        $url = (string) strtok( $url, '?' );
        
        // Enforce consistent URL scheme
        $url = set_url_scheme( $url, is_ssl() ? 'https' : 'http' );

        return esc_url_raw( $url );
    }

    /**
     * Get image URL for a product with parent fallback for variations.
     * 
     * @param WC_Product $product The product object
     * @return string Image URL or empty string
     */
    public static function get_product_image_url( WC_Product $product ): string {
        $image_id = $product->get_image_id();

        // Fallback to parent image for variations without their own image
        if ( ! $image_id && $product->is_type( 'variation' ) ) {
            $parent   = wc_get_product( $product->get_parent_id() );
            $image_id = $parent ? $parent->get_image_id() : 0;
        }

        if ( ! $image_id ) {
            return '';
        }

        return self::get_stable_image_url( $image_id );
    }

    /**
     * Get additional image URLs for a product (gallery images).
     * 
     * @param WC_Product $product The product object
     * @param int $limit Maximum number of additional images to return
     * @return array Array of image URLs
     */
    public static function get_additional_image_urls( WC_Product $product, int $limit = 10 ): array {
        $gallery_ids = $product->get_gallery_image_ids();
        $urls = [];

        foreach ( $gallery_ids as $image_id ) {
            if ( count( $urls ) >= $limit ) {
                break;
            }

            $url = self::get_stable_image_url( (int) $image_id );
            if ( $url ) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /**
     * Get all image URLs for a product (featured + gallery).
     * 
     * @param WC_Product $product The product object
     * @param int $limit Maximum number of total images to return
     * @return array Array of image URLs
     */
    public static function get_all_image_urls( WC_Product $product, int $limit = 11 ): array {
        $urls = [];

        // Featured image
        $featured_url = self::get_product_image_url( $product );
        if ( $featured_url ) {
            $urls[] = $featured_url;
        }

        // Gallery images
        $additional_urls = self::get_additional_image_urls( $product, $limit - 1 );
        $urls = array_merge( $urls, $additional_urls );

        return array_slice( $urls, 0, $limit );
    }
}
