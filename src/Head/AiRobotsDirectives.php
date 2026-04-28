<?php

/**
 * The file that emits noai / noimageai meta tags for AI training bots.
 *
 * @package EightshiftSeo\Head
 */

declare(strict_types=1);

namespace EightshiftSeo\Head;

use EightshiftSeo\Config\AiBotRegistry;
use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;
use WP_Post;

/**
 * AiRobotsDirectives — emits noai / noimageai directives in wp_head.
 *
 * These directives are not part of the standard wp_robots filter (which handles
 * the single <meta name="robots"> tag). They require separate <meta> tags:
 *   <meta name="robots" content="noai">
 *   <meta name="GPTBot" content="noai">   ← per training-bot variants
 *
 * Resolution order (per directive):
 *   1. Per-post meta (es_seo_noai / es_seo_noimageai)
 *   2. Site-wide default (robotsDefaults.ai.noai / .noimageai)
 *
 * Compliance note: noai / noimageai are honoured by Anthropic, Microsoft, and
 * several other AI vendors. Compliance is not universal — documented in admin.
 */
class AiRobotsDirectives implements ServiceInterface
{
	/**
	 * Training-category bot names from the registry that honour noai/noimageai.
	 *
	 * Derived at runtime from AiBotRegistry; only 'training' category bots are
	 * included since 'search' and 'user' bots are for retrieval, not training.
	 */
	private const NOAI_COMPLIANT_VENDORS = [
		'GPTBot',
		'ClaudeBot',
		'Google-Extended',
		'Applebot-Extended',
		'CCBot',
		'Bytespider',
		'Meta-ExternalAgent',
		'cohere-ai',
		'Ai2Bot',
		'xAI-Bot',
	];

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('wp_head', [$this, 'outputAiDirectives'], 2);
	}

	/**
	 * Emit noai / noimageai meta tags when the post or site default requests them.
	 *
	 * @return void
	 */
	public function outputAiDirectives(): void
	{
		$post   = \get_post();
		$postId = $post instanceof WP_Post ? $post->ID : 0;

		$noai      = $this->resolve('noai', $postId);
		$noimageai = $this->resolve('noimageai', $postId);

		if (!$noai && !$noimageai) {
			return;
		}

		if ($noai) {
			echo '<meta name="robots" content="noai">' . "\n";

			foreach (self::NOAI_COMPLIANT_VENDORS as $botName) {
				echo '<meta name="' . \esc_attr($botName) . '" content="noai">' . "\n";
			}
		}

		if ($noimageai) {
			echo '<meta name="robots" content="noimageai">' . "\n";

			foreach (self::NOAI_COMPLIANT_VENDORS as $botName) {
				echo '<meta name="' . \esc_attr($botName) . '" content="noimageai">' . "\n";
			}
		}
	}

	/**
	 * Resolve the effective boolean value for a given AI directive.
	 *
	 * @param string $directive Meta key logical name ('noai' or 'noimageai').
	 * @param int    $postId    Current post ID (0 if not a singular view).
	 *
	 * @return bool
	 */
	private function resolve(string $directive, int $postId): bool
	{
		$metaKey = Options::getMetaKey($directive);

		// Per-post meta takes precedence.
		if ($postId > 0 && $metaKey !== '') {
			$postValue = \get_post_meta($postId, $metaKey, true);
			// register_post_meta default is false; only use meta if explicitly set.
			if ($postValue !== '' && $postValue !== null) {
				return (bool) $postValue;
			}
		}

		// Site-wide default.
		return (bool) Options::getOption(['robotsDefaults', 'ai', $directive]);
	}
}
