<?php

/**
 * @package EightshiftSeo\Health\Checks
 */

declare(strict_types=1);

namespace EightshiftSeo\Health\Checks;

use EightshiftSeo\Health\HealthCheckInterface;
use EightshiftSeo\Options\Options;

class VerificationConfiguredCheck implements HealthCheckInterface
{
	public function getId(): string
	{
		return 'verification_configured';
	}

	public function getLabel(): string
	{
		return \__('Webmaster verification', 'eightshift-seo');
	}

	public function run(): array
	{
		$webmaster = Options::getOption(['webmaster']);

		if (\is_array($webmaster)) {
			foreach ($webmaster as $code) {
				if (!empty($code)) {
					return ['status' => 'ok', 'message' => \__('At least one webmaster verification code is configured.', 'eightshift-seo'), 'actionUrl' => ''];
				}
			}
		}

		return [
			'status'    => 'warn',
			'message'   => \__('No webmaster verification codes configured. Consider adding Google Search Console verification.', 'eightshift-seo'),
			'actionUrl' => \admin_url('admin.php?page=es-seo-settings#general'),
		];
	}
}
