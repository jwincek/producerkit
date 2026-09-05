<?php
/**
 * The prompt that asks what a producer makes.
 *
 * Found by walking the getting-started guide against a clean install: nothing
 * ever asks. Everything downstream is named by the answer, and the default is
 * a farm because that is where the plugin started — so a beekeeper quietly
 * gets somebody else's words, and the longer they go the more a switch costs
 * them in re-reading.
 */

declare(strict_types=1);

use ProducerKit\ProducerProfiles\FirstRun;
use ProducerKit\ProducerProfiles\Profiles;

final class FirstRunPromptTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		delete_option( Profiles\OPTION );
		delete_option( FirstRun\DISMISSED );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	public function tear_down(): void {
		delete_option( Profiles\OPTION );
		delete_option( FirstRun\DISMISSED );
		parent::tear_down();
	}

	public function test_a_fresh_install_is_asked(): void {
		$this->assertTrue( FirstRun\should_prompt() );
	}

	public function test_choosing_anything_stops_the_asking(): void {
		update_option( Profiles\OPTION, [ 'beekeeping' ] );

		$this->assertFalse( FirstRun\should_prompt() );
	}

	public function test_deliberately_choosing_farm_also_stops_it(): void {
		// The case the whole detection turns on. active_slugs() falls back to
		// 'farm', so asking it can never tell a site that picked a farm from
		// one that has never been asked — and those are exactly the two that
		// need telling apart.
		update_option( Profiles\OPTION, [ 'farm' ] );

		$this->assertSame( [ 'farm' ], Profiles\active_slugs() );
		$this->assertTrue( FirstRun\profile_chosen() );
		$this->assertFalse( FirstRun\should_prompt() );
	}

	public function test_an_unset_option_still_reports_farm_as_active(): void {
		// The other half of the same fact, asserted so a future change to
		// DEFAULT_SLUG handling cannot quietly break the detection.
		$this->assertSame( [ 'farm' ], Profiles\active_slugs() );
		$this->assertFalse( FirstRun\profile_chosen() );
	}

	public function test_dismissing_stops_the_asking(): void {
		update_option( FirstRun\DISMISSED, 1 );

		$this->assertFalse( FirstRun\should_prompt() );
	}

	public function test_somebody_who_cannot_edit_is_never_asked(): void {
		// It is not their question.
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$this->assertFalse( FirstRun\should_prompt() );
	}

	public function test_the_dismissal_is_site_wide_not_per_user(): void {
		// The choice being prompted for is site-wide, so nagging a colleague
		// about a decision somebody already made is asking a question that is
		// not theirs to answer.
		update_option( FirstRun\DISMISSED, 1 );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$this->assertFalse( FirstRun\should_prompt() );
	}

	public function test_the_prompt_renders_only_where_it_is_relevant(): void {
		// Not every admin page. Someone writing a blog post is not being
		// asked what trade they practise, and a notice everywhere is how a
		// plugin teaches people to ignore its notices.
		set_current_screen( 'edit-post' );
		$this->assertFalse( FirstRun\on_a_relevant_screen() );

		set_current_screen( 'pkit_product' );
		$this->assertTrue( FirstRun\on_a_relevant_screen() );

		set_current_screen( 'pkit_event' );
		$this->assertTrue( FirstRun\on_a_relevant_screen() );
	}

	public function test_it_renders_nothing_when_it_should_not(): void {
		update_option( Profiles\OPTION, [ 'farm' ] );
		set_current_screen( 'pkit_product' );

		ob_start();
		FirstRun\render();

		$this->assertSame( '', ob_get_clean() );
	}

	public function test_it_renders_a_way_forward_and_a_way_out(): void {
		set_current_screen( 'pkit_product' );

		ob_start();
		FirstRun\render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'page=pkit-producer-profile', $html, 'A link to the screen that answers it.' );
		$this->assertStringContainsString( FirstRun\DISMISS_ARG, $html, 'And a way to stop being asked.' );
		$this->assertStringContainsString( '_wpnonce', $html, 'The dismissal is a state change, so it carries a nonce.' );
	}

	public function test_the_uninstaller_knows_about_the_flag(): void {
		// An option left behind is the kind of thing nobody notices until a
		// reinstall behaves oddly.
		$this->assertStringContainsString(
			FirstRun\DISMISSED,
			(string) file_get_contents( dirname( __DIR__, 2 ) . '/uninstall.php' )
		);
	}
}
