<?php

/**
 * The file that contributes Article / BlogPosting / WebPage nodes to the schema graph.
 *
 * @package EightshiftSeo\Schema
 */

declare(strict_types=1);

namespace EightshiftSeo\Schema;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;
use WP_Post;
use WP_Term;

/**
 * ArticleSchema — contributes Article-family and WebPage nodes to the
 * es_seo_schema_graph filter for every supported singular view.
 *
 * Type mapping (filterable via es_seo_article_type):
 *   - 'post'  → BlogPosting
 *   - 'page'  → (WebPage node only; no Article)
 *   - other   → Article
 *
 * Cross-references:
 *   - author    → Person @id contributed by AuthorSchema
 *   - publisher → Organization @id contributed by SiteRepresentationSchema
 */
class ArticleSchema implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_filter(Options::getFilter('schemaGraph'), [$this, 'addArticleNode'], 30, 2);
	}

	/**
	 * Contribute an Article / WebPage node for singular views.
	 *
	 * @param array<int, array<string, mixed>> $graph   Current graph nodes.
	 * @param array<string, mixed>             $context Request context from GraphEmitter.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function addArticleNode(array $graph, array $context): array
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

		$permalink = (string) \get_permalink($post->ID);
		$type      = \apply_filters(Options::getFilter('articleType'), $this->resolveType($post->post_type), $post);
		$headline  = $this->buildHeadline($post);
		$language  = $this->getLanguage();

		// WebPage node — always emitted for singular views.
		$webPageNode = [
			'@type'      => 'WebPage',
			'@id'        => $permalink,
			'url'        => $permalink,
			'name'       => $headline,
			'inLanguage' => $language,
			'isPartOf'   => ['@id' => \home_url('/#website')],
		];

		$webPageNode = \apply_filters(Options::getFilter('webpageSchemaNode'), $webPageNode, $post);

		if (!empty($webPageNode)) {
			$graph[] = $webPageNode;
		}

		// Pages only get a WebPage node.
		if ($post->post_type === 'page') {
			return $graph;
		}

		// Article node.
		$node = [
			'@type'            => $type,
			'@id'              => $permalink . '#article',
			'headline'         => $headline,
			'url'              => $permalink,
			'datePublished'    => \get_the_date('c', $post),
			'dateModified'     => \get_the_modified_date('c', $post),
			'inLanguage'       => $language,
			'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $permalink],
		];

		// Description.
		$description = $this->buildDescription($post);
		if ($description !== '') {
			$node['description'] = $description;
		}

		// Word count.
		$wordCount = \str_word_count(\wp_strip_all_tags((string) \apply_filters('the_content', $post->post_content)));
		if ($wordCount > 0) {
			$node['wordCount'] = $wordCount;
		}

		// Author cross-reference.
		$authorId = (int) $post->post_author;
		if ($authorId > 0) {
			$node['author'] = ['@id' => \home_url('/?author=' . $authorId . '#person')];
		}

		// Publisher cross-reference.
		$node['publisher'] = ['@id' => \home_url('/#organization')];

		// Primary category / articleSection.
		$section = $this->resolveArticleSection($post);
		if ($section !== '') {
			$node['articleSection'] = $section;
		}

		// Keywords: focus keyphrase + post tags.
		$keywords = $this->collectKeywords($post);
		if (!empty($keywords)) {
			$node['keywords'] = \implode(', ', $keywords);
		}

		// Featured image.
		$imageNode = $this->buildImageNode($post);
		if (!empty($imageNode)) {
			$node['image'] = $imageNode;
		}

		// Citations (es_seo_citations).
		// get_post_meta returns '' when not set; cast only when we have an actual array.
		$rawCitations = \get_post_meta($post->ID, Options::getMetaKey('citations'), true);
		$citationItems = \is_array($rawCitations) ? $rawCitations : [];
		if (!empty($citationItems)) {
			$citations = \array_values(\array_filter(\array_map(function (mixed $c) {
				if (!\is_array($c) || empty($c['label']) || empty($c['url'])) {
					return null;
				}
				$entry = ['@type' => 'CreativeWork', 'name' => (string)$c['label'], 'url' => (string)$c['url']];
				if (!empty($c['publisher'])) {
					$entry['publisher'] = ['@type' => 'Organization', 'name' => (string)$c['publisher']];
				}
				if (!empty($c['datePublished'])) {
					$entry['datePublished'] = (string)$c['datePublished'];
				}
				return $entry;
			}, $citationItems)));
			if (!empty($citations)) {
				$node['citation'] = $citations;
			}
		}

		// Last reviewed date (Phase 8 — content freshness).
		$reviewed = (string) \get_post_meta($post->ID, Options::getMetaKey('dateReviewed'), true);
		if ($reviewed !== '' && \preg_match('/^\d{4}-\d{2}-\d{2}$/', $reviewed)) {
			$node['dateReviewed'] = $reviewed;
		}

		// Speakable.
		// get_post_meta returns '' when not set; only treat actual non-empty arrays as custom selectors.
		$rawSelectors = \get_post_meta($post->ID, Options::getMetaKey('speakableSelectors'), true);
		$speakableSelectors = \is_array($rawSelectors)
			? \array_values(\array_filter($rawSelectors, 'strlen'))
			: [];
		if (empty($speakableSelectors)) {
			// Auto selectors — only emit when TL;DR is set so they point at real content.
			$tldr = (string) \get_post_meta($post->ID, Options::getMetaKey('tldr'), true);
			if ($tldr !== '') {
				$speakableSelectors = \apply_filters(
					Options::getFilter('speakableDefaultSelectors'),
					['.es-seo-tldr', 'article h2:first-of-type', 'article ul:first-of-type']
				);
			}
		}
		if (!empty($speakableSelectors)) {
			$node['speakable'] = [
				'@type'       => 'SpeakableSpecification',
				'cssSelector' => \array_values(\array_unique($speakableSelectors)),
			];
		}

		$node = \apply_filters(Options::getFilter('articleSchemaNode'), $node, $post);

		if (!empty($node)) {
			$graph[] = $node;
		}

		return $graph;
	}

	/**
	 * Map post type to Schema.org Article sub-type.
	 *
	 * @param string $postType WordPress post type slug.
	 *
	 * @return string Schema.org @type string.
	 */
	private function resolveType(string $postType): string
	{
		return match ($postType) {
			'post' => 'BlogPosting',
			'page' => 'WebPage',
			default => 'Article',
		};
	}

	/**
	 * Build a headline string (≤ 110 chars, no HTML).
	 *
	 * @param WP_Post $post The current post.
	 *
	 * @return string
	 */
	private function buildHeadline(WP_Post $post): string
	{
		$title = (string) \get_post_meta($post->ID, Options::getMetaKey('title'), true);

		if ($title === '') {
			$title = \get_the_title($post->ID);
		}

		$title = \wp_strip_all_tags($title);

		if (\mb_strlen($title) > 110) {
			if (\defined('WP_DEBUG') && WP_DEBUG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
				\trigger_error(
					\sprintf(
						'[eightshift-seo] Article headline for post %d exceeds 110 chars (%d). Consider shortening the SEO title.',
						$post->ID,
						\mb_strlen($title)
					),
					\E_USER_NOTICE
				);
			}
			$title = \mb_substr($title, 0, 110);
		}

		return $title;
	}

	/**
	 * Build the description string for the Article node.
	 *
	 * @param WP_Post $post The current post.
	 *
	 * @return string
	 */
	private function buildDescription(WP_Post $post): string
	{
		$desc = (string) \get_post_meta($post->ID, Options::getMetaKey('description'), true);

		if ($desc === '') {
			$desc = \wp_trim_words(\get_the_excerpt($post->ID), 30, '');
		}

		return \wp_strip_all_tags($desc);
	}

	/**
	 * Resolve the articleSection from primary category or first assigned category.
	 *
	 * @param WP_Post $post The current post.
	 *
	 * @return string
	 */
	private function resolveArticleSection(WP_Post $post): string
	{
		$primaryTermId = (int) \get_post_meta($post->ID, Options::getMetaKey('primaryCategory'), true);

		if ($primaryTermId > 0) {
			$term = \get_term($primaryTermId);
			if ($term instanceof WP_Term) {
				return $term->name;
			}
		}

		$categories = \get_the_category($post->ID);
		if (\is_array($categories) && !empty($categories)) {
			return \reset($categories)->name;
		}

		return '';
	}

	/**
	 * Collect keywords from focus keyphrase and post tags.
	 *
	 * @param WP_Post $post The current post.
	 *
	 * @return array<int, string>
	 */
	private function collectKeywords(WP_Post $post): array
	{
		$keywords = [];

		$keyphrase = (string) \get_post_meta($post->ID, Options::getMetaKey('focusKeyphrase'), true);
		if ($keyphrase !== '') {
			$keywords[] = $keyphrase;
		}

		$tags = \get_the_tags($post->ID);
		if (\is_array($tags)) {
			foreach ($tags as $tag) {
				if (!\in_array($tag->name, $keywords, true)) {
					$keywords[] = $tag->name;
				}
			}
		}

		return $keywords;
	}

	/**
	 * Build an ImageObject node for the featured image.
	 *
	 * @param WP_Post $post The current post.
	 *
	 * @return array<string, mixed>
	 */
	private function buildImageNode(WP_Post $post): array
	{
		$imageId = (int) \get_post_thumbnail_id($post->ID);

		if ($imageId <= 0) {
			// Fall back to the default OG image as a last resort.
			$imageId = (int) Options::getOption(['defaultOgImage']);
		}

		if ($imageId <= 0) {
			return [];
		}

		$imageData = \wp_get_attachment_image_src($imageId, 'full');
		if (!$imageData) {
			return [];
		}

		$node = [
			'@type'  => 'ImageObject',
			'@id'    => $imageData[0] . '#image',
			'url'    => $imageData[0],
			'width'  => (int) $imageData[1],
			'height' => (int) $imageData[2],
		];

		$alt = (string) \get_post_meta($imageId, '_wp_attachment_image_alt', true);
		if ($alt !== '') {
			$node['caption'] = $alt;
		}

		return $node;
	}

	/**
	 * Map a WordPress locale to a BCP-47 language tag.
	 *
	 * @return string
	 */
	private function getLanguage(): string
	{
		return \str_replace('_', '-', \get_locale());
	}
}
