<?php

/**
 * @package EightshiftSeo\Health\Checks
 */

declare(strict_types=1);

namespace EightshiftSeo\Health\Checks;

use EightshiftSeo\Health\HealthCheckInterface;
use EightshiftSeo\Options\Options;

/**
 * SiteRepresentationCompleteCheck — verifies that the Organization or Person
 * site representation has enough fields to produce a useful schema node.
 *
 * Passing criteria:
 *   - Organization: name + logo + at least 2 sameAs entries.
 *   - Person:       personId set + at least 1 sameAs entry.
 */
class SiteRepresentationCompleteCheck implements HealthCheckInterface
{
	public function getId(): string
	{
		return 'site_representation_complete';
	}

	public function getLabel(): string
	{
		return \__('Site representation schema', 'eightshift-seo');
	}

	public function run(): array
	{
		$type = (string) Options::getOption(['siteRepresentation', 'type']) ?: 'organization';

		if ($type === 'person') {
			return $this->checkPerson();
		}

		return $this->checkOrganization();
	}

	/**
	 * @return array{status: 'ok'|'warn'|'fail', message: string, actionUrl: string}
	 */
	private function checkOrganization(): array
	{
		$name   = (string) Options::getOption(['siteRepresentation', 'name']);
		$logo   = (int) Options::getOption(['siteRepresentation', 'logo']);
		$social = Options::getOption(['siteRepresentation', 'social']) ?? [];

		$sameAsCount = 0;
		if (\is_array($social)) {
			foreach ($social as $val) {
				if (\is_string($val) && \trim($val) !== '') {
					$sameAsCount++;
				}
			}
		}

		$missing = [];

		if ($name === '') {
			$missing[] = \__('organisation name', 'eightshift-seo');
		}

		if ($logo <= 0) {
			$missing[] = \__('logo', 'eightshift-seo');
		}

		if ($sameAsCount < 2) {
			$missing[] = \__('at least 2 social / sameAs URLs', 'eightshift-seo');
		}

		if (empty($missing)) {
			return [
				'status'    => 'ok',
				'message'   => \__('Organisation schema is complete with name, logo, and social profiles.', 'eightshift-seo'),
				'actionUrl' => '',
			];
		}

		return [
			'status'    => 'warn',
			'message'   => \sprintf(
				/* translators: %s: comma-separated list of missing items */
				\__('Organisation schema is missing: %s. This weakens the site entity signal for AI engines.', 'eightshift-seo'),
				\implode(', ', $missing)
			),
			'actionUrl' => \admin_url('admin.php?page=es-seo-settings#site'),
		];
	}

	/**
	 * @return array{status: 'ok'|'warn'|'fail', message: string, actionUrl: string}
	 */
	private function checkPerson(): array
	{
		$personId = (int) Options::getOption(['siteRepresentation', 'personId']);

		if ($personId <= 0) {
			return [
				'status'    => 'fail',
				'message'   => \__('Person representation: no user selected. The site entity schema will not emit.', 'eightshift-seo'),
				'actionUrl' => \admin_url('admin.php?page=es-seo-settings#site'),
			];
		}

		$sameAs = (array) \get_user_meta($personId, 'es_seo_author_same_as', true);
		$sameAs = \array_filter($sameAs);

		if (empty($sameAs)) {
			return [
				'status'    => 'warn',
				'message'   => \__('Person representation has no sameAs profiles. Add social/authority URLs in the user profile to strengthen E-E-A-T.', 'eightshift-seo'),
				'actionUrl' => \admin_url('user-edit.php?user_id=' . $personId),
			];
		}

		return [
			'status'    => 'ok',
			'message'   => \__('Person site representation is configured with sameAs profiles.', 'eightshift-seo'),
			'actionUrl' => '',
		];
	}
}
