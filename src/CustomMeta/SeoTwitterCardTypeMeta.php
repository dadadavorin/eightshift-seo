<?php

/**
 * The file that registers the es_seo_twitter_card_type postmeta field.
 *
 * @package EightshiftSeo\CustomMeta
 */

declare(strict_types=1);

namespace EightshiftSeo\CustomMeta;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * SeoTwitterCardTypeMeta class — registers a per-post Twitter card type override.
 *
 * Allowed values: 'summary', 'summary_large_image', or '' (inherit site default).
 */
class SeoTwitterCardTypeMeta implements ServiceInterface
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
			\register_post_meta($postType, Options::getMetaKey('twitterCardType'), [
				'type'          => 'string',
				'single'        => true,
				'default'       => '',
				'show_in_rest'  => [
					'schema' => [
						'type' => 'string',
						'enum' => ['', 'summary', 'summary_large_image'],
					],
				],
				'auth_callback' => static fn () => \current_user_can('edit_posts'),
			]);
		}
	}
}
