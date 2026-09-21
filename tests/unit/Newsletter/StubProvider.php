<?php
/**
 * A Provider stand-in with a fixed slug/availability, for exercising Providers::resolve()'s
 * fallback chain in isolation from WordPress and Config.
 *
 * @package TTM\Tests\Unit\Newsletter
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Newsletter;

use TTM\Core\Newsletter\Provider\Provider;

class StubProvider implements Provider {

	public function __construct( private string $stub_slug, private bool $is_available ) {}

	public function slug(): string {
		return $this->stub_slug;
	}

	public function available(): bool {
		return $this->is_available;
	}

	public function render( string $placement ): string {
		return $this->stub_slug . ':' . $placement;
	}
}
