<?php

/**
 * Class that registers WP-CLI command for ThemeOptions.
 *
 * @package EightshiftLibs\ThemeOptions
 *
 * @license MIT
 * Modified by eightshift-meilisearch on 01-April-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace EightshiftSeoVendor\EightshiftLibs\ThemeOptions;

use EightshiftSeoVendor\EightshiftLibs\Cli\AbstractCli;
use EightshiftSeoVendor\EightshiftLibs\Cli\ParentGroups\CliCreate;
use EightshiftSeoVendor\EightshiftLibs\Helpers\Helpers;

/**
 * Class ThemeOptionsCli
 */
class ThemeOptionsCli extends AbstractCli
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
		return 'theme-options';
	}

	/**
	 * Get WP-CLI command doc
	 *
	 * @return array<string, array<int, array<string, bool|string>>|string>
	 */
	public function getDoc(): array
	{
		return [
			'shortdesc' => 'Create project Theme Options service class.',
			'longdesc' => $this->prepareLongDesc("
				## USAGE

				Used to create theme options service class to register project specific options.

				## EXAMPLES

				# Create service class:
				$ wp {$this->commandParentName} {$this->getCommandParentName()} {$this->getCommandName()}

				## RESOURCES

				Service class will be created from this example:
				https://github.com/infinum/eightshift-libs/blob/develop/src/ThemeOptions/ThemeOptionsExample.php

				ACF documentation:
				https://www.advancedcustomfields.com/resources/options-page/
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
			->outputWrite(Helpers::getProjectPaths('src', 'ThemeOptions'), "{$className}.php", $assocArgs);
	}
}
