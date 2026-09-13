<?php
/**
 * The getting-started guide, in the site's own words.
 *
 * A plugin whose whole argument is that a potter should not be shown a farm's
 * words should not hand them a farm's manual either. The guide is authored
 * once with {{tokens}} and rendered twice: farm-flavoured into
 * GETTING-STARTED.md for the repo, and live in the dashboard against whatever
 * trade the site chose.
 */

declare(strict_types=1);

namespace ProducerKit\Tests\Integration;

use WP_UnitTestCase;

use ProducerKit\Guide;
use ProducerKit\ProducerProfiles\Profiles;


class GuideTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		\ProducerKit\Core\Post_Types\register();
		\ProducerKit\Core\Taxonomies\register();
	}

	public function tear_down(): void {
		delete_option( Profiles\OPTION );
		parent::tear_down();
	}

	private function template(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/docs/getting-started.tpl.md' );
	}

	/**
	 * Nothing may reach a reader as {{braces}}.
	 */
	public function test_every_token_in_the_template_resolves(): void {
		preg_match_all( '/\{\{([a-z_]+)\}\}/', $this->template(), $found );

		$tokens = Guide\tokens();

		foreach ( array_unique( $found[1] ) as $token ) {
			$this->assertArrayHasKey( $token, $tokens, "The template uses {{{$token}}} and nothing resolves it." );
			$this->assertNotSame( '', trim( (string) $tokens[ $token ] ), "{{{$token}}} resolves to nothing." );
		}
	}

	public function test_the_rendered_guide_has_no_tokens_left(): void {
		$html = Guide\html();

		$this->assertNotSame( '', $html, 'The guide rendered empty.' );
		$this->assertDoesNotMatchRegularExpression( '/\{\{[a-z_]+\}\}/', $html );
	}

	/**
	 * The point of the exercise.
	 */
	public function test_a_beekeeper_reads_a_beekeepers_guide(): void {
		update_option( Profiles\OPTION, [ 'beekeeping' ] );

		$html = Guide\html();

		$this->assertStringContainsString( 'home yard', $html );
		$this->assertStringContainsString( 'Hive Notes', $html );
		$this->assertStringContainsString( 'Apiary', $html );

		$this->assertStringNotContainsString( 'farm stand', $html );
		$this->assertStringNotContainsString( 'Growing / Baking', $html );
	}

	public function test_a_potter_reads_a_potters_guide(): void {
		update_option( Profiles\OPTION, [ 'pottery' ] );

		$html = Guide\html();

		$this->assertStringContainsString( 'studio', $html );
		$this->assertStringContainsString( 'Clay Supplier', $html );
		$this->assertStringContainsString( 'Commissions', $html );
	}

	/**
	 * Fifteen of the sixteen profiles call their place "The Something", which
	 * is right on a location and wrong after "your".
	 */
	public function test_no_trade_is_told_about_your_the_studio(): void {
		foreach ( Profiles\get_slugs() as $slug ) {
			update_option( Profiles\OPTION, [ $slug ] );

			$this->assertDoesNotMatchRegularExpression(
				'/\byour the\b/i',
				Guide\html(),
				"The {$slug} guide says \"your the …\"."
			);
		}
	}

	/**
	 * The repo's copy is generated with a farm's words. If the generator's
	 * table and the resolver disagree, GitHub readers are told something the
	 * plugin would not say.
	 */
	public function test_the_repo_copy_matches_what_a_farm_would_read(): void {
		update_option( Profiles\OPTION, [ 'farm' ] );

		$markdown = (string) file_get_contents( dirname( __DIR__, 2 ) . '/GETTING-STARTED.md' );
		$tokens   = Guide\tokens();

		$this->assertStringNotContainsString( '{{', $markdown, 'The repo copy still has unresolved tokens.' );

		foreach ( [ 'place_lower', 'requests', 'notes_field', 'source_field' ] as $token ) {
			$this->assertStringContainsString(
				(string) $tokens[ $token ],
				$markdown,
				"GETTING-STARTED.md does not contain the farm value for {{{$token}}}, so the "
					. 'generator and the resolver have drifted apart.'
			);
		}
	}

	/**
	 * Block and menu names are labels a reader will hunt for on screen. They
	 * are not vocabulary and must survive every trade unchanged.
	 */
	public function test_fixed_ui_labels_are_never_substituted(): void {
		foreach ( [ 'beekeeping', 'pottery', 'musician' ] as $slug ) {
			update_option( Profiles\OPTION, [ $slug ] );

			$html = Guide\html();

			foreach ( [ 'Stand Status Banner', 'Stand Hours Schedule', 'Fresh Sheet' ] as $label ) {
				$this->assertStringContainsString( $label, $html, "{$slug} lost the {$label} label." );
			}
		}
	}
}
