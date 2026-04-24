<?php

/**
 * The file that handles canonical URL output.
 *
 * @package EightshiftSeo\Head
 */

declare(strict_types=1);

namespace EightshiftSeo\Head;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;
use WP_Post;
use WP_Term;

/**
 * Canonical class — replaces WordPress core rel_canonical with our own output.
 *
 * Resolution order:
 *   1. Post-level es_seo_canonical meta override
 *   2. get_permalink() — with pagination suffix via get_pagenum_link()
 *   3. apply_filters('es_seo_canonical', $url, $postId) for per-project overrides
 */
class Canonical implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		// Remove core canonical so we don't output two <link rel="canonical"> tags.
		\add_action('template_redirect', [$this, 'removeCoreCononical']);

		// Output ours at priority 1 so it appears early in <head>.
		\add_action('wp_head', [$this, 'outputCanonical'], 1);
	}

	/**
	 * Remove the WordPress core rel_canonical action.
	 *
	 * @return void
	 */
	public function removeCoreCononical(): void
	{
		\remove_action('wp_head', 'rel_canonical');
	}

	/**
	 * Output the <link rel="canonical"> tag, and optionally rel=prev/next.
	 *
	 * Feeds already carry a <link rel="self"> in their body; a canonical here
	 * would conflict, so we skip emission entirely on feed pages.
	 *
	 * @return void
	 */
	public function outputCanonical(): void
	{
		// Feeds manage their own self-reference; no canonical needed.
		if (\is_feed()) {
			return;
		}

		$url = $this->resolveCanonical();

		if (empty($url)) {
			return;
		}

		echo '<link rel="canonical" href="' . \esc_url($url) . '">' . "\n";

		// Optional rel=prev/next (default off; Google deprecated but Bing still uses).
		if (Options::getOption(['pagination', 'emitPrevNext'])) {
			$this->outputPrevNext();
		}
	}

	/**
	 * Emit rel=prev and rel=next for paginated archives.
	 *
	 * @return void
	 */
	private function outputPrevNext(): void
	{
		$paged = (int) \get_query_var('paged', 1);
		if ($paged < 1) {
			$paged = 1;
		}

		if ($paged > 1) {
			echo '<link rel="prev" href="' . \esc_url(\get_pagenum_link($paged - 1)) . '">' . "\n";
		}

		global $wp_query;
		$maxPages = (int) ($wp_query->max_num_pages ?? 1);
		if ($paged < $maxPages) {
			echo '<link rel="next" href="' . \esc_url(\get_pagenum_link($paged + 1)) . '">' . "\n";
		}
	}

	/**
	 * Resolve the canonical URL for the current page.
	 *
	 * @return string Canonical URL or empty string if undetermined.
	 */
	private function resolveCanonical(): string
	{
		$post   = \get_post();
		$postId = $post instanceof WP_Post ? $post->ID : 0;
		$url    = '';

		if ($post instanceof WP_Post) {
			// 1. Per-post meta override.
			$meta = \get_post_meta($post->ID, Options::getMetaKey('canonical'), true);
			if (!empty($meta)) {
				$url = (string) $meta;
			}
		}

		if (empty($url)) {
			// 2. Auto-generate: permalink + pagination.
			if ($post instanceof WP_Post) {
				$permalink = \get_permalink($post->ID);
				$url       = $permalink ?: '';

				// Handle paginated single posts (/page/2/).
				$page = (int) \get_query_var('page', 0);
				if ($page > 1 && !empty($url)) {
					$url = \trailingslashit($url) . 'page/' . $page . '/';
				}
			} elseif (\is_front_page() || \is_home()) {
				$url = \home_url('/');
			} elseif (\is_tax() || \is_category() || \is_tag()) {
				// Check per-term canonical override first.
				$term = \get_queried_object();
				if ($term instanceof WP_Term) {
					$termCanonical = \get_term_meta($term->term_id, Options::getTermMetaKey('canonical'), true);
					if (!empty($termCanonical)) {
						$url = (string) $termCanonical;
					}
				}
				if (empty($url)) {
					$url = \get_pagenum_link(\get_query_var('paged', 1));
				}
			} elseif (\is_archive()) {
				$url = \get_pagenum_link(\get_query_var('paged', 1));
			} elseif (\is_search()) {
				$url = \get_search_link();
			}
		}

		// 3. Per-project filter.
		$url = \apply_filters(Options::getFilter('canonical'), $url, $postId);

		return (string) $url;
	}
}
