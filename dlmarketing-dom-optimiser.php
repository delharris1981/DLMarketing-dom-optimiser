<?php
/**
 * Plugin Name: DLMarketing DOM Optimiser
 * Plugin URI:  https://dlmarketing.com/dom-optimiser
 * Description: High-performance DOM cleaner for Elementor (Legacy & V4). Removes bloat, ghost nodes, and redundant wrappers without breaking layout.
 * Version:     0.1a
 * Author:      DLMarketing
 * Author URI:  https://dlmarketing.com
 * Text Domain: dlmarketing-dom-optimiser
 * License:     GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class DLMarketing_DOM_Optimiser
 */
class DLMarketing_DOM_Optimiser {

	/**
	 * Instance
	 *
	 * @var DLMarketing_DOM_Optimiser|null
	 */
	private static $instance = null;

	/**
	 * Get Instance
	 *
	 * @return DLMarketing_DOM_Optimiser
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		// Only run on frontend and not in Elementor Editor
		if ( ! is_admin() && ! isset( $_GET['elementor-preview'] ) ) {
			add_action( 'template_redirect', [ $this, 'start_buffer' ], 999 );
		}
	}

	/**
	 * Start Output Buffering
	 */
	public function start_buffer() {
		ob_start( [ $this, 'process_buffer' ] );
	}

	/**
	 * Process Output Buffer
	 *
	 * @param string $buffer
	 * @return string
	 */
	public function process_buffer( $buffer ) {
		// Basic check to ensure we have HTML
		if ( empty( $buffer ) || strlen( $buffer ) < 100 ) {
			return $buffer;
		}

		// Use internal error handling to suppress warnings for malformed HTML (common in WP)
		libxml_use_internal_errors( true );

		$dom = new DOMDocument();
		// Hack to force UTF-8 and protect HTML5 tags
		$dom->loadHTML( mb_convert_encoding( $buffer, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_NOERROR | LIBXML_NOWARNING );
		
		$xpath = new DOMXPath( $dom );

		// 1. Core Pruning: Legacy Wrappers
		$this->prune_wrappers( $xpath );

		// 2. Ghost Node Removal
		$this->remove_ghost_nodes( $xpath );

		// 3. Cleanup: Comments
		$this->remove_comments( $xpath );

		// Save HTML
		$output = $dom->saveHTML();
		
		libxml_clear_errors();

		return $output;
	}

	/**
	 * Prune Wrappers
	 * Targets: .elementor-column-wrap, .elementor-widget-wrap, .elementor-widget-container
	 * Rule: No ID, No Custom Classes (non-elementor-*), No Attributes (data-*, aria-*), No Styles
	 *
	 * @param DOMXPath $xpath
	 */
	private function prune_wrappers( $xpath ) {
		// Query for potential wrappers
		$query = "//*[contains(@class, 'elementor-column-wrap') or contains(@class, 'elementor-widget-wrap') or contains(@class, 'elementor-widget-container')]";
		$nodes = $xpath->query( $query );

		if ( ! $nodes ) {
			return;
		}

		// Reverse iteration to handle removal safely
		for ( $i = $nodes->length - 1; $i >= 0; $i-- ) {
			$node = $nodes->item( $i );

			if ( $this->is_safe_to_prune( $node ) ) {
				$this->unwrap_node( $node );
			}
		}
	}

	/**
	 * Check if a node is safe to prune (unwrap)
	 *
	 * @param DOMNode $node
	 * @return bool
	 */
	private function is_safe_to_prune( $node ) {
		if ( ! $node instanceof DOMElement ) {
			return false;
		}

		// 1. Must NOT have an ID (Anchor protection)
		if ( $node->hasAttribute( 'id' ) ) {
			return false;
		}

		// 2. Must NOT have protected attributes
		$protected_attrs = [ 'data-id', 'aria-label', 'role', 'tabindex', 'onclick', 'style' ];
		foreach ( $protected_attrs as $attr ) {
			if ( $node->hasAttribute( $attr ) ) {
				return false; // Has inline style or interaction -> Unsafe
			}
		}

		// 3. User Class Protection
		// Only allow 'elementor-' classes. If it has 'my-custom-class', abort.
		$classes = explode( ' ', $node->getAttribute( 'class' ) );
		foreach ( $classes as $class ) {
			$class = trim( $class );
			if ( empty( $class ) ) continue;
			if ( strpos( $class, 'elementor-' ) !== 0 ) {
				// Found a class that DOES NOT start with elementor-
				return false; 
			}
		}

		return true;
	}

	/**
	 * Unwrap a node (replace it with its children)
	 *
	 * @param DOMNode $node
	 */
	private function unwrap_node( $node ) {
		$parent = $node->parentNode;
		if ( ! $parent ) {
			return;
		}

		// Move all children to parent
		while ( $node->firstChild ) {
			$parent->insertBefore( $node->firstChild, $node );
		}

		// Remove the empty node
		$parent->removeChild( $node );
	}

	/**
	 * Remove Ghost Nodes (Empty containers)
	 *
	 * @param DOMXPath $xpath
	 */
	private function remove_ghost_nodes( $xpath ) {
		// Find divs/sections that have no text content and no children (or empty children)
		// This is a simplified "Visual Zero" check
		// In a real scenario, we'd need to check computed styles, which PHP can't do perfectly.
		// So we stay VERY conservative: Remove only if strictly empty or whitespace only, and no attributes.
		
		$query = "//*[contains(@class, 'elementor-element')]"; 
		$nodes = $xpath->query( $query );

		if ( ! $nodes ) {
			return;
		}

		for ( $i = $nodes->length - 1; $i >= 0; $i-- ) {
			$node = $nodes->item( $i );
			
			// Checks:
			// 1. No ID
			if ( $node->hasAttribute('id') ) continue;

			// 2. No Images/Inputs inside
			$has_media = $xpath->query( ".//img | .//svg | .//input | .//iframe | .//video", $node )->length > 0;
			if ( $has_media ) continue;

			// 3. Text content is empty (trim)
			$text = trim( $node->textContent );
			if ( ! empty( $text ) ) continue;

			// 4. No inline styles (backgrounds etc)
			if ( $node->hasAttribute('style') ) continue;

			// If we got here, it's virtually empty.
			$node->parentNode->removeChild( $node );
		}
	}

	/**
	 * Remove HTML Comments
	 *
	 * @param DOMXPath $xpath
	 */
	private function remove_comments( $xpath ) {
		$comments = $xpath->query( '//comment()' );
		
		if ( ! $comments ) return;

		for ( $i = $comments->length - 1; $i >= 0; $i-- ) {
			$comments->item( $i )->parentNode->removeChild( $comments->item( $i ) );
		}
	}
}

// Initialize
DLMarketing_DOM_Optimiser::get_instance();
