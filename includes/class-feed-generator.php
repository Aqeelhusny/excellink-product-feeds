<?php
if ( ! defined( 'ABSPATH' ) ) exit;

abstract class ELF_Feed_Generator {

    protected string $feed_filename;
    protected int    $batch_size;
    protected bool   $include_out_of_stock;
    protected string $condition;
    protected string $brand_fallback;
    protected int    $last_item_count    = 0;
    protected int    $skipped_no_image  = 0;
    protected int    $skipped_no_price  = 0;

    /** Bulk-primed caches — populated once before the product loop. */
    private array $permalink_cache   = [];
    private array $image_url_cache   = [];
    private string $currency         = '';

    public function __construct() {
        $settings = get_option( 'elf_settings', [] );

        $this->batch_size           = absint( $settings['batch_size'] ?? 200 );
        $this->include_out_of_stock = ( ( $settings['include_out_of_stock'] ?? 'no' ) === 'yes' );
        $this->condition            = sanitize_text_field( $settings['condition'] ?? 'new' );
        $this->brand_fallback       = sanitize_text_field( $settings['brand_fallback'] ?? get_bloginfo( 'name' ) );
        $this->currency             = get_woocommerce_currency();
    }

    abstract public function generate(): bool;
    abstract protected function build_dom( array $products ): DOMDocument;

    protected function get_feed_path(): string {
        $upload = wp_upload_dir();
        return trailingslashit( $upload['basedir'] ) . 'excellink-feeds/' . $this->feed_filename;
    }

    protected function get_feed_url(): string {
        $upload = wp_upload_dir();
        return trailingslashit( $upload['baseurl'] ) . 'excellink-feeds/' . $this->feed_filename;
    }

    // ── Product loading ───────────────────────────────────────────────────────

    protected function load_products(): array {
        $args = [
            'status'   => 'publish',
            'type'     => [ 'simple', 'variable' ],
            'limit'    => $this->batch_size,
            'paginate' => false,
            'return'   => 'ids',   // IDs only — avoid loading full objects twice
        ];

        if ( ! $this->include_out_of_stock ) {
            $args['stock_status'] = 'instock';
        }

        $ids = wc_get_products( $args );

        // Prime WordPress object caches for posts + meta in two bulk queries
        _prime_post_caches( $ids, false, true );
        update_object_term_cache( $ids, 'product' );

        $products = [];
        foreach ( $ids as $id ) {
            $product = wc_get_product( $id );
            if ( ! $product ) continue;

            if ( $product->is_type( 'variable' ) ) {
                $variation_ids = $product->get_children();
                // Prime variation caches before instantiating
                _prime_post_caches( $variation_ids, false, true );
                update_object_term_cache( $variation_ids, 'product_variation' );

                foreach ( $product->get_available_variations( 'objects' ) as $variation ) {
                    $products[] = $variation;
                }
            } else {
                $products[] = $product;
            }
        }

        // Prime all attachment image caches in one shot
        $this->prime_image_cache( $products );

        // Pre-warm permalink cache (one rewrite lookup per post type, not per post)
        $this->prime_permalink_cache( $products );

        return $products;
    }

    /**
     * Collect every attachment ID needed across all products and prime
     * their meta in a single bulk query.
     *
     * Critical edge case: a variation with no own image falls back to its
     * parent's image inside get_image_url(). If only variation IDs are primed
     * the parent's attachment meta is a cache miss, which causes wp_get_attachment_url()
     * to return false on strict object-cache backends (Memcached, Redis with
     * 'miss' ≠ 'not set' semantics). We collect parent image IDs here so the
     * single _prime_post_caches() call covers everything.
     */
    private function prime_image_cache( array $products ): void {
        $attachment_ids = [];

        // Build a parent_id → parent_object map so we can look up parent images
        // without extra DB calls (parents are already in the WC runtime cache
        // from the load_products() loop above).
        $parent_image_cache = [];

        foreach ( $products as $product ) {
            $img_id = (int) $product->get_image_id();

            if ( ! $img_id && $product->is_type( 'variation' ) ) {
                $parent_id = $product->get_parent_id();
                if ( ! isset( $parent_image_cache[ $parent_id ] ) ) {
                    $parent = wc_get_product( $parent_id ); // WC runtime cache hit
                    $parent_image_cache[ $parent_id ] = $parent ? (int) $parent->get_image_id() : 0;
                }
                $img_id = $parent_image_cache[ $parent_id ];
            }

            if ( $img_id ) {
                $attachment_ids[] = $img_id;
            }
            foreach ( $product->get_gallery_image_ids() as $gid ) {
                $attachment_ids[] = (int) $gid;
            }
        }

        if ( ! empty( $attachment_ids ) ) {
            _prime_post_caches( array_unique( $attachment_ids ), false, true );
        }
    }

    /**
     * Pre-resolve permalinks for all product IDs into a local array.
     * Variations use WC's get_permalink() which appends ?attribute_pa_color=red etc.,
     * landing the shopper on the pre-selected variant as Google's spec requires.
     */
    private function prime_permalink_cache( array $products ): void {
        foreach ( $products as $product ) {
            $id = $product->get_id();
            if ( ! isset( $this->permalink_cache[ $id ] ) ) {
                $this->permalink_cache[ $id ] = $product->is_type( 'variation' )
                    ? esc_url_raw( $product->get_permalink() )
                    : esc_url_raw( get_permalink( $id ) );
            }
        }
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    protected function write_dom( DOMDocument $dom ): bool {
        $path = $this->get_feed_path();
        $dir  = dirname( $path );

        if ( ! is_dir( $dir ) ) {
            if ( ! wp_mkdir_p( $dir ) ) {
                ELF_Logger::error( 'Failed to create feed directory', 'feed_generation', [ 'path' => $dir ] );
                return false;
            }
        }

        $tmp = $path . '.tmp';
        $xml = $dom->saveXML();

        if ( false === $xml ) {
            ELF_Logger::error( 'Failed to generate XML from DOM', 'feed_generation' );
            return false;
        }

        $write_result = file_put_contents( $tmp, $xml );
        if ( false === $write_result ) {
            ELF_Logger::error( 'Failed to write temp feed file', 'feed_generation', [ 'path' => $tmp ] );
            return false;
        }

        if ( ! rename( $tmp, $path ) ) {
            ELF_Logger::error( 'Failed to rename temp feed file to final path', 'feed_generation', [ 'temp' => $tmp, 'final' => $path ] );
            @unlink( $tmp );
            return false;
        }

        return true;
    }

    // ── Field helpers ─────────────────────────────────────────────────────────

    protected function get_permalink( WC_Product $product ): string {
        $id = $product->get_id();
        return $this->permalink_cache[ $id ] ?? (
            $product->is_type( 'variation' )
                ? esc_url_raw( $product->get_permalink() )
                : esc_url_raw( get_permalink( $id ) )
        );
    }

    protected function get_price_string( WC_Product $product ): string {
        return number_format( (float) $product->get_regular_price(), 2, '.', '' ) . ' ' . $this->currency;
    }

    protected function get_sale_price_string( WC_Product $product ): string {
        if ( ! $product->is_on_sale() ) {
            return '';
        }
        return number_format( (float) $product->get_sale_price(), 2, '.', '' ) . ' ' . $this->currency;
    }

    protected function get_availability( WC_Product $product ): string {
        if ( $product->is_in_stock() ) {
            return 'in_stock';
        }
        if ( $product->is_on_backorder() ) {
            return 'backorder';
        }
        return 'out_of_stock';
    }

    protected function get_image_url( WC_Product $product ): string {
        return ELF_Image_Helper::get_product_image_url( $product );
    }

    protected function get_additional_image_urls( WC_Product $product ): array {
        return ELF_Image_Helper::get_additional_image_urls( $product, 10 );
    }

    /** RFC 2822 modification date for a product — used for RSS pubDate and sitemap lastmod. */
    protected function get_modified_rfc( WC_Product $product ): string {
        $date = $product->get_date_modified();
        return $date ? $date->date( 'D, d M Y H:i:s O' ) : '';
    }

    protected function get_modified_w3c( WC_Product $product ): string {
        $date = $product->get_date_modified();
        return $date ? $date->date( 'Y-m-d' ) : '';
    }

    protected function get_brand( WC_Product $product ): string {
        $brand = $product->get_attribute( 'brand' )
              ?: $product->get_attribute( 'pa_brand' )
              ?: $this->brand_fallback;
        return sanitize_text_field( $brand );
    }

    protected function get_gtin( WC_Product $product ): string {
        $raw = $product->get_meta( '_gtin' )
            ?: $product->get_meta( '_upc' )
            ?: $product->get_meta( '_ean' )
            ?: '';

        if ( ! $raw ) return '';

        // Digits only; valid GTIN lengths are 8, 12, 13, 14
        $gtin = preg_replace( '/\D/', '', $raw );
        $len  = strlen( $gtin );

        if ( ! in_array( $len, [ 8, 12, 13, 14 ], true ) ) return '';
        if ( ltrim( $gtin, '0' ) === '' ) return ''; // reject all-zeros

        return $gtin;
    }

    protected function clean_description( string $text ): string {
        return wp_strip_all_tags( wp_specialchars_decode( $text, ENT_QUOTES ) );
    }

    protected function add_cdata( DOMDocument $dom, DOMElement $parent, string $tag, string $value ): void {
        $el = $dom->createElement( $tag );
        $el->appendChild( $dom->createCDATASection( $value ) );
        $parent->appendChild( $el );
    }

    protected function add_text( DOMDocument $dom, DOMElement $parent, string $tag, string $value ): void {
        $el = $dom->createElement( $tag );
        $el->appendChild( $dom->createTextNode( $value ) );
        $parent->appendChild( $el );
    }
}
