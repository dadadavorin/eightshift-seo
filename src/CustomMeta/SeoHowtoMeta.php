<?php

/**
 * The file that registers the es_seo_howto postmeta field.
 *
 * @package EightshiftSeo\CustomMeta
 */

declare(strict_types=1);

namespace EightshiftSeo\CustomMeta;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * SeoHowtoMeta class — registers the per-post HowTo field.
 *
 * Stored as a JSON-encoded string to avoid WordPress's inconsistent handling of
 * serialised complex objects in post meta. Consumers use json_decode() to read it.
 *
 * Expected JSON structure:
 * {
 *   "name":        string,
 *   "description": string,
 *   "totalTime":   string (ISO 8601 duration, e.g. "PT30M"),
 *   "steps": [
 *     { "name": string, "text": string, "image": string (URI, optional) }
 *   ]
 * }
 *
 * Consumed by HowToSchema to emit a HowTo structured-data node.
 */
class SeoHowtoMeta implements ServiceInterface
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
			\register_post_meta($postType, Options::getMetaKey('howto'), [
				'type'          => 'string',
				'single'        => true,
				'default'       => '',
				'show_in_rest'  => [
					'schema' => [
						'type' => 'string',
					],
				],
				'auth_callback' => static fn () => \current_user_can('edit_posts'),
			]);
		}
	}
}
