<?php

/**
 * The file that registers the es_seo_unavailable_after postmeta field.
 *
 * @package EightshiftSeo\CustomMeta
 */

declare(strict_types=1);

namespace EightshiftSeo\CustomMeta;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * SeoRobotsUnavailableAfterMeta class — registers the per-post unavailable_after directive.
 *
 * Stores an ISO 8601 date string. When set, tells crawlers not to index the page after
 * that date (e.g., for time-limited content like event registrations).
 */
class SeoRobotsUnavailableAfterMeta implements ServiceInterface
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
			\register_post_meta($postType, Options::getMetaKey('unavailableAfter'), [
				'type'          => 'string',
				'single'        => true,
				'default'       => '',
				'show_in_rest'  => [
					'schema' => [
						'type'   => 'string',
						'format' => 'date-time',
					],
				],
				'auth_callback' => static fn () => \current_user_can('edit_posts'),
			]);
		}
	}
}
