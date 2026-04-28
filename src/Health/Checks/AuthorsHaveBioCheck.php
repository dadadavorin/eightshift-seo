<?php

/**
 * @package EightshiftSeo\Health\Checks
 */

declare(strict_types=1);

namespace EightshiftSeo\Health\Checks;

use EightshiftSeo\Health\HealthCheckInterface;

/**
 * AuthorsHaveBioCheck — counts users with published posts who are missing
 * E-E-A-T signals (WP bio or es_seo_author_same_as).
 *
 * Only checks users who have at least one published post so editors without
 * author content do not trigger false positives.
 */
class AuthorsHaveBioCheck implements HealthCheckInterface
{
	public function getId(): string
	{
		return 'authors_have_bio';
	}

	public function getLabel(): string
	{
		return \__('Author E-E-A-T profiles', 'eightshift-seo');
	}

	public function run(): array
	{
		$authors = \get_users([
			'has_published_posts' => true,
			'fields'              => ['ID', 'description'],
			'number'              => 50, // sample cap to keep the check fast
		]);

		if (empty($authors)) {
			return [
				'status'    => 'ok',
				'message'   => \__('No authors with published posts found.', 'eightshift-seo'),
				'actionUrl' => '',
			];
		}

		$incomplete = [];

		foreach ($authors as $author) {
			$bio    = \trim((string) ($author->description ?? ''));
			$sameAs = (array) \get_user_meta($author->ID, 'es_seo_author_same_as', true);
			$sameAs = \array_filter($sameAs);

			if ($bio === '' || empty($sameAs)) {
				$incomplete[] = $author->ID;
			}
		}

		$total      = \count($authors);
		$incomplete = \count($incomplete);

		if ($incomplete === 0) {
			return [
				'status'    => 'ok',
				'message'   => \sprintf(
					/* translators: %d: number of authors */
					\__('All %d authors with published posts have bio and sameAs profiles set.', 'eightshift-seo'),
					$total
				),
				'actionUrl' => '',
			];
		}

		return [
			'status'    => 'warn',
			'message'   => \sprintf(
				/* translators: %1$d: incomplete, %2$d: total */
				\__('%1$d of %2$d authors are missing a bio or social profiles (sameAs). These reduce AI citation signals.', 'eightshift-seo'),
				$incomplete,
				$total
			),
			'actionUrl' => \admin_url('users.php'),
		];
	}
}
