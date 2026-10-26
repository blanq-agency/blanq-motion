<?php
/**
 * Erkennt im fertigen HTML einer Seite, welche Animations-Bibliotheken
 * gebraucht werden. Kennt WordPress nicht, damit es sich ohne WordPress
 * testen lässt.
 */

namespace Blanq\Motion;

defined( 'ABSPATH' ) || defined( 'BQ_MOTION_TESTING' ) || exit;

class Detector {

	/** @var array Manifest aus vendor/manifest.json */
	private $manifest;

	public function __construct( array $manifest ) {
		$this->manifest = $manifest;
	}

	/**
	 * Liefert die benötigten Bibliotheken in Ladereihenfolge
	 * (Abhängigkeiten zuerst) und die Gründe dafür.
	 *
	 * @return array{libs: string[], reasons: string[]}
	 */
	public function detect( string $html ): array {
		$libs    = array();
		$reasons = array();
		$scripts = $this->inline_scripts( $html );

		// 1. Bricksmotion-Komponenten
		if ( preg_match( '/\bBM\.(ready|load)\s*\(/', $scripts ) ) {
			$reasons[] = 'Bricksmotion';
			$libs[]    = 'gsap';
			$libs[]    = 'ScrollTrigger';

			// Plugins aus BM.load(['SplitText', ...])
			if ( preg_match_all( '/\bBM\.load\s*\(\s*\[([^\]]*)\]/', $scripts, $calls ) ) {
				foreach ( $calls[1] as $list ) {
					preg_match_all( '/[\'"]([A-Za-z0-9]+)[\'"]/', $list, $names );
					foreach ( $names[1] as $name ) {
						$resolved = $this->resolve( $name );
						if ( $resolved ) {
							$libs[] = $resolved;
						}
					}
				}
			}

			// Bricksmotion nutzt Lenis, außer es ist am <html>-Tag abgeschaltet.
			if ( ! preg_match( '/<html[^>]*\bdata-bm-lenis=["\']?off/i', $html ) ) {
				$libs[] = 'Lenis';
			}
		}

		// 2. Eigener GSAP-Code (z. B. in Bricks-Code-Elementen)
		if ( preg_match( '/\bgsap\s*\./', $scripts ) ) {
			$reasons[] = 'GSAP-Code';
			$libs[]    = 'gsap';
		}
		foreach ( array_keys( $this->manifest['libs'] ) as $name ) {
			if ( 'gsap' === $name ) {
				continue;
			}
			if ( preg_match( '/\b' . preg_quote( $name, '/' ) . '\b/', $scripts ) ) {
				$libs[] = $name;
			}
		}

		// 3. Ausdrücklich angefordert: data-bq-gsap="Flip Draggable" an einem Element
		if ( preg_match_all( '/\bdata-bq-gsap=["\']([^"\']*)["\']/i', $html, $attrs ) ) {
			$reasons[] = 'data-bq-gsap';
			$libs[]    = 'gsap';
			foreach ( $attrs[1] as $value ) {
				foreach ( preg_split( '/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY ) as $name ) {
					$resolved = $this->resolve( $name );
					if ( $resolved ) {
						$libs[] = $resolved;
					}
				}
			}
		}

		return array(
			'libs'    => $this->order( array_unique( $libs ) ),
			'reasons' => array_values( array_unique( $reasons ) ),
		);
	}

	/**
	 * Bibliotheken, die bereits von anderer Stelle (z. B. Bricksforge) per
	 * <script src> eingebunden werden, nicht doppelt laden.
	 */
	public function already_loaded( string $html, array $libs ): array {
		$loaded = array();
		if ( ! preg_match_all( '/<script\b[^>]*\bsrc=["\']([^"\']+)["\']/i', $html, $m ) ) {
			return $loaded;
		}
		foreach ( $libs as $name ) {
			$file = 'Lenis' === $name ? 'lenis' : $name;
			foreach ( $m[1] as $src ) {
				if ( preg_match( '#/' . preg_quote( $file, '#' ) . '(\.min)?\.js(\?|$)#i', $src ) ) {
					$loaded[] = $name;
					break;
				}
			}
		}
		return $loaded;
	}

	/** Name aus Code oder Attribut auf einen Manifest-Eintrag abbilden. */
	public function resolve( string $name ): ?string {
		$libs = $this->manifest['libs'];
		foreach ( array( $name, $name . 'Plugin', ucfirst( $name ), ucfirst( $name ) . 'Plugin' ) as $candidate ) {
			if ( isset( $libs[ $candidate ] ) ) {
				return $candidate;
			}
		}
		if ( 'lenis' === strtolower( $name ) ) {
			return 'Lenis';
		}
		return null;
	}

	/** Abhängigkeiten ergänzen und in Ladereihenfolge bringen. */
	private function order( array $libs ): array {
		$ordered = array();
		$visit   = function ( $name ) use ( &$visit, &$ordered ) {
			if ( in_array( $name, $ordered, true ) || ! isset( $this->manifest['libs'][ $name ] ) ) {
				return;
			}
			foreach ( $this->manifest['libs'][ $name ]['deps'] as $dep ) {
				$visit( $dep );
			}
			$ordered[] = $name;
		};
		foreach ( $libs as $name ) {
			$visit( $name );
		}
		return $ordered;
	}

	/** Inhalt aller Inline-Skripte (ohne src, ohne JSON und Templates). */
	private function inline_scripts( string $html ): string {
		if ( ! preg_match_all( '/<script\b([^>]*)>(.*?)<\/script>/is', $html, $m, PREG_SET_ORDER ) ) {
			return '';
		}
		$code = '';
		foreach ( $m as $script ) {
			$attrs = $script[1];
			if ( preg_match( '/\bsrc\s*=/i', $attrs ) ) {
				continue;
			}
			if ( preg_match( '/\btype=["\']?(application\/(ld\+)?json|text\/template|text\/html|text\/x-template|importmap|speculationrules)/i', $attrs ) ) {
				continue;
			}
			$code .= "\n" . $script[2];
		}
		return $code;
	}
}
