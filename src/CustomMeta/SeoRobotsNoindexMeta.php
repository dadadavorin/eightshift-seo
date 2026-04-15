<?php

/**
 * The file that registers the es_seo_noindex postmeta field.
 *
 * @package EightshiftSeo\CustomMeta
 */

declare(strict_types=1);

namespace EightshiftSeo\CustomMeta;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * SeoRobotsNoindexMeta class — registers the per-post noindex robots flag.
 *
 * Stored as boolean (not private) so it is queryable via meta_query,
 * which is required for sitemap exclusion.
 */
class SeoRobotsNoindexMeta implements ServiceInterface
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
			\register_post_meta($postType, Options::getMetaKey('noindex'), [
				'type'          => 'boolean',
				'single'        => true,
				'default'       => false,
				'show_in_rest'  => [
					'schema' => [
						'type' => 'boolean',
					],
				],
				'auth_callback' => static fn () => \current_user_can('edit_posts'),
			]);
		}
	}
}
