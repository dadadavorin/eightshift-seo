<?php

/**
 * The file that defines the project options class.
 *
 * @package EightshiftSeo\Options
 */

declare(strict_types=1);

namespace EightshiftSeo\Options;

use EightshiftSeoVendor\EightshiftLibs\Helpers\Helpers;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;
use Exception;

/**
 * The class that holds project options.
 */
class Options implements ServiceInterface
{
	/**
	 * Default value for boolean options when nothing is stored.
	 *
	 * @var bool
	 */
	public const OPTION_CHECKED_INITIAL = false;

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('init', [$this, 'registerOptions'], 20);

		// Prevent the plugin's post-meta keys from appearing in the Classic Editor
		// "Custom Fields" metabox. Gutenberg submits a hidden compatibility form for
		// classic metaboxes; if our keys are in that form they carry stale values that
		// overwrite what the REST API just saved. Marking them protected suppresses
		// them from that form without affecting REST API reads/writes.
		\add_filter('is_protected_meta', static function (bool $protected, string $metaKey): bool {
			return $protected || \str_starts_with($metaKey, 'es_seo_');
		}, 10, 2);
	}

	/**
	 * Register settings with WordPress and expose via REST API.
	 *
	 * The setting is stored as a JSON string in a single autoloaded wp_options row.
	 * Exposing it via show_in_rest allows the React admin to read/write via
	 * apiFetch({ path: '/wp/v2/settings' }) — no custom REST routes needed.
	 *
	 * @return void
	 */
	public function registerOptions(): void
	{
		\register_setting(
			'options',
			self::getOptionsName(),
			[
				'type'         => 'string',
				'description'  => 'Eightshift SEO settings (JSON-encoded)',
				'show_in_rest' => [
					'schema' => [
						'type'        => 'string',
						'description' => 'JSON-encoded SEO settings object',
					],
				],
				'default'      => self::getOptionsDefaultValue() ?: '{}',
				'sanitize_callback' => static function (mixed $value): string {
					if (!\is_string($value)) {
						return '{}';
					}
					// Validate it's well-formed JSON.
					$decoded = \json_decode($value, true);
					if (\json_last_error() !== \JSON_ERROR_NONE || !\is_array($decoded)) {
						return '{}';
					}
					return $value;
				},
			]
		);
	}

	/**
	 * Get option value by key path.
	 *
	 * Reads the JSON blob once per request and traverses by keys.
	 * Uses null sentinel so an empty options array is still cached properly.
	 *
	 * @param array<int, string> $keys Key path to traverse (e.g. ['sitemap', 'addToRobotsTxt']).
	 *
	 * @return mixed
	 */
	public static function getOption(array $keys): mixed
	{
		static $options = null;

		if ($options === null) {
			$data = \get_option(self::getOptionsName(), '{}');

			try {
				$options = Helpers::parseManifest(\is_string($data) ? $data : '{}');
			} catch (Exception $e) {
				$options = [];
			}
		}

		$initial = self::getInitialValue($keys);

		// Traverse the key path.
		$value = $options;
		foreach ($keys as $key) {
			if (!\is_array($value) || !\array_key_exists($key, $value)) {
				return $initial;
			}
			$value = $value[$key];
		}

		// Never coerce booleans — return them as-is.
		if (\is_bool($value)) {
			return $value;
		}

		// For integers (e.g. attachment IDs), 0 is valid.
		if (\is_int($value)) {
			return $value;
		}

		if (empty($value)) {
			return $initial;
		}

		return $value;
	}

	/**
	 * Get initial/default value for a key path from optionsDefaultValue in manifest.
	 *
	 * @param array<int, string> $keys Key path.
	 *
	 * @return mixed
	 */
	public static function getInitialValue(array $keys): mixed
	{
		$defaults = Helpers::getSettings()['optionsDefaultValue'] ?? [];

		$value = $defaults;
		foreach ($keys as $key) {
			if (!\is_array($value) || !\array_key_exists($key, $value)) {
				return self::OPTION_CHECKED_INITIAL;
			}
			$value = $value[$key];
		}

		return $value ?? self::OPTION_CHECKED_INITIAL;
	}

	/**
	 * Returns all project capabilities from the manifest.
	 *
	 * @return array<mixed>
	 */
	public static function getCaps(): array
	{
		return Helpers::getSettings()['caps'] ?? [];
	}

	/**
	 * Returns a specific project capability ID.
	 *
	 * @param string $key Key to get.
	 *
	 * @return string
	 */
	public static function getCap(string $key): string
	{
		return self::getCaps()[$key]['id'] ?? '';
	}

	/**
	 * Returns a project filter hook name.
	 *
	 * @param string $key Key to get.
	 *
	 * @return string
	 */
	public static function getFilter(string $key): string
	{
		return Helpers::getSettings()['filters'][$key] ?? '';
	}

	/**
	 * Returns a project meta key name.
	 *
	 * @param string $key Key to get.
	 *
	 * @return string
	 */
	public static function getMetaKey(string $key): string
	{
		return Helpers::getSettings()['meta'][$key] ?? '';
	}

	/**
	 * Returns the meta key name for a given term meta field key.
	 *
	 * @param string $key Logical key (e.g. 'title', 'noindex').
	 *
	 * @return string WP meta key string (e.g. 'es_seo_term_title').
	 */
	public static function getTermMetaKey(string $key): string
	{
		return Helpers::getSettings()['termMeta'][$key] ?? '';
	}

	/**
	 * Returns the admin page slug with the configured prefix.
	 *
	 * @param string $suffix Suffix to append (e.g. 'settings').
	 *
	 * @return string
	 */
	public static function getAdminPageSlug(string $suffix): string
	{
		return Helpers::getSettings()['adminPagesPrefix'] . $suffix;
	}

	/**
	 * Check if option value is truthy.
	 *
	 * @param array<int, string> $keys Key path.
	 *
	 * @return bool
	 */
	public static function getOptionChecked(array $keys): bool
	{
		return (bool) static::getOption($keys);
	}

	/**
	 * Returns the wp_options key used to store all SEO settings.
	 *
	 * @return string
	 */
	public static function getOptionsName(): string
	{
		return Helpers::getSettings()['optionsName'];
	}

	/**
	 * Returns the JSON-encoded default options blob from the manifest.
	 *
	 * @return string
	 */
	public static function getOptionsDefaultValue(): string
	{
		return \wp_json_encode(Helpers::getSettings()['optionsDefaultValue']) ?: '{}';
	}

	/**
	 * Get all public non-attachment post types.
	 *
	 * @param array<string> $exclude Additional post type slugs to exclude.
	 *
	 * @return array<string>
	 */
	public static function getPublicPostTypes(array $exclude = []): array
	{
		$postTypes = \get_post_types(['public' => true]);

		unset($postTypes['attachment']);

		foreach ($exclude as $type) {
			unset($postTypes[$type]);
		}

		return \array_values($postTypes);
	}

	/**
	 * Get all public taxonomies.
	 *
	 * @return array<string>
	 */
	public static function getPublicTaxonomies(): array
	{
		return \array_values(\get_taxonomies(['public' => true]));
	}
}
