<?php

/**
 * The WP-CLI command for Eightshift SEO.
 *
 * @package EightshiftSeo\Cli
 */

declare(strict_types=1);

namespace EightshiftSeo\Cli;

use EightshiftSeo\Head\IndexNowKey;
use EightshiftSeo\Head\IndexNowSubmit;
use EightshiftSeo\Options\Options;

/**
 * SeoCommand — WP-CLI commands registered under `wp es-seo`.
 *
 * Registers itself via ServiceInterface::register() so the DI container
 * picks it up automatically. WP-CLI is detected via the WP_CLI constant.
 */
class SeoCommand implements \EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface
{
	/**
	 * Register the CLI command when WP-CLI is present.
	 *
	 * @return void
	 */
	public function register(): void
	{
		if (!\defined('WP_CLI') || !WP_CLI) {
			return;
		}

		\WP_CLI::add_command('es-seo', self::class);
	}

	/**
	 * Export settings to JSON.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<path>]
	 * : Write output to this file path instead of stdout.
	 *
	 * ## EXAMPLES
	 *
	 *     wp es-seo settings export
	 *     wp es-seo settings export --file=./es-seo-backup.json
	 *
	 * @subcommand settings export
	 * @param array<string>        $args       Positional arguments.
	 * @param array<string,string> $assocArgs  Named arguments.
	 *
	 * @return void
	 */
	public function settings_export(array $args, array $assocArgs): void // phpcs:ignore
	{
		$raw      = (string) \get_option(Options::getOptionsName(), '{}');
		$settings = \json_decode($raw, true);

		if (!\is_array($settings)) {
			\WP_CLI::error('Settings could not be parsed as JSON.');
			return;
		}

		$json = (string) \wp_json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

		if (isset($assocArgs['file'])) {
			$file = (string) $assocArgs['file'];
			\file_put_contents($file, $json); // phpcs:ignore WordPress.WP.AlternativeFunctions
			\WP_CLI::success("Settings exported to {$file}");
		} else {
			\WP_CLI::line($json);
		}
	}

	/**
	 * Import settings from a JSON file.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the JSON file to import.
	 *
	 * ## EXAMPLES
	 *
	 *     wp es-seo settings import ./es-seo-backup.json
	 *
	 * @subcommand settings import
	 * @param array<string>        $args       Positional arguments (file path at index 0).
	 * @param array<string,string> $assocArgs  Named arguments (unused).
	 *
	 * @return void
	 */
	public function settings_import(array $args, array $assocArgs): void // phpcs:ignore
	{
		$file = $args[0] ?? '';

		if ($file === '' || !\file_exists($file)) {
			\WP_CLI::error("File not found: {$file}");
			return;
		}

		$raw      = (string) \file_get_contents($file); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$settings = \json_decode($raw, true);

		if (!\is_array($settings)) {
			\WP_CLI::error('File does not contain valid JSON.');
			return;
		}

		// Strip any keys not present in the defaults to prevent injection.
		$defaults  = \json_decode(Options::getOptionsDefaultValue(), true);
		$sanitized = $this->intersectKeys($settings, \is_array($defaults) ? $defaults : []);

		\update_option(Options::getOptionsName(), \wp_json_encode($sanitized));
		\WP_CLI::success('Settings imported successfully.');
	}

	/**
	 * Set a meta field value across all posts of a given type.
	 *
	 * ## OPTIONS
	 *
	 * --post_type=<type>
	 * : The post type to target (e.g. post, page).
	 *
	 * --field=<field>
	 * : The logical SEO field name (e.g. noindex, title, description).
	 *
	 * --value=<value>
	 * : The value to set.
	 *
	 * [--overwrite]
	 * : Overwrite existing values (default: skip posts that already have a value).
	 *
	 * [--dry-run]
	 * : Preview the operation without making changes.
	 *
	 * ## EXAMPLES
	 *
	 *     wp es-seo meta set --post_type=page --field=noindex --value=1 --dry-run
	 *
	 * @subcommand meta set
	 * @param array<string>        $args       Positional arguments.
	 * @param array<string,string> $assocArgs  Named arguments.
	 *
	 * @return void
	 */
	public function meta_set(array $args, array $assocArgs): void // phpcs:ignore
	{
		$postType  = $assocArgs['post_type'] ?? '';
		$fieldKey  = $assocArgs['field'] ?? '';
		$value     = $assocArgs['value'] ?? '';
		$overwrite = isset($assocArgs['overwrite']);
		$dryRun    = isset($assocArgs['dry-run']);

		if ($postType === '' || $fieldKey === '') {
			\WP_CLI::error('--post_type and --field are required.');
			return;
		}

		$metaKey = Options::getMetaKey($fieldKey);
		if ($metaKey === '') {
			\WP_CLI::error("Unknown field: {$fieldKey}");
			return;
		}

		$posts = \get_posts([
			'post_type'      => $postType,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		]);

		$updated = 0;
		$skipped = 0;

		foreach ($posts as $postId) {
			$existing = \get_post_meta((int) $postId, $metaKey, true);

			if (!$overwrite && $existing !== '' && $existing !== false) {
				$skipped++;
				continue;
			}

			if (!$dryRun) {
				\update_post_meta((int) $postId, $metaKey, $value);
			}

			$updated++;
		}

		$prefix = $dryRun ? '[DRY RUN] Would update' : 'Updated';
		\WP_CLI::success("{$prefix} {$updated} posts, skipped {$skipped}.");
	}

	/**
	 * Remove a meta field from all posts of a given type.
	 *
	 * ## OPTIONS
	 *
	 * --post_type=<type>
	 * : The post type to target.
	 *
	 * --field=<field>
	 * : The logical SEO field name.
	 *
	 * [--dry-run]
	 * : Preview without making changes.
	 *
	 * ## EXAMPLES
	 *
	 *     wp es-seo meta clear --post_type=post --field=noindex --dry-run
	 *
	 * @subcommand meta clear
	 * @param array<string>        $args      Positional arguments.
	 * @param array<string,string> $assocArgs Named arguments.
	 *
	 * @return void
	 */
	public function meta_clear(array $args, array $assocArgs): void // phpcs:ignore
	{
		$postType = $assocArgs['post_type'] ?? '';
		$fieldKey = $assocArgs['field'] ?? '';
		$dryRun   = isset($assocArgs['dry-run']);

		if ($postType === '' || $fieldKey === '') {
			\WP_CLI::error('--post_type and --field are required.');
			return;
		}

		$metaKey = Options::getMetaKey($fieldKey);
		if ($metaKey === '') {
			\WP_CLI::error("Unknown field: {$fieldKey}");
			return;
		}

		$posts   = \get_posts([
			'post_type'      => $postType,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => $metaKey,
		]);

		$cleared = 0;

		foreach ($posts as $postId) {
			if (!$dryRun) {
				\delete_post_meta((int) $postId, $metaKey);
			}
			$cleared++;
		}

		$prefix = $dryRun ? '[DRY RUN] Would clear' : 'Cleared';
		\WP_CLI::success("{$prefix} {$cleared} posts.");
	}

	/**
	 * Ping IndexNow for all published, indexable URLs.
	 *
	 * ## OPTIONS
	 *
	 * [--post_type=<type>]
	 * : Limit to a specific post type (default: all public post types).
	 *
	 * ## EXAMPLES
	 *
	 *     wp es-seo sitemap ping
	 *     wp es-seo sitemap ping --post_type=post
	 *
	 * @subcommand sitemap ping
	 * @param array<string>        $args      Positional arguments.
	 * @param array<string,string> $assocArgs Named arguments.
	 *
	 * @return void
	 */
	public function sitemap_ping(array $args, array $assocArgs): void // phpcs:ignore
	{
		if (!Options::getOptionChecked(['indexNow', 'enabled'])) {
			\WP_CLI::warning('IndexNow is disabled. Enable it in SEO settings first.');
			return;
		}

		$key = (string) Options::getOption(['indexNow', 'key']);
		if ($key === '') {
			\WP_CLI::error('No IndexNow key configured.');
			return;
		}

		$postType   = $assocArgs['post_type'] ?? '';
		$postTypes  = $postType !== '' ? [$postType] : Options::getPublicPostTypes();
		$noindexKey = Options::getMetaKey('noindex');

		$allUrls = [];

		foreach ($postTypes as $type) {
			$posts = \get_posts([
				'post_type'      => $type,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [
					'relation' => 'OR',
					['key' => $noindexKey, 'compare' => 'NOT EXISTS'],
					['key' => $noindexKey, 'value' => '1', 'compare' => '!='],
				],
			]);

			foreach ($posts as $postId) {
				$url = \get_permalink((int) $postId);
				if (\is_string($url) && $url !== '') {
					$allUrls[] = $url;
				}
			}
		}

		if (empty($allUrls)) {
			\WP_CLI::warning('No indexable URLs found.');
			return;
		}

		// Send in batches of 100 (IndexNow limit per request).
		$batches = \array_chunk($allUrls, 100);
		$submitter = new IndexNowSubmit();

		foreach ($batches as $batch) {
			$host   = \wp_parse_url(\home_url('/'), PHP_URL_HOST);
			$keyUrl = \home_url("/{$key}.txt");

			$payload = [
				'host'        => $host,
				'key'         => $key,
				'keyLocation' => $keyUrl,
				'urlList'     => $batch,
			];

			$response = \wp_remote_post(
				'https://api.indexnow.org/IndexNow',
				[
					'body'    => \wp_json_encode($payload),
					'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
					'timeout' => 10,
				]
			);

			if (\is_wp_error($response)) {
				\WP_CLI::warning('Ping failed: ' . $response->get_error_message());
			} else {
				$code = (int) \wp_remote_retrieve_response_code($response);
				\WP_CLI::line("Batch of " . count($batch) . " URLs: HTTP {$code}");
			}

			// Slight rate-limiting between batches.
			if (count($batches) > 1) {
				\sleep(1);
			}
		}

		\WP_CLI::success("Pinged IndexNow for " . count($allUrls) . " URLs.");
	}

	/**
	 * Regenerate the llms.txt transient.
	 *
	 * ## EXAMPLES
	 *
	 *     wp es-seo llms regenerate
	 *
	 * @subcommand llms regenerate
	 * @param array<string>        $args      Positional arguments.
	 * @param array<string,string> $assocArgs Named arguments.
	 *
	 * @return void
	 */
	public function llms_regenerate(array $args, array $assocArgs): void // phpcs:ignore
	{
		\delete_transient('es_seo_llms_txt');
		// Force regeneration by instantiating the generator.
		$gen     = new \EightshiftSeo\Llms\LlmsTxtGenerator();
		$content = $gen->generate();
		\set_transient('es_seo_llms_txt', $content, \DAY_IN_SECONDS);
		\WP_CLI::success('llms.txt regenerated (' . \strlen($content) . ' bytes).');
	}

	/**
	 * Preview llms.txt output.
	 *
	 * ## EXAMPLES
	 *
	 *     wp es-seo llms preview
	 *
	 * @subcommand llms preview
	 * @param array<string>        $args      Positional arguments.
	 * @param array<string,string> $assocArgs Named arguments.
	 *
	 * @return void
	 */
	public function llms_preview(array $args, array $assocArgs): void // phpcs:ignore
	{
		$gen = new \EightshiftSeo\Llms\LlmsTxtGenerator();
		\WP_CLI::line($gen->generate());
	}

	/**
	 * Print the LLM sitemap URL list, one per line (Phase 8).
	 *
	 * ## EXAMPLES
	 *
	 *     wp es-seo sitemap llm
	 *
	 * @subcommand sitemap llm
	 * @param array<string>        $args      Positional arguments.
	 * @param array<string,string> $assocArgs Named arguments.
	 *
	 * @return void
	 */
	public function sitemap_llm(array $args, array $assocArgs): void // phpcs:ignore
	{
		$provider = new \EightshiftSeo\Sitemap\LlmSitemapProvider();
		\WP_CLI::line($provider->generate());
	}

	/**
	 * Regenerate cached sitemap variants (Phase 8 — LLM sitemap).
	 *
	 * ## EXAMPLES
	 *
	 *     wp es-seo sitemap regenerate
	 *
	 * @subcommand sitemap regenerate
	 * @param array<string>        $args      Positional arguments.
	 * @param array<string,string> $assocArgs Named arguments.
	 *
	 * @return void
	 */
	public function sitemap_regenerate(array $args, array $assocArgs): void // phpcs:ignore
	{
		$provider = new \EightshiftSeo\Sitemap\LlmSitemapProvider();
		$provider->invalidateCache();
		$content = $provider->generate();
		\WP_CLI::success('LLM sitemap regenerated (' . \strlen($content) . ' bytes).');
	}

	/**
	 * Recursively keep only the keys present in $defaults.
	 *
	 * @param array<mixed> $input    Input settings array.
	 * @param array<mixed> $defaults Default settings array.
	 *
	 * @return array<mixed>
	 */
	private function intersectKeys(array $input, array $defaults): array
	{
		$result = [];

		foreach ($defaults as $key => $defaultValue) {
			if (!\array_key_exists($key, $input)) {
				continue;
			}

			$inputValue = $input[$key];

			if (\is_array($defaultValue) && \is_array($inputValue)) {
				$result[$key] = $this->intersectKeys($inputValue, $defaultValue);
			} else {
				$result[$key] = $inputValue;
			}
		}

		return $result;
	}
}
