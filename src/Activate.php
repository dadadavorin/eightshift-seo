<?php

/**
 * The file that defines actions on plugin activation.
 *
 * @package EightshiftSeo
 */

declare(strict_types=1);

namespace EightshiftSeo;

use EightshiftSeoVendor\EightshiftLibs\Plugin\HasActivationInterface;
use WP_Role;

/**
 * The plugin activation class.
 */
class Activate implements HasActivationInterface
{
	/**
	 * Activate the plugin.
	 *
	 * @return void
	 */
	public function activate(): void
	{
		// Add caps from manifest to administrator role.
		$role = \get_role('administrator');

		if ($role instanceof WP_Role) {
			$output = \file_get_contents(__DIR__ . '/Blocks/manifest.json'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file read, not a remote request.

			if ($output !== false) {
				$decoded = \json_decode($output, true);
				$caps = \is_array($decoded) ? ($decoded['caps'] ?? []) : [];

				foreach ($caps as $cap) {
					$capName = $cap['id'] ?? '';

					if (!$capName) {
						continue;
					}

					$role->add_cap($capName);
				}
			}
		}

		// Register LLM endpoint rewrite rules before flushing so they are
		// included in the regenerated rule set (init has not fired yet here).
		\add_rewrite_rule('^llm-sitemap\.xml$', 'index.php?es_seo_llm_sitemap=1', 'top');
		\add_rewrite_rule('^llms\.txt$', 'index.php?es_seo_llms_txt=1', 'top');

		// Flush rewrite rules so the sitemap endpoint is available.
		\flush_rewrite_rules();
	}
}
