<?php
/**
 * Integration test for TTM\Core\Newsletter\Provider\Jetpack against the captured widget form
 * (docs/spikes/P1-jetpack-form.md, docs/fixtures/jetpack-subscriptions.html): the widget's own
 * markup requires a nonce (R1-01), so the provider deliberately does not reproduce it.
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

	public function test_widget_contract_requires_a_nonce_so_the_provider_does_not_emulate_the_widget_post(): void {
		// docs/spikes/P1-jetpack-form.md ("Handler (review R1-01)"): the captured widget form
		// *does* require a `_wpnonce` (Jetpack_Subscriptions::widget_submit() validates it), which
		// rule 7 forbids on cacheable output. The provider therefore never reproduces that
		// widget's markup; it posts through the site's own admin-post.php handler instead.
		$fixture_fields = $this->fixture_field_names();

		$this->assertContains(
			'_wpnonce',
			$fixture_fields,
			'The captured widget form fixture should require a nonce -- this is the whole reason the provider does not emulate it.'
		);

		$provider = new Jetpack();
		$html     = $provider->render( 'poster' );

		$this->assertStringNotContainsString( '_wpnonce', $html );
		$this->assertStringNotContainsString( 'name="jetpack_subscriptions_widget"', $html );
	}
}
