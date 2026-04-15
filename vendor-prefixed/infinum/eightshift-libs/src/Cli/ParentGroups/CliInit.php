<?php

/**
 * Class that registers WP-CLI commands used as parent placeholders.
 *
 * @package EightshiftSeoVendor\EightshiftLibs\Cli\ParentGroups
 *
 * @license MIT
 * Modified by eightshift-meilisearch on 01-April-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace EightshiftSeoVendor\EightshiftLibs\Cli\ParentGroups;

use WP_CLI_Command;

/**
 * Initially setup your project like theme, plugin, project, etc. These commands should be used only once.
 */
class CliInit extends WP_CLI_Command
{
	/**
	 * Cli command name parent constant.
	 */
	public const COMMAND_NAME = 'init';
}
