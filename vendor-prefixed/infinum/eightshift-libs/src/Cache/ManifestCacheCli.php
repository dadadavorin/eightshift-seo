<?php

/**
 * Class that registers WP-CLI command for Manifest Cache.
 *
 * @package EightshiftLibs\Cache
 *
 * @license MIT
 * Modified by eightshift-meilisearch on 01-April-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace EightshiftSeoVendor\EightshiftLibs\Cache;

use EightshiftSeoVendor\EightshiftLibs\Cli\AbstractCli;
use EightshiftSeoVendor\EightshiftLibs\Cli\ParentGroups\CliCreate;
use EightshiftSeoVendor\EightshiftLibs\Helpers\Helpers;

/**
 * Class ManifestCacheCli
 */
class ManifestCacheCli extends AbstractCli
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
		return 'manifest-cache';
	}

	/**
	 * Get WP-CLI command doc
	 *
	 * @return array<string, array<int, array<string, bool|string>>|string>
	 */
	public function getDoc(): array
	{
		return [
			'shortdesc' => 'Create manifest cache service class.',
			'longdesc' => $this->prepareLongDesc("
				## USAGE

				Used to create manifest cache service class to optimize the loading of assets in your theme or plugin.

				## EXAMPLES

				# Create service class:
				$ wp {$this->commandParentName} {$this->getCommandParentName()} {$this->getCommandName()}

				## RESOURCES

				Service class will be created from this example:
				https://github.com/infinum/eightshift-libs/blob/develop/src/Cache/ManifestCacheExample.php
			"),
		];
	}

	/* @phpstan-ignore-next-line */
	public function __invoke(array $args, array $assocArgs)
	{
		$assocArgs = $this->prepareArgs($assocArgs);

		$this->getIntroText($assocArgs);

		$className = $this->getClassShortName();

		// Read the template contents, and replace the placeholders with provided variables.
		$this->getExampleTemplate(__DIR__, $className)
			->renameClassName($className)
			->renameGlobals($assocArgs)
			->outputWrite(Helpers::getProjectPaths('src', 'Cache'), "{$className}.php", $assocArgs);
	}
}
