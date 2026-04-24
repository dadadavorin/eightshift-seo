<?php

/**
 * The file that serves the IndexNow key verification file.
 *
 * @package EightshiftSeo\Head
 */

declare(strict_types=1);

namespace EightshiftSeo\Head;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * IndexNowKey class — serves the IndexNow API key at /{key}.txt and generates
 * the key on first use if none is stored.
 *
 * The key is persisted inside the es-seo-settings option under indexNow.key.
 * Key generation happens lazily on the first admin_init after activation so
 * the rewrite rule is in place before the key is publicly needed.
 */
class IndexNowKey implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('init', [$this, 'addRewriteRule'], 5);
		\add_action('template_redirect', [$this, 'serveKeyFile'], 1);
		\add_action('admin_init', [$this, 'maybeGenerateKey']);
		\add_action('rest_api_init', [$this, 'registerRestRoute']);
	}

	/**
	 * Register the REST endpoint for regenerating the IndexNow key.
	 *
	 * @return void
	 */
	public function registerRestRoute(): void
	{
		\register_rest_route(
			'es-seo/v1',
			'/indexnow-regenerate',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => static function (): \WP_REST_Response {
					$key = self::regenerateKey();
					return new \WP_REST_Response(['key' => $key], 200);
				},
				'permission_callback' => static fn() => \current_user_can('manage_options'),
			]
		);
	}

	/**
	 * Register a rewrite rule for /{key}.txt so it hits WP instead of 404.
	 *
	 * @return void
	 */
	public function addRewriteRule(): void
	{
		\add_rewrite_rule(
			'^([a-f0-9]{8,64})\.txt$',
			'index.php?es_seo_indexnow_key=$matches[1]',
			'top'
		);
		\add_rewrite_tag('%es_seo_indexnow_key%', '([a-f0-9]{8,64})');
	}

	/**
	 * If the request matches our IndexNow key, serve the key as plain text.
	 *
	 * @return void
	 */
	public function serveKeyFile(): void
	{
		$requestedKey = \get_query_var('es_seo_indexnow_key');

		if (empty($requestedKey)) {
			return;
		}

		$storedKey = (string) Options::getOption(['indexNow', 'key']);

		if ($storedKey === '' || $requestedKey !== $storedKey) {
			return;
		}

		\header('Content-Type: text/plain; charset=utf-8');
		\header('Cache-Control: max-age=3600, public');
		echo \esc_html($storedKey);
		exit;
	}

	/**
	 * Generate and store a new IndexNow API key if none exists.
	 *
	 * @return void
	 */
	public function maybeGenerateKey(): void
	{
		if (!\current_user_can('manage_options')) {
			return;
		}

		$key = (string) Options::getOption(['indexNow', 'key']);

		if ($key !== '') {
			return;
		}

		$this->regenerateKey();
	}

	/**
	 * Generate a new 32-character hex key and persist it.
	 *
	 * @return string The new key.
	 */
	public static function regenerateKey(): string
	{
		$newKey = \bin2hex(\random_bytes(16)); // 32 hex chars.

		$optionName = Options::getOptionsName();
		$raw        = (string) \get_option($optionName, '{}');
		$settings   = \json_decode($raw, true);

		if (!\is_array($settings)) {
			$settings = [];
		}

		if (!isset($settings['indexNow']) || !\is_array($settings['indexNow'])) {
			$settings['indexNow'] = [];
		}

		$settings['indexNow']['key'] = $newKey;

		\update_option($optionName, \wp_json_encode($settings));

		// Flush rewrites so the new key endpoint is immediately reachable.
		\flush_rewrite_rules(false);

		return $newKey;
	}
}
