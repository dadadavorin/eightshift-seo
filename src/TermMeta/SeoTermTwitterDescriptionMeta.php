<?php

/**
 * The file that registers the es_seo_term_twitter_description term meta field.
 *
 * @package EightshiftSeo\TermMeta
 */

declare(strict_types=1);

namespace EightshiftSeo\TermMeta;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * SeoTermTwitterDescriptionMeta class — registers a per-term twitter:description for all public taxonomies.
 */
class SeoTermTwitterDescriptionMeta implements ServiceInterface
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
			\register_term_meta($taxonomy, Options::getTermMetaKey('twitterDescription'), [
				'show_in_rest'  => [
					'schema' => [
						'type'      => 'string',
						'maxLength' => 300,
					],
				],
				'single'        => true,
				'type'          => 'string',
				'default'       => '',
				'auth_callback' => static fn () => \current_user_can('manage_categories'),
			]);
		}
	}
}
