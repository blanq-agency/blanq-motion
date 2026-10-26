<?php
/** Tests des Update-Zeitplans:  php tests/schedule-test.php */
define( 'BQ_MOTION_TESTING', true );
require __DIR__ . '/../includes/schedule.php';

$tz     = new DateTimeZone( 'Europe/Berlin' );
$failed = 0;
$cases  = array(
	// Datum, Modus, erwartet
	array( '2026-11-02 09:00', 'first-monday', true ),  // erster Montag November
	array( '2026-11-09 09:00', 'first-monday', false ), // zweiter Montag
	array( '2026-11-03 09:00', 'first-monday', false ), // Dienstag
	array( '2026-12-07 00:30', 'first-monday', true ),  // erster Montag Dezember (Tag 7)
	array( '2026-12-14 00:30', 'first-monday', false ),
	array( '2027-02-01 23:59', 'first-monday', true ),  // Monatserster ist Montag
	array( '2026-11-09 09:00', 'always', true ),
	array( '2026-11-02 09:00', false, false ),
);
foreach ( $cases as [ $date, $mode, $expected ] ) {
	$ok = bq_motion_auto_update_allowed( $mode, new DateTimeImmutable( $date, $tz ) ) === $expected;
	$failed += $ok ? 0 : 1;
	printf( "%s %s %s → %s\n", $ok ? '✓' : '✗', $date, var_export( $mode, true ), var_export( $expected, true ) );
}
echo $failed ? "\n$failed Test(s) fehlgeschlagen\n" : "\nAlle Tests bestanden\n";
exit( $failed ? 1 : 0 );
