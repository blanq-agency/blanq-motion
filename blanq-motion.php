<?php
/**
 * Plugin Name:       blanq Motion
 * Description:       Lädt GSAP und Lenis lokal (ohne CDN) und nur auf Seiten, die sie brauchen – für Bricksmotion-Komponenten und eigenen GSAP-Code.
 * Version:           1.0.2
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            blanq GmbH
 * Author URI:        https://blanq.agency
 * License:           GPL-2.0-or-later
 * Text Domain:       blanq-motion
 * Update URI:        https://github.com/blanq-agency/blanq-motion
 */

defined( 'ABSPATH' ) || exit;

define( 'BQ_MOTION_VERSION', '1.0.2' );
define( 'BQ_MOTION_FILE', __FILE__ );

/*
 * Einstellungen – bei Bedarf in der wp-config.php überschreiben:
 *
 *   define( 'BQ_MOTION_REPO', 'https://github.com/blanq-agency/blanq-motion/' );
 *   define( 'BQ_MOTION_GITHUB_TOKEN', 'github_pat_…' ); // nur bei privatem Repository
 *   define( 'BQ_MOTION_AUTO_UPDATE', 'first-monday' );  // Standard: nur am ersten Montag des Monats
 *   define( 'BQ_MOTION_AUTO_UPDATE', 'always' );        // sofort, sobald ein Update da ist
 *   define( 'BQ_MOTION_AUTO_UPDATE', false );           // nie automatisch, nur von Hand
 */
defined( 'BQ_MOTION_REPO' ) || define( 'BQ_MOTION_REPO', 'https://github.com/blanq-agency/blanq-motion/' );
defined( 'BQ_MOTION_AUTO_UPDATE' ) || define( 'BQ_MOTION_AUTO_UPDATE', 'first-monday' );

require_once __DIR__ . '/includes/schedule.php';
require_once __DIR__ . '/includes/class-detector.php';
require_once __DIR__ . '/includes/class-loader.php';

add_action(
	'plugins_loaded',
	function () {
		$manifest_file = __DIR__ . '/vendor/manifest.json';
		if ( ! is_readable( $manifest_file ) ) {
			return;
		}
		$manifest = json_decode( (string) file_get_contents( $manifest_file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( empty( $manifest['libs'] ) ) {
			return;
		}
		( new Blanq\Motion\Loader( new Blanq\Motion\Detector( $manifest ), $manifest ) )->register();
	}
);

/*
 * Updates direkt aus den GitHub-Releases.
 */
$bq_motion_puc = __DIR__ . '/lib/plugin-update-checker/plugin-update-checker.php';
if ( is_readable( $bq_motion_puc ) ) {
	require_once $bq_motion_puc;
	$bq_motion_updater = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		BQ_MOTION_REPO,
		__FILE__,
		'blanq-motion'
	);
	$bq_motion_updater->getVcsApi()->enableReleaseAssets( '/blanq-motion\.zip/' );
	if ( defined( 'BQ_MOTION_GITHUB_TOKEN' ) && BQ_MOTION_GITHUB_TOKEN ) {
		$bq_motion_updater->setAuthentication( BQ_MOTION_GITHUB_TOKEN );
	}
}

/*
 * Automatische Installation nur am ersten Montag des Monats (Zeitzone der Website).
 * WordPress prüft etwa alle 12 Stunden; an den übrigen Tagen wird das Update nur
 * angezeigt und kann von Hand eingespielt werden.
 */
add_filter(
	'auto_update_plugin',
	function ( $update, $item ) {
		if ( ! isset( $item->plugin ) || plugin_basename( __FILE__ ) !== $item->plugin ) {
			return $update;
		}
		return bq_motion_auto_update_allowed( BQ_MOTION_AUTO_UPDATE, current_datetime() );
	},
	10,
	2
);

// In der Plugin-Liste anzeigen, wann automatisch installiert wird.
add_filter(
	'plugin_auto_update_debug_string',
	function ( $text, $plugin, $enabled ) {
		if ( plugin_basename( __FILE__ ) === $plugin && 'first-monday' === BQ_MOTION_AUTO_UPDATE ) {
			return 'Automatisch am ersten Montag des Monats';
		}
		return $text;
	},
	10,
	3
);
