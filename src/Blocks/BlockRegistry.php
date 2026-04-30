<?php

/**
 * The file that registers the plugin's authoring blocks.
 *
 * @package EightshiftSeo\Blocks
 */

declare(strict_types=1);

namespace EightshiftSeo\Blocks;

use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * BlockRegistry — registers the two GEO-oriented authoring blocks
 * (es-seo/statistic and es-seo/expert-quote) on the server side.
 *
 * The matching client-side `registerBlockType` calls live in the existing
 * applicationEditor bundle. Server-side registration here is what makes the
 * blocks survive validation, REST writes, and editor reloads.
 *
 * The blocks ship with no editorScript / script of their own — both are
 * already part of the editor bundle enqueued by EnqueueAdmin.
 */
class BlockRegistry implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('init', [$this, 'registerBlocks']);
		// Run after eightshift-forms (priority 99999) which rebuilds the list from scratch.
		\add_filter('allowed_block_types_all', [$this, 'allowOurBlocks'], 100000, 1);
	}

	/**
	 * Register block types from their block.json metadata files.
	 *
	 * @return void
	 */
	public function registerBlocks(): void
	{
		$blocksDir = \dirname(__DIR__) . '/Blocks/components/blocks';

		foreach (['statistic', 'expert-quote'] as $blockSlug) {
			$path = $blocksDir . '/' . $blockSlug;
			if (\file_exists($path . '/block.json')) {
				\register_block_type($path);
			}
		}
	}

	/**
	 * Append our blocks to whatever allowed-block list the theme or other
	 * plugins set. If the theme passes `true` (all blocks allowed) we leave
	 * it unchanged.
	 *
	 * @param bool|string[] $allowedBlockTypes Existing allowed-block types list.
	 *
	 * @return bool|string[]
	 */
	public function allowOurBlocks($allowedBlockTypes)
	{
		if (!\is_array($allowedBlockTypes)) {
			return $allowedBlockTypes;
		}

		return \array_merge($allowedBlockTypes, ['es-seo/statistic', 'es-seo/expert-quote']);
	}
}
