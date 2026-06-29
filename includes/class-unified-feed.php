<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Unified product feed — Google Shopping RSS 2.0 format.
 * Facebook Catalog natively accepts this format, so one feed serves both platforms.
 *
 * Google required:  id, title, description, link, image_link, availability, price, condition
 * Google recommended: sale_price, google_product_category, product_type, brand, gtin, mpn, item_group_id
 * Facebook extras:  color, size, material, pattern (mapped from variation attributes)
 */
class ELF_Unified_Feed extends ELF_Feed_Generator {

    public function __construct() {
        parent::__construct();
        $this->feed_filename = 'product-feed.xml';
    }

    public function generate(): bool {
        ELF_Logger::info( 'Starting feed generation', 'feed_generation' );
        
        $products = $this->load_products();

        if ( empty( $products ) ) {
            ELF_Logger::warning( 'No products found for feed generation', 'feed_generation' );
            return false;
        }

        ELF_Logger::info( 'Loaded products for feed generation', 'feed_generation', [ 'count' => count( $products ) ] );

        $dom = $this->build_dom( $products );
        $ok  = $this->write_dom( $dom );

        if ( $ok ) {
            // Validate the generated feed
            $feed_content = file_get_contents( $this->get_feed_path() );
            if ( $feed_content ) {
                $validation = ELF_Feed_Validator::validate_feed( $feed_content );
                
                if ( ! $validation['valid'] ) {
                    ELF_Logger::error( 'Feed validation failed', 'feed_validation', [
                        'errors' => $validation['errors']
                    ] );
                } elseif ( ! empty( $validation['warnings'] ) ) {
                    ELF_Logger::warning( 'Feed validation warnings', 'feed_validation', [
                        'warnings' => $validation['warnings']
                    ] );
                }
            }
            
            update_option( 'elf_feed_last_generated',    current_time( 'mysql' ) );
            update_option( 'elf_feed_product_count',     $this->last_item_count );
            update_option( 'elf_feed_skipped_no_image',  $this->skipped_no_image );
            update_option( 'elf_feed_skipped_no_price',  $this->skipped_no_price );
            
            ELF_Logger::info( 'Feed generated successfully', 'feed_generation', [
                'product_count' => $this->last_item_count,
                'skipped_no_image' => $this->skipped_no_image,
                'skipped_no_price' => $this->skipped_no_price
            ]);
        } else {
            ELF_Logger::error( 'Feed generation failed', 'feed_generation' );
        }

        return $ok;
    }

    protected function build_dom( array $products ): DOMDocument {
        $dom = new DOMDocument( '1.0', 'UTF-8' );
        $dom->formatOutput = true;

        $rss = $dom->createElement( 'rss' );
        $rss->setAttribute( 'version', '2.0' );
        $rss->setAttribute( 'xmlns:g', 'http://base.google.com/ns/1.0' );
        $dom->appendChild( $rss );

        $channel = $dom->createElement( 'channel' );
        $rss->appendChild( $channel );

        $this->add_cdata( $dom, $channel, 'title', get_bloginfo( 'name' ) );
        $this->add_cdata( $dom, $channel, 'link', get_bloginfo( 'url' ) );
        $this->add_cdata( $dom, $channel, 'description', 'Product feed for Google Shopping & Facebook Catalog' );
        // lastBuildDate tells Google exactly when this feed was regenerated,
        // so it knows to come back and re-crawl images on the next scheduled fetch.
        $this->add_text( $dom, $channel, 'lastBuildDate', gmdate( 'D, d M Y H:i:s O' ) );

        $this->last_item_count   = 0;
        $this->skipped_no_image  = 0;
        $this->skipped_no_price  = 0;

        foreach ( $products as $product ) {
            $item = $this->build_item( $dom, $product );
            if ( $item ) {
                $channel->appendChild( $item );
                $this->last_item_count++;
            }
        }

        return $dom;
    }

    private function build_item( DOMDocument $dom, WC_Product $product ): ?DOMElement {
        $image = $this->get_image_url( $product );

        if ( ! $image ) {
            $this->skipped_no_image++;
            return null;
        }

        if ( ! $product->get_regular_price() ) {
            $this->skipped_no_price++;
            return null;
        }

        $is_variation = $product->is_type( 'variation' );
        $parent_id    = $is_variation ? $product->get_parent_id() : $product->get_id();
        $permalink    = $this->get_permalink( $product );
        $description  = $this->clean_description(
            $product->get_description() ?: $product->get_short_description()
        );

        // Fall back to parent description for variations
        if ( ! $description && $is_variation ) {
            $parent      = wc_get_product( $parent_id );
            $description = $parent
                ? $this->clean_description( $parent->get_description() ?: $parent->get_short_description() )
                : '';
        }

        // Enforce Google field length limits
        $title       = mb_substr( $product->get_name(), 0, 150 );
        $description = mb_substr( $description ?: $title, 0, 5000 ); // fallback to title if empty

        $item = $dom->createElement( 'item' );

        // pubDate signals Google when this item last changed so images get re-crawled
        $pub = $this->get_modified_rfc( $product );
        if ( $pub ) {
            $this->add_text( $dom, $item, 'pubDate', $pub );
        }

        // ── Required ──────────────────────────────────────────────────────────
        $this->g( $dom, $item, 'id',          (string) $product->get_id() );
        $this->g( $dom, $item, 'title',        $title );
        $this->g( $dom, $item, 'description',  $description );
        $this->g( $dom, $item, 'link',         $permalink );
        $this->g( $dom, $item, 'image_link',   $image );
        $this->g( $dom, $item, 'availability', $this->get_availability( $product ) );
        $this->g( $dom, $item, 'price',        $this->get_price_string( $product ) );
        $this->g( $dom, $item, 'condition',    $this->condition );

        // ── Recommended ───────────────────────────────────────────────────────
        $sale = $this->get_sale_price_string( $product );
        if ( $sale ) {
            $this->g( $dom, $item, 'sale_price', $sale );
        }

        $google_cat = ELF_Category_Mapper::resolve_google_category( $product );
        if ( $google_cat ) {
            $this->g( $dom, $item, 'google_product_category', $google_cat );
        }

        $product_type = ELF_Category_Mapper::get_product_type_path( $product );
        if ( $product_type ) {
            $this->g( $dom, $item, 'product_type', $product_type );
        }

        $this->g( $dom, $item, 'brand', mb_substr( $this->get_brand( $product ), 0, 70 ) );

        $sku  = mb_substr( $product->get_sku(), 0, 70 );
        $gtin = $this->get_gtin( $product );

        if ( $gtin ) {
            $this->g( $dom, $item, 'gtin', $gtin );
        }
        if ( $sku ) {
            $this->g( $dom, $item, 'mpn', $sku );
        }

        // identifier_exists = no when no unique product identifier exists (GTIN or MPN)
        if ( ! $gtin && ! $sku ) {
            $this->g( $dom, $item, 'identifier_exists', 'no' );
        }

        // Variations — link back to parent for item_group_id
        if ( $is_variation ) {
            $this->g( $dom, $item, 'item_group_id', (string) $parent_id );
            $this->add_variation_attributes( $dom, $item, $product );
        }

        // Additional images (up to 10)
        foreach ( $this->get_additional_image_urls( $product ) as $extra_img ) {
            $this->g( $dom, $item, 'additional_image_link', $extra_img );
        }

        return $item;
    }

    /**
     * Map variation attributes to both Google custom labels and Facebook
     * standardised fields (color, size, material, pattern).
     */
    private function add_variation_attributes( DOMDocument $dom, DOMElement $item, WC_Product $product ): void {
        $attrs         = $product->get_variation_attributes();
        $custom_label  = 0;

        $fb_field_map = [
            'color'    => 'color',
            'colour'   => 'color',
            'size'     => 'size',
            'material' => 'material',
            'pattern'  => 'pattern',
        ];

        foreach ( $attrs as $attr_key => $raw_value ) {
            $value = sanitize_text_field( $raw_value );
            if ( '' === $value ) continue;

            // Resolve taxonomy term labels (e.g. pa_color slug → "Red")
            if ( str_starts_with( $attr_key, 'attribute_pa_' ) ) {
                $taxonomy = str_replace( 'attribute_', '', $attr_key );
                $term     = get_term_by( 'slug', $value, $taxonomy );
                if ( $term && ! is_wp_error( $term ) ) {
                    $value = $term->name;
                }
            }

            // Normalise attr name for matching
            $name = strtolower( str_replace( [ 'attribute_pa_', 'attribute_', 'pa_' ], '', $attr_key ) );

            // Facebook standardised field
            if ( isset( $fb_field_map[ $name ] ) ) {
                $this->g( $dom, $item, $fb_field_map[ $name ], $value );
            }

            // Google custom label (0–4)
            if ( $custom_label < 5 ) {
                $this->g( $dom, $item, 'custom_label_' . $custom_label, $value );
                $custom_label++;
            }
        }
    }

    private function g( DOMDocument $dom, DOMElement $parent, string $tag, string $value ): void {
        $el = $dom->createElement( 'g:' . $tag );
        $el->appendChild( $dom->createCDATASection( $value ) );
        $parent->appendChild( $el );
    }
}
