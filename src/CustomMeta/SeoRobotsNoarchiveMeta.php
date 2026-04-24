<?php

/**
 * The file that registers the es_seo_noarchive postmeta field.
 *
 * @package EightshiftSeo\CustomMeta
 */

declare(strict_types=1);

namespace EightshiftSeo\CustomMeta;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * SeoRobotsNoarchiveMeta class — registers the per-post noarchive robots directive.
 *
 * When true, tells crawlers not to show a cached version of the page.
 */
class SeoRobotsNoarchiveMeta implements ServiceInterface
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
			\register_post_meta($postType, Options::getMetaKey('noarchive'), [
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
