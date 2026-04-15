<?php

/**
 * The file that registers the es_seo_max_snippet post meta field.
 *
 * @package EightshiftSeo\CustomMeta
 */

declare(strict_types=1);

namespace EightshiftSeo\CustomMeta;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * SeoMaxSnippetMeta class — registers the max-snippet robots directive value.
 *
 * -1 means no limit (Google default). Any positive integer limits the text snippet
 * length in characters. Maps to the max-snippet:<number> robots directive.
 */
class SeoMaxSnippetMeta implements ServiceInterface
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
			\register_meta('post', Options::getMetaKey('maxSnippet'), [
				'object_subtype' => $postType,
				'show_in_rest'   => [
					'schema' => [
						'type'    => 'integer',
						'minimum' => -1,
					],
				],
				'single'        => true,
				'type'          => 'integer',
				'default'       => -1,
				'auth_callback' => static fn () => \current_user_can('edit_posts'),
			]);
		}
	}
}
