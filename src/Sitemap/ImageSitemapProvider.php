<?php

/**
 * The file that registers the image sitemap provider.
 *
 * @package EightshiftSeo\Sitemap
 */

declare(strict_types=1);

namespace EightshiftSeo\Sitemap;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * ImageSitemapProvider class — registers a custom WP_Sitemaps_Provider that
 * generates `/wp-sitemap-es-seo-images-1.xml` containing images found in
 * published posts (featured image + inline content images).
 *
 * Respects the `settings.images.includeSitemap` toggle (default: true) and
 * the `settings.sitemap.excludedPostTypes` exclusion list.
 *
 * Uses the WP Sitemaps API (WP 5.5+) for fully standards-compliant output.
 */
class ImageSitemapProvider implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('init', [$this, 'registerProvider'], 20);
	}

	/**
	 * Register the custom provider with the WP sitemaps registry.
	 *
	 * @return void
	 */
	public function registerProvider(): void
	{
		if (!Options::getOptionChecked(['images', 'includeSitemap'])) {
			return;
		}

		\add_filter('wp_sitemaps_add_provider', [$this, 'addImageProvider'], 10, 2);
	}

	/**
	 * Inject our image provider alongside the built-in providers.
	 *
	 * @param \WP_Sitemaps_Provider|false $provider Provider instance.
	 * @param string                      $name     Provider name.
	 *
	 * @return \WP_Sitemaps_Provider|false
	 */
	public function addImageProvider($provider, string $name)
	{
		if ($name !== 'posts') {
			return $provider;
		}

		// Register after posts provider is confirmed present.
		\add_action('wp_sitemaps_init', [$this, 'doRegisterProvider']);

		return $provider;
	}

	/**
	 * Register the image provider on wp_sitemaps_init.
	 *
	 * @return void
	 */
	public function doRegisterProvider(): void
	{
		global $wp_sitemaps;

		if (!isset($wp_sitemaps) || !method_exists($wp_sitemaps, 'registry')) {
			return;
		}

		$wp_sitemaps->registry->add_provider('es-seo-images', new EsSeoImageProvider());
	}
}

/**
 * EsSeoImageProvider — the actual WP_Sitemaps_Provider implementation.
 *
 * Each image URL gets its own <url> entry so search engines can discover and
 * index images independently.
 */
class EsSeoImageProvider extends \WP_Sitemaps_Provider // phpcs:ignore
{
	/**
	 * Provider name.
	 *
	 * @var string
	 */
	protected $name = 'es-seo-images';

	public function __construct()
	{
		$this->object_type = 'image';
	}

	/**
	 * Return the list of supported object subtypes (post types).
	 *
	 * @return array<string, object>
	 */
	public function get_object_subtypes(): array
	{
		$postTypes = \get_post_types(['public' => true], 'objects');
		unset($postTypes['attachment']);

		$excluded = Options::getOption(['sitemap', 'excludedPostTypes']);
		if (\is_array($excluded)) {
			foreach ($excluded as $slug) {
				unset($postTypes[$slug]);
			}
		}

		return $postTypes;
	}

	/**
	 * Return the URL list for a given page of images.
	 *
	 * @param int    $pageNum  Page number (1-based).
	 * @param string $objectSubtype Post type slug.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function get_url_list($pageNum, $objectSubtype = ''): array
	{
		$posts = $this->queryPosts($pageNum, $objectSubtype);

		if (empty($posts)) {
			return [];
		}

		$urlList = [];

		foreach ($posts as $post) {
			$images = $this->collectImages($post);

			foreach ($images as $imageUrl) {
				$urlList[] = ['loc' => $imageUrl];
			}
		}

		// Allow per-project overrides.
		return \apply_filters(Options::getFilter('imageSitemapEntry'), $urlList, $objectSubtype);
	}

	/**
	 * Return the total number of pages for pagination.
	 *
	 * @param string $objectSubtype Post type slug.
	 *
	 * @return int
	 */
	public function get_max_num_pages($objectSubtype = ''): int
	{
		$postsPerPage = \wp_sitemaps_get_max_urls($this->object_type);

		$query = new \WP_Query([
			'post_type'      => $objectSubtype ?: 'post',
			'posts_per_page' => $postsPerPage,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'no_found_rows'  => false,
			'meta_query'     => $this->noindexExclusionQuery(),
		]);

		return (int) ceil($query->found_posts / $postsPerPage);
	}

	/**
	 * Run a paginated post query.
	 *
	 * @param int    $pageNum       Page number.
	 * @param string $objectSubtype Post type.
	 *
	 * @return array<\WP_Post>
	 */
	private function queryPosts(int $pageNum, string $objectSubtype): array
	{
		$postsPerPage = \wp_sitemaps_get_max_urls($this->object_type);

		$query = new \WP_Query([
			'post_type'      => $objectSubtype ?: 'post',
			'posts_per_page' => $postsPerPage,
			'paged'          => $pageNum,
			'post_status'    => 'publish',
			'no_found_rows'  => true,
			'meta_query'     => $this->noindexExclusionQuery(),
		]);

		return $query->posts;
	}

	/**
	 * Collect all image URLs from a post (featured image + content images).
	 *
	 * @param \WP_Post $post The post object.
	 *
	 * @return array<string>
	 */
	private function collectImages(\WP_Post $post): array
	{
		$images = [];

		// Featured image.
		$thumbId = (int) \get_post_thumbnail_id($post->ID);
		if ($thumbId > 0) {
			$src = \wp_get_attachment_image_url($thumbId, 'full');
			if (\is_string($src) && $src !== '') {
				$images[] = $src;
			}
		}

		// Inline content images — parse src attributes.
		$content = (string) $post->post_content;
		if ($content !== '') {
			\preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/', $content, $matches);
			foreach ($matches[1] ?? [] as $src) {
				$src = \esc_url_raw($src);
				if ($src !== '' && !\in_array($src, $images, true)) {
					$images[] = $src;
				}
			}
		}

		return $images;
	}

	/**
	 * Build meta_query to exclude noindexed posts.
	 *
	 * @return array<mixed>
	 */
	private function noindexExclusionQuery(): array
	{
		$noindexKey = Options::getMetaKey('noindex');

		if (empty($noindexKey)) {
			return [];
		}

		return [
			'relation' => 'OR',
			[
				'key'     => $noindexKey,
				'compare' => 'NOT EXISTS',
			],
			[
				'key'     => $noindexKey,
				'value'   => '1',
				'compare' => '!=',
			],
		];
	}
}
