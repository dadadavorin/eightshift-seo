<?php

/**
 * The file that collects schema contributions and emits a single JSON-LD graph.
 *
 * @package EightshiftSeo\Schema
 */

declare(strict_types=1);

namespace EightshiftSeo\Schema;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;
use WP_Post;

/**
 * GraphEmitter — owns the single <script type="application/ld+json"> output.
 *
 * All structured-data contributors (BreadcrumbList, SiteRepresentation, Article,
 * Author, FAQ, HowTo …) add their nodes to the es_seo_schema_graph filter instead
 * of emitting independent <script> blocks. This class collects them, de-duplicates
 * by @id, and emits one consolidated {"@context","@graph":[…]} payload.
 *
 * De-duplication: nodes with an @id key are indexed by that id — the last
 * contributor at the highest filter priority wins. Nodes without @id are always
 * appended (no de-duplication possible for anonymous nodes).
 *
 * In WP_DEBUG mode a wp_footer hook notes that the graph was emitted so
 * developers can manually verify there are no duplicate <script> blocks in the
 * page source (e.g. from eightshift-utils if its schema builder was re-enabled).
 *
 * eightshift-utils note: its JSON-LD schema builder is off by default. Keep it
 * that way — this plugin is the canonical owner of structured data output.
 */
class GraphEmitter implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('wp_head', [$this, 'emitGraph'], 11);

		if (\defined('WP_DEBUG') && WP_DEBUG) {
			\add_action('wp_footer', [$this, 'debugReminder'], 999);
		}
	}

	/**
	 * Collect all schema contributions and emit a single @graph script.
	 *
	 * @return void
	 */
	public function emitGraph(): void
	{
		$context = $this->buildContext();
		$context = \apply_filters(Options::getFilter('schemaContext'), $context);

		/** @var array<int, array<string, mixed>> $nodes */
		$nodes = \apply_filters(Options::getFilter('schemaGraph'), [], $context);

		if (empty($nodes) || !\is_array($nodes)) {
			return;
		}

		$nodes   = $this->deduplicateById($nodes);
		$payload = [
			'@context' => 'https://schema.org',
			'@graph'   => \array_values($nodes),
		];

		echo '<script type="application/ld+json">'
			. \wp_json_encode($payload, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)
			. '</script>' . "\n";
	}

	/**
	 * Log a WP_DEBUG reminder about schema ownership.
	 *
	 * Developers can inspect page source to confirm no duplicate JSON-LD scripts
	 * are present. If eightshift-utils' schema builder has been re-enabled,
	 * its output will appear as a separate <script> block before ours.
	 *
	 * @return void
	 */
	public function debugReminder(): void
	{
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		\error_log(
			'[eightshift-seo] JSON-LD graph emitted. Verify page source contains exactly one ' .
			'<script type="application/ld+json"> block. If eightshift-utils\' schema builder ' .
			'is enabled, disable it to prevent duplicate output.'
		);
	}

	/**
	 * Build the context array passed to every schema contributor.
	 *
	 * @return array<string, mixed>
	 */
	private function buildContext(): array
	{
		$post = \get_post();

		return [
			'isFrontPage'   => \is_front_page(),
			'isHome'        => \is_home(),
			'isSingular'    => \is_singular(),
			'isArchive'     => \is_archive(),
			'isSearch'      => \is_search(),
			'is404'         => \is_404(),
			'postId'        => $post instanceof WP_Post ? $post->ID : 0,
			'post'          => $post instanceof WP_Post ? $post : null,
			'queriedObject' => \get_queried_object(),
		];
	}

	/**
	 * Remove duplicate nodes by @id; the last contributor's version wins.
	 *
	 * @param array<int, array<string, mixed>> $nodes Raw collected nodes.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function deduplicateById(array $nodes): array
	{
		$indexed   = []; // keyed by @id
		$anonymous = []; // nodes without @id

		foreach ($nodes as $node) {
			if (!\is_array($node)) {
				continue;
			}

			$id = $node['@id'] ?? null;

			if ($id !== null && $id !== '') {
				$indexed[(string) $id] = $node;
			} else {
				$anonymous[] = $node;
			}
		}

		// Anonymous nodes first, then identified nodes (preserves @id cross-references).
		return \array_merge($anonymous, \array_values($indexed));
	}
}
