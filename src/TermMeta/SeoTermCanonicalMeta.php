<?php

/**
 * The file that registers the es_seo_term_canonical term meta field.
 *
 * @package EightshiftSeo\TermMeta
 */

declare(strict_types=1);

namespace EightshiftSeo\TermMeta;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * SeoTermCanonicalMeta class — registers a per-term canonical URL override for all public taxonomies.
 */
class SeoTermCanonicalMeta implements ServiceInterface
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
			\register_term_meta($taxonomy, Options::getTermMetaKey('canonical'), [
				'show_in_rest'  => [
					'schema' => [
						'type'   => 'string',
						'format' => 'uri',
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
