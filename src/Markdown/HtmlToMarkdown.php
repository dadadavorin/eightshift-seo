<?php

/**
 * Lightweight HTML-to-Markdown converter for Gutenberg output.
 *
 * @package EightshiftSeo\Markdown
 */

declare(strict_types=1);

namespace EightshiftSeo\Markdown;

/**
 * HtmlToMarkdown — static utility for converting HTML to Markdown.
 *
 * Covers the most common Gutenberg block output. Uses a multi-pass regex
 * approach for simplicity and predictability; no DOM parser required.
 *
 * Supported conversions:
 *   - Block-level removal: <script>, <style>, <nav>, <header>, <footer>, <aside>
 *   - Headings h1–h6
 *   - Paragraphs, line breaks
 *   - Bold, italic, links, images
 *   - Ordered and unordered lists
 *   - Blockquotes
 *   - Inline code, fenced code blocks
 *   - Horizontal rules
 *   - Remaining tags stripped
 */
class HtmlToMarkdown
{
	/**
	 * Convert an HTML string to Markdown.
	 *
	 * @param string $html HTML input (typically Gutenberg post_content after the_content filter).
	 *
	 * @return string Markdown output.
	 */
	public static function convert(string $html): string
	{
		if ($html === '') {
			return '';
		}

		$md = $html;

		// 1. Strip entirely unwanted block elements (with all their content).
		$blockStrip = ['script', 'style', 'nav', 'header', 'footer', 'aside'];
		foreach ($blockStrip as $tag) {
			$md = \preg_replace('#<' . $tag . '[^>]*>.*?</' . $tag . '>#is', '', $md) ?? $md;
		}

		// 2. Fenced code blocks: <pre><code ...>...</code></pre>.
		$md = \preg_replace_callback(
			'#<pre[^>]*>\s*<code[^>]*>(.*?)</code>\s*</pre>#is',
			static function (array $m): string {
				$code = \html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
				return "\n\`\`\`\n" . \trim($code) . "\n\`\`\`\n";
			},
			$md
		) ?? $md;

		// 3. Inline code: <code>...</code>.
		$md = \preg_replace_callback(
			'#<code[^>]*>(.*?)</code>#is',
			static function (array $m): string {
				$code = \wp_strip_all_tags($m[1]);
				return '`' . $code . '`';
			},
			$md
		) ?? $md;

		// 4. Images: <img src="..." alt="...">.
		$md = \preg_replace_callback(
			'#<img[^>]+src=["\']([^"\']+)["\'][^>]*(?:alt=["\']([^"\']*)["\'])?[^>]*/?>#i',
			static function (array $m): string {
				$src = $m[1];
				// Try to find alt attribute separately if not captured in group 2.
				$alt = $m[2] ?? '';
				if ($alt === '') {
					\preg_match('/alt=["\']([^"\']*)["\']/', $m[0], $altMatch);
					$alt = $altMatch[1] ?? '';
				}
				return '![' . $alt . '](' . $src . ')';
			},
			$md
		) ?? $md;

		// 5. Links: <a href="...">text</a>.
		$md = \preg_replace_callback(
			'#<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is',
			static function (array $m): string {
				$url  = $m[1];
				$text = \wp_strip_all_tags($m[2]);
				return '[' . $text . '](' . $url . ')';
			},
			$md
		) ?? $md;

		// 6. Bold: <strong> and <b>.
		$md = \preg_replace_callback(
			'#<(?:strong|b)[^>]*>(.*?)</(?:strong|b)>#is',
			static fn (array $m): string => '**' . \wp_strip_all_tags($m[1]) . '**',
			$md
		) ?? $md;

		// 7. Italic: <em> and <i>.
		$md = \preg_replace_callback(
			'#<(?:em|i)[^>]*>(.*?)</(?:em|i)>#is',
			static fn (array $m): string => '*' . \wp_strip_all_tags($m[1]) . '*',
			$md
		) ?? $md;

		// 8. Headings h1–h6.
		$md = \preg_replace_callback(
			'#<h([1-6])[^>]*>(.*?)</h\1>#is',
			static function (array $m): string {
				$level = (int) $m[1];
				$text  = \wp_strip_all_tags($m[2]);
				return "\n" . \str_repeat('#', $level) . ' ' . $text . "\n";
			},
			$md
		) ?? $md;

		// 9. Blockquote.
		$md = \preg_replace_callback(
			'#<blockquote[^>]*>(.*?)</blockquote>#is',
			static function (array $m): string {
				$inner = \wp_strip_all_tags($m[1]);
				$lines = \explode("\n", \trim($inner));
				$quoted = \implode("\n", \array_map(
					static fn (string $line): string => '> ' . \trim($line),
					$lines
				));
				return "\n" . $quoted . "\n";
			},
			$md
		) ?? $md;

		// 10. Ordered lists: <ol><li>...</li></ol>.
		$md = \preg_replace_callback(
			'#<ol[^>]*>(.*?)</ol>#is',
			static function (array $m): string {
				\preg_match_all('#<li[^>]*>(.*?)</li>#is', $m[1], $items);
				$lines = [];
				$i     = 1;
				foreach ($items[1] as $item) {
					$lines[] = $i . '. ' . \wp_strip_all_tags($item);
					$i++;
				}
				return "\n" . \implode("\n", $lines) . "\n";
			},
			$md
		) ?? $md;

		// 11. Unordered lists: <ul><li>...</li></ul>.
		$md = \preg_replace_callback(
			'#<ul[^>]*>(.*?)</ul>#is',
			static function (array $m): string {
				\preg_match_all('#<li[^>]*>(.*?)</li>#is', $m[1], $items);
				$lines = [];
				foreach ($items[1] as $item) {
					$lines[] = '- ' . \wp_strip_all_tags($item);
				}
				return "\n" . \implode("\n", $lines) . "\n";
			},
			$md
		) ?? $md;

		// 12. Paragraphs: <p>...</p>.
		$md = \preg_replace_callback(
			'#<p[^>]*>(.*?)</p>#is',
			static function (array $m): string {
				$text = \wp_strip_all_tags($m[1]);
				return "\n" . \trim($text) . "\n\n";
			},
			$md
		) ?? $md;

		// 13. Line breaks: <br> variants.
		$md = \preg_replace('#<br\s*/?>#i', "\n", $md) ?? $md;

		// 14. Horizontal rules: <hr>.
		$md = \preg_replace('#<hr\s*/?>#i', "\n---\n", $md) ?? $md;

		// 15. Strip any remaining HTML tags.
		$md = \wp_strip_all_tags($md);

		// 16. Decode HTML entities.
		$md = \html_entity_decode($md, ENT_QUOTES | ENT_HTML5, 'UTF-8');

		// 17. Normalize whitespace: collapse 3+ blank lines to 2.
		$md = \preg_replace('/\n{3,}/', "\n\n", $md) ?? $md;

		return \trim($md);
	}
}
