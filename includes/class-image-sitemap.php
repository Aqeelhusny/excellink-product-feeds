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
 *
 * Extends ELF_Feed_Generator to reuse the shared, paginated product loader
 * and the atomic tmp-file write path instead of duplicating them.
 */
class ELF_Image_Sitemap extends ELF_Feed_Generator {

    private const SITEMAP_NS = 'http://www.sitemaps.org/schemas/sitemap/0.9';
    private const IMAGE_NS   = 'http://www.google.com/schemas/sitemap-image/1.1';

    public function __construct() {
        parent::__construct();
        $this->feed_filename = 'image-sitemap.xml';
    }

    public function get_sitemap_url(): string {
        return $this->get_feed_url();
    }

    public function get_sitemap_path(): string {
        return $this->get_feed_path();
    }

    public function generate(): bool {
        ELF_Logger::info( 'Starting image sitemap generation', 'sitemap_generation' );

        $products = $this->load_products();
        if ( empty( $products ) ) {
            ELF_Logger::warning( 'No products found for sitemap generation', 'sitemap_generation' );
            return false;
        }

        ELF_Logger::info( 'Loaded products for sitemap generation', 'sitemap_generation', [ 'count' => count( $products ) ] );

        $dom    = $this->build_dom( $products );
        $result = $this->write_dom( $dom );

        if ( $result ) {
            global $wp_filesystem;
            if ( empty( $wp_filesystem ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            $sitemap_content = $wp_filesystem->get_contents( $this->get_feed_path() );
            if ( $sitemap_content ) {
                $validation = ELF_Feed_Validator::validate_sitemap( $sitemap_content );

                if ( ! $validation['valid'] ) {
                    ELF_Logger::error( 'Sitemap validation failed', 'sitemap_validation', [
                        'errors' => $validation['errors'],
                    ] );
                } elseif ( ! empty( $validation['warnings'] ) ) {
                    ELF_Logger::warning( 'Sitemap validation warnings', 'sitemap_validation', [
                        'warnings' => $validation['warnings'],
                    ] );
                }
            }

            ELF_Logger::info( 'Image sitemap generated successfully', 'sitemap_generation', [ 'product_count' => count( $products ) ] );
        } else {
            ELF_Logger::error( 'Image sitemap generation failed', 'sitemap_generation' );
        }

        return $result;
    }

    protected function build_dom( array $products ): DOMDocument {
        $dom = new DOMDocument( '1.0', 'UTF-8' );
        $dom->formatOutput = true;

        $urlset = $dom->createElementNS( self::SITEMAP_NS, 'urlset' );
        $urlset->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:image',
            self::IMAGE_NS
        );
        $dom->appendChild( $urlset );

        // load_products() flattens variable products into individual variation
        // objects (needed for the shopping feed). For the sitemap we want one
        // <url> per product page, so group variations back under their parent.
        $by_parent = [];
        foreach ( $products as $product ) {
            $parent_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
            $by_parent[ $parent_id ][] = $product;
        }

        foreach ( $by_parent as $parent_id => $group ) {
            $url_el = $this->build_url_entry( $dom, (int) $parent_id, $group );
            if ( $url_el ) {
                $urlset->appendChild( $url_el );
            }
        }

        return $dom;
    }

    /** @param WC_Product[] $group The parent product and/or its variations. */
    private function build_url_entry( DOMDocument $dom, int $parent_id, array $group ): ?DOMElement {
        $permalink = esc_url_raw( get_permalink( $parent_id ) );
        if ( ! $permalink ) return null;

        $image_ids        = [];
        $latest_modified  = null;

        foreach ( $group as $product ) {
            $img_id = (int) $product->get_image_id();
            if ( $img_id ) $image_ids[] = $img_id;

            foreach ( $product->get_gallery_image_ids() as $gid ) {
                $image_ids[] = (int) $gid;
            }

            $modified = $product->get_date_modified();
            if ( $modified && ( ! $latest_modified || $modified->getTimestamp() > $latest_modified->getTimestamp() ) ) {
                $latest_modified = $modified;
            }
        }

        $image_ids = array_unique( $image_ids );
        if ( empty( $image_ids ) ) return null;

        $url_el = $dom->createElement( 'url' );

        $loc = $dom->createElement( 'loc' );
        $loc->appendChild( $dom->createTextNode( $permalink ) );
        $url_el->appendChild( $loc );

        // lastmod — W3C date format (YYYY-MM-DD)
        if ( $latest_modified ) {
            $lastmod = $dom->createElement( 'lastmod' );
            $lastmod->appendChild( $dom->createTextNode( $latest_modified->date( 'Y-m-d' ) ) );
            $url_el->appendChild( $lastmod );
        }

        $product_name = get_the_title( $parent_id );

        foreach ( $image_ids as $img_id ) {
            $url = ELF_Image_Helper::get_stable_image_url( $img_id );
            if ( ! $url ) continue;

            $image_el = $dom->createElementNS( self::IMAGE_NS, 'image:image' );

            $image_loc = $dom->createElementNS( self::IMAGE_NS, 'image:loc' );
            $image_loc->appendChild( $dom->createTextNode( $url ) );
            $image_el->appendChild( $image_loc );

            // image:title — alt text is more descriptive; fall back to product name
            $alt        = get_post_meta( $img_id, '_wp_attachment_image_alt', true );
            $title_text = sanitize_text_field( $alt ?: $product_name );
            if ( $title_text ) {
                $image_title = $dom->createElementNS( self::IMAGE_NS, 'image:title' );
                $image_title->appendChild( $dom->createCDATASection( $title_text ) );
                $image_el->appendChild( $image_title );
            }

            $url_el->appendChild( $image_el );
        }

        return $url_el;
    }
}
