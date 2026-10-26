<?php
/**
 * Zeitplan für automatische Updates.
 */

defined( 'ABSPATH' ) || defined( 'BQ_MOTION_TESTING' ) || exit;

/** Erster Montag des Monats = Montag mit Tag 1 bis 7. */
function bq_motion_is_first_monday( DateTimeInterface $date ): bool {
	return '1' === $date->format( 'N' ) && (int) $date->format( 'j' ) <= 7;
}

/**
 * @param mixed             $mode 'first-monday' (Standard), 'always' oder false
 * @param DateTimeInterface $now  aktuelle Zeit in der Zeitzone der Website
 */
function bq_motion_auto_update_allowed( $mode, DateTimeInterface $now ): bool {
	if ( 'always' === $mode || true === $mode ) {
		return true;
	}
	if ( 'first-monday' === $mode ) {
		return bq_motion_is_first_monday( $now );
	}
	return false;
}
