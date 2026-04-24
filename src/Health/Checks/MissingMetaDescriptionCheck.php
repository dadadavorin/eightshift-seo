<?php

/**
 * @package EightshiftSeo\Health\Checks
 */

declare(strict_types=1);

namespace EightshiftSeo\Health\Checks;

use EightshiftSeo\Health\HealthCheckInterface;
use EightshiftSeo\Options\Options;

class MissingMetaDescriptionCheck implements HealthCheckInterface
{
	public function getId(): string
	{
		return 'missing_meta_description';
	}

	public function getLabel(): string
	{
		return \__('Posts without meta description', 'eightshift-seo');
	}

	public function run(): array
	{
		$metaKey   = Options::getMetaKey('description');
		$noindexKey = Options::getMetaKey('noindex');

		// Count published posts that have no meta description and are not noindexed.
		$count = (int) (new \WP_Query([
			'post_type'      => Options::getPublicPostTypes(),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
			'meta_query'     => [
				'relation' => 'AND',
				[
					'relation' => 'OR',
					['key' => $noindexKey, 'compare' => 'NOT EXISTS'],
					['key' => $noindexKey, 'value' => '1', 'compare' => '!='],
				],
				[
					'relation' => 'OR',
					['key' => $metaKey, 'compare' => 'NOT EXISTS'],
					['key' => $metaKey, 'value' => '', 'compare' => '='],
				],
			],
		]))->found_posts;

		if ($count === 0) {
			return ['status' => 'ok', 'message' => \__('All public posts have a meta description.', 'eightshift-seo'), 'actionUrl' => ''];
		}

		return [
			'status'    => 'warn',
			'message'   => \sprintf(
				/* translators: %d: number of posts */
				\__('%d published post(s) are missing a meta description.', 'eightshift-seo'),
				$count
			),
			'actionUrl' => \admin_url('edit.php'),
		];
	}
}
