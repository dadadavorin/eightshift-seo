<?php

/**
 * @package EightshiftSeo\Health\Checks
 */

declare(strict_types=1);

namespace EightshiftSeo\Health\Checks;

use EightshiftSeo\Health\HealthCheckInterface;

class SitemapReachableCheck implements HealthCheckInterface
{
	public function getId(): string
	{
		return 'sitemap_reachable';
	}

	public function getLabel(): string
	{
		return \__('XML sitemap reachable', 'eightshift-seo');
	}

	public function run(): array
	{
		$sitemapUrl = \home_url('/wp-sitemap.xml');

		$response = \wp_remote_head($sitemapUrl, ['timeout' => 5, 'redirection' => 3, 'sslverify' => false]);

		if (\is_wp_error($response)) {
			return [
				'status'    => 'warn',
				'message'   => \sprintf(
					/* translators: %s: error message */
					\__('Could not reach the sitemap: %s', 'eightshift-seo'),
					$response->get_error_message()
				),
				'actionUrl' => $sitemapUrl,
			];
		}

		$code = (int) \wp_remote_retrieve_response_code($response);

		if ($code === 200) {
			return ['status' => 'ok', 'message' => \__('XML sitemap is reachable (HTTP 200).', 'eightshift-seo'), 'actionUrl' => ''];
		}

		return [
			'status'    => 'warn',
			'message'   => \sprintf(
				/* translators: %d: HTTP status code */
				\__('Sitemap returned HTTP %d instead of 200.', 'eightshift-seo'),
				$code
			),
			'actionUrl' => $sitemapUrl,
		];
	}
}
