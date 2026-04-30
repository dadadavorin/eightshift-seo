<?php

/**
 * The file that contributes DefinedTerm nodes to the schema graph.
 *
 * @package EightshiftSeo\Schema
 */

declare(strict_types=1);

namespace EightshiftSeo\Schema;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;
use WP_Post;

/**
 * DefinedTermSchema — emits a schema.org DefinedTerm node when the post
 * begins with a definition-first opener (e.g. "X is …", "Y means …") or
 * when the author has explicitly set the es_seo_definition_term meta.
 *
 * AI engines reward definition-first content because the lead paragraph is
 * directly extractable as a snippet answer. This contributor formalises that
 * pattern in structured data so engines can resolve it without NLP guesswork.
 *
 * Detection caches per post + post_modified to avoid re-parsing block trees
 * on every front-end request.
 */
class DefinedTermSchema implements ServiceInterface
{
	private const CACHE_PREFIX = 'es_seo_def_term_';
	private const REGEX        = '/^[^.!?]{0,200}?\b(is|are|means|refers to)\b/iu';

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_filter(Options::getFilter('schemaGraph'), [$this, 'addDefinedTermNode'], 50, 2);
		\add_action('save_post', [$this, 'invalidateCache']);
	}

	/**
	 * Contribute a DefinedTerm node and link it from the Article via `about`.
	 *
	 * @param array<int, array<string, mixed>> $graph   Current graph nodes.
	 * @param array<string, mixed>             $context Request context from GraphEmitter.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function addDefinedTermNode(array $graph, array $context): array
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

		$detection = $this->detect($post);
		if ($detection === null) {
			return $graph;
		}

		$permalink = (string) \get_permalink($post->ID);

		$node = [
			'@type'            => 'DefinedTerm',
			'@id'              => $permalink . '#term',
			'name'             => $detection['term'],
			'description'      => $detection['description'],
			'inDefinedTermSet' => (string) \get_bloginfo('name'),
			'url'              => $permalink,
		];

		$node = \apply_filters(Options::getFilter('definedTermSchemaNode'), $node, $post);

		if (empty($node) || !\is_array($node)) {
			return $graph;
		}

		$graph[] = $node;

		// Cross-reference: add `about` to the Article node if one is present.
		foreach ($graph as $i => $existing) {
			if (
				\is_array($existing)
				&& ($existing['@id'] ?? '') === $permalink . '#article'
			) {
				$existingAbout = $existing['about'] ?? [];
				if (!\is_array($existingAbout) || isset($existingAbout['@id'])) {
					$existingAbout = $existingAbout ? [$existingAbout] : [];
				}
				$existingAbout[]   = ['@id' => $permalink . '#term'];
				$graph[$i]['about'] = $existingAbout;
				break;
			}
		}

		return $graph;
	}

	/**
	 * Detect the defined term and its description for the given post.
	 *
	 * Returns null when neither an explicit term meta nor a definition-first
	 * opener is found.
	 *
	 * @param WP_Post $post The post being rendered.
	 *
	 * @return array{term: string, description: string}|null
	 */
	public function detect(WP_Post $post): ?array
	{
		$cacheKey = self::CACHE_PREFIX . $post->ID . '_' . \strtotime((string) $post->post_modified_gmt);
		$cached   = \wp_cache_get($cacheKey, 'es_seo');

		if (\is_array($cached)) {
			return $cached === [] ? null : $cached;
		}

		$result    = null;
		$firstPara = $this->extractFirstParagraph($post);

		$termMeta = (string) \get_post_meta($post->ID, Options::getMetaKey('definitionTerm'), true);

		if ($termMeta !== '' && $firstPara !== '') {
			$result = ['term' => $termMeta, 'description' => $firstPara];
		} elseif ($firstPara !== '' && \preg_match(self::REGEX, $firstPara, $matches) === 1) {
			$splitTerm = \trim((string) \preg_replace('/\s+\b(is|are|means|refers to)\b.*$/iu', '', $firstPara));
			$splitTerm = $splitTerm !== '' ? $splitTerm : \get_the_title($post);
			if ($splitTerm !== '') {
				$result = ['term' => $splitTerm, 'description' => $firstPara];
			}
		}

		\wp_cache_set($cacheKey, $result ?? [], 'es_seo', \HOUR_IN_SECONDS);

		return $result;
	}

	/**
	 * Extract the plain-text content of the first paragraph block in the post.
	 *
	 * @param WP_Post $post The post to inspect.
	 *
	 * @return string
	 */
	private function extractFirstParagraph(WP_Post $post): string
	{
		$blocks = \parse_blocks((string) $post->post_content);

		foreach ($blocks as $block) {
			$name    = $block['blockName'] ?? null;
			$content = (string) ($block['innerHTML'] ?? '');

			if ($name === null && \trim($content) === '') {
				continue;
			}

			if ($name === 'core/paragraph' || $name === null) {
				$plain = \trim(\wp_strip_all_tags($content));
				if ($plain !== '') {
					return $plain;
				}
			}

			// First non-paragraph block reached — abort, no leading paragraph.
			break;
		}

		return '';
	}

	/**
	 * Invalidate the cached detection when the post is saved.
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
