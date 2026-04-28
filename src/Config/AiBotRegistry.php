<?php

/**
 * The file that defines the AI crawler bot registry.
 *
 * @package EightshiftSeo\Config
 */

declare(strict_types=1);

namespace EightshiftSeo\Config;

/**
 * AiBotRegistry — versioned list of known AI crawler user-agents.
 *
 * Keyed by a stable slug; each entry carries the vendor name, the canonical
 * User-agent string for robots.txt, and a category:
 *   - training : used to collect data for model training
 *   - search   : used to index content for AI-powered search results
 *   - user     : used for real-time retrieval during a live user session
 *
 * Projects can extend or override entries via the es_seo_ai_bot_registry filter
 * (applied in AiCrawlerRobotsTxt before building the robots.txt stanzas).
 */
class AiBotRegistry
{
	/**
	 * Date the bot list was last verified against vendor documentation.
	 * Surfaced in the admin UI so operators know the registry currency.
	 */
	public const LAST_VERIFIED = '2026-04-27';

	/**
	 * Return the built-in bot registry.
	 *
	 * @return array<string, array{name: string, vendor: string, category: string}>
	 */
	public static function getBots(): array
	{
		return [
			'gptbot'             => ['name' => 'GPTBot',            'vendor' => 'OpenAI',       'category' => 'training'],
			'oai-searchbot'      => ['name' => 'OAI-SearchBot',     'vendor' => 'OpenAI',       'category' => 'search'],
			'chatgpt-user'       => ['name' => 'ChatGPT-User',      'vendor' => 'OpenAI',       'category' => 'user'],
			'claudebot'          => ['name' => 'ClaudeBot',         'vendor' => 'Anthropic',    'category' => 'training'],
			'claude-searchbot'   => ['name' => 'Claude-SearchBot',  'vendor' => 'Anthropic',    'category' => 'search'],
			'claude-user'        => ['name' => 'Claude-User',       'vendor' => 'Anthropic',    'category' => 'user'],
			'google-extended'    => ['name' => 'Google-Extended',   'vendor' => 'Google',       'category' => 'training'],
			'perplexitybot'      => ['name' => 'PerplexityBot',     'vendor' => 'Perplexity',   'category' => 'search'],
			'perplexity-user'    => ['name' => 'Perplexity-User',   'vendor' => 'Perplexity',   'category' => 'user'],
			'applebot-extended'  => ['name' => 'Applebot-Extended', 'vendor' => 'Apple',        'category' => 'training'],
			'ccbot'              => ['name' => 'CCBot',             'vendor' => 'Common Crawl', 'category' => 'training'],
			'bytespider'         => ['name' => 'Bytespider',        'vendor' => 'ByteDance',    'category' => 'training'],
			'meta-externalagent' => ['name' => 'Meta-ExternalAgent','vendor' => 'Meta',         'category' => 'training'],
			'mistralai-user'     => ['name' => 'MistralAI-User',    'vendor' => 'Mistral',      'category' => 'user'],
			'cohere-ai'          => ['name' => 'cohere-ai',         'vendor' => 'Cohere',       'category' => 'training'],
			'duckassistbot'      => ['name' => 'DuckAssistBot',     'vendor' => 'DuckDuckGo',   'category' => 'search'],
			'youbot'             => ['name' => 'YouBot',            'vendor' => 'You.com',      'category' => 'search'],
			'ai2bot'             => ['name' => 'Ai2Bot',            'vendor' => 'AI2',          'category' => 'training'],
			'xai-bot'            => ['name' => 'xAI-Bot',           'vendor' => 'xAI',          'category' => 'training'],
		];
	}
}
