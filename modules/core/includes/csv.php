<?php
/**
 * Reading and writing CSV, safely, in one place.
 *
 * Every CSV this plugin produces or consumes goes through write_row() and
 * read_row(), for two reasons that are easy to get right once and easy to
 * forget each time.
 *
 * Formula injection. A cell beginning = + - @ or a control character runs as a
 * formula when the file is opened in Excel or Sheets, where HYPERLINK and
 * WEBSERVICE can send the rest of the sheet elsewhere. The product export wrote
 * titles, excerpts and notes — anything a Contributor can type — straight into
 * cells, while the RSVP export escaped them (#99). Same shape, one path
 * hardened. Now there is one path.
 *
 * The escape character. PHP 8.4 deprecated relying on fputcsv(), fgetcsv() and
 * str_getcsv()'s default $escape, because the default is changing. With display
 * errors on, that deprecation is printed into the response — inside the
 * downloaded file, ahead of the data. It is pinned here to the historical
 * default so files exported before this still read back byte-for-byte.
 */

declare(strict_types=1);

namespace ProducerKit\Core\Csv;

defined( 'ABSPATH' ) || exit;

const DELIMITER = ',';
const ENCLOSURE = '"';
const ESCAPE    = '\\';

/**
 * Characters that make a spreadsheet treat a cell as a formula.
 */
const FORMULA_LEAD = '/^[=+\-@\t\r]/';

/**
 * Defuse a cell a spreadsheet would run as a formula.
 *
 * Numbers are left alone so numeric columns still import as numbers — a price
 * of -5 is data, not an instruction.
 */
function escape_field( string $value ): string {
	if ( '' === $value || is_numeric( $value ) ) {
		return $value;
	}

	return preg_match( FORMULA_LEAD, $value ) ? "'" . $value : $value;
}

/**
 * Exactly undo escape_field(), and nothing else.
 *
 * The product CSV is exported and then imported again, so escaping on the way
 * out without reversing it on the way in would corrupt the round trip: a real
 * product called "-40° Frozen Berries" would come back with an apostrophe
 * permanently in its title. Only a single leading apostrophe followed by a
 * character escape_field() would have guarded is removed, so an apostrophe that
 * was part of the data survives.
 */
function unescape_field( string $value ): string {
	if ( isset( $value[1] ) && "'" === $value[0] && preg_match( FORMULA_LEAD, substr( $value, 1 ) ) ) {
		return substr( $value, 1 );
	}

	return $value;
}

/**
 * Write one row, every cell escaped.
 *
 * @param resource             $handle
 * @param array<int, scalar|null> $cells
 */
function write_row( $handle, array $cells ): void {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- Streaming to php://output; WP_Filesystem has no CSV writer.
	fputcsv(
		$handle,
		array_map( static fn ( $cell ): string => escape_field( (string) $cell ), $cells ),
		DELIMITER,
		ENCLOSURE,
		ESCAPE
	);
}

/**
 * Read one row, every cell unescaped. False at the end of the file.
 *
 * @param resource $handle
 * @return array<int, string>|false
 */
function read_row( $handle ): array|false {
	$cells = fgetcsv( $handle, null, DELIMITER, ENCLOSURE, ESCAPE );

	if ( false === $cells ) {
		return false;
	}

	return array_map( static fn ( $cell ): string => unescape_field( (string) $cell ), $cells );
}
