<?php
/**
 * Plugin Name: Blockify Google Maps
 * Plugin URI:  https://blockifywp.com/blocks/google map
 * Description: Lightweight, customizable Google Map block for WordPress.
 * Author:      Blockify
 * Author URI:  https://blockifywp.com/
 * Version:     0.0.1
 * License:     GPLv2-or-Later
 * Text Domain: blockify
 */

declare( strict_types=1 );

namespace Blockify\GoogleMap;

use DOMElement;
use function add_action;
use function file_exists;
use function file_get_contents;
use function is_admin;
use function json_decode;
use function md5;
use function rand;
use function register_block_type;
use function substr;
use function wp_enqueue_script;
use function wp_localize_script;

const NS = __NAMESPACE__ . '\\';
const DS = DIRECTORY_SEPARATOR;

add_action( 'init', NS . 'register' );
/**
 * Registers the block.
 *
 * @since 0.0.1
 *
 * @since 1.0.0
 *
 * @return void
 */
function register() {
	register_block_type( __DIR__ . '/build' );
}

add_filter( 'render_block_blockify/google-map', NS . 'render_google_map_block', 10, 2 );
/**
 * Modifies front end HTML output of block.
 *
 * `render_block` runs just after `template_redirect`, before `wp_enqueue_scripts`.
 *
 * @since 0.0.2
 *
 * @param string $content
 * @param array  $block
 *
 * @return string
 */
function render_google_map_block( string $content, array $block ): string {
	if ( is_admin() ) {
		return $content;
	}

	static $enqueued = null;

	$google_maps_api_key = $block['attrs']['apiKey'] ?? '';

	$map = [
		'zoom'   => $block['attrs']['zoom'] ?? 8,
		'center' => [
			'lat' => $block['attrs']['lat'] ?? -25.344,
			'lng' => $block['attrs']['lng'] ?? 131.031,
		],
	];

	$json_file = DIR . 'src/blocks/google-map/styles/' . ( $block['attrs']['lightStyle'] ?? 'default' ) . '.json';

	if ( file_exists( $json_file ) ) {
		$map['styles'] = json_decode( file_get_contents( $json_file ) );
	}

	$dark_file = DIR . 'src/blocks/google-map/styles/' . ( $block['attrs']['darkStyle'] ?? 'night-mode' ) . '.json';
	$dark      = [];

	if ( file_exists( $dark_file ) ) {
		$dark = json_decode( file_get_contents( $dark_file ) );
	}

	$hex = substr( md5( (string) rand() ), 0, 6 );
	$id  = 'blockify-map-' . $hex;

	add_action( 'wp_enqueue_scripts', function () use ( $google_maps_api_key, $enqueued, $map, $id, $hex, $dark ) {

		if ( ! $enqueued ) {
			wp_enqueue_script(
				'blockify-google-maps',
				'//maps.googleapis.com/maps/api/js?key=' . $google_maps_api_key . '&libraries=places&callback=initMaps',
				[],
				null,
				true
			);
		}

		// Allows multiple maps on same page.
		wp_localize_script(
			'blockify-google-maps',
			'blockifyGoogleMap' . $hex,
			[
				'id'       => $id,
				'dark'     => $dark,
				'map'      => $map,
				'position' => $map['center'],
			]
		);
	} );

	$enqueued = true;

	$dom = dom( $content );

	/**
	 * @var $div DOMElement
	 */
	$div = $dom->firstChild;
	$div->setAttribute( 'data-id', $hex );

	return $dom->saveHTML();
}


use function defined;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function mb_convert_encoding;
use DOMDocument;

/**
 * Returns a formatted DOMDocument object from a given string.
 *
 * @since 0.0.2
 *
 * @param string $html
 *
 * @return string
 */
function dom( string $html ): DOMDocument {
	$dom = new DOMDocument();

	if ( ! $html ) {
		return $dom;
	}

	$libxml_previous_state   = libxml_use_internal_errors( true );
	$dom->preserveWhiteSpace = true;

	if ( defined( 'LIBXML_HTML_NOIMPLIED' ) && defined( 'LIBXML_HTML_NODEFDTD' ) ) {
		$options = LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD;
	} else if ( defined( 'LIBXML_HTML_NOIMPLIED' ) ) {
		$options = LIBXML_HTML_NOIMPLIED;
	} else if ( defined( 'LIBXML_HTML_NODEFDTD' ) ) {
		$options = LIBXML_HTML_NODEFDTD;
	} else {
		$options = 0;
	}

	$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ), $options );

	$dom->formatOutput = true;

	libxml_clear_errors();
	libxml_use_internal_errors( $libxml_previous_state );

	return $dom;
}
