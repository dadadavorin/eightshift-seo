<?php

/**
 * The file that registers the es_seo_max_image_preview post meta field.
 *
 * @package EightshiftSeo\CustomMeta
 */

declare(strict_types=1);

namespace EightshiftSeo\CustomMeta;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * SeoMaxImagePreviewMeta class — registers the max-image-preview robots directive value.
 *
 * Allowed values: "none" | "standard" | "large". Defaults to "large" (Google default).
 * Maps to the max-image-preview:<setting> robots directive.
 */
class SeoMaxImagePreviewMeta implements ServiceInterface
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
			\register_meta('post', Options::getMetaKey('maxImagePreview'), [
				'object_subtype' => $postType,
				'show_in_rest'   => [
					'schema' => [
						'type' => 'string',
						'enum' => ['none', 'standard', 'large'],
					],
				],
				'single'        => true,
				'type'          => 'string',
				'default'       => 'large',
				'auth_callback' => static fn () => \current_user_can('edit_posts'),
			]);
		}
	}
}
