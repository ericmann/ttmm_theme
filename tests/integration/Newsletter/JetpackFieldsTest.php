<?php
/**
 * Integration test for TTM\Core\Newsletter\Provider\Jetpack against the captured widget form
 * (docs/spikes/P1-jetpack-form.md, docs/fixtures/jetpack-subscriptions.html).
 *
 * @package TTM\Tests\Integration\Newsletter
 */

declare( strict_types=1 );

use TTM\Core\Cli\Seeder;
use TTM\Core\Newsletter\Form;
use TTM\Core\Newsletter\Provider\Jetpack;

class JetpackFieldsTest extends TTM_IntegrationTestCase {

	public function tear_down(): void {
		Form::reset();
		parent::tear_down();
	}

	/**
	 * Every `name="…"` attribute (inputs and the submit button) found in the captured fixture.
	 *
	 * @return string[]
	 */
	private function fixture_field_names(): array {
		$path = Seeder::fixtures_root_dir() . '/jetpack-subscriptions.html';
		$this->assertFileExists( $path, 'Run P1-07 first: docs/fixtures/jetpack-subscriptions.html is missing.' );

		$html = (string) file_get_contents( $path );

		if ( ! preg_match_all( '/name="([^"]+)"/', $html, $matches ) ) {
			return [];
		}

		return array_values( array_unique( $matches[1] ) );
	}

	public function test_provider_fields_match_captured_widget_form(): void {
		// docs/spikes/P1-jetpack-form.md recorded Outcome B (Jetpack was reachable in wp-env),
		// so this always runs; an Outcome C spike would have hand-authored the fixture from
		// SPEC §6.3 and this test would still hold against that fallback fixture.
		$fixture_fields = $this->fixture_field_names();

		$provider = new Jetpack();
		$html     = $provider->render( 'poster' );

		if ( ! preg_match_all( '/name="([^"]+)"/', $html, $matches ) ) {
			$this->fail( 'Jetpack provider rendered no named fields.' );
		}

		foreach ( $matches[1] as $emitted_name ) {
			if ( 'email' === $emitted_name ) {
				continue; // Present in both; not a "hidden field" per se.
			}

			$this->assertContains(
				$emitted_name,
				$fixture_fields,
				"Provider emits field '{$emitted_name}', which is not in the captured widget form."
			);
		}
	}
}
