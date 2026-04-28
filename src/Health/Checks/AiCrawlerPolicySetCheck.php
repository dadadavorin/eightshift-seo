<?php

/**
 * @package EightshiftSeo\Health\Checks
 */

declare(strict_types=1);

namespace EightshiftSeo\Health\Checks;

use EightshiftSeo\Health\HealthCheckInterface;
use EightshiftSeo\Options\Options;

/**
 * AiCrawlerPolicySetCheck — verifies that the AI crawler governance feature
 * has been explicitly configured by the operator.
 *
 * A pass here simply means the feature is enabled in settings (the operator
 * opened the tab and acknowledged the defaults). It does not validate whether
 * any specific bot policy is set — that is intentional: the default "allow all"
 * is a valid and deliberate policy.
 */
class AiCrawlerPolicySetCheck implements HealthCheckInterface
{
	public function getId(): string
	{
		return 'ai_crawler_policy_set';
	}

	public function getLabel(): string
	{
		return \__('AI crawler policy', 'eightshift-seo');
	}

	public function run(): array
	{
		$enabled = (bool) Options::getOption(['aiCrawlers', 'enabled']);

		if ($enabled) {
			return [
				'status'    => 'ok',
				'message'   => \__('AI crawler governance is active. Bots not individually configured follow the default policy.', 'eightshift-seo'),
				'actionUrl' => '',
			];
		}

		return [
			'status'    => 'warn',
			'message'   => \__('AI crawler governance is disabled. Consider reviewing which AI bots may train on your content.', 'eightshift-seo'),
			'actionUrl' => \admin_url('admin.php?page=es-seo-settings#ai-crawlers'),
		];
	}
}
