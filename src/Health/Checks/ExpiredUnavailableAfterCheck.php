<?php

/**
 * @package EightshiftSeo\Health\Checks
 */

declare(strict_types=1);

namespace EightshiftSeo\Health\Checks;

use EightshiftSeo\Health\HealthCheckInterface;
use EightshiftSeo\Options\Options;

class ExpiredUnavailableAfterCheck implements HealthCheckInterface
{
	public function getId(): string
	{
		return 'expired_unavailable_after';
	}

	public function getLabel(): string
	{
		return \__('Expired unavailable_after directives', 'eightshift-seo');
	}

	public function run(): array
	{
		$metaKey = Options::getMetaKey('unavailableAfter');
		$now     = \gmdate('Y-m-d\TH:i:s\Z');

		$count = (int) (new \WP_Query([
			'post_type'      => Options::getPublicPostTypes(),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
			'meta_query'     => [
				[
					'key'     => $metaKey,
					'value'   => $now,
					'compare' => '<',
					'type'    => 'CHAR',
				],
			],
		]))->found_posts;

		if ($count === 0) {
			return ['status' => 'ok', 'message' => \__('No published posts have an expired unavailable_after date.', 'eightshift-seo'), 'actionUrl' => ''];
		}

		return [
			'status'    => 'warn',
			'message'   => \sprintf(
				/* translators: %d: number of posts */
				\__('%d post(s) have an unavailable_after date in the past. Search engines may have stopped indexing them.', 'eightshift-seo'),
				$count
			),
			'actionUrl' => \admin_url('edit.php'),
		];
	}
}
