<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) return;

$saved_map  = ELF_Category_Mapper::get_map();
$taxonomy   = ELF_Category_Mapper::get_google_taxonomy(); // empty array if not cached yet
$categories = get_terms( [
    'taxonomy'   => 'product_cat',
    'hide_empty' => false,
    'orderby'    => 'name',
] );

if ( is_wp_error( $categories ) ) {
    $categories = [];
}

$taxonomy_ready = ! empty( $taxonomy );
?>
<div class="wrap elf-wrap">
    <h1><?php esc_html_e( 'Category Mapping', 'excellink-product-feeds' ); ?></h1>
    <p class="description">
        <?php esc_html_e( 'Map your WooCommerce product categories to Google\'s standardised taxonomy. Facebook Catalog also uses these Google taxonomy IDs.', 'excellink-product-feeds' ); ?>
        <a href="https://support.google.com/merchants/answer/6324436" target="_blank" rel="noopener">
            <?php esc_html_e( 'View Google taxonomy reference →', 'excellink-product-feeds' ); ?>
        </a>
    </p>

    <?php if ( ! $taxonomy_ready ) : ?>
        <div class="notice notice-info inline">
            <p><?php esc_html_e( 'Google taxonomy is being downloaded in the background. This happens automatically once per week. Refresh this page in a moment to start mapping.', 'excellink-product-feeds' ); ?></p>
        </div>
    <?php endif; ?>

    <div class="elf-card">
        <form id="elf-category-map-form">
            <table class="widefat elf-cat-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'WooCommerce Category', 'excellink-product-feeds' ); ?></th>
                        <th><?php esc_html_e( 'Google Taxonomy Category', 'excellink-product-feeds' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $categories as $category ) :
                        $term_id     = $category->term_id;
                        $mapped_id   = $saved_map[ $term_id ] ?? 0;
                        $depth       = $category->parent ? 1 : 0;
                        $prefix      = $depth ? '— ' : '';

                        // Show the label if taxonomy is cached and mapping exists
                        $mapped_label = '';
                        if ( $mapped_id ) {
                            $mapped_label = isset( $taxonomy[ $mapped_id ] )
                                ? '[' . $mapped_id . '] ' . $taxonomy[ $mapped_id ]
                                : '#' . $mapped_id;
                        }
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $prefix . $category->name ); ?></strong>
                                <br><small class="elf-muted"><?php echo esc_html( $category->slug ); ?> (<?php echo absint( $category->count ); ?> <?php esc_html_e( 'products', 'excellink-product-feeds' ); ?>)</small>
                            </td>
                            <td>
                                <div class="elf-tax-search-wrap" data-term-id="<?php echo absint( $term_id ); ?>">
                                    <input type="text"
                                           class="elf-tax-search regular-text"
                                           placeholder="<?php esc_attr_e( 'Type to search Google taxonomy…', 'excellink-product-feeds' ); ?>"
                                           value="<?php echo esc_attr( $mapped_label ); ?>"
                                           autocomplete="off"
                                           <?php disabled( ! $taxonomy_ready ); ?>>
                                    <input type="hidden"
                                           name="category_map[<?php echo absint( $term_id ); ?>]"
                                           class="elf-tax-value"
                                           value="<?php echo absint( $mapped_id ); ?>">
                                    <div class="elf-tax-dropdown"></div>
                                    <?php if ( $mapped_id ) : ?>
                                        <span class="elf-badge elf-badge--ok elf-mapped-badge"><?php esc_html_e( 'Mapped', 'excellink-product-feeds' ); ?></span>
                                    <?php else : ?>
                                        <span class="elf-badge elf-badge--warn elf-mapped-badge"><?php esc_html_e( 'Unmapped', 'excellink-product-feeds' ); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary" id="elf-save-map-btn"
                        <?php disabled( ! $taxonomy_ready ); ?>>
                    <?php esc_html_e( 'Save Category Mapping', 'excellink-product-feeds' ); ?>
                </button>
                <span id="elf-map-save-status" class="elf-inline-status"></span>
            </p>
        </form>
    </div>
</div>
