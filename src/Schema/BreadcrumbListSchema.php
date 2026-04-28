<?php

/**
 * The file that outputs BreadcrumbList JSON-LD structured data.
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
 * BreadcrumbListSchema class — emits BreadcrumbList JSON-LD in wp_head.
 *
 * The trail mirrors eightshift-ui-kit's breadcrumb logic:
 *   Home → parent hierarchy (get_post_ancestors / term parents) → Current
 *
 * No HTML rendering, no shortcodes — visual breadcrumbs stay in eightshift-ui-kit.
 * Only one schema type emitted here; all other Schema.org output lives in eightshift-utils.
 */
class BreadcrumbListSchema implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		// Contribute to the unified @graph instead of emitting a standalone script.
		\add_filter(Options::getFilter('schemaGraph'), [$this, 'addBreadcrumbNode'], 20, 2);
	}

	/**
	 * Contribute a BreadcrumbList node to the schema graph.
	 *
	 * @param array<int, array<string, mixed>> $graph   Current graph nodes.
	 * @param array<string, mixed>             $context Request context from GraphEmitter.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function addBreadcrumbNode(array $graph, array $context): array
	{
		if (!Options::getOption(['breadcrumbs', 'enableSchema'])) {
			return $graph;
		}

		$items = $this->buildTrail();

		if (empty($items)) {
			return $graph;
		}

		$queriedObject = $context['queriedObject'] ?? \get_queried_object();
		$items         = \apply_filters(Options::getFilter('breadcrumbSchema'), $items, $queriedObject);

		// No @context here — GraphEmitter adds it at the outer envelope level.
		$node = [
			'@type'           => 'BreadcrumbList',
			'@id'             => \home_url('/') . '#breadcrumb',
			'itemListElement' => $items,
		];

		$graph[] = $node;

		return $graph;
	}

	/**
	 * Build the ordered breadcrumb item list for the current page.
	 *
	 * @return array<int, array<string, mixed>> Array of ListItem schema objects.
	 */
	private function buildTrail(): array
	{
		$homeLabel = Options::getOption(['breadcrumbs', 'homeLabel']) ?: 'Home';
		$homeUrl   = \home_url('/');
		$items     = [];
		$position  = 1;

		// Always start with Home.
		$items[] = $this->makeItem($position++, $homeLabel, $homeUrl);

		$queriedObject = \get_queried_object();

		if ($queriedObject instanceof WP_Post && !\is_front_page()) {
			// Build ancestor chain: oldest ancestor → parent → current post.
			$ancestors = \array_reverse(\get_post_ancestors($queriedObject->ID));

			foreach ($ancestors as $ancestorId) {
				$ancestor = \get_post($ancestorId);
				if (!$ancestor instanceof WP_Post) {
					continue;
				}
				$items[] = $this->makeItem(
					$position++,
					\get_the_title($ancestor),
					(string) \get_permalink($ancestor->ID)
				);
			}

			// Current post (no URL in schema — it is the current page).
			$items[] = $this->makeItem(
				$position,
				\get_the_title($queriedObject),
				(string) \get_permalink($queriedObject->ID)
			);
		} elseif ($queriedObject instanceof WP_Term) {
			// Build term parent chain.
			$termParents = $this->getTermAncestors($queriedObject);

			foreach ($termParents as $parent) {
				$termLink = \get_term_link($parent);
				if (\is_wp_error($termLink)) {
					continue;
				}
				$items[] = $this->makeItem($position++, $parent->name, $termLink);
			}

			// Current term.
			$termLink = \get_term_link($queriedObject);
			$items[]  = $this->makeItem(
				$position,
				$queriedObject->name,
				\is_wp_error($termLink) ? '' : $termLink
			);
		} elseif (\is_post_type_archive()) {
			$postTypeObj = \get_queried_object();
			if ($postTypeObj instanceof \WP_Post_Type) {
				$archiveLink = \get_post_type_archive_link($postTypeObj->name);
				$items[]     = $this->makeItem(
					$position,
					$postTypeObj->labels->name ?? $postTypeObj->name,
					$archiveLink ?: ''
				);
			}
		} else {
			// Home or search — just the home crumb is enough; return early.
			return \is_front_page() ? [] : $items;
		}

		return $items;
	}

	/**
	 * Build an ordered term ancestor list (oldest → direct parent), excluding the term itself.
	 *
	 * @param WP_Term $term The current term.
	 *
	 * @return array<WP_Term> Ordered list of ancestor terms.
	 */
	private function getTermAncestors(WP_Term $term): array
	{
		$ancestors = [];
		$parentId  = $term->parent;

		while ($parentId > 0) {
			$parent = \get_term($parentId, $term->taxonomy);
			if (!$parent instanceof WP_Term) {
				break;
			}
			$ancestors[] = $parent;
			$parentId    = $parent->parent;
		}

		return \array_reverse($ancestors);
	}

	/**
	 * Build a single ListItem schema object.
	 *
	 * @param int    $position Item position (1-based).
	 * @param string $name     Display name.
	 * @param string $url      Canonical URL for this item.
	 *
	 * @return array<string, mixed>
	 */
	private function makeItem(int $position, string $name, string $url): array
	{
		return [
			'@type'    => 'ListItem',
			'position' => $position,
			'name'     => $name,
			'item'     => $url,
		];
	}
}
