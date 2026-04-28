<?php

/**
 * The file that contributes FAQPage nodes to the schema graph.
 *
 * @package EightshiftSeo\Schema
 */

declare(strict_types=1);

namespace EightshiftSeo\Schema;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;
use WP_Post;

/**
 * FaqSchema — contributes a FAQPage node to the es_seo_schema_graph filter
 * for singular views that have FAQ items stored in es_seo_faq meta.
 *
 * The node uses the mainEntity property to list Question/Answer pairs
 * as required by Google's FAQ rich result specification.
 */
class FaqSchema implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_filter(Options::getFilter('schemaGraph'), [$this, 'addFaqNode'], 40, 2);
	}

	/**
	 * Contribute a FAQPage node for singular views with FAQ meta set.
	 *
	 * @param array<int, array<string, mixed>> $graph   Current graph nodes.
	 * @param array<string, mixed>             $context Request context from GraphEmitter.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function addFaqNode(array $graph, array $context): array
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

		$faqItems = \get_post_meta($post->ID, Options::getMetaKey('faq'), true);

		if (!\is_array($faqItems) || empty($faqItems)) {
			return $graph;
		}

		$mainEntity = \array_values(\array_filter(\array_map(
			static function (mixed $qa): ?array {
				if (!\is_array($qa)) {
					return null;
				}

				$question = \wp_strip_all_tags((string) ($qa['question'] ?? ''));
				$answer   = \wp_kses_post((string) ($qa['answer'] ?? ''));

				if ($question === '' || $answer === '') {
					return null;
				}

				return [
					'@type'          => 'Question',
					'name'           => $question,
					'acceptedAnswer' => [
						'@type' => 'Answer',
						'text'  => $answer,
					],
				];
			},
			$faqItems
		)));

		if (empty($mainEntity)) {
			return $graph;
		}

		$node = \apply_filters(
			Options::getFilter('faqRender'),
			[
				'@type'      => 'FAQPage',
				'@id'        => \get_permalink($post->ID) . '#faq',
				'mainEntity' => $mainEntity,
			],
			$post
		);

		if (!empty($node)) {
			$graph[] = $node;
		}

		return $graph;
	}
}
