<?php

/**
 * The file that contributes inline-block-derived schema (Statistic / Quote)
 * onto the Article node via the `mentions` property.
 *
 * @package EightshiftSeo\Schema
 */

declare(strict_types=1);

namespace EightshiftSeo\Schema;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;
use WP_Post;

/**
 * InlineBlocksSchema — scans post content for the plugin's authoring blocks
 * (es-seo/statistic and es-seo/expert-quote) and injects matching schema.org
 * fragments under the Article node's `mentions` array.
 *
 * Statistic blocks become `Claim` entries; expert-quote blocks become
 * `Quotation` entries. The scan is cached per post + post_modified so the
 * block tree is only walked once per content change.
 */
class InlineBlocksSchema implements ServiceInterface
{
	private const CACHE_PREFIX = 'es_seo_inline_blocks_';

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		// Run after ArticleSchema (priority 30) so the Article node already
		// exists in the graph and we can extend it.
		\add_filter(Options::getFilter('schemaGraph'), [$this, 'addInlineMentions'], 35, 2);
		\add_action('save_post', [$this, 'invalidateCache']);
	}

	/**
	 * Append `mentions` derived from authoring blocks to the Article node.
	 *
	 * @param array<int, array<string, mixed>> $graph   Current graph nodes.
	 * @param array<string, mixed>             $context Request context from GraphEmitter.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function addInlineMentions(array $graph, array $context): array
	{
		if (!($context['isSingular'] ?? false)) {
			return $graph;
		}

		$post = $context['post'] ?? null;
		if (!$post instanceof WP_Post) {
			return $graph;
		}

		$mentions = $this->extractMentions($post);
		if (empty($mentions)) {
			return $graph;
		}

		$articleId = (string) \get_permalink($post->ID) . '#article';

		foreach ($graph as $i => $existing) {
			if (!\is_array($existing)) {
				continue;
			}
			if (($existing['@id'] ?? '') !== $articleId) {
				continue;
			}

			$existingMentions = $existing['mentions'] ?? [];
			if (!\is_array($existingMentions)) {
				$existingMentions = [];
			}

			$graph[$i]['mentions'] = \array_merge($existingMentions, $mentions);
			break;
		}

		return $graph;
	}

	/**
	 * Walk the post block tree and produce schema fragments for any
	 * statistic or expert-quote blocks found.
	 *
	 * @param WP_Post $post The post being rendered.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function extractMentions(WP_Post $post): array
	{
		$cacheKey = self::CACHE_PREFIX . $post->ID . '_' . \strtotime((string) $post->post_modified_gmt);
		$cached   = \wp_cache_get($cacheKey, 'es_seo');

		if (\is_array($cached)) {
			return $cached;
		}

		$mentions = [];
		$this->walkBlocks(\parse_blocks((string) $post->post_content), $mentions);

		\wp_cache_set($cacheKey, $mentions, 'es_seo', \HOUR_IN_SECONDS);

		return $mentions;
	}

	/**
	 * Recursively walk a parsed-block array and collect schema fragments.
	 *
	 * @param array<int, array<string, mixed>> $blocks   Parsed blocks at this level.
	 * @param array<int, array<string, mixed>> $mentions Accumulator (passed by reference).
	 *
	 * @return void
	 */
	private function walkBlocks(array $blocks, array &$mentions): void
	{
		foreach ($blocks as $block) {
			$name = $block['blockName'] ?? null;
			$attr = $block['attrs'] ?? [];

			if ($name === 'es-seo/statistic') {
				$entry = $this->statisticToClaim(\is_array($attr) ? $attr : []);
				if ($entry !== null) {
					$mentions[] = $entry;
				}
			} elseif ($name === 'es-seo/expert-quote') {
				$entry = $this->quoteToQuotation(\is_array($attr) ? $attr : []);
				if ($entry !== null) {
					$mentions[] = $entry;
				}
			}

			$inner = $block['innerBlocks'] ?? [];
			if (\is_array($inner) && !empty($inner)) {
				$this->walkBlocks($inner, $mentions);
			}
		}
	}

	/**
	 * Map an es-seo/statistic block's attrs to a schema.org Claim fragment.
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 *
	 * @return array<string, mixed>|null Null when the block has no usable data.
	 */
	private function statisticToClaim(array $attrs): ?array
	{
		$value = \trim((string) ($attrs['value'] ?? ''));
		$label = \trim((string) ($attrs['label'] ?? ''));

		if ($value === '' && $label === '') {
			return null;
		}

		$claim = [
			'@type' => 'Claim',
			'name'  => $value !== '' ? $value : $label,
		];

		if ($label !== '' && $value !== '') {
			$claim['description'] = $label;
		}

		$source    = \trim((string) ($attrs['source'] ?? ''));
		$sourceUrl = \trim((string) ($attrs['sourceUrl'] ?? ''));

		if ($sourceUrl !== '' || $source !== '') {
			$claim['citation'] = \array_filter([
				'@type'     => 'CreativeWork',
				'name'      => $source !== '' ? $source : null,
				'url'       => $sourceUrl !== '' ? $sourceUrl : null,
			]);
		}

		$datePublished = \trim((string) ($attrs['datePublished'] ?? ''));
		if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $datePublished)) {
			$claim['datePublished'] = $datePublished;
		}

		return $claim;
	}

	/**
	 * Map an es-seo/expert-quote block's attrs to a schema.org Quotation fragment.
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 *
	 * @return array<string, mixed>|null Null when the block has no usable data.
	 */
	private function quoteToQuotation(array $attrs): ?array
	{
		$quote  = \trim((string) ($attrs['quote'] ?? ''));
		$author = \trim((string) ($attrs['author'] ?? ''));

		if ($quote === '' && $author === '') {
			return null;
		}

		$node = [
			'@type' => 'Quotation',
			'text'  => $quote,
		];

		if ($author !== '') {
			$creator = ['@type' => 'Person', 'name' => $author];

			$authorTitle = \trim((string) ($attrs['authorTitle'] ?? ''));
			if ($authorTitle !== '') {
				$creator['jobTitle'] = $authorTitle;
			}

			$authorUrl = \trim((string) ($attrs['authorUrl'] ?? ''));
			if ($authorUrl !== '') {
				$creator['url'] = $authorUrl;
			}

			$node['creator'] = $creator;
		}

		return $node;
	}

	/**
	 * Invalidate the cached extraction when the post is saved.
	 *
	 * @param int $postId Saved post ID.
	 *
	 * @return void
	 */
	public function invalidateCache(int $postId): void
	{
		\wp_cache_delete(self::CACHE_PREFIX . $postId, 'es_seo');
	}
}
