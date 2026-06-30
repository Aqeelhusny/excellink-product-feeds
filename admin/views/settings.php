<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) return;

$settings        = get_option( 'elf_settings', [] );
$upload          = wp_upload_dir();
$feed_url        = trailingslashit( $upload['baseurl'] ) . 'excellink-feeds/product-feed.xml';
$feed_path       = trailingslashit( $upload['basedir'] ) . 'excellink-feeds/product-feed.xml';
$sitemap_url     = trailingslashit( $upload['baseurl'] ) . 'excellink-feeds/image-sitemap.xml';
$sitemap_path    = trailingslashit( $upload['basedir'] ) . 'excellink-feeds/image-sitemap.xml';
$feed_exists          = file_exists( $feed_path );
$sitemap_exists       = file_exists( $sitemap_path );
$last_gen             = get_option( 'elf_feed_last_generated', '' );
$feed_count           = get_option( 'elf_feed_product_count', 0 );
$skipped_no_image     = (int) get_option( 'elf_feed_skipped_no_image', -1 );
$skipped_no_price     = (int) get_option( 'elf_feed_skipped_no_price', -1 );
?>
<div class="wrap elf-wrap">
    <h1><?php esc_html_e( 'Excellink Product Feeds', 'excellink-product-feeds' ); ?></h1>

    <div class="elf-grid">

        <!-- Feed Status Card -->
        <div class="elf-card elf-feed-status">
            <h2><?php esc_html_e( 'Feed URL', 'excellink-product-feeds' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'One feed works for both platforms — paste this URL into Google Merchant Center and Facebook Catalog Manager.', 'excellink-product-feeds' ); ?>
            </p>

            <table class="elf-feed-table">
                <tr>
                    <th><?php esc_html_e( 'Google Shopping', 'excellink-product-feeds' ); ?></th>
                    <td>
                        <?php if ( $feed_exists ) : ?>
                            <code class="elf-feed-url"><?php echo esc_url( $feed_url ); ?></code>
                            <button class="button button-small elf-copy-btn" data-url="<?php echo esc_attr( $feed_url ); ?>">
                                <?php esc_html_e( 'Copy', 'excellink-product-feeds' ); ?>
                            </button>
                            <span class="elf-badge elf-badge--ok"><?php esc_html_e( 'Active', 'excellink-product-feeds' ); ?></span>
                        <?php else : ?>
                            <span class="elf-badge elf-badge--warn"><?php esc_html_e( 'Not generated yet', 'excellink-product-feeds' ); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Facebook Catalog', 'excellink-product-feeds' ); ?></th>
                    <td>
                        <?php if ( $feed_exists ) : ?>
                            <code class="elf-feed-url"><?php echo esc_url( $feed_url ); ?></code>
                            <button class="button button-small elf-copy-btn" data-url="<?php echo esc_attr( $feed_url ); ?>">
                                <?php esc_html_e( 'Copy', 'excellink-product-feeds' ); ?>
                            </button>
                            <span class="elf-badge elf-badge--ok"><?php esc_html_e( 'Active', 'excellink-product-feeds' ); ?></span>
                        <?php else : ?>
                            <span class="elf-badge elf-badge--warn"><?php esc_html_e( 'Not generated yet', 'excellink-product-feeds' ); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <?php if ( $feed_exists ) : ?>
                <p class="elf-feed-meta">
                    <?php printf(
                        // translators: %1$d is the number of products in the feed, %2$s is the last generation date/time.
                        esc_html__( '%1$d products in feed &middot; Last generated: %2$s', 'excellink-product-feeds' ),
                        absint( $feed_count ),
                        esc_html( $last_gen ?: __( 'Never', 'excellink-product-feeds' ) )
                    ); ?>
                </p>

                <?php if ( $skipped_no_image > 0 ) : ?>
                    <div class="notice notice-warning inline" style="margin:8px 0 4px;">
                        <p>
                            <strong><?php printf(
                                // translators: %d is the number of products excluded from the feed due to missing image.
                                esc_html__( '%d product(s) excluded — no image.', 'excellink-product-feeds' ),
                                absint( $skipped_no_image )
                            ); ?></strong>
                            <?php esc_html_e( 'Google requires an image for every product. Go to Products → find items with no featured image and add one.', 'excellink-product-feeds' ); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if ( $skipped_no_price > 0 ) : ?>
                    <div class="notice notice-warning inline" style="margin:8px 0 4px;">
                        <p>
                            <strong><?php printf(
                                // translators: %d is the number of products excluded from the feed due to missing price.
                                esc_html__( '%d product(s) excluded — no regular price.', 'excellink-product-feeds' ),
                                absint( $skipped_no_price )
                            ); ?></strong>
                            <?php esc_html_e( 'Set a Regular Price on these products to include them in the feed.', 'excellink-product-feeds' ); ?>
                        </p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <p>
                <button id="elf-regenerate-btn" class="button button-primary">
                    <?php esc_html_e( 'Regenerate Feed Now', 'excellink-product-feeds' ); ?>
                </button>
                <span id="elf-regen-status" class="elf-inline-status"></span>
            </p>

            <div class="elf-platform-note">
                <strong><?php esc_html_e( 'Where to paste this URL:', 'excellink-product-feeds' ); ?></strong>
                <ul>
                    <li>
                        <strong>Google:</strong>
                        <?php esc_html_e( 'Merchant Center → Products → Feeds → Add Feed → Scheduled Fetch', 'excellink-product-feeds' ); ?>
                    </li>
                    <li>
                        <strong>Facebook / Instagram:</strong>
                        <?php esc_html_e( 'Commerce Manager → Catalog → Data Sources → Use a URL → Google Shopping feed format', 'excellink-product-feeds' ); ?>
                    </li>
                </ul>
            </div>

            <h2 style="margin-top:24px;"><?php esc_html_e( 'Image Sitemap', 'excellink-product-feeds' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'Submitted to Google Search Console to tell Googlebot-Image which product photos exist and when they last changed — directly improves image crawl rate.', 'excellink-product-feeds' ); ?>
            </p>
            <table class="elf-feed-table">
                <tr>
                    <th><?php esc_html_e( 'Sitemap URL', 'excellink-product-feeds' ); ?></th>
                    <td>
                        <?php if ( $sitemap_exists ) : ?>
                            <code class="elf-feed-url"><?php echo esc_url( $sitemap_url ); ?></code>
                            <button class="button button-small elf-copy-btn" data-url="<?php echo esc_attr( $sitemap_url ); ?>">
                                <?php esc_html_e( 'Copy', 'excellink-product-feeds' ); ?>
                            </button>
                            <span class="elf-badge elf-badge--ok"><?php esc_html_e( 'Active', 'excellink-product-feeds' ); ?></span>
                        <?php else : ?>
                            <span class="elf-badge elf-badge--warn"><?php esc_html_e( 'Not generated yet — click Regenerate', 'excellink-product-feeds' ); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <p class="description" style="margin-top:6px;">
                <?php esc_html_e( 'Submit this URL in: Google Search Console → Sitemaps → Add a new sitemap', 'excellink-product-feeds' ); ?>
            </p>
        </div>

        <!-- Settings Form -->
        <div class="elf-card">
            <h2><?php esc_html_e( 'Feed Settings', 'excellink-product-feeds' ); ?></h2>
            <form id="elf-settings-form">

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="elf-schedule"><?php esc_html_e( 'Refresh Schedule', 'excellink-product-feeds' ); ?></label>
                        </th>
                        <td>
                            <select name="schedule" id="elf-schedule">
                                <?php
                                $schedules = [
                                    'hourly'     => __( 'Hourly', 'excellink-product-feeds' ),
                                    'twicedaily' => __( 'Twice Daily', 'excellink-product-feeds' ),
                                    'daily'      => __( 'Daily', 'excellink-product-feeds' ),
                                    'weekly'     => __( 'Weekly', 'excellink-product-feeds' ),
                                ];
                                $current = $settings['schedule'] ?? 'daily';
                                foreach ( $schedules as $val => $label ) :
                                ?>
                                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current, $val ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Google recommends daily. Facebook supports up to hourly.', 'excellink-product-feeds' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="elf-batch-size"><?php esc_html_e( 'Products per Feed', 'excellink-product-feeds' ); ?></label>
                        </th>
                        <td>
                            <input type="number" name="batch_size" id="elf-batch-size"
                                   value="<?php echo absint( $settings['batch_size'] ?? 200 ); ?>"
                                   min="50" max="1000" class="small-text">
                            <p class="description"><?php esc_html_e( 'Max products per feed (50–1000).', 'excellink-product-feeds' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'Out of Stock Products', 'excellink-product-feeds' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="include_out_of_stock" value="yes"
                                    <?php checked( ( $settings['include_out_of_stock'] ?? 'no' ), 'yes' ); ?>>
                                <?php esc_html_e( 'Include out-of-stock products', 'excellink-product-feeds' ); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="elf-condition"><?php esc_html_e( 'Default Condition', 'excellink-product-feeds' ); ?></label>
                        </th>
                        <td>
                            <select name="condition" id="elf-condition">
                                <?php
                                $conditions  = [
                                    'new'         => __( 'New', 'excellink-product-feeds' ),
                                    'refurbished' => __( 'Refurbished', 'excellink-product-feeds' ),
                                    'used'        => __( 'Used', 'excellink-product-feeds' ),
                                ];
                                $current_cond = $settings['condition'] ?? 'new';
                                foreach ( $conditions as $val => $label ) :
                                ?>
                                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current_cond, $val ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="elf-brand-fallback"><?php esc_html_e( 'Default Brand', 'excellink-product-feeds' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="brand_fallback" id="elf-brand-fallback"
                                   value="<?php echo esc_attr( $settings['brand_fallback'] ?? get_bloginfo( 'name' ) ); ?>"
                                   class="regular-text">
                            <p class="description"><?php esc_html_e( 'Used when no brand attribute is set on the product.', 'excellink-product-feeds' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'Enable Logging', 'excellink-feeds' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_logging" value="yes"
                                    <?php checked( ( $settings['enable_logging'] ?? 'no' ), 'yes' ); ?>>
                                <?php esc_html_e( 'Enable plugin logging', 'excellink-feeds' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'When enabled, the plugin logs feed generation events, errors, and warnings. Disable to reduce database writes in production.', 'excellink-feeds' ); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary" id="elf-save-btn">
                        <?php esc_html_e( 'Save Settings', 'excellink-product-feeds' ); ?>
                    </button>
                    <span id="elf-save-status" class="elf-inline-status"></span>
                </p>
            </form>

            <h2 style="margin-top:24px;"><?php esc_html_e( 'Import/Export Settings', 'excellink-product-feeds' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'Export your settings to backup or transfer to another site. Import settings from a previously exported file.', 'excellink-product-feeds' ); ?>
            </p>
            
            <div class="elf-import-export-buttons">
                <button type="button" class="button" id="elf-export-btn">
                    <?php esc_html_e( 'Export Settings', 'excellink-product-feeds' ); ?>
                </button>
                
                <label for="elf-import-file" class="button">
                    <?php esc_html_e( 'Import Settings', 'excellink-product-feeds' ); ?>
                </label>
                <input type="file" id="elf-import-file" accept=".json" style="display:none;">
                <span id="elf-import-status" class="elf-inline-status"></span>
            </div>
        </div>

    </div><!-- .elf-grid -->
</div>
