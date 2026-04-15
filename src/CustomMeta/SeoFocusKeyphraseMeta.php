<?php

/**
 * The file that registers the es_seo_focus_keyphrase post meta field.
 *
 * @package EightshiftSeo\CustomMeta
 */

declare(strict_types=1);

namespace EightshiftSeo\CustomMeta;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * SeoFocusKeyphraseMeta class — registers the focus keyphrase meta field for all
 * public post types. Used by the pre-publish panel to run on-page SEO checks.
 */
class SeoFocusKeyphraseMeta implements ServiceInterface
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
	 * Register the meta field for every public post type.
	 *
	 * @return void
	 */
	public function registerMeta(): void
	{
		foreach (Options::getPublicPostTypes() as $postType) {
			\register_meta('post', Options::getMetaKey('focusKeyphrase'), [
				'object_subtype' => $postType,
				'show_in_rest'   => [
					'schema' => [
						'type'      => 'string',
						'maxLength' => 200,
					],
				],
				'single'        => true,
				'type'          => 'string',
				'default'       => '',
				'auth_callback' => static fn () => \current_user_can('edit_posts'),
			]);
		}
	}
}
