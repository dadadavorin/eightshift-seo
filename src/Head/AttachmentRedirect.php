<?php

/**
 * The file that redirects attachment pages.
 *
 * @package EightshiftSeo\Head
 */

declare(strict_types=1);

namespace EightshiftSeo\Head;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * AttachmentRedirect class — 301-redirects attachment pages to either the
 * attachment file URL or the parent post, according to the configured mode.
 *
 * Modes:
 *   - 'file'     (default) — redirect to the attachment file URL.
 *   - 'parent'   — redirect to the parent post permalink (falls through if no parent).
 *   - 'disabled' — no-op, WordPress renders the attachment template as usual.
 */
class AttachmentRedirect implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('template_redirect', [$this, 'maybeRedirect'], 1);
	}

	/**
	 * Perform the redirect when on an attachment page.
	 *
	 * @return void
	 */
	public function maybeRedirect(): void
	{
		if (!\is_attachment()) {
			return;
		}

		$mode = (string) Options::getOption(['attachmentRedirect']) ?: 'file';

		if ($mode === 'disabled') {
			return;
		}

		$attachmentId = (int) \get_queried_object_id();
		if ($attachmentId <= 0) {
			return;
		}

		if ($mode === 'parent') {
			$parentId = (int) \wp_get_post_parent_id($attachmentId);
			if ($parentId > 0) {
				$permalink = \get_permalink($parentId);
				if (\is_string($permalink) && $permalink !== '') {
					\wp_safe_redirect($permalink, 301);
					exit;
				}
			}
			// Fall back to file when there's no parent.
		}

		$fileUrl = \wp_get_attachment_url($attachmentId);
		if (\is_string($fileUrl) && $fileUrl !== '') {
			\wp_redirect($fileUrl, 301); // phpcs:ignore WordPress.Security.SafeRedirect
			exit;
		}
	}
}
