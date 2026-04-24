<?php

/**
 * @package EightshiftSeo\Health\Checks
 */

declare(strict_types=1);

namespace EightshiftSeo\Health\Checks;

use EightshiftSeo\Health\HealthCheckInterface;
use EightshiftSeo\Options\Options;

class HomepageTitleTemplateCheck implements HealthCheckInterface
{
	public function getId(): string
	{
		return 'homepage_title_template';
	}

	public function getLabel(): string
	{
		return \__('Homepage title template', 'eightshift-seo');
	}

	public function run(): array
	{
		$template = (string) Options::getOption(['titleTemplates', 'page']);
		$tagline  = (string) \get_bloginfo('description');

		if ($template !== '' || $tagline !== '') {
			return ['status' => 'ok', 'message' => \__('Homepage has a title template or site tagline set.', 'eightshift-seo'), 'actionUrl' => ''];
		}

		return [
			'status'    => 'warn',
			'message'   => \__('No title template or site tagline configured for the homepage.', 'eightshift-seo'),
			'actionUrl' => \admin_url('admin.php?page=es-seo-settings#defaults'),
		];
	}
}
