<?php

/**
 * The file that defines the template token resolver.
 *
 * @package EightshiftSeo\Templates
 */

declare(strict_types=1);

namespace EightshiftSeo\Templates;

use EightshiftSeo\Options\Options;
use WP_Post;
use WP_Term;

/**
 * TemplateResolver — resolves %token% placeholders in title/description templates.
 *
 * Pure class, not a ServiceInterface. Tokens:
 *   %title%          — post title or archive title
 *   %sitename%       — get_bloginfo('name')
 *   %sep%            — separator from settings (default: –)
 *   %excerpt%        — post excerpt (auto-generated if empty)
 *   %archive_title%  — post type archive label or term name
 *   %author%         — post author display name
 *   %date%           — post date (Y)
 *
 * Projects can extend the token list via the es_seo_template_tokens filter.
 */
class TemplateResolver
{
	/**
	 * Resolve a template string against the given post or term context.
	 *
	 * @param string                    $template Template string with %token% placeholders.
	 * @param WP_Post|WP_Term|null      $context  Post or term context; null for archive/home pages.
	 *
	 * @return string Resolved string with all known tokens replaced.
	 */
	public static function resolve(string $template, WP_Post|WP_Term|null $context = null): string
	{
		$tokens = self::buildTokens($context);

		// Allow projects to add or override tokens.
		$tokens = \apply_filters(Options::getFilter('templateTokens'), $tokens, $context);

		$search  = \array_keys($tokens);
		$replace = \array_values($tokens);

		return \str_replace($search, $replace, $template);
	}

	/**
	 * Build the default token map for the given context.
	 *
	 * @param WP_Post|WP_Term|null $context Post or term context.
	 *
	 * @return array<string, string> Map of %token% → resolved value.
	 */
	private static function buildTokens(WP_Post|WP_Term|null $context): array
	{
		$sep      = Options::getOption(['separator']) ?: '–';
		$sitename = \get_bloginfo('name');

		$tokens = [
			'%sitename%' => $sitename,
			'%sep%'      => $sep,
			'%title%'    => '',
			'%excerpt%'  => '',
			'%archive_title%' => '',
			'%author%'   => '',
			'%date%'     => '',
		];

		if ($context instanceof WP_Post) {
			$tokens['%title%']   = \get_the_title($context);
			$tokens['%excerpt%'] = self::getPostExcerpt($context);
			$tokens['%author%']  = \get_the_author_meta('display_name', (int) $context->post_author);
			$tokens['%date%']    = \get_the_date('Y', $context);

			$postType = \get_post_type_object($context->post_type);
			$tokens['%archive_title%'] = $postType ? ($postType->labels->name ?? '') : '';
		} elseif ($context instanceof WP_Term) {
			$tokens['%title%']         = $context->name;
			$tokens['%archive_title%'] = $context->name;
			$tokens['%excerpt%']       = \wp_strip_all_tags($context->description ?? '');
		} else {
			// Archive or home context — use queried object if available.
			$queriedObject = \get_queried_object();

			if ($queriedObject instanceof WP_Post) {
				$tokens['%title%']  = \get_the_title($queriedObject);
				$tokens['%excerpt%'] = self::getPostExcerpt($queriedObject);
			} elseif ($queriedObject instanceof WP_Term) {
				$tokens['%title%']         = $queriedObject->name;
				$tokens['%archive_title%'] = $queriedObject->name;
			} elseif (\is_post_type_archive()) {
				$postTypeObj = \get_queried_object();
				if ($postTypeObj instanceof \WP_Post_Type) {
					$tokens['%title%']         = $postTypeObj->labels->name ?? '';
					$tokens['%archive_title%'] = $postTypeObj->labels->name ?? '';
				}
			} elseif (\is_home() || \is_front_page()) {
				$tokens['%title%'] = $sitename;
			}
		}

		return $tokens;
	}

	/**
	 * Get a post's excerpt, generating one from content if the explicit excerpt is empty.
	 *
	 * @param WP_Post $post Post object.
	 *
	 * @return string Plain-text excerpt.
	 */
	public static function getPostExcerpt(WP_Post $post): string
	{
		if (!empty($post->post_excerpt)) {
			return \wp_strip_all_tags($post->post_excerpt);
		}

		// Auto-generate from content: strip shortcodes/tags and trim to 160 chars.
		$content = \strip_shortcodes($post->post_content);
		$content = \wp_strip_all_tags($content);
		$content = \trim(\preg_replace('/\s+/', ' ', $content) ?? '');

		return \mb_substr($content, 0, 160);
	}
}
