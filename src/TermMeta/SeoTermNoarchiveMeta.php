<?php

/**
 * The file that registers the es_seo_term_noarchive term meta field.
 *
 * @package EightshiftSeo\TermMeta
 */

declare(strict_types=1);

namespace EightshiftSeo\TermMeta;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * SeoTermNoarchiveMeta class — registers a per-term noarchive robots directive.
 */
class SeoTermNoarchiveMeta implements ServiceInterface
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
			\register_term_meta($taxonomy, Options::getTermMetaKey('noarchive'), [
				'show_in_rest'  => [
					'schema' => [
						'type' => 'boolean',
					],
				],
				'single'        => true,
				'type'          => 'boolean',
				'default'       => false,
				'auth_callback' => static fn () => \current_user_can('manage_categories'),
			]);
		}
	}
}
