<?php

/**
 * The file that handles the <title> tag output.
 *
 * @package EightshiftSeo\Head
 */

declare(strict_types=1);

namespace EightshiftSeo\Head;

use EightshiftSeo\Options\Options;
use EightshiftSeo\Templates\TemplateResolver;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;
use WP_Post;
use WP_Term;

/**
 * TitleTag class — controls the document <title> for all page types.
 *
 * Resolution order:
 *   1. Post-level es_seo_title meta override
 *   2. Per-post-type template from settings (resolved through TemplateResolver)
 *   3. WordPress default (null returned = WP continues its own logic)
 */
class TitleTag implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_filter('pre_get_document_title', [$this, 'filterDocumentTitle'], 10);
		\add_filter('document_title_parts', [$this, 'filterDocumentTitleParts'], 10);
	}

	/**
	 * Attempt to supply the full title string before WordPress builds it.
	 *
	 * Returning a non-empty string short-circuits WP's title logic.
	 * Returning an empty string (default) lets WP continue.
	 *
	 * @param string $title Current title (usually empty at this stage).
	 *
	 * @return string Resolved title or empty string to let WP continue.
	 */
	public function filterDocumentTitle(string $title): string
	{
		$post = \get_post();

		if ($post instanceof WP_Post) {
			// 1. Per-post override.
			$meta = \get_post_meta($post->ID, Options::getMetaKey('title'), true);
			if (!empty($meta)) {
				return \esc_html((string) $meta);
			}

			// 2. Template from settings.
			$template = $this->getTitleTemplate($post->post_type);
			if ($template) {
				$resolved = TemplateResolver::resolve($template, $post);
				if (!empty($resolved)) {
					return \esc_html($resolved);
				}
			}
		}

		// Let WordPress build the title naturally.
		return '';
	}

	/**
	 * Fallback filter for archive / term / search pages via document_title_parts.
	 *
	 * Modifies only the 'title' part so WP still appends site name + separator.
	 *
	 * @param array<string, string> $parts Title parts array.
	 *
	 * @return array<string, string>
	 */
	public function filterDocumentTitleParts(array $parts): array
	{
		if (\is_singular()) {
			// Already handled by pre_get_document_title when a meta/template is set.
			return $parts;
		}

		$queriedObject = \get_queried_object();
		$context       = ($queriedObject instanceof WP_Post || $queriedObject instanceof WP_Term)
			? $queriedObject
			: null;

		// 1. Per-term meta override on taxonomy archives.
		if ($queriedObject instanceof WP_Term) {
			$termTitle = \get_term_meta($queriedObject->term_id, Options::getTermMetaKey('title'), true);
			if (!empty($termTitle)) {
				$parts['title'] = \esc_html((string) $termTitle);
				return $parts;
			}
		}

		$postType = \is_post_type_archive() ? \get_query_var('post_type', '') : '';
		$template = $postType ? $this->getTitleTemplate((string) $postType) : null;

		if ($template) {
			$resolved = TemplateResolver::resolve($template, $context);
			if (!empty($resolved)) {
				$parts['title'] = $resolved;
			}
		}

		return $parts;
	}

	/**
	 * Get the title template for a given post type from settings.
	 *
	 * @param string $postType Post type slug.
	 *
	 * @return string|null Template string or null if none configured.
	 */
	private function getTitleTemplate(string $postType): ?string
	{
		$template = Options::getOption(['titleTemplates', $postType]);

		if (empty($template)) {
			// Fall back to 'post' template for unknown post types.
			$template = Options::getOption(['titleTemplates', 'post']);
		}

		// Allow per-project override.
		$template = \apply_filters(Options::getFilter('titleTemplate'), $template, $postType);

		return $template ?: null;
	}
}
