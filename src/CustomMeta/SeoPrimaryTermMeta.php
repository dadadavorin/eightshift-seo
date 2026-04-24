<?php

/**
 * The file that registers the es_seo_primary_category postmeta field.
 *
 * @package EightshiftSeo\CustomMeta
 */

declare(strict_types=1);

namespace EightshiftSeo\CustomMeta;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * SeoPrimaryTermMeta class — registers a per-post primary term ID for each
 * public hierarchical taxonomy.
 *
 * Meta key format: es_seo_primary_{taxonomy}, e.g. es_seo_primary_category.
 * The manifest 'primaryCategory' key maps to the default `category` taxonomy only;
 * additional taxonomies use the same pattern with their slug appended.
 */
class SeoPrimaryTermMeta implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('init', [$this, 'registerMeta'], 20);
	}

	/**
	 * Register one meta field per public hierarchical taxonomy × supported post type.
	 *
	 * @return void
	 */
	public function registerMeta(): void
	{
		$postTypes = \apply_filters(
			Options::getFilter('supportedPostTypes'),
			Options::getPublicPostTypes()
		);

		$taxonomies = \get_taxonomies(['public' => true, 'hierarchical' => true], 'names');

		foreach ($taxonomies as $taxonomy) {
			$metaKey = 'es_seo_primary_' . $taxonomy;

			foreach ($postTypes as $postType) {
				\register_post_meta($postType, $metaKey, [
					'type'          => 'integer',
					'single'        => true,
					'default'       => 0,
					'show_in_rest'  => [
						'schema' => [
							'type'    => 'integer',
							'minimum' => 0,
						],
					],
					'auth_callback' => static fn () => \current_user_can('edit_posts'),
				]);
			}
		}
	}
}
