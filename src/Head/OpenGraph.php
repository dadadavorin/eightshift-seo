<?php

/**
 * The file that outputs Open Graph meta tags.
 *
 * @package EightshiftSeo\Head
 */

declare(strict_types=1);

namespace EightshiftSeo\Head;

use EightshiftSeo\Options\Options;
use EightshiftSeo\Templates\TemplateResolver;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;
use WP_Post;

/**
 * OpenGraph class — outputs og:* meta tags in wp_head.
 *
 * Fallback chain per tag:
 *   OG-specific meta → SEO meta (title/description) → post title/excerpt/featured image → site defaults
 */
class OpenGraph implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('wp_head', [$this, 'outputOpenGraphTags'], 5);
	}

	/**
	 * Build and output the og:* meta tags.
	 *
	 * @return void
	 */
	public function outputOpenGraphTags(): void
	{
		$tags = $this->buildTags();

		// Allow per-project mutations.
		$post = \get_post();
		$tags = \apply_filters(
			Options::getFilter('ogTags'),
			$tags,
			$post instanceof WP_Post ? $post : null
		);

		foreach ($tags as $property => $content) {
			if (empty($content) && $content !== '0') {
				continue;
			}
			echo '<meta property="' . \esc_attr($property) . '" content="' . \esc_attr((string) $content) . '">' . "\n";
		}
	}

	/**
	 * Build the full og:* tag array for the current page.
	 *
	 * @return array<string, string>
	 */
	private function buildTags(): array
	{
		$post     = \get_post();
		$tags     = [];
		$siteUrl  = \home_url('/');
		$locale   = \get_locale();
		$siteName = \get_bloginfo('name');

		// og:site_name and og:locale are always set.
		$tags['og:site_name'] = $siteName;
		$tags['og:locale']    = \str_replace('-', '_', $locale);

		if ($post instanceof WP_Post) {
			// og:type
			$tags['og:type'] = 'article';

			// og:url
			$tags['og:url'] = \get_permalink($post->ID) ?: $siteUrl;

			// og:title: OG-specific override → SEO title meta → post title
			$ogTitle = \get_post_meta($post->ID, Options::getMetaKey('ogTitle'), true);
			if (empty($ogTitle)) {
				$ogTitle = \get_post_meta($post->ID, Options::getMetaKey('title'), true);
			}
			if (empty($ogTitle)) {
				$ogTitle = \get_the_title($post);
			}
			$tags['og:title'] = (string) $ogTitle;

			// og:description: OG-specific override → SEO description meta → excerpt
			$ogDesc = \get_post_meta($post->ID, Options::getMetaKey('ogDescription'), true);
			if (empty($ogDesc)) {
				$ogDesc = \get_post_meta($post->ID, Options::getMetaKey('description'), true);
			}
			if (empty($ogDesc)) {
				$ogDesc = TemplateResolver::getPostExcerpt($post);
			}
			$tags['og:description'] = (string) $ogDesc;

			// og:image: OG-specific override → featured image → site default
			$ogImageId = (int) \get_post_meta($post->ID, Options::getMetaKey('ogImage'), true);
			if ($ogImageId <= 0) {
				$ogImageId = (int) \get_post_thumbnail_id($post->ID);
			}
			if ($ogImageId <= 0) {
				$ogImageId = (int) Options::getOption(['defaultOgImage']);
			}
			if ($ogImageId > 0) {
				$imageData = \wp_get_attachment_image_src($ogImageId, 'full');
				if ($imageData) {
					$tags['og:image']        = $imageData[0];
					$tags['og:image:width']  = (string) $imageData[1];
					$tags['og:image:height'] = (string) $imageData[2];
				}
			}
		} else {
			// Home / archive pages.
			$tags['og:type'] = 'website';
			$tags['og:url']  = \home_url(\add_query_arg([]));
			$tags['og:title'] = $siteName;

			// Default OG image for non-singular pages.
			$defaultImageId = (int) Options::getOption(['defaultOgImage']);
			if ($defaultImageId > 0) {
				$imageData = \wp_get_attachment_image_src($defaultImageId, 'full');
				if ($imageData) {
					$tags['og:image']        = $imageData[0];
					$tags['og:image:width']  = (string) $imageData[1];
					$tags['og:image:height'] = (string) $imageData[2];
				}
			}
		}

		return $tags;
	}
}
