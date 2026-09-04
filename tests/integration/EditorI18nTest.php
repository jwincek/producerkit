<?php
/**
 * Editor-facing JavaScript strings must be translatable.
 *
 * The PHP side of this plugin has always been fully translated, and the
 * sidebar panels and block controls sitting beside it were hard-coded English
 * — 161 strings a translator could not reach, because `wp i18n make-pot` only
 * sees what is wrapped.
 *
 * Two halves have to stay true together, which is why they are asserted here
 * rather than trusted:
 *
 *   1. The strings are wrapped in __() with this plugin's text domain.
 *   2. Something registers a catalogue against the handle. Without that the
 *      __() calls still run and still resolve to themselves, so the wrapping
 *      is decoration. Blocks get this from block.json's textdomain, which
 *      WP_Scripts::set_translations() honours by also adding the wp-i18n
 *      dependency; the three sidebar scripts are not blocks and need the
 *      explicit wp_set_script_translations() call.
 */

declare(strict_types=1);

final class EditorI18nTest extends WP_UnitTestCase {

	/**
	 * Keys whose value a human reads.
	 *
	 * `instructions` is on this list because leaving it off is how the first
	 * pass missed a whole category — the Placeholder component's body text,
	 * which is as user-facing as any label.
	 */
	private const TRANSLATABLE_KEYS = [ 'label', 'title', 'help', 'placeholder', 'alt', 'instructions' ];

	/**
	 * @return string[]
	 */
	private function editor_scripts(): array {
		$root = dirname( __DIR__, 2 );

		return array_merge(
			(array) glob( $root . '/assets/js/editor-*.js' ),
			(array) glob( $root . '/blocks/*/index.js' )
		);
	}

	public function test_there_are_editor_scripts_to_check(): void {
		// A glob that silently matches nothing would make every test below
		// pass by vacuum.
		$this->assertGreaterThan( 10, count( $this->editor_scripts() ) );
	}

	public function test_no_user_visible_string_is_left_bare(): void {
		$bare = [];

		foreach ( $this->editor_scripts() as $file ) {
			$source = (string) file_get_contents( $file );

			preg_match_all(
				"/\b(" . implode( '|', self::TRANSLATABLE_KEYS ) . ")\s*:\s*'((?:[^'\\\\]|\\\\.)*)'/",
				$source,
				$matches,
				PREG_SET_ORDER
			);

			foreach ( $matches as $match ) {
				// A blank is a deliberate absence and a symbol is not a
				// sentence; neither is a message anybody translates.
				if ( '' === trim( $match[2] ) || ! preg_match( '/[A-Za-z]/', $match[2] ) ) {
					continue;
				}

				$bare[] = basename( dirname( $file ) ) . '/' . basename( $file ) . ': ' . $match[0];
			}
		}

		$this->assertSame(
			[],
			$bare,
			"These editor strings are hard-coded English:\n" . implode( "\n", $bare )
		);
	}

	public function test_identifiers_are_never_wrapped(): void {
		// The mirror of the test above, and the more dangerous direction: a
		// translated option value, CSS class or icon name is not a cosmetic
		// problem but a broken control, and it would break only in locales
		// nobody here tests in.
		$wrapped = [];

		foreach ( $this->editor_scripts() as $file ) {
			preg_match_all(
				"/\b(value|className|icon|type|name|key|tagName|role|variant|scope|method|status)\s*:\s*__\(/",
				(string) file_get_contents( $file ),
				$matches,
				PREG_SET_ORDER
			);

			foreach ( $matches as $match ) {
				$wrapped[] = basename( dirname( $file ) ) . '/' . basename( $file ) . ': ' . $match[0];
			}
		}

		$this->assertSame( [], $wrapped, implode( "\n", $wrapped ) );
	}

	public function test_every_wrapped_string_uses_this_plugins_domain(): void {
		$wrong = [];

		foreach ( $this->editor_scripts() as $file ) {
			preg_match_all(
				"/__\(\s*'(?:[^'\\\\]|\\\\.)*'\s*,\s*'([^']+)'\s*\)/",
				(string) file_get_contents( $file ),
				$matches,
				PREG_SET_ORDER
			);

			foreach ( $matches as $match ) {
				if ( 'producerkit' !== $match[1] ) {
					$wrong[] = basename( $file ) . ': ' . $match[1];
				}
			}
		}

		$this->assertSame( [], $wrong, implode( "\n", $wrong ) );
	}

	public function test_a_wrapped_file_actually_has_the_translate_function(): void {
		$missing = [];

		foreach ( $this->editor_scripts() as $file ) {
			$source = (string) file_get_contents( $file );

			if ( ! str_contains( $source, '__(' ) ) {
				continue;
			}

			if ( ! str_contains( $source, 'const __ =' ) ) {
				$missing[] = basename( dirname( $file ) ) . '/' . basename( $file );
			}
		}

		$this->assertSame(
			[],
			$missing,
			"These call __() without defining it, which is a ReferenceError the moment the panel renders:\n"
				. implode( "\n", $missing )
		);
	}

	public function test_the_sidebar_scripts_register_a_catalogue(): void {
		// Blocks get this from block.json; these three are not blocks, so
		// without the explicit call their wrapped strings stay English.
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/producerkit.php' );

		$this->assertStringContainsString( 'wp_set_script_translations( $handle, \'producerkit\' )', $source );
		$this->assertStringContainsString( "'wp-i18n'", $source );
	}

	public function test_every_block_declares_the_text_domain(): void {
		// register_block_type_from_metadata() only calls
		// wp_set_script_translations() when this is present.
		foreach ( (array) glob( dirname( __DIR__, 2 ) . '/blocks/*/block.json' ) as $file ) {
			$meta = json_decode( (string) file_get_contents( $file ), true );

			$this->assertSame(
				'producerkit',
				$meta['textdomain'] ?? null,
				basename( dirname( $file ) ) . ' would ship untranslatable block strings.'
			);
		}
	}
}
