<?php
if ( ! defined( 'ABSPATH' ) exit;

/**
 * XML schema validation for generated feeds.
 * Validates feed structure and required fields according to Google Shopping specifications.
 */
class ELF_Feed_Validator {

    /**
     * Validate a product feed XML file.
     *
     * @param string $xml_content The XML content to validate
     * @return array Validation result with 'valid' bool and 'errors' array
     */
    public static function validate_feed( string $xml_content ): array {
        $result = [
            'valid'  => true,
            'errors' => [],
            'warnings' => [],
        ];

        // Basic XML well-formedness check
        libxml_use_internal_errors( true );
        $dom = new DOMDocument();
        
        if ( ! $dom->loadXML( $xml_content ) ) {
            $result['valid'] = false;
            $result['errors'][] = 'XML is not well-formed';
            
            $errors = libxml_get_errors();
            foreach ( $errors as $error ) {
                $result['errors'][] = 'XML Error: ' . trim( $error->message );
            }
            
            libxml_clear_errors();
            return $result;
        }

        libxml_clear_errors();

        // Validate RSS structure
        $rss = $dom->getElementsByTagName( 'rss' );
        if ( $rss->length === 0 ) {
            $result['valid'] = false;
            $result['errors'][] = 'Missing RSS root element';
            return $result;
        }

        // Check for required namespace
        $rss_element = $rss->item( 0 );
        $has_google_ns = $rss_element->hasAttribute( 'xmlns:g' );
        
        if ( ! $has_google_ns ) {
            $result['warnings'][] = 'Missing Google Shopping namespace (xmlns:g)';
        }

        // Validate channel structure
        $channels = $dom->getElementsByTagName( 'channel' );
        if ( $channels->length === 0 ) {
            $result['valid'] = false;
            $result['errors'][] = 'Missing channel element';
            return $result;
        }

        $channel = $channels->item( 0 );
        
        // Check required channel elements
        $required_channel_elements = [ 'title', 'link', 'description' ];
        foreach ( $required_channel_elements as $element ) {
            $elements = $channel->getElementsByTagName( $element );
            if ( $elements->length === 0 ) {
                $result['errors'][] = "Missing required channel element: {$element}";
            }
        }

        // Validate items
        $items = $channel->getElementsByTagName( 'item' );
        if ( $items->length === 0 ) {
            $result['warnings'][] = 'No items found in feed';
        }

        // Check each item for required Google Shopping fields
        $required_fields = [
            'g:id',
            'g:title',
            'g:description',
            'g:link',
            'g:image_link',
            'g:availability',
            'g:price',
            'g:condition',
        ];

        $item_count = 0;
        foreach ( $items as $item ) {
            $item_count++;
            $item_errors = self::validate_item( $item, $required_fields, $item_count );
            
            if ( ! empty( $item_errors['errors'] ) ) {
                $result['valid'] = false;
                $result['errors'] = array_merge( $result['errors'], $item_errors['errors'] );
            }
            
            if ( ! empty( $item_errors['warnings'] ) ) {
                $result['warnings'] = array_merge( $result['warnings'], $item_errors['warnings'] );
            }
        }

        ELF_Logger::info( 'Feed validation completed', 'feed_validation', [
            'valid' => $result['valid'],
            'item_count' => $item_count,
            'error_count' => count( $result['errors'] ),
            'warning_count' => count( $result['warnings'] )
        ]);

        return $result;
    }

    /**
     * Validate a single feed item.
     *
     * @param DOMElement $item The item element
     * @param array $required_fields Required field names
     * @param int $item_number Item number for error reporting
     * @return array Validation result for this item
     */
    private static function validate_item( DOMElement $item, array $required_fields, int $item_number ): array {
        $result = [
            'errors' => [],
            'warnings' => [],
        ];

        $item_context = "Item #{$item_number}";

        foreach ( $required_fields as $field ) {
            // Handle namespaced elements
            if ( strpos( $field, ':' ) !== false ) {
                $parts = explode( ':', $field );
                $namespace = 'g';
                $local_name = $parts[1];
                
                $elements = $item->getElementsByTagNameNS( 'http://base.google.com/ns/1.0', $local_name );
            } else {
                $elements = $item->getElementsByTagName( $field );
            }

            if ( $elements->length === 0 ) {
                $result['errors'][] = "{$item_context}: Missing required field: {$field}";
            } elseif ( empty( trim( $elements->item( 0 )->textContent ) ) ) {
                $result['errors'][] = "{$item_context}: Empty required field: {$field}";
            }
        }

        // Check for recommended fields
        $recommended_fields = [ 'g:brand', 'g:gtin', 'g:mpn' ];
        foreach ( $recommended_fields as $field ) {
            $parts = explode( ':', $field );
            $local_name = $parts[1];
            $elements = $item->getElementsByTagNameNS( 'http://base.google.com/ns/1.0', $local_name );
            
            if ( $elements->length === 0 || empty( trim( $elements->item( 0 )->textContent ) ) ) {
                $result['warnings'][] = "{$item_context}: Missing recommended field: {$field}";
            }
        }

        // Validate price format
        $price_elements = $item->getElementsByTagNameNS( 'http://base.google.com/ns/1.0', 'price' );
        if ( $price_elements->length > 0 ) {
            $price = trim( $price_elements->item( 0 )->textContent );
            if ( ! preg_match( '/^\d+\.\d{2}\s[A-Z]{3}$/', $price ) ) {
                $result['warnings'][] = "{$item_context}: Price format may be incorrect (expected: 10.00 USD): {$price}";
            }
        }

        return $result;
    }

    /**
     * Validate image sitemap XML.
     *
     * @param string $xml_content The XML content to validate
     * @return array Validation result
     */
    public static function validate_sitemap( string $xml_content ): array {
        $result = [
            'valid'  => true,
            'errors' => [],
            'warnings' => [],
        ];

        libxml_use_internal_errors( true );
        $dom = new DOMDocument();
        
        if ( ! $dom->loadXML( $xml_content ) ) {
            $result['valid'] = false;
            $result['errors'][] = 'XML is not well-formed';
            
            $errors = libxml_get_errors();
            foreach ( $errors as $error ) {
                $result['errors'][] = 'XML Error: ' . trim( $error->message );
            }
            
            libxml_clear_errors();
            return $result;
        }

        libxml_clear_errors();

        // Validate sitemap namespace
        $urlset = $dom->getElementsByTagNameNS( 'http://www.sitemaps.org/schemas/sitemap/0.9', 'urlset' );
        if ( $urlset->length === 0 ) {
            $result['warnings'][] = 'Missing or incorrect sitemap namespace';
        }

        // Check for URL elements
        $urls = $dom->getElementsByTagName( 'url' );
        if ( $urls->length === 0 ) {
            $result['warnings'][] = 'No URLs found in sitemap';
        }

        return $result;
    }
}
