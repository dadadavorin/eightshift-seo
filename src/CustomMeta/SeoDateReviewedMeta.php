<?php

/**
 * The file that registers the es_seo_date_reviewed postmeta field.
 *
 * @package EightshiftSeo\CustomMeta
 */

declare(strict_types=1);

namespace EightshiftSeo\CustomMeta;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * SeoDateReviewedMeta class — registers a per-post "last reviewed" date.
 *
 * Stored as ISO 8601 calendar date (YYYY-MM-DD). Surfaces on the Article
 * schema as the schema.org-recognised `dateReviewed` property and powers
 * the StaleContentCheck health check.
 */
class SeoDateReviewedMeta implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('init', [$this, 'registerMeta']);
	}

	/**
	 * Register the postmeta field for all SEO-enabled post types.
	 *
	 * @return void
	 */
	public function registerMeta(): void
	{
		$postTypes = \apply_filters(
			Options::getFilter('supportedPostTypes'),
			Options::getPublicPostTypes()
		);

		foreach ($postTypes as $postType) {
			\register_post_meta($postType, Options::getMetaKey('dateReviewed'), [
				'type'          => 'string',
				'single'        => true,
				'default'       => '',
				'show_in_rest'  => [
					'schema' => [
						'type'    => 'string',
						'format'  => 'date',
						'pattern' => '^(\\d{4}-\\d{2}-\\d{2})?$',
					],
				],
				'auth_callback' => static fn () => \current_user_can('edit_posts'),
			]);
		}
	}
}
