<?php

/**
 * The file that registers the es_seo_term_twitter_image term meta field.
 *
 * @package EightshiftSeo\TermMeta
 */

declare(strict_types=1);

namespace EightshiftSeo\TermMeta;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * SeoTermTwitterImageMeta class — registers a per-term twitter:image (attachment ID) for all public taxonomies.
 */
class SeoTermTwitterImageMeta implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('init', [$this, 'registerMeta'], 10);
	}

	/**
	 * Register the term meta for every public taxonomy.
	 *
	 * @return void
	 */
	public function registerMeta(): void
	{
		foreach (\get_taxonomies(['public' => true]) as $taxonomy) {
			\register_term_meta($taxonomy, Options::getTermMetaKey('twitterImage'), [
				'show_in_rest'  => [
					'schema' => [
						'type'    => 'integer',
						'minimum' => 0,
					],
				],
				'single'        => true,
				'type'          => 'integer',
				'default'       => 0,
				'auth_callback' => static fn () => \current_user_can('manage_categories'),
			]);
		}
	}
}
