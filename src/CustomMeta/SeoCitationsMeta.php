<?php

/**
 * The file that registers the es_seo_citations postmeta field.
 *
 * @package EightshiftSeo\CustomMeta
 */

declare(strict_types=1);

namespace EightshiftSeo\CustomMeta;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * SeoCitationsMeta class — registers the per-post citations array field.
 *
 * Each citation is an object with:
 *   - label        (string, max 240, required)
 *   - url          (string, URI format, required)
 *   - publisher    (string, max 120, optional)
 *   - datePublished (string, date format, optional)
 *
 * Consumed by ArticleSchema to emit citation nodes on the Article graph node.
 */
class SeoCitationsMeta implements ServiceInterface
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
			\register_post_meta($postType, Options::getMetaKey('citations'), [
				'type'          => 'array',
				'single'        => true,
				'show_in_rest'  => [
					'schema' => [
						'type'  => 'array',
						'items' => [
							'type'                 => 'object',
							'additionalProperties' => false,
							'required'             => ['label', 'url'],
							'properties'           => [
								'label'         => [
									'type'      => 'string',
									'maxLength' => 240,
								],
								'url'           => [
									'type'   => 'string',
									'format' => 'uri',
								],
								'publisher'     => [
									'type'      => 'string',
									'maxLength' => 120,
								],
								'datePublished' => [
									'type'   => 'string',
									'format' => 'date',
								],
							],
						],
					],
				],
				'auth_callback' => static fn () => \current_user_can('edit_posts'),
			]);
		}
	}
}
