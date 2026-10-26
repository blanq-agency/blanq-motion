<?php
/**
 * Hängt sich an die Seitenausgabe, prüft das fertige HTML und bindet nur
 * die benötigten Bibliotheken lokal ein.
 */

namespace Blanq\Motion;

defined( 'ABSPATH' ) || exit;

class Loader {

	/** @var Detector */
	private $detector;

	/** @var array */
	private $manifest;

	/** @var bool Hinweis-Kommentar für Admins ausgeben */
	private $debug = false;

	public function __construct( Detector $detector, array $manifest ) {
		$this->detector = $detector;
		$this->manifest = $manifest;
	}

	public function register(): void {
		add_action( 'template_redirect', array( $this, 'start' ), PHP_INT_MAX );
	}

	public function start(): void {
		if ( ! $this->should_run() ) {
			return;
		}
		$this->debug = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || current_user_can( 'manage_options' );
		ob_start( array( $this, 'process' ) );
	}

	/** Nur normale Frontend-Seiten, nicht im Bricks-Editor, Admin, REST, Feeds usw. */
	private function should_run(): bool {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_embed() || is_customize_preview() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		// Bricks-Editor und Editor-Vorschau (?bricks=run, Canvas-Iframe)
		if ( isset( $_GET['bricks'] ) || ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return false;
		}
		return (bool) apply_filters( 'bq_motion_enabled', true );
	}

	/** Output-Buffer-Callback: fertiges HTML prüfen und Skripte einfügen. */
	public function process( $html ) {
		if ( ! is_string( $html ) || false === stripos( $html, '</head>' ) || ! $this->is_html_response() ) {
			return $html;
		}

		$result = $this->detector->detect( $html );
		$libs   = apply_filters( 'bq_motion_libraries', $result['libs'], $html );
		$libs   = array_values( array_diff( $libs, $this->detector->already_loaded( $html, $libs ) ) );

		if ( empty( $libs ) ) {
			return $html;
		}

		$tags = $this->tags( $libs, $result['reasons'] );
		$pos = stripos( $html, '</head>' );
		return substr( $html, 0, $pos ) . $tags . substr( $html, $pos );
	}

	private function is_html_response(): bool {
		foreach ( headers_list() as $header ) {
			if ( 0 === stripos( $header, 'content-type:' ) && false === stripos( $header, 'text/html' ) ) {
				return false;
			}
		}
		return true;
	}

	private function tags( array $libs, array $reasons ): string {
		$base  = plugin_dir_url( BQ_MOTION_FILE ) . 'vendor/';
		$defer = (bool) apply_filters( 'bq_motion_defer', false );
		$out   = "\n";

		if ( $this->debug ) {
			$out .= sprintf(
				"<!-- blanq Motion %s: %s (Grund: %s) -->\n",
				esc_html( BQ_MOTION_VERSION ),
				esc_html( implode( ', ', $libs ) ),
				esc_html( $reasons ? implode( ', ', $reasons ) : 'Filter' )
			);
		}

		foreach ( $libs as $name ) {
			$lib = $this->manifest['libs'][ $name ];
			if ( ! empty( $lib['css'] ) ) {
				$out .= sprintf(
					"<link rel=\"stylesheet\" id=\"bq-motion-%s-css\" href=\"%s\">\n",
					esc_attr( strtolower( $name ) ),
					esc_url( $base . $lib['css'] . '?ver=' . $lib['version'] )
				);
			}
			$out .= sprintf(
				"<script id=\"bq-motion-%s-js\" src=\"%s\"%s></script>\n",
				esc_attr( strtolower( $name ) ),
				esc_url( $base . $lib['file'] . '?ver=' . $lib['version'] ),
				$defer ? ' defer' : ''
			);
		}
		return $out;
	}
}
