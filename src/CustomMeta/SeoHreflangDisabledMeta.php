<?php

/**
 * The file that registers the hreflang disabled post meta.
 *
 * @package EightshiftSeo\CustomMeta
 */

declare(strict_types=1);

namespace EightshiftSeo\CustomMeta;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * SeoHreflangDisabledMeta class — registers the es_seo_hreflang_disabled boolean
 * post meta that suppresses hreflang emission for individual posts.
 */
class SeoHreflangDisabledMeta implements ServiceInterface
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
	 * Register the post meta with the REST API.
	 *
	 * @return void
	 */
	public function registerMeta(): void
	{
		$metaKey   = Options::getMetaKey('hreflangDisabled');
		$postTypes = Options::getPublicPostTypes();

		foreach ($postTypes as $postType) {
			\register_post_meta(
				$postType,
				$metaKey,
				[
					'type'          => 'boolean',
					'single'        => true,
					'default'       => false,
					'show_in_rest'  => true,
					'auth_callback' => static fn() => \current_user_can('edit_posts'),
				]
			);
		}
	}
}
