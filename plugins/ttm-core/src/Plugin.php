<?php
/**
 * Composition root: boots every plugin module exactly once.
 *
 * @package TTM\Core
 */

declare( strict_types=1 );

namespace TTM\Core;

/**
 * Boots every plugin module exactly once.
 */
class Plugin {

	/**
	 * Guards against double registration.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/**
	 * Ordered list of module classes. Each must expose a static `register()`.
	 *
	 * @return string[]
	 */
	public static function modules(): array {
		return [
			Compat\Theme::class,
			Taxonomy\Series::class,
			Taxonomy\SeriesAdmin::class,
			Meta\PostMeta::class,
			Meta\PrimaryCategory::class,
			Meta\Form::class,
			Meta\WordCount::class,
			Query\SeriesIndex::class,
			Query\Stats::class,
			Editor\Sidebar::class,
			Editor\Columns::class,
			Admin\Page::class,
			Admin\General::class,
			Fiction\Books::class,
			Rest\SeriesController::class,
			Rest\LeadController::class,
			Cli\Loader::class,
			Nav\CurrentSection::class,
			Templates\Hierarchy::class,
		];
	}

	/**
	 * Register every module once.
	 */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		foreach ( self::modules() as $module ) {
			$module::register();
		}
	}

	/**
	 * Clear the boot guard. Test-only.
	 */
	public static function reset(): void {
		self::$booted = false;
	}
}
