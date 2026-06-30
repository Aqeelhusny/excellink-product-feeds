<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Google Image Sitemap generator.
 *
 * Produces /excellink-feeds/image-sitemap.xml — a standard sitemap extended
 * with <image:image> elements per Google's Image Sitemap spec.
 *
 * Why this matters for crawl rate:
 *  - The Shopping feed XML is consumed by the product importer, not by
 *    Googlebot-Image. Google's image crawler reads sitemaps independently.
 *  - Submitting this sitemap in Search Console tells Googlebot-Image exactly
 *    which images exist, their canonical URLs, and when they last changed.
 *  - <lastmod> on each <url> tells Google when to re-crawl — critical for
 *    stores that frequently update product photos.
 *  - Every product URL lists ALL its images (featured + gallery + variation
 *    images), so Google discovers photos that may not be linked in the HTML.
 */
class ELF_Image_Sitemap {

    private const SITEMAP_FILENAME = 'image-sitemap.xml';
    private const SITEMAP_NS       = 'http://www.sitemaps.org/schemas/sitemap/0.9';
    private const IMAGE_NS         = 'http://www.google.com/schemas/sitemap-image/1.1';

    private int    $batch_size;
    private bool   $include_out_of_stock;

    public function __construct() {
        $settings                   = get_option( 'elf_settings', [] );
        $this->batch_size           = absint( $settings['batch_size'] ?? 200 );
        $this->include_out_of_stock = ( ( $settings['include_out_of_stock'] ?? 'no' ) === 'yes' );
    }

    public function get_sitemap_url(): string {
        $upload = wp_upload_dir();
        return trailingslashit( $upload['baseurl'] ) . 'excellink-feeds/' . self::SITEMAP_FILENAME;
    }

    public function get_sitemap_path(): string {
        $upload = wp_upload_dir();
        return trailingslashit( $upload['basedir'] ) . 'excellink-feeds/' . self::SITEMAP_FILENAME;
    }

    public function generate(): bool {
        ELF_Logger::info( 'Starting image sitemap generation', 'sitemap_generation' );
        
        $ids = $this->load_product_ids();
        if ( empty( $ids ) ) {
            ELF_Logger::warning( 'No products found for sitemap generation', 'sitemap_generation' );
            return false;
        }

        ELF_Logger::info( 'Loaded products for sitemap generation', 'sitemap_generation', [ 'count' => count( $ids ) ] );

        // Bulk-prime all product meta and term caches before the loop
        _prime_post_caches( $ids, false, true );
        update_object_term_cache( $ids, 'product' );

        // Collect every attachment ID we'll need across all products
        $attachment_ids = [];
        $products       = [];

        foreach ( $ids as $id ) {
            $product = wc_get_product( $id );
            if ( ! $product ) continue;
            $products[] = $product;

            $img = $product->get_image_id();
            if ( $img ) $attachment_ids[] = (int) $img;
            foreach ( $product->get_gallery_image_ids() as $gid ) {
                $attachment_ids[] = (int) $gid;
            }

            if ( $product->is_type( 'variable' ) ) {
                $var_ids = $product->get_children();
                if ( ! empty( $var_ids ) ) {
                    _prime_post_caches( $var_ids, false, true );
                    foreach ( $product->get_available_variations( 'objects' ) as $variation ) {
                        $vid = $variation->get_image_id();
                        if ( $vid ) $attachment_ids[] = (int) $vid;
                    }
                }
            }
        }

        // Prime all attachment meta in one query — covers _wp_attached_file,
        // _wp_attachment_metadata (needed by wp_get_original_image_url), and alt text
        if ( ! empty( $attachment_ids ) ) {
            _prime_post_caches( array_unique( $attachment_ids ), false, true );
        }

        $dom = $this->build_dom( $products );
        $result = $this->write_dom( $dom );
        
        if ( $result ) {
            // Validate the generated sitemap
            global $wp_filesystem;
            if ( empty( $wp_filesystem ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            $sitemap_content = $wp_filesystem->get_contents( $this->get_sitemap_path() );
            if ( $sitemap_content ) {
                $validation = ELF_Feed_Validator::validate_sitemap( $sitemap_content );
                
                if ( ! $validation['valid'] ) {
                    ELF_Logger::error( 'Sitemap validation failed', 'sitemap_validation', [
                        'errors' => $validation['errors']
                    ] );
                } elseif ( ! empty( $validation['warnings'] ) ) {
                    ELF_Logger::warning( 'Sitemap validation warnings', 'sitemap_validation', [
                        'warnings' => $validation['warnings']
                    ] );
                }
            }
            
            ELF_Logger::info( 'Image sitemap generated successfully', 'sitemap_generation', [ 'product_count' => count( $products ) ] );
        } else {
            ELF_Logger::error( 'Image sitemap generation failed', 'sitemap_generation' );
        }
        
        return $result;
    }

    private function load_product_ids(): array {
        $args = [
            'status'   => 'publish',
            'type'     => [ 'simple', 'variable' ],
            'limit'    => $this->batch_size,
            'paginate' => false,
            'return'   => 'ids',
        ];

        if ( ! $this->include_out_of_stock ) {
            $args['stock_status'] = 'instock';
        }

        return wc_get_products( $args );
    }

    private function build_dom( array $products ): DOMDocument {
        $dom = new DOMDocument( '1.0', 'UTF-8' );
        $dom->formatOutput = true;

        $urlset = $dom->createElementNS( self::SITEMAP_NS, 'urlset' );
        $urlset->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:image',
            self::IMAGE_NS
        );
        $dom->appendChild( $urlset );

        foreach ( $products as $product ) {
            $url_el = $this->build_url_entry( $dom, $product );
            if ( $url_el ) {
                $urlset->appendChild( $url_el );
            }
        }

        return $dom;
    }

    private function build_url_entry( DOMDocument $dom, WC_Product $product ): ?DOMElement {
        $permalink = esc_url_raw( get_permalink( $product->get_id() ) );
        if ( ! $permalink ) return null;

        // Collect all image IDs: featured → gallery → variation-specific
        $image_ids = [];
        $main_id   = (int) $product->get_image_id();

        if ( $main_id ) $image_ids[] = $main_id;

        foreach ( $product->get_gallery_image_ids() as $gid ) {
            $image_ids[] = (int) $gid;
        }

        if ( $product->is_type( 'variable' ) ) {
            foreach ( $product->get_available_variations( 'objects' ) as $variation ) {
                $vid = (int) $variation->get_image_id();
                if ( $vid && $vid !== $main_id ) {
                    $image_ids[] = $vid;
                }
            }
        }

        $image_ids = array_unique( $image_ids );
        if ( empty( $image_ids ) ) return null;

        $url_el = $dom->createElement( 'url' );

        $loc = $dom->createElement( 'loc' );
        $loc->appendChild( $dom->createTextNode( $permalink ) );
        $url_el->appendChild( $loc );

        // lastmod — W3C date format (YYYY-MM-DD)
        $modified = $product->get_date_modified();
        if ( $modified ) {
            $lastmod = $dom->createElement( 'lastmod' );
            $lastmod->appendChild( $dom->createTextNode( $modified->date( 'Y-m-d' ) ) );
            $url_el->appendChild( $lastmod );
        }

        foreach ( $image_ids as $img_id ) {
            $url = $this->get_stable_image_url( $img_id );
            if ( ! $url ) continue;

            $image_el = $dom->createElementNS( self::IMAGE_NS, 'image:image' );

            $image_loc = $dom->createElementNS( self::IMAGE_NS, 'image:loc' );
            $image_loc->appendChild( $dom->createTextNode( $url ) );
            $image_el->appendChild( $image_loc );

            // image:title — alt text is more descriptive; fall back to product name
            $alt        = get_post_meta( $img_id, '_wp_attachment_image_alt', true );
            $title_text = sanitize_text_field( $alt ?: $product->get_name() );
            if ( $title_text ) {
                $image_title = $dom->createElementNS( self::IMAGE_NS, 'image:title' );
                $image_title->appendChild( $dom->createCDATASection( $title_text ) );
                $image_el->appendChild( $image_title );
            }

            $url_el->appendChild( $image_el );
        }

        return $url_el;
    }

    /**
     * Returns a stable, crawlable image URL.
     * Uses the shared ELF_Image_Helper for consistency.
     */
    private function get_stable_image_url( int $attachment_id ): string {
        return ELF_Image_Helper::get_stable_image_url( $attachment_id );
    }

    private function write_dom( DOMDocument $dom ): bool {
        $path = $this->get_sitemap_path();
        $dir  = dirname( $path );

        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }

        $tmp = $path . '.tmp';
        $xml = $dom->saveXML();

        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if ( ! $wp_filesystem->put_contents( $tmp, $xml, FS_CHMOD_FILE ) ) {
            return false;
        }

        return $wp_filesystem->move( $tmp, $path, true );
    }
}
