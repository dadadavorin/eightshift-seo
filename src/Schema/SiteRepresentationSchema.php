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
		// Contribute to the unified @graph instead of emitting a standalone script.
		// Priority 10 so the Organization / Person node is present before Article
		// (priority 30) tries to cross-reference it by @id.
		\add_filter(Options::getFilter('schemaGraph'), [$this, 'addRepresentationNode'], 10, 2);
	}

	/**
	 * Contribute an Organization or Person node to the schema graph.
	 *
	 * Emits on every page (not only the homepage) so that Article nodes on
	 * singular posts can cross-reference the publisher by @id. The @id is
	 * globally stable, so de-duplication in GraphEmitter keeps a single node.
	 *
	 * @param array<int, array<string, mixed>> $graph   Current graph nodes.
	 * @param array<string, mixed>             $context Request context from GraphEmitter.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function addRepresentationNode(array $graph, array $context): array
	{
		// WebSite node — always emitted so WebPage/Article isPartOf references resolve.
		$graph[] = $this->buildWebSiteNode();

		$node = $this->buildSchema();
		$node = \apply_filters(Options::getFilter('siteRepresentationSchema'), $node);

		if (!empty($node) && \is_array($node)) {
			$graph[] = $node;
		}

		return $graph;
	}

	/**
	 * Build the WebSite node referenced by WebPage and Article nodes via isPartOf.
	 *
	 * @return array<string, mixed>
	 */
	private function buildWebSiteNode(): array
	{
		$homeUrl = \home_url('/');
		$name    = (string) Options::getOption(['siteRepresentation', 'name']);
		if ($name === '') {
			$name = (string) \get_bloginfo('name');
		}

		return [
			'@type'       => 'WebSite',
			'@id'         => $homeUrl . '#website',
			'url'         => $homeUrl,
			'name'        => $name,
			'inLanguage'  => \get_bloginfo('language'),
		];
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
				'@type' => 'Person',
				'@id'   => $homeUrl . '#person-' . $userId,
				'name'  => $user->display_name,
				'url'   => $homeUrl,
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
			'@type' => 'Organization',
			'@id'   => $homeUrl . '#organization',
			'name'  => $name,
			'url'   => $homeUrl,
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
