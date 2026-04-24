<?php

/**
 * The file that registers the es_seo_nosnippet postmeta field.
 *
 * @package EightshiftSeo\CustomMeta
 */

declare(strict_types=1);

namespace EightshiftSeo\CustomMeta;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * SeoRobotsNosnippetMeta class — registers the per-post nosnippet robots directive.
 *
 * When true, prevents Google from showing a text snippet below the search result.
 */
class SeoRobotsNosnippetMeta implements ServiceInterface
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
			\register_post_meta($postType, Options::getMetaKey('nosnippet'), [
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
