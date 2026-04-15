<?php

/**
 * Class that registers WP-CLI command for Plugin.
 *
 * @package EightshiftLibs\Plugin
 *
 * @license MIT
 * Modified by eightshift-meilisearch on 01-April-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace EightshiftSeoVendor\EightshiftLibs\Plugin;

use EightshiftSeoVendor\EightshiftLibs\Cli\AbstractCli;
use EightshiftSeoVendor\EightshiftLibs\Cli\ParentGroups\CliCreate;
use EightshiftSeoVendor\EightshiftLibs\Helpers\Helpers;

/**
 * Class PluginCli
 */
class PluginCli extends AbstractCli
{
	/**
	 * Get WP-CLI command parent name
	 *
	 * @return string
	 */
	public function getCommandParentName(): string
	{
		return CliCreate::COMMAND_NAME;
	}

	/**
	 * Get WP-CLI command name
	 *
	 * @return string
	 */
	public function getCommandName(): string
	{
		return 'plugin';
	}

	/**
	 * Get WP-CLI command doc
	 *
	 * @return array<string, array<int, array<string, bool|string>>|string>
	 */
	public function getDoc(): array
	{
		return [
			'shortdesc' => 'Create plugin activation/deactivation service classes.',
			'longdesc' => $this->prepareLongDesc("
				## USAGE

				Used to create plugin activation/deactivation service classes to register custom functionality restricted to the WordPress plugin activation/deactivation hooks.

				## EXAMPLES

				# Create service class:
				$ wp {$this->commandParentName} {$this->getCommandParentName()} {$this->getCommandName()}

				## RESOURCES

				Service classes will be created from these example:
				https://github.com/infinum/eightshift-libs/blob/develop/src/Plugin/PluginFactoryExample.php
				https://github.com/infinum/eightshift-libs/blob/develop/src/Plugin/ActivateExample.php
				https://github.com/infinum/eightshift-libs/blob/develop/src/Plugin/DeactivateExample.php
			"),
		];
	}

	/* @phpstan-ignore-next-line */
	public function __invoke(array $args, array $assocArgs)
	{
		$assocArgs = $this->prepareArgs($assocArgs);

		$this->getIntroText($assocArgs);

		$output = [
			'PluginFactory',
			'Activate',
			'Deactivate',
		];

		foreach ($output as $className) {
			// Read the template contents, and replace the placeholders with provided variables.
			$this->getExampleTemplate(__DIR__, $className)
				->renameClassName($className)
				->renameGlobals($assocArgs)
				->outputWrite(Helpers::getProjectPaths('src'), "{$className}.php", $assocArgs);
		}
	}
}
