<?php

/**
 * The file that registers the es_seo_term_og_title term meta field.
 *
 * @package EightshiftSeo\TermMeta
 */

declare(strict_types=1);

namespace EightshiftSeo\TermMeta;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * SeoTermOgTitleMeta class — registers a per-term og:title override for all public taxonomies.
 */
class SeoTermOgTitleMeta implements ServiceInterface
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
			\register_term_meta($taxonomy, Options::getTermMetaKey('ogTitle'), [
				'show_in_rest'  => [
					'schema' => [
						'type'      => 'string',
						'maxLength' => 200,
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
