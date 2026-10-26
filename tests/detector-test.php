<?php
/**
 * Tests der Bedarfserkennung ohne WordPress:  php tests/detector-test.php
 */

define( 'BQ_MOTION_TESTING', true );
require __DIR__ . '/../includes/class-detector.php';

use Blanq\Motion\Detector;

$manifest = json_decode( file_get_contents( __DIR__ . '/../vendor/manifest.json' ), true );
$d        = new Detector( $manifest );
$failed   = 0;

function check( string $label, array $expected, array $actual ): void {
	global $failed;
	if ( $expected === $actual ) {
		echo "✓ $label\n";
		return;
	}
	$failed++;
	echo "✗ $label\n  erwartet: " . implode( ', ', $expected ) . "\n  erhalten: " . implode( ', ', $actual ) . "\n";
}

$page = fn( string $body, string $html_attr = '' ) => "<!doctype html><html$html_attr><head><title>T</title></head><body>$body</body></html>";

// Seite ohne Animationen
check( 'Normale Seite lädt nichts', array(), $d->detect( $page( '<h1>Hallo</h1><script>var x = 1;</script>' ) )['libs'] );

// Bricksmotion-Komponente mit SplitText
$bm = '<section class="ts6__component"></section><script>BM.ready(function(){ BM.load([\'SplitText\']).then(function(){}); });</script>';
check( 'Bricksmotion mit SplitText', array( 'gsap', 'ScrollTrigger', 'SplitText', 'Lenis' ), $d->detect( $page( $bm ) )['libs'] );

// Bricksmotion mit abgeschaltetem Lenis
check( 'Bricksmotion ohne Lenis', array( 'gsap', 'ScrollTrigger', 'SplitText' ), $d->detect( $page( $bm, ' lang="de" data-bm-lenis="off"' ) )['libs'] );

// Bricksmotion mit Plugin-Kurzname (ScrambleText → ScrambleTextPlugin)
check(
	'Bricksmotion-Kurzname wird aufgelöst',
	array( 'gsap', 'ScrollTrigger', 'ScrambleTextPlugin', 'Lenis' ),
	$d->detect( $page( '<script>BM.ready(function(){BM.load(["ScrambleText"])})</script>' ) )['libs']
);

// Eigener GSAP-Code aus einem Bricks-Code-Element
$own = '<script>gsap.registerPlugin(ScrollTrigger, Flip); gsap.from(".bq-hero__title", {y: 40});</script>';
check( 'Eigener Code mit ScrollTrigger und Flip', array( 'gsap', 'Flip', 'ScrollTrigger' ), $d->detect( $page( $own ) )['libs'] );

// Abhängigkeiten: ScrollSmoother braucht ScrollTrigger
check(
	'ScrollSmoother zieht ScrollTrigger nach',
	array( 'gsap', 'ScrollTrigger', 'ScrollSmoother' ),
	$d->detect( $page( '<script>ScrollSmoother.create({smooth: 1});</script>' ) )['libs']
);

// Ausdrücklich per Attribut angefordert
check(
	'data-bq-gsap Attribut',
	array( 'gsap', 'Draggable', 'InertiaPlugin' ),
	$d->detect( $page( '<div data-bq-gsap="Draggable, Inertia"></div>' ) )['libs']
);

// Fehlalarme vermeiden
check(
	'IntersectionObserver ist kein GSAP-Observer',
	array(),
	$d->detect( $page( '<script>new IntersectionObserver(function(){});</script>' ) )['libs']
);
check(
	'JSON-LD wird ignoriert',
	array(),
	$d->detect( $page( '<script type="application/ld+json">{"name":"gsap.Flip ScrollTrigger"}</script>' ) )['libs']
);
check(
	'Externe Skripte werden nicht als Code gelesen',
	array(),
	$d->detect( $page( '<script src="/app.js?gsap.to"></script><p>ScrollTrigger im Fließtext</p>' ) )['libs']
);

// Bereits von anderer Stelle geladen (z. B. Bricksforge)
$html = $page( '<script src="/wp-content/plugins/bricksforge/assets/vendor/gsap.min.js?ver=3"></script>' . $own );
check( 'Bereits geladenes GSAP wird erkannt', array( 'gsap' ), $d->already_loaded( $html, array( 'gsap', 'Flip', 'ScrollTrigger' ) ) );

echo $failed ? "\n$failed Test(s) fehlgeschlagen\n" : "\nAlle Tests bestanden\n";
exit( $failed ? 1 : 0 );
