<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Health monitoring system for feed generation.
 * Integrates with WordPress Site Health for status monitoring.
 */
class ELF_Health_Monitor {

    public static function init(): void {
        add_filter( 'site_status_tests', [ __CLASS__, 'add_health_tests' ] );
    }

    /**
     * Add custom health tests to WordPress Site Health.
     *
     * @param array $tests Existing health tests
     * @return array Modified tests
     */
    public static function add_health_tests( array $tests ): array {
        $tests['direct']['elf_feed_status'] = [
            'label' => __( 'Product Feed Status', 'excellink-feeds' ),
            'test'  => [ __CLASS__, 'test_feed_status' ],
        ];

        $tests['direct']['elf_feed_freshness'] = [
            'label' => __( 'Feed Freshness', 'excellink-feeds' ),
            'test'  => [ __CLASS__, 'test_feed_freshness' ],
        ];

        $tests['direct']['elf_image_sitemap_status'] = [
            'label' => __( 'Image Sitemap Status', 'excellink-feeds' ),
            'test'  => [ __CLASS__, 'test_image_sitemap_status' ],
        ];

        $tests['direct']['elf_category_mapping'] = [
            'label' => __( 'Category Mapping Coverage', 'excellink-feeds' ),
            'test'  => [ __CLASS__, 'test_category_mapping' ],
        ];

        return $tests;
    }

    /**
     * Test if feed files exist and are accessible.
     *
     * @return array Test result
     */
    public static function test_feed_status(): array {
        $result = [
            'label'       => __( 'Product feeds are accessible', 'excellink-feeds' ),
            'status'      => 'good',
            'badge'       => [
                'label' => __( 'Feed Status', 'excellink-feeds' ),
                'color' => 'blue',
            ],
            'description' => sprintf(
                '<p>%s</p>',
                __( 'Your product feeds are generated and accessible for Google Shopping and Facebook Catalog.', 'excellink-feeds' )
            ),
            'actions'     => '',
            'test'        => 'elf_feed_status',
        ];

        $upload = wp_upload_dir();
        $feed_path = trailingslashit( $upload['basedir'] ) . 'excellink-feeds/product-feed.xml';

        if ( ! file_exists( $feed_path ) ) {
            $result['status'] = 'critical';
            $result['label'] = __( 'Product feeds are not generated', 'excellink-feeds' );
            $result['description'] = sprintf(
                '<p>%s</p>',
                __( 'No product feed file found. Please regenerate your feeds from the settings page.', 'excellink-feeds' )
            );
            $result['actions'] = sprintf(
                '<a href="%s" class="button button-primary">%s</a>',
                admin_url( 'admin.php?page=elf-product-feeds' ),
                __( 'Regenerate Feeds', 'excellink-feeds' )
            );
        } elseif ( ! is_readable( $feed_path ) ) {
            $result['status'] = 'critical';
            $result['label'] = __( 'Product feeds are not readable', 'excellink-feeds' );
            $result['description'] = sprintf(
                '<p>%s</p>',
                __( 'Product feed file exists but is not readable. Check file permissions.', 'excellink-feeds' )
            );
        }

        return $result;
    }

    /**
     * Test if feeds are fresh (generated recently).
     *
     * @return array Test result
     */
    public static function test_feed_freshness(): array {
        $result = [
            'label'       => __( 'Product feeds are up to date', 'excellink-feeds' ),
            'status'      => 'good',
            'badge'       => [
                'label' => __( 'Feed Freshness', 'excellink-feeds' ),
                'color' => 'blue',
            ],
            'description' => sprintf(
                '<p>%s</p>',
                __( 'Your product feeds have been generated recently and are current.', 'excellink-feeds' )
            ),
            'actions'     => '',
            'test'        => 'elf_feed_freshness',
        ];

        $last_generated = get_option( 'elf_feed_last_generated', '' );
        
        if ( empty( $last_generated ) ) {
            $result['status'] = 'recommended';
            $result['label'] = __( 'Product feeds have never been generated', 'excellink-feeds' );
            $result['description'] = sprintf(
                '<p>%s</p>',
                __( 'Feeds should be generated regularly to ensure product data is current.', 'excellink-feeds' )
            );
            return $result;
        }

        $last_generated_time = strtotime( $last_generated );
        $hours_ago = ( time() - $last_generated_time ) / HOUR_IN_SECONDS;

        if ( $hours_ago > 48 ) {
            $result['status'] = 'recommended';
            $result['label'] = __( 'Product feeds are outdated', 'excellink-feeds' );
            $result['description'] = sprintf(
                '<p>%s</p>',
                sprintf(
                    __( 'Your feeds were generated %d hours ago. Consider regenerating them for current product data.', 'excellink-feeds' ),
                    round( $hours_ago )
                )
            );
            $result['actions'] = sprintf(
                '<a href="%s" class="button button-primary">%s</a>',
                admin_url( 'admin.php?page=elf-product-feeds' ),
                __( 'Regenerate Feeds', 'excellink-feeds' )
            );
        }

        return $result;
    }

    /**
     * Test if image sitemap exists and is accessible.
     *
     * @return array Test result
     */
    public static function test_image_sitemap_status(): array {
        $result = [
            'label'       => __( 'Image sitemap is accessible', 'excellink-feeds' ),
            'status'      => 'good',
            'badge'       => [
                'label' => __( 'Image Sitemap', 'excellink-feeds' ),
                'color' => 'blue',
            ],
            'description' => sprintf(
                '<p>%s</p>',
                __( 'Your image sitemap is generated and accessible for Google image crawling.', 'excellink-feeds' )
            ),
            'actions'     => '',
            'test'        => 'elf_image_sitemap_status',
        ];

        $upload = wp_upload_dir();
        $sitemap_path = trailingslashit( $upload['basedir'] ) . 'excellink-feeds/image-sitemap.xml';

        if ( ! file_exists( $sitemap_path ) ) {
            $result['status'] = 'recommended';
            $result['label'] = __( 'Image sitemap is not generated', 'excellink-feeds' );
            $result['description'] = sprintf(
                '<p>%s</p>',
                __( 'No image sitemap file found. Generating an image sitemap helps Google discover your product images.', 'excellink-feeds' )
            );
            $result['actions'] = sprintf(
                '<a href="%s" class="button button-primary">%s</a>',
                admin_url( 'admin.php?page=elf-product-feeds' ),
                __( 'Regenerate Feeds', 'excellink-feeds' )
            );
        }

        return $result;
    }

    /**
     * Test category mapping coverage.
     *
     * @return array Test result
     */
    public static function test_category_mapping(): array {
        $result = [
            'label'       => __( 'Category mapping is well configured', 'excellink-feeds' ),
            'status'      => 'good',
            'badge'       => [
                'label' => __( 'Category Mapping', 'excellink-feeds' ),
                'color' => 'blue',
            ],
            'description' => sprintf(
                '<p>%s</p>',
                __( 'Your WooCommerce categories are mapped to Google taxonomy categories.', 'excellink-feeds' )
            ),
            'actions'     => '',
            'test'        => 'elf_category_mapping',
        ];

        $categories = get_terms( [
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'fields'     => 'ids',
        ] );

        if ( is_wp_error( $categories ) || empty( $categories ) ) {
            $result['status'] = 'recommended';
            $result['label'] = __( 'No product categories found', 'excellink-feeds' );
            $result['description'] = sprintf(
                '<p>%s</p>',
                __( 'No WooCommerce product categories found. Create categories for better product organization.', 'excellink-feeds' )
            );
            return $result;
        }

        $category_map = ELF_Category_Mapper::get_map();
        $mapped_count = 0;
        $total_count = count( $categories );

        foreach ( $categories as $category_id ) {
            if ( isset( $category_map[ $category_id ] ) && ! empty( $category_map[ $category_id ] ) ) {
                $mapped_count++;
            }
        }

        $coverage = ( $mapped_count / $total_count ) * 100;

        if ( $coverage < 50 ) {
            $result['status'] = 'recommended';
            $result['label'] = __( 'Category mapping coverage is low', 'excellink-feeds' );
            $result['description'] = sprintf(
                '<p>%s</p>',
                sprintf(
                    __( 'Only %d%% of your categories are mapped to Google taxonomy. Mapping more categories improves feed quality.', 'excellink-feeds' ),
                    round( $coverage )
                )
            );
            $result['actions'] = sprintf(
                '<a href="%s" class="button button-primary">%s</a>',
                admin_url( 'admin.php?page=elf-category-map' ),
                __( 'Map Categories', 'excellink-feeds' )
            );
        }

        return $result;
    }

    /**
     * Get overall health status summary.
     *
     * @return array Health summary
     */
    public static function get_health_summary(): array {
        $summary = [
            'status' => 'good',
            'issues' => [],
        ];

        // Check feed status
        $upload = wp_upload_dir();
        $feed_path = trailingslashit( $upload['basedir'] ) . 'excellink-feeds/product-feed.xml';
        
        if ( ! file_exists( $feed_path ) ) {
            $summary['status'] = 'critical';
            $summary['issues'][] = __( 'Product feed not generated', 'excellink-feeds' );
        }

        // Check feed freshness
        $last_generated = get_option( 'elf_feed_last_generated', '' );
        if ( ! empty( $last_generated ) ) {
            $last_generated_time = strtotime( $last_generated );
            $hours_ago = ( time() - $last_generated_time ) / HOUR_IN_SECONDS;
            
            if ( $hours_ago > 48 ) {
                $summary['status'] = 'recommended';
                $summary['issues'][] = __( 'Feed is outdated', 'excellink-feeds' );
            }
        }

        // Check error logs
        $log_counts = ELF_Logger::get_log_counts();
        if ( $log_counts['error'] > 0 ) {
            $summary['status'] = 'recommended';
            $summary['issues'][] = sprintf(
                __( '%d errors logged', 'excellink-feeds' ),
                $log_counts['error']
            );
        }

        return $summary;
    }
}
