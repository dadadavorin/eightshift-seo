<?php

/**
 * The file that contributes HowTo nodes to the schema graph.
 *
 * @package EightshiftSeo\Schema
 */

declare(strict_types=1);

namespace EightshiftSeo\Schema;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;
use WP_Post;

/**
 * HowToSchema — contributes a HowTo node to the es_seo_schema_graph filter
 * for singular views that have HowTo data stored in es_seo_howto meta.
 *
 * The meta value is a JSON-encoded string. This class decodes it and emits
 * a structured HowTo node with steps and optional image objects.
 */
class HowToSchema implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_filter(Options::getFilter('schemaGraph'), [$this, 'addHowToNode'], 45, 2);
	}

	/**
	 * Contribute a HowTo node for singular views with HowTo meta set.
	 *
	 * @param array<int, array<string, mixed>> $graph   Current graph nodes.
	 * @param array<string, mixed>             $context Request context from GraphEmitter.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function addHowToNode(array $graph, array $context): array
	{
		if (!($context['isSingular'] ?? false)) {
			return $graph;
		}

		$post = $context['post'] ?? null;
		if (!$post instanceof WP_Post) {
			return $graph;
		}

		$supportedPostTypes = \apply_filters(
			Options::getFilter('supportedPostTypes'),
			Options::getPublicPostTypes()
		);

		if (!\in_array($post->post_type, $supportedPostTypes, true)) {
			return $graph;
		}

		$rawMeta = \get_post_meta($post->ID, Options::getMetaKey('howto'), true);

		if (!\is_string($rawMeta) || $rawMeta === '') {
			return $graph;
		}

		$howto = \json_decode($rawMeta, true);

		if (!\is_array($howto) || empty($howto)) {
			return $graph;
		}

		$steps = \array_values(\array_map(
			static function (mixed $step): array {
				if (!\is_array($step)) {
					return [];
				}

				$entry = \array_filter([
					'@type' => 'HowToStep',
					'name'  => (string) ($step['name'] ?? ''),
					'text'  => (string) ($step['text'] ?? ''),
					'image' => !empty($step['image'])
						? ['@type' => 'ImageObject', 'url' => (string) $step['image']]
						: null,
				]);

				return $entry;
			},
			$howto['steps'] ?? []
		));

		$node = \array_filter([
			'@type'       => 'HowTo',
			'@id'         => \get_permalink($post->ID) . '#howto',
			'name'        => (string) ($howto['name'] ?? ''),
			'description' => !empty($howto['description']) ? (string) $howto['description'] : null,
			'totalTime'   => !empty($howto['totalTime']) ? (string) $howto['totalTime'] : null,
			'step'        => $steps ?: null,
		]);

		$node = \apply_filters(
			Options::getFilter('howtoRender'),
			$node,
			$post
		);

		if (!empty($node)) {
			$graph[] = $node;
		}

		return $graph;
	}
}
