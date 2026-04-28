<?php

/**
 * The file that registers the es_seo_noai postmeta field.
 *
 * @package EightshiftSeo\CustomMeta
 */

declare(strict_types=1);

namespace EightshiftSeo\CustomMeta;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * SeoNoaiMeta class — registers the per-post noai flag.
 *
 * When true, the AiRobotsDirectives service emits:
 *   <meta name="robots" content="noai">
 * plus per-bot variants for all training-category bots in the registry.
 */
class SeoNoaiMeta implements ServiceInterface
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
			\register_post_meta($postType, Options::getMetaKey('noai'), [
				'type'          => 'boolean',
				'single'        => true,
				'default'       => false,
				'show_in_rest'  => [
					'schema' => ['type' => 'boolean'],
				],
				'auth_callback' => static fn () => \current_user_can('edit_posts'),
			]);
		}
	}
}
