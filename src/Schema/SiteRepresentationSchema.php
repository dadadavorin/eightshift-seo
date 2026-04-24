<?php

/**
 * The file that outputs Organization / Person JSON-LD on the homepage.
 *
 * @package EightshiftSeo\Schema
 */

declare(strict_types=1);

namespace EightshiftSeo\Schema;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * SiteRepresentationSchema class — emits Organization or Person JSON-LD on the
 * homepage, with sameAs pointing to configured social profiles.
 *
 * Only emits on is_front_page() or is_home() to avoid duplicating the site
 * entity across every page. Per-project projects can reshape the payload via
 * the es_seo_site_representation_schema filter, or suppress it by returning
 * an empty array.
 */
class SiteRepresentationSchema implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('wp_head', [$this, 'outputRepresentationSchema'], 12);
	}

	/**
	 * Build and output the Organization / Person JSON-LD script tag.
	 *
	 * @return void
	 */
	public function outputRepresentationSchema(): void
	{
		if (!\is_front_page() && !\is_home()) {
			return;
		}

		$schema = $this->buildSchema();

		$schema = \apply_filters(Options::getFilter('siteRepresentationSchema'), $schema);

		if (empty($schema) || !\is_array($schema)) {
			return;
		}

		echo '<script type="application/ld+json">' . \wp_json_encode($schema, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
	}

	/**
	 * Build the JSON-LD payload for the configured representation.
	 *
	 * @return array<string, mixed>
	 */
	private function buildSchema(): array
	{
		$type = (string) Options::getOption(['siteRepresentation', 'type']) ?: 'organization';
		$homeUrl = \home_url('/');
		$sameAs = $this->collectSameAs();

		if ($type === 'person') {
			$userId = (int) Options::getOption(['siteRepresentation', 'personId']);
			if ($userId <= 0) {
				return [];
			}

			$user = \get_userdata($userId);
			if (!$user) {
				return [];
			}

			$schema = [
				'@context' => 'https://schema.org',
				'@type'    => 'Person',
				'name'     => $user->display_name,
				'url'      => $homeUrl,
			];

			$avatarUrl = \get_avatar_url($userId, ['size' => 512]);
			if (!empty($avatarUrl)) {
				$schema['image'] = $avatarUrl;
			}

			if (!empty($sameAs)) {
				$schema['sameAs'] = $sameAs;
			}

			return $schema;
		}

		// Organization.
		$name = (string) Options::getOption(['siteRepresentation', 'name']);
		if ($name === '') {
			$name = (string) \get_bloginfo('name');
		}

		$schema = [
			'@context' => 'https://schema.org',
			'@type'    => 'Organization',
			'name'     => $name,
			'url'      => $homeUrl,
		];

		$logoId = (int) Options::getOption(['siteRepresentation', 'logo']);
		if ($logoId > 0) {
			$imageData = \wp_get_attachment_image_src($logoId, 'full');
			if ($imageData) {
				$schema['logo'] = [
					'@type'  => 'ImageObject',
					'url'    => $imageData[0],
					'width'  => (int) $imageData[1],
					'height' => (int) $imageData[2],
				];
			}
		}

		if (!empty($sameAs)) {
			$schema['sameAs'] = $sameAs;
		}

		return $schema;
	}

	/**
	 * Collect all non-empty social profile URLs into a single sameAs array.
	 *
	 * @return array<int, string>
	 */
	private function collectSameAs(): array
	{
		$social = Options::getOption(['siteRepresentation', 'social']);
		if (!\is_array($social)) {
			return [];
		}

		$urls = [];

		foreach (['facebook', 'instagram', 'linkedin', 'youtube', 'twitter', 'github', 'wikipedia'] as $key) {
			$url = isset($social[$key]) ? \trim((string) $social[$key]) : '';
			if ($url !== '') {
				$urls[] = $url;
			}
		}

		if (isset($social['other']) && \is_array($social['other'])) {
			foreach ($social['other'] as $url) {
				$url = \trim((string) $url);
				if ($url !== '') {
					$urls[] = $url;
				}
			}
		}

		return \array_values(\array_unique($urls));
	}
}
