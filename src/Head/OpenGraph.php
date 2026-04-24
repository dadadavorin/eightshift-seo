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
			// Array values emit one <meta> per element with the same property name.
			if (\is_array($content)) {
				foreach ($content as $item) {
					if (!empty($item)) {
						echo '<meta property="' . \esc_attr($property) . '" content="' . \esc_attr((string) $item) . '">' . "\n";
					}
				}
				continue;
			}

			if (empty($content) && $content !== '0') {
				continue;
			}
			echo '<meta property="' . \esc_attr($property) . '" content="' . \esc_attr((string) $content) . '">' . "\n";
		}
	}

	/**
	 * Build the full og:* tag array for the current page.
	 *
	 * Values are strings; article:tag uses string[] for multi-emit support.
	 *
	 * @return array<string, string|string[]>
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

					// og:image:alt from attachment alt text.
					$imageAlt = \get_post_meta($ogImageId, '_wp_attachment_image_alt', true);
					if (!empty($imageAlt)) {
						$tags['og:image:alt'] = (string) $imageAlt;
					}
				}
			}

			// article:* tags — only for article type.
			$author = \get_userdata((int) $post->post_author);
			if ($author) {
				$tags['article:author'] = $author->display_name;
			}

			$tags['article:published_time'] = \get_post_time('c', true, $post);
			$tags['article:modified_time']  = \get_post_modified_time('c', true, $post);

			// article:section — primary category (es_seo_primary_category) or first assigned.
			$primaryCatId = (int) \get_post_meta($post->ID, 'es_seo_primary_category', true);
			if ($primaryCatId > 0) {
				$primaryCat = \get_term($primaryCatId, 'category');
			} else {
				$cats = \get_the_category($post->ID);
				$primaryCat = !empty($cats) ? $cats[0] : null;
			}
			if ($primaryCat instanceof \WP_Term) {
				$tags['article:section'] = $primaryCat->name;
			}

			// article:tag — one <meta> per assigned tag; stored as array for multi-emit.
			$postTags = \get_the_tags($post->ID);
			if (\is_array($postTags) && !empty($postTags)) {
				$tags['article:tag'] = \array_map(static fn (\WP_Term $t) => $t->name, $postTags);
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

					$imageAlt = \get_post_meta($defaultImageId, '_wp_attachment_image_alt', true);
					if (!empty($imageAlt)) {
						$tags['og:image:alt'] = (string) $imageAlt;
					}
				}
			}
		}

		return $tags;
	}
}
