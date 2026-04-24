<?php

/**
 * The file that submits URLs to the IndexNow API.
 *
 * @package EightshiftSeo\Head
 */

declare(strict_types=1);

namespace EightshiftSeo\Head;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;
use WP_Post;

/**
 * IndexNowSubmit class — pings the IndexNow API when a post is published or updated.
 *
 * Submission is skipped when:
 *   - IndexNow is disabled in settings.
 *   - The post is noindexed.
 *   - The post type is not public or not supported.
 *
 * Uses wp_remote_post with a 5-second timeout. Errors are logged via error_log.
 */
class IndexNowSubmit implements ServiceInterface
{
	private const API_ENDPOINT = 'https://api.indexnow.org/IndexNow';

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('transition_post_status', [$this, 'onPostStatusTransition'], 10, 3);
	}

	/**
	 * Submit the URL to IndexNow when a post transitions to published.
	 *
	 * @param string  $newStatus New post status.
	 * @param string  $oldStatus Previous post status.
	 * @param WP_Post $post      The post object.
	 *
	 * @return void
	 */
	public function onPostStatusTransition(string $newStatus, string $oldStatus, WP_Post $post): void
	{
		if ($newStatus !== 'publish') {
			return;
		}

		if (!Options::getOptionChecked(['indexNow', 'enabled'])) {
			return;
		}

		$key = (string) Options::getOption(['indexNow', 'key']);
		if ($key === '') {
			return;
		}

		// Skip non-public or unsupported post types.
		$postTypeObj = \get_post_type_object($post->post_type);
		if (!$postTypeObj || !$postTypeObj->public) {
			return;
		}

		// Skip noindexed posts.
		$noindex = (bool) \get_post_meta($post->ID, Options::getMetaKey('noindex'), true);
		if ($noindex) {
			return;
		}

		$url = \get_permalink($post->ID);
		if (!\is_string($url) || $url === '') {
			return;
		}

		// Allow per-project override of the URL list before submission.
		$urls = \apply_filters(Options::getFilter('indexNowSubmit'), [$url], $post);
		if (!\is_array($urls) || empty($urls)) {
			return;
		}

		$this->submit($key, $urls);
	}

	/**
	 * Send URLs to the IndexNow API.
	 *
	 * @param string        $key  The IndexNow API key.
	 * @param array<string> $urls List of URLs to submit.
	 *
	 * @return void
	 */
	private function submit(string $key, array $urls): void
	{
		$host    = \wp_parse_url(\home_url('/'), PHP_URL_HOST);
		$keyUrl  = \home_url("/{$key}.txt");

		$payload = [
			'host'        => $host,
			'key'         => $key,
			'keyLocation' => $keyUrl,
			'urlList'     => \array_values(\array_filter($urls, 'is_string')),
		];

		$response = \wp_remote_post(
			self::API_ENDPOINT,
			[
				'body'    => \wp_json_encode($payload),
				'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
				'timeout' => 5,
			]
		);

		if (\is_wp_error($response)) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			\error_log('[Eightshift SEO] IndexNow submission failed: ' . $response->get_error_message());
			return;
		}

		$code = (int) \wp_remote_retrieve_response_code($response);

		if ($code < 200 || $code >= 300) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			\error_log("[Eightshift SEO] IndexNow returned HTTP {$code} for URLs: " . implode(', ', $urls));
		}
	}
}
