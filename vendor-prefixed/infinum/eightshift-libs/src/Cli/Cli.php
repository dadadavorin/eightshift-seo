<?php

/**
 * The class file that holds abstract class for WP-CLI
 *
 * @package EightshiftLibs\Cli
 *
 * @license MIT
 * Modified by eightshift-meilisearch on 01-April-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace EightshiftSeoVendor\EightshiftLibs\Cli;

use EightshiftSeoVendor\EightshiftLibs\AdminMenus\AdminMenuCli;
use EightshiftSeoVendor\EightshiftLibs\AdminMenus\AdminPatternsHeaderFooterMenu\AdminPatternsHeaderFooterMenuCli;
use EightshiftSeoVendor\EightshiftLibs\AdminMenus\AdminPatternsMenu\AdminPatternsMenuCli;
use EightshiftSeoVendor\EightshiftLibs\AdminMenus\AdminSubMenuCli;
use EightshiftSeoVendor\EightshiftLibs\AdminMenus\AdminThemeOptionsMenu\AdminThemeOptionsMenuCli;
use EightshiftSeoVendor\EightshiftLibs\BlockPatterns\BlockPatternCli;
use EightshiftSeoVendor\EightshiftLibs\Blocks\BlocksCli;
use EightshiftSeoVendor\EightshiftLibs\Cache\ManifestCacheCli;
use EightshiftSeoVendor\EightshiftLibs\Cli\ParentGroups\CliBoilerplate;
use EightshiftSeoVendor\EightshiftLibs\Cli\ParentGroups\CliCreate;
use EightshiftSeoVendor\EightshiftLibs\Cli\ParentGroups\CliRun;
use EightshiftSeoVendor\EightshiftLibs\Cli\ParentGroups\CliBlocks;
use EightshiftSeoVendor\EightshiftLibs\Cli\ParentGroups\CliInit;
use EightshiftSeoVendor\EightshiftLibs\Config\ConfigThemeCli;
use EightshiftSeoVendor\EightshiftLibs\Config\ConfigPluginCli;
use EightshiftSeoVendor\EightshiftLibs\View\EscapedViewCli;
use EightshiftSeoVendor\EightshiftLibs\Setup\SetupCli;
use EightshiftSeoVendor\EightshiftLibs\CustomPostType\PostTypeCli;
use EightshiftSeoVendor\EightshiftLibs\CustomTaxonomy\TaxonomyCli;
use EightshiftSeoVendor\EightshiftLibs\Enqueue\Admin\EnqueueAdminCli;
use EightshiftSeoVendor\EightshiftLibs\Enqueue\Blocks\EnqueueBlocksCli;
use EightshiftSeoVendor\EightshiftLibs\Enqueue\Theme\EnqueueThemeCli;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceExampleCli;
use EightshiftSeoVendor\EightshiftLibs\I18n\I18nCli;
use EightshiftSeoVendor\EightshiftLibs\Login\LoginCli;
use EightshiftSeoVendor\EightshiftLibs\Main\MainCli;
use EightshiftSeoVendor\EightshiftLibs\Media\MediaCli;
use EightshiftSeoVendor\EightshiftLibs\Menu\MenuCli;
use EightshiftSeoVendor\EightshiftLibs\ModifyAdminAppearance\ModifyAdminAppearanceCli;
use EightshiftSeoVendor\EightshiftLibs\Rest\Fields\FieldCli;
use EightshiftSeoVendor\EightshiftLibs\Rest\Routes\RouteCli;
use EightshiftSeoVendor\EightshiftLibs\Geolocation\GeolocationCli;
use EightshiftSeoVendor\EightshiftLibs\Init\InitAllCli;
use EightshiftSeoVendor\EightshiftLibs\Media\RegenerateWebPMediaCli;
use EightshiftSeoVendor\EightshiftLibs\Optimization\OptimizationCli;
use EightshiftSeoVendor\EightshiftLibs\Plugin\PluginCli;
use EightshiftSeoVendor\EightshiftLibs\ThemeOptions\ThemeOptionsCli;
use EightshiftSeoVendor\EightshiftLibs\WpCli\WpCli;
use ReflectionClass;
// phpcs:ignore SlevomatCodingStandard.Namespaces.UnusedUses.UnusedUse
use Exception;
use WP_CLI;

/**
 * Class Cli
 */
class Cli
{
	/**
	 * All commands defined as parent list commands.
	 *
	 * @var array<string>
	 */
	public const PARENT_COMMANDS = [
		CliCreate::class,
		CliRun::class,
		CliBlocks::class,
		CliInit::class,
	];

	/**
	 * All commands that are service classes type. Command prefix - create.
	 *
	 * @var array<string>
	 */
	public const CREATE_COMMANDS = [
		AdminMenuCli::class,
		AdminPatternsMenuCli::class,
		AdminThemeOptionsMenuCli::class,
		AdminSubMenuCli::class,
		AdminPatternsHeaderFooterMenuCli::class,
		ConfigPluginCli::class,
		ConfigThemeCli::class,
		PostTypeCli::class,
		TaxonomyCli::class,
		EnqueueAdminCli::class,
		EnqueueBlocksCli::class,
		EnqueueThemeCli::class,
		GeolocationCli::class,
		I18nCli::class,
		LoginCli::class,
		MainCli::class,
		MediaCli::class,
		MenuCli::class,
		ModifyAdminAppearanceCli::class,
		OptimizationCli::class,
		FieldCli::class,
		RouteCli::class,
		ServiceExampleCli::class,
		SetupCli::class,
		ThemeOptionsCli::class,
		EscapedViewCli::class,
		WpCli::class,
		ManifestCacheCli::class,
		PluginCli::class,
	];

	/**
	 * All commands that can be used on a WP project directly from the libs. Command prefix - run.
	 *
	 * @var array<string>
	 */
	public const RUN_COMMANDS = [
		RegenerateWebPMediaCli::class,
	];

	/**
	 * All commands used for block editor. Command prefix - blocks.
	 *
	 * @var array<string>
	 */
	public const BLOCKS_COMMANDS = [
		BlockPatternCli::class,
		BlocksCli::class,
	];

	/**
	 * All commands used for setting up. Command prefix - init.
	 *
	 * @var array<string>
	 */
	public const INIT_COMMANDS = [
		InitAllCli::class,
	];

	/**
	 * Define all classes to register for normal WP.
	 *
	 * @return class-string[]
	 */
	public function getCommandsClasses(): array
	{
		return [
			...static::CREATE_COMMANDS,
			...static::BLOCKS_COMMANDS,
			...static::INIT_COMMANDS,
			...static::RUN_COMMANDS,
		];
	}

	/**
	 * Run all CLI commands for normal WP-CLI.
	 *
	 * @param string $commandParentName Define top level commands name.
	 *
	 * @throws Exception Exception if the class doesn't exist.
	 *
	 * @return void
	 */
	public function load(string $commandParentName): void
	{
		// Duplicate condition because WP_CLI will throw error on the project.
		if (\defined('WP_CLI')) {
			// Top Level command name.
			WP_CLI::add_command($commandParentName, new CliBoilerplate());

			// Register all top level commands.
			foreach (self::PARENT_COMMANDS as $item) {
				$reflectionClass = new ReflectionClass($item);
				$class = $reflectionClass->newInstanceArgs();
				$name = $reflectionClass->getConstant('COMMAND_NAME');

				WP_CLI::add_command("{$commandParentName} {$name}", $class);
			}
		}

		foreach ($this->getCommandsClasses() as $item) {
			$reflectionClass = new ReflectionClass($item);
			$class = $reflectionClass->newInstanceArgs([$commandParentName]);

			if ($class instanceof CliInterface) {
				$class->register();
			}
		}
	}
}
