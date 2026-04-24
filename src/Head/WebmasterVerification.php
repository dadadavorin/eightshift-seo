<?php

/**
 * The file that outputs webmaster / search-console verification meta tags.
 *
 * @package EightshiftSeo\Head
 */

declare(strict_types=1);

namespace EightshiftSeo\Head;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * WebmasterVerification class — emits <meta name="...-site-verification"> tags
 * in wp_head for each configured search-console provider.
 */
class WebmasterVerification implements ServiceInterface
{
	/**
	 * Map of settings key → meta tag name.
	 *
	 * @var array<string, string>
	 */
	private const ENGINE_META_NAMES = [
		'google'    => 'google-site-verification',
		'bing'      => 'msvalidate.01',
		'yandex'    => 'yandex-verification',
		'pinterest' => 'p:domain_verify',
		'baidu'     => 'baidu-site-verification',
	];

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('wp_head', [$this, 'outputVerificationTags'], 1);
	}

	/**
	 * Build and output the verification meta tags.
	 *
	 * @return void
	 */
	public function outputVerificationTags(): void
	{
		$tags = [];

		foreach (self::ENGINE_META_NAMES as $key => $name) {
			$code = (string) Options::getOption(['webmaster', $key]);
			$code = $this->sanitizeCode($code);

			if ($code === '') {
				continue;
			}

			$tags[$name] = $code;
		}

		$tags = \apply_filters(Options::getFilter('webmasterVerificationTags'), $tags);

		foreach ($tags as $name => $content) {
			echo '<meta name="' . \esc_attr((string) $name) . '" content="' . \esc_attr((string) $content) . '">' . "\n";
		}
	}

	/**
	 * Strip a full <meta> tag if the user pasted it by mistake, keeping only
	 * the content attribute value.
	 *
	 * @param string $raw Raw input value.
	 *
	 * @return string
	 */
	private function sanitizeCode(string $raw): string
	{
		$raw = \trim($raw);

		if ($raw === '') {
			return '';
		}

		if (\preg_match('/content\s*=\s*["\']([^"\']+)["\']/i', $raw, $matches) === 1) {
			return \trim($matches[1]);
		}

		return $raw;
	}
}
