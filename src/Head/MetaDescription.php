<?php

/**
 * The file that outputs <meta name="description">.
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
 * MetaDescription class — outputs the meta description tag in wp_head.
 *
 * Resolution order:
 *   1. Post-level es_seo_description meta override
 *   2. Per-post-type description template from settings (resolved through TemplateResolver)
 *   3. Auto-generated excerpt from post content
 */
class MetaDescription implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('wp_head', [$this, 'outputMetaDescription'], 1);
	}

	/**
	 * Output the <meta name="description"> tag.
	 *
	 * @return void
	 */
	public function outputMetaDescription(): void
	{
		$description = $this->resolveDescription();

		if (empty($description)) {
			return;
		}

		echo '<meta name="description" content="' . \esc_attr($description) . '">' . "\n";
	}

	/**
	 * Resolve the description for the current page.
	 *
	 * @return string Resolved description or empty string.
	 */
	private function resolveDescription(): string
	{
		$post = \get_post();

		if ($post instanceof WP_Post) {
			// 1. Per-post meta override.
			$meta = \get_post_meta($post->ID, Options::getMetaKey('description'), true);
			if (!empty($meta)) {
				return (string) $meta;
			}

			// 2. Template from settings.
			$template = $this->getDescriptionTemplate($post->post_type);
			if ($template) {
				$resolved = TemplateResolver::resolve($template, $post);
				if (!empty($resolved)) {
					return $resolved;
				}
			}

			// 3. Auto-generated excerpt.
			return TemplateResolver::getPostExcerpt($post);
		}

		// For taxonomy archives: check per-term meta first.
		$queriedObject = \get_queried_object();
		if ($queriedObject instanceof WP_Term) {
			$termDesc = \get_term_meta($queriedObject->term_id, Options::getTermMetaKey('description'), true);
			if (!empty($termDesc)) {
				return (string) $termDesc;
			}
		}

		// Fall back to page description template.
		$template = Options::getOption(['descriptionTemplates', 'page']);
		if ($template) {
			$template = \apply_filters(Options::getFilter('descriptionTemplate'), $template, '');
			return TemplateResolver::resolve((string) $template, $queriedObject instanceof WP_Term ? $queriedObject : null);
		}

		return '';
	}

	/**
	 * Get the description template for a given post type.
	 *
	 * @param string $postType Post type slug.
	 *
	 * @return string|null Template string or null if none configured.
	 */
	private function getDescriptionTemplate(string $postType): ?string
	{
		$template = Options::getOption(['descriptionTemplates', $postType]);

		if (empty($template)) {
			$template = Options::getOption(['descriptionTemplates', 'post']);
		}

		$template = \apply_filters(Options::getFilter('descriptionTemplate'), $template, $postType);

		return $template ?: null;
	}
}
