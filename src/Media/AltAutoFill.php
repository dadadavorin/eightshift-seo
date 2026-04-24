<?php

/**
 * The file that auto-fills image alt text on upload.
 *
 * @package EightshiftSeo\Media
 */

declare(strict_types=1);

namespace EightshiftSeo\Media;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * AltAutoFill class — when a new image attachment is added, derives the alt text
 * from the attachment title if no alt text has been set.
 *
 * Derivation: strip file extension, replace hyphens/underscores with spaces,
 * trim, then convert to title case.
 *
 * Only runs when settings.images.autoFillAlt is true (default: true).
 */
class AltAutoFill implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('add_attachment', [$this, 'maybeSetAlt']);
	}

	/**
	 * Set the alt text for a newly uploaded image if not already set.
	 *
	 * @param int $attachmentId The attachment post ID.
	 *
	 * @return void
	 */
	public function maybeSetAlt(int $attachmentId): void
	{
		if (!Options::getOptionChecked(['images', 'autoFillAlt'])) {
			return;
		}

		$mimeType = (string) \get_post_mime_type($attachmentId);
		if (!\str_starts_with($mimeType, 'image/')) {
			return;
		}

		$existing = \get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
		if (!empty($existing)) {
			return;
		}

		$post = \get_post($attachmentId);
		if ($post === null) {
			return;
		}

		$title  = (string) $post->post_title;
		$altText = $this->titleToAlt($title);

		if ($altText !== '') {
			\update_post_meta($attachmentId, '_wp_attachment_image_alt', \sanitize_text_field($altText));
		}
	}

	/**
	 * Convert an attachment title to a human-readable alt text string.
	 *
	 * WordPress already strips the file extension and converts dashes/underscores
	 * to spaces when creating the post_title on upload — so we just need to apply
	 * title-case here.
	 *
	 * @param string $title Attachment post title.
	 *
	 * @return string
	 */
	private function titleToAlt(string $title): string
	{
		// Strip any residual extension (.jpg, .png, etc.) in case title wasn't cleaned.
		$title = (string) \preg_replace('/\.\w{2,5}$/', '', \trim($title));
		// Replace hyphens and underscores with spaces.
		$title = \str_replace(['-', '_'], ' ', $title);
		// Collapse multiple spaces.
		$title = (string) \preg_replace('/\s+/', ' ', $title);

		return \ucwords(\strtolower(\trim($title)));
	}
}
