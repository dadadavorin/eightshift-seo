<?php

/**
 * The file that registers the es_seo_definition_term postmeta field.
 *
 * @package EightshiftSeo\CustomMeta
 */

declare(strict_types=1);

namespace EightshiftSeo\CustomMeta;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * SeoDefinitionTermMeta class — registers the per-post defined-term field.
 *
 * When set, the post is treated as defining the given term and a
 * DefinedTerm node is contributed to the schema graph. Otherwise the
 * detection logic falls back to scanning the first paragraph for a
 * definition-first opener (see DefinedTermSchema::detect()).
 */
class SeoDefinitionTermMeta implements ServiceInterface
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
			\register_post_meta($postType, Options::getMetaKey('definitionTerm'), [
				'type'          => 'string',
				'single'        => true,
				'default'       => '',
				'show_in_rest'  => [
					'schema' => [
						'type'      => 'string',
						'maxLength' => 200,
					],
				],
				'auth_callback' => static fn () => \current_user_can('edit_posts'),
			]);
		}
	}
}
