<?php

/**
 * @package EightshiftSeo\Health\Checks
 */

declare(strict_types=1);

namespace EightshiftSeo\Health\Checks;

use EightshiftSeo\Health\HealthCheckInterface;
use EightshiftSeo\Options\Options;

class AttachmentPagesIndexableCheck implements HealthCheckInterface
{
	public function getId(): string
	{
		return 'attachment_pages_indexable';
	}

	public function getLabel(): string
	{
		return \__('Attachment pages redirect', 'eightshift-seo');
	}

	public function run(): array
	{
		$mode = (string) Options::getOption(['attachmentRedirect']);

		if ($mode !== 'disabled') {
			return ['status' => 'ok', 'message' => \__('Attachment pages are redirected, preventing thin-content indexing.', 'eightshift-seo'), 'actionUrl' => ''];
		}

		return [
			'status'    => 'warn',
			'message'   => \__('Attachment page redirect is disabled. Search engines may index thin media pages.', 'eightshift-seo'),
			'actionUrl' => \admin_url('admin.php?page=es-seo-settings#advanced'),
		];
	}
}
