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
 *   %title%             — post title or archive title
 *   %sitename%          — get_bloginfo('name')
 *   %tagline%           — get_bloginfo('description')
 *   %sep%               — separator from settings (default: –)
 *   %excerpt%           — post excerpt (auto-generated if empty)
 *   %archive_title%     — post type archive label or term name
 *   %author%            — post author display name
 *   %date%              — post date (Y)
 *   %modified_date%     — post modified date (filter-configurable format)
 *   %id%                — post / term ID
 *   %parent_title%      — parent post title for hierarchical post types
 *   %primary_category%  — primary category meta or first assigned category
 *   %category%          — comma-separated category names
 *   %tag%               — comma-separated tag names
 *   %page%              — current page on paginated content
 *   %pagetotal%         — total pages for paginated content
 *   %search_phrase%     — get_search_query() on search pages
 *   %current_year%      — current year (date_i18n)
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

		$resolved = \str_replace($search, $replace, $template);

		// Collapse runs of whitespace left behind by empty tokens.
		return \trim(\preg_replace('/\s+/u', ' ', $resolved) ?? $resolved);
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
		$tagline  = \get_bloginfo('description');

		$tokens = [
			'%sitename%'         => (string) $sitename,
			'%tagline%'          => (string) $tagline,
			'%sep%'              => $sep,
			'%title%'            => '',
			'%excerpt%'          => '',
			'%archive_title%'    => '',
			'%author%'           => '',
			'%date%'             => '',
			'%modified_date%'    => '',
			'%id%'               => '',
			'%parent_title%'     => '',
			'%primary_category%' => '',
			'%category%'         => '',
			'%tag%'              => '',
			'%page%'             => '',
			'%pagetotal%'        => '',
			'%search_phrase%'    => '',
			'%current_year%'     => (string) \date_i18n('Y'),
		];

		$dateFormat = \apply_filters(
			Options::getFilter('templateTokensDateFormat'),
			\get_option('date_format') ?: 'Y'
		);

		// Pagination values — available on any page type.
		$paged     = (int) \get_query_var('paged');
		$page      = (int) \get_query_var('page');
		$current   = \max(1, $paged > 0 ? $paged : $page);
		$pageTotal = self::getPageTotal();

		$tokens['%page%']      = (string) $current;
		$tokens['%pagetotal%'] = (string) $pageTotal;

		if (\is_search()) {
			$tokens['%search_phrase%'] = (string) \get_search_query();
		}

		if ($context instanceof WP_Post) {
			$tokens['%title%']   = \get_the_title($context);
			$tokens['%excerpt%'] = self::getPostExcerpt($context);
			$tokens['%author%']  = (string) \get_the_author_meta('display_name', (int) $context->post_author);
			$tokens['%date%']    = (string) \get_the_date($dateFormat, $context);
			$tokens['%modified_date%'] = (string) \get_the_modified_date($dateFormat, $context);
			$tokens['%id%']      = (string) $context->ID;

			if ($context->post_parent > 0) {
				$tokens['%parent_title%'] = (string) \get_the_title($context->post_parent);
			}

			$postType = \get_post_type_object($context->post_type);
			$tokens['%archive_title%'] = $postType ? ($postType->labels->name ?? '') : '';

			$tokens['%primary_category%'] = self::getPrimaryCategoryName($context);
			$tokens['%category%']         = self::getTermList($context, 'category');
			$tokens['%tag%']              = self::getTermList($context, 'post_tag');
		} elseif ($context instanceof WP_Term) {
			$tokens['%title%']         = $context->name;
			$tokens['%archive_title%'] = $context->name;
			$tokens['%excerpt%']       = (string) \wp_strip_all_tags($context->description ?? '');
			$tokens['%id%']            = (string) $context->term_id;
		} else {
			// Archive or home context — use queried object if available.
			$queriedObject = \get_queried_object();

			if ($queriedObject instanceof WP_Post) {
				$tokens['%title%']   = \get_the_title($queriedObject);
				$tokens['%excerpt%'] = self::getPostExcerpt($queriedObject);
				$tokens['%id%']      = (string) $queriedObject->ID;
				$tokens['%modified_date%'] = (string) \get_the_modified_date($dateFormat, $queriedObject);
			} elseif ($queriedObject instanceof WP_Term) {
				$tokens['%title%']         = $queriedObject->name;
				$tokens['%archive_title%'] = $queriedObject->name;
				$tokens['%id%']            = (string) $queriedObject->term_id;
			} elseif (\is_post_type_archive()) {
				$postTypeObj = \get_queried_object();
				if ($postTypeObj instanceof \WP_Post_Type) {
					$tokens['%title%']         = $postTypeObj->labels->name ?? '';
					$tokens['%archive_title%'] = $postTypeObj->labels->name ?? '';
				}
			} elseif (\is_home() || \is_front_page()) {
				$tokens['%title%'] = (string) $sitename;
			}
		}

		return $tokens;
	}

	/**
	 * Determine the total page count for paginated content.
	 *
	 * @return int
	 */
	private static function getPageTotal(): int
	{
		global $wp_query, $numpages;

		if (\is_singular() && isset($numpages) && (int) $numpages > 0) {
			return (int) $numpages;
		}

		if ($wp_query instanceof \WP_Query && (int) $wp_query->max_num_pages > 0) {
			return (int) $wp_query->max_num_pages;
		}

		return 1;
	}

	/**
	 * Resolve the primary category name for a post, falling back to the first
	 * assigned category when no primary term is stored.
	 *
	 * @param WP_Post $post Post object.
	 *
	 * @return string
	 */
	private static function getPrimaryCategoryName(WP_Post $post): string
	{
		$primaryKey = Options::getMetaKey('primaryCategory');
		if ($primaryKey !== '') {
			$primaryId = (int) \get_post_meta($post->ID, $primaryKey, true);
			if ($primaryId > 0) {
				$term = \get_term($primaryId, 'category');
				if ($term instanceof WP_Term) {
					return $term->name;
				}
			}
		}

		$terms = \get_the_terms($post->ID, 'category');
		if (\is_array($terms) && !empty($terms)) {
			$first = \reset($terms);
			if ($first instanceof WP_Term) {
				return $first->name;
			}
		}

		return '';
	}

	/**
	 * Build a comma-separated list of term names attached to a post.
	 *
	 * @param WP_Post $post     Post object.
	 * @param string  $taxonomy Taxonomy slug.
	 *
	 * @return string
	 */
	private static function getTermList(WP_Post $post, string $taxonomy): string
	{
		$terms = \get_the_terms($post->ID, $taxonomy);
		if (!\is_array($terms) || empty($terms)) {
			return '';
		}

		$names = [];
		foreach ($terms as $term) {
			if ($term instanceof WP_Term) {
				$names[] = $term->name;
			}
		}

		return \implode(', ', $names);
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

		if (\mb_strlen($content) <= 160) {
			return $content;
		}

		// Trim at a word boundary so the description never ends mid-word.
		$trimmed   = \mb_substr($content, 0, 160);
		$lastSpace = \mb_strrpos($trimmed, ' ');

		return $lastSpace !== false ? \mb_substr($trimmed, 0, $lastSpace) : $trimmed;
	}
}
