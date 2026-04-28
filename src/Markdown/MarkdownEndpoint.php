<?php

/**
 * The file that serves post content as Markdown via .md URL suffix.
 *
 * @package EightshiftSeo\Markdown
 */

declare(strict_types=1);

namespace EightshiftSeo\Markdown;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;
use WP_Post;

/**
 * MarkdownEndpoint — serves any published post as Markdown by appending .md to its URL.
 *
 * Rewrite rules:
 *   - /{slug}.md       → page or single post
 *   - /{path/slug}.md  → hierarchical pages
 *
 * The response includes a YAML front-matter block followed by the post content
 * converted to Markdown via HtmlToMarkdown::convert().
 *
 * Caching: per-post transients invalidated on save_post.
 */
class MarkdownEndpoint implements ServiceInterface
{
	/**
	 * Transient prefix for cached Markdown output.
	 *
	 * @var string
	 */
	private const CACHE_PREFIX = 'es_seo_md_';

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('init', [$this, 'registerRewriteRules']);
		\add_filter('query_vars', [$this, 'addQueryVars']);
		\add_action('template_redirect', [$this, 'serve'], 1);
		\add_filter('redirect_canonical', [$this, 'suppressCanonicalRedirect']);
		\add_action('save_post', [$this, 'invalidateCache']);
	}

	/**
	 * Prevent WordPress from redirect-to-slash-ing our .md endpoints.
	 *
	 * @param string|false $redirectUrl The canonical redirect URL WP wants to issue.
	 *
	 * @return string|false
	 */
	public function suppressCanonicalRedirect(string|false $redirectUrl): string|false
	{
		if (\get_query_var('es_seo_md')) {
			return false;
		}

		return $redirectUrl;
	}

	/**
	 * Register rewrite rules for .md suffix variants.
	 *
	 * @return void
	 */
	public function registerRewriteRules(): void
	{
		\add_rewrite_rule('^([^/]+)\.md$', 'index.php?name=$matches[1]&es_seo_md=1', 'top');
		\add_rewrite_rule('^(.+)\.md$', 'index.php?pagename=$matches[1]&es_seo_md=1', 'top');
	}

	/**
	 * Add custom query variable.
	 *
	 * @param array<string> $vars Existing query vars.
	 *
	 * @return array<string>
	 */
	public function addQueryVars(array $vars): array
	{
		$vars[] = 'es_seo_md';
		return $vars;
	}

	/**
	 * Serve the post as Markdown when the query var is set.
	 *
	 * @return void
	 */
	public function serve(): void
	{
		if (!\get_query_var('es_seo_md')) {
			return;
		}

		$post = \get_queried_object();

		// The name= rewrite var only queries post-type "post". For pages and CPTs,
		// the main query may come up empty. Fall back to a cross-post-type slug lookup.
		if (!$post instanceof WP_Post) {
			$slug = \get_query_var('name') ?: \get_query_var('pagename');
			if ($slug !== '') {
				$posts = \get_posts([
					'name'        => $slug,
					'post_type'   => 'any',
					'post_status' => 'publish',
					'numberposts' => 1,
				]);
				$post = $posts[0] ?? null;
			}
		}

		if (!$post instanceof WP_Post) {
			\status_header(404);
			echo '404 Not Found';
			exit;
		}

		// Bail on protected/non-published/unsupported content.
		if ($post->post_status !== 'publish') {
			\status_header(404);
			echo '404 Not Found';
			exit;
		}

		if (!empty($post->post_password)) {
			\status_header(403);
			echo '403 Forbidden';
			exit;
		}

		$supportedPostTypes = \apply_filters(
			Options::getFilter('supportedPostTypes'),
			Options::getPublicPostTypes()
		);

		if (!\in_array($post->post_type, $supportedPostTypes, true)) {
			\status_header(404);
			echo '404 Not Found';
			exit;
		}

		// Respect noindex — do not expose content excluded from indexing.
		$noindex = (bool) \get_post_meta($post->ID, Options::getMetaKey('noindex'), true);
		if ($noindex) {
			\status_header(404);
			echo '404 Not Found';
			exit;
		}

		// Try cache.
		$cacheKey = self::CACHE_PREFIX . $post->ID;
		$cached   = \get_transient($cacheKey);

		if (\is_string($cached) && $cached !== '') {
			$output = $cached;
		} else {
			$output = $this->buildOutput($post);
			\set_transient($cacheKey, $output, \HOUR_IN_SECONDS);
		}

		$permalink = (string) \get_permalink($post->ID);

		// The main query may have been set up as a 404 (e.g. name= matched a page, not a post).
		// Override the status before sending our response.
		\status_header(200);
		\header('Content-Type: text/markdown; charset=utf-8');
		\header('Link: <' . $permalink . '>; rel="canonical"');
		\header('Cache-Control: public, max-age=3600');

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $output;
		exit;
	}

	/**
	 * Build YAML front-matter + Markdown body for a post.
	 *
	 * @param WP_Post $post The post to render.
	 *
	 * @return string
	 */
	private function buildOutput(WP_Post $post): string
	{
		$permalink   = (string) \get_permalink($post->ID);
		$seoTitle    = (string) \get_post_meta($post->ID, Options::getMetaKey('title'), true);
		$title       = $seoTitle !== '' ? $seoTitle : \get_the_title($post->ID);
		$seoDesc     = (string) \get_post_meta($post->ID, Options::getMetaKey('description'), true);
		$description = $seoDesc !== '' ? $seoDesc : \wp_trim_words(\get_the_excerpt($post->ID), 30, '');
		$tldr        = (string) \get_post_meta($post->ID, Options::getMetaKey('tldr'), true);
		$author      = (string) \get_the_author_meta('display_name', (int) $post->post_author);

		$frontMatter = "---\n";
		$frontMatter .= 'title: ' . $this->yamlScalar($title) . "\n";
		$frontMatter .= 'description: ' . $this->yamlScalar($description) . "\n";
		$frontMatter .= 'canonical: ' . $permalink . "\n";
		$frontMatter .= 'datePublished: ' . \get_the_date('Y-m-d', $post) . "\n";
		$frontMatter .= 'dateModified: ' . \get_the_modified_date('Y-m-d', $post) . "\n";
		$frontMatter .= 'author: ' . $this->yamlScalar($author) . "\n";
		$frontMatter .= 'tldr: ' . $this->yamlScalar($tldr) . "\n";
		$frontMatter .= "---\n\n";

		$htmlContent = (string) \apply_filters('the_content', $post->post_content);
		$markdown    = HtmlToMarkdown::convert($htmlContent);

		return $frontMatter . $markdown;
	}

	/**
	 * Encode a value as a safe YAML scalar (quoted if needed).
	 *
	 * @param string $value Raw string value.
	 *
	 * @return string
	 */
	private function yamlScalar(string $value): string
	{
		if ($value === '') {
			return "''";
		}

		// Quote if the value contains special YAML characters.
		if (\preg_match('/[:#\[\]{},&*?|<>=!%@`\'"\n\r]/', $value) || \str_starts_with($value, '-')) {
			return "'" . \str_replace("'", "''", $value) . "'";
		}

		return $value;
	}

	/**
	 * Invalidate the cached Markdown for a post when it is saved.
	 *
	 * @param int $postId The saved post ID.
	 *
	 * @return void
	 */
	public function invalidateCache(int $postId): void
	{
		\delete_transient(self::CACHE_PREFIX . $postId);
	}
}
