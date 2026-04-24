<?php

/**
 * The file that outputs Twitter Card meta tags.
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
 * TwitterCards class — outputs twitter:* meta tags in wp_head.
 *
 * Fallback chain per tag:
 *   Twitter-specific meta → OG meta → SEO meta → post title/excerpt/featured image → site defaults
 */
class TwitterCards implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('wp_head', [$this, 'outputTwitterTags'], 5);
	}

	/**
	 * Build and output the twitter:* meta tags.
	 *
	 * @return void
	 */
	public function outputTwitterTags(): void
	{
		$tags = $this->buildTags();

		// Allow per-project mutations.
		$post = \get_post();
		$tags = \apply_filters(
			Options::getFilter('twitterTags'),
			$tags,
			$post instanceof WP_Post ? $post : null
		);

		foreach ($tags as $name => $content) {
			if (empty($content) && $content !== '0') {
				continue;
			}
			echo '<meta name="' . \esc_attr($name) . '" content="' . \esc_attr((string) $content) . '">' . "\n";
		}
	}

	/**
	 * Build the full twitter:* tag array for the current page.
	 *
	 * @return array<string, string>
	 */
	private function buildTags(): array
	{
		$post = \get_post();
		$tags = [];

		// Resolve base card type: per-post override → site default → 'summary'.
		$siteDefault = (string) (Options::getOption(['twitterCardDefault']) ?: 'summary_large_image');
		$tags['twitter:card'] = $siteDefault ?: 'summary';

		// twitter:site: @handle from settings.
		$handle = Options::getOption(['twitterHandle']);
		if (!empty($handle)) {
			$tags['twitter:site'] = '@' . \ltrim((string) $handle, '@');
		}

		if ($post instanceof WP_Post) {
			// Per-post card type override ('' means inherit site default).
			$perPostCard = (string) \get_post_meta($post->ID, Options::getMetaKey('twitterCardType'), true);
			if (!empty($perPostCard)) {
				$tags['twitter:card'] = $perPostCard;
			}

			// twitter:title: Twitter-specific → OG-specific → SEO title → post title
			$twTitle = \get_post_meta($post->ID, Options::getMetaKey('twitterTitle'), true);
			if (empty($twTitle)) {
				$twTitle = \get_post_meta($post->ID, Options::getMetaKey('ogTitle'), true);
			}
			if (empty($twTitle)) {
				$twTitle = \get_post_meta($post->ID, Options::getMetaKey('title'), true);
			}
			if (empty($twTitle)) {
				$twTitle = \get_the_title($post);
			}
			$tags['twitter:title'] = (string) $twTitle;

			// twitter:description: Twitter-specific → OG → SEO description → excerpt
			$twDesc = \get_post_meta($post->ID, Options::getMetaKey('twitterDescription'), true);
			if (empty($twDesc)) {
				$twDesc = \get_post_meta($post->ID, Options::getMetaKey('ogDescription'), true);
			}
			if (empty($twDesc)) {
				$twDesc = \get_post_meta($post->ID, Options::getMetaKey('description'), true);
			}
			if (empty($twDesc)) {
				$twDesc = TemplateResolver::getPostExcerpt($post);
			}
			$tags['twitter:description'] = (string) $twDesc;

			// twitter:image: Twitter-specific → OG-specific → featured image → site default
			$twImageId = (int) \get_post_meta($post->ID, Options::getMetaKey('twitterImage'), true);
			if ($twImageId <= 0) {
				$twImageId = (int) \get_post_meta($post->ID, Options::getMetaKey('ogImage'), true);
			}
			if ($twImageId <= 0) {
				$twImageId = (int) \get_post_thumbnail_id($post->ID);
			}
			if ($twImageId <= 0) {
				$twImageId = (int) Options::getOption(['defaultOgImage']);
			}
			if ($twImageId > 0) {
				$imageData = \wp_get_attachment_image_src($twImageId, 'full');
				if ($imageData) {
					$tags['twitter:image'] = $imageData[0];

					// Use summary_large_image when we have an image and no explicit card type override.
					if (empty($perPostCard)) {
						$tags['twitter:card'] = 'summary_large_image';
					}

					$imageAlt = \get_post_meta($twImageId, '_wp_attachment_image_alt', true);
					if (!empty($imageAlt)) {
						$tags['twitter:image:alt'] = (string) $imageAlt;
					}
				}
			}
		} else {
			// Home / archive.
			$tags['twitter:title'] = \get_bloginfo('name');

			$defaultImageId = (int) Options::getOption(['defaultOgImage']);
			if ($defaultImageId > 0) {
				$imageData = \wp_get_attachment_image_src($defaultImageId, 'full');
				if ($imageData) {
					$tags['twitter:image'] = $imageData[0];
					$tags['twitter:card']  = 'summary_large_image';

					$imageAlt = \get_post_meta($defaultImageId, '_wp_attachment_image_alt', true);
					if (!empty($imageAlt)) {
						$tags['twitter:image:alt'] = (string) $imageAlt;
					}
				}
			}
		}

		return $tags;
	}
}
