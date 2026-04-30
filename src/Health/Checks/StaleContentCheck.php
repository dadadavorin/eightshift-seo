<?php

/**
 * @package EightshiftSeo\Health\Checks
 */

declare(strict_types=1);

namespace EightshiftSeo\Health\Checks;

use EightshiftSeo\Health\HealthCheckInterface;
use EightshiftSeo\Options\Options;

/**
 * StaleContentCheck — surfaces published posts whose dateModified is older
 * than the configurable freshness staleness threshold (default: 365 days).
 *
 * Stale posts are a GEO signal: AI engines and search crawlers down-rank
 * older content. This check lists the top 20 oldest unmodified posts so
 * editors can prioritise reviewing or refreshing them.
 */
class StaleContentCheck implements HealthCheckInterface
{
	private const SAMPLE_SIZE = 20;

	public function getId(): string
	{
		return 'stale_content';
	}

	public function getLabel(): string
	{
		return \__('Content freshness', 'eightshift-seo');
	}

	public function run(): array
	{
		$thresholdDays = (int) Options::getOption(['freshness', 'stalenessThresholdDays']);
		if ($thresholdDays <= 0) {
			$thresholdDays = 365;
		}

		$cutoff = \gmdate('Y-m-d H:i:s', \strtotime("-{$thresholdDays} days") ?: \time());

		$posts = \get_posts([
			'post_type'      => Options::getPublicPostTypes(),
			'post_status'    => 'publish',
			'posts_per_page' => self::SAMPLE_SIZE,
			'orderby'        => 'modified',
			'order'          => 'ASC',
			'date_query'     => [
				[
					'column' => 'post_modified_gmt',
					'before' => $cutoff,
				],
			],
			'fields'         => 'ids',
		]);

		if (empty($posts)) {
			return [
				'status'    => 'ok',
				'message'   => \sprintf(
					/* translators: %d: staleness threshold in days */
					\__('No published content older than %d days needs review.', 'eightshift-seo'),
					$thresholdDays
				),
				'actionUrl' => '',
			];
		}

		return [
			'status'    => 'warn',
			'message'   => \sprintf(
				/* translators: %1$d: number of stale posts, %2$d: staleness threshold in days */
				\__('%1$d posts have not been updated in %2$d+ days. Refresh or mark as reviewed to keep freshness signals current.', 'eightshift-seo'),
				\count($posts),
				$thresholdDays
			),
			'actionUrl' => \admin_url('edit.php?orderby=modified&order=asc'),
		];
	}
}
