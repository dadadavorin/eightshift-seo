<?php

/**
 * The file that registers the es_seo_notranslate postmeta field.
 *
 * @package EightshiftSeo\CustomMeta
 */

declare(strict_types=1);

namespace EightshiftSeo\CustomMeta;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * SeoRobotsNotranslateMeta class — registers the per-post notranslate robots directive.
 *
 * When true, tells Google not to offer a translation of the page in search results.
 */
class SeoRobotsNotranslateMeta implements ServiceInterface
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
			\register_post_meta($postType, Options::getMetaKey('notranslate'), [
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
