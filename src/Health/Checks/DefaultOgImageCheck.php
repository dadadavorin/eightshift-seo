<?php

/**
 * @package EightshiftSeo\Health\Checks
 */

declare(strict_types=1);

namespace EightshiftSeo\Health\Checks;

use EightshiftSeo\Health\HealthCheckInterface;
use EightshiftSeo\Options\Options;

class DefaultOgImageCheck implements HealthCheckInterface
{
	public function getId(): string
	{
		return 'default_og_image';
	}

	public function getLabel(): string
	{
		return \__('Default OG image', 'eightshift-seo');
	}

	public function run(): array
	{
		$imageId = (int) Options::getOption(['defaultOgImage']);

		if ($imageId > 0) {
			return ['status' => 'ok', 'message' => \__('Default OG image is configured.', 'eightshift-seo'), 'actionUrl' => ''];
		}

		return [
			'status'    => 'fail',
			'message'   => \__('No default OG image set. Posts without a featured image will have no social preview image.', 'eightshift-seo'),
			'actionUrl' => \admin_url('admin.php?page=es-seo-settings#general'),
		];
	}
}
