<?php

/**
 * @package EightshiftSeo\Health\Checks
 */

declare(strict_types=1);

namespace EightshiftSeo\Health\Checks;

use EightshiftSeo\Health\HealthCheckInterface;
use EightshiftSeo\Options\Options;

/**
 * ArticleSchemaCoverageCheck — samples the 20 most recently modified published
 * posts and checks for the two fields that most impact Article schema quality:
 * a featured image and a meta description.
 *
 * Missing either field means the Article schema will emit without image/description,
 * reducing rich-result eligibility and AI citation probability.
 */
class ArticleSchemaCoverageCheck implements HealthCheckInterface
{
	private const SAMPLE_SIZE = 20;

	public function getId(): string
	{
		return 'article_schema_coverage';
	}

	public function getLabel(): string
	{
		return \__('Article schema completeness', 'eightshift-seo');
	}

	public function run(): array
	{
		$posts = \get_posts([
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => self::SAMPLE_SIZE,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'fields'         => 'ids',
		]);

		if (empty($posts)) {
			return [
				'status'    => 'ok',
				'message'   => \__('No published posts found to sample.', 'eightshift-seo'),
				'actionUrl' => '',
			];
		}

		$noImage       = 0;
		$noDescription = 0;

		foreach ($posts as $postId) {
			$postId = (int) $postId;

			if (!\has_post_thumbnail($postId)) {
				$noImage++;
			}

			$desc = (string) \get_post_meta($postId, Options::getMetaKey('description'), true);
			if ($desc === '') {
				$noDescription++;
			}
		}

		$total  = \count($posts);
		$issues = [];

		if ($noImage > 0) {
			$issues[] = \sprintf(
				/* translators: %1$d: count without image, %2$d: sample size */
				\__('%1$d / %2$d posts missing a featured image', 'eightshift-seo'),
				$noImage,
				$total
			);
		}

		if ($noDescription > 0) {
			$issues[] = \sprintf(
				/* translators: %1$d: count without description, %2$d: sample size */
				\__('%1$d / %2$d posts missing a meta description', 'eightshift-seo'),
				$noDescription,
				$total
			);
		}

		if (empty($issues)) {
			return [
				'status'    => 'ok',
				'message'   => \sprintf(
					/* translators: %d: sample size */
					\__('All %d sampled posts have a featured image and meta description.', 'eightshift-seo'),
					$total
				),
				'actionUrl' => '',
			];
		}

		return [
			'status'    => 'warn',
			'message'   => \sprintf(
				/* translators: %s: comma-separated issue list */
				\__('Article schema gaps in recent posts: %s. Incomplete nodes reduce rich-result eligibility.', 'eightshift-seo'),
				\implode('; ', $issues)
			),
			'actionUrl' => \admin_url('edit.php'),
		];
	}
}
