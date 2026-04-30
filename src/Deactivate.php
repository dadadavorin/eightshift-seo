<?php

/**
 * The file that defines actions on plugin deactivation.
 *
 * @package EightshiftSeo
 */

declare(strict_types=1);

namespace EightshiftSeo;

use EightshiftSeo\BotInsights\BotCounters;
use EightshiftSeoVendor\EightshiftLibs\Plugin\HasDeactivationInterface;

/**
 * The plugin deactivation class.
 */
class Deactivate implements HasDeactivationInterface
{
	/**
	 * Deactivate the plugin.
	 *
	 * @return void
	 */
	public function deactivate(): void
	{
		\flush_rewrite_rules();

		// Stop the bot counters prune cron — table itself is preserved across
		// deactivations and only removed during uninstall.
		$scheduled = \wp_next_scheduled(BotCounters::PRUNE_HOOK);
		if ($scheduled) {
			\wp_unschedule_event($scheduled, BotCounters::PRUNE_HOOK);
		}
	}
}
