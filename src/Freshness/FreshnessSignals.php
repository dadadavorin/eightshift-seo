<?php

/**
 * The file that handles content freshness signals.
 *
 * @package EightshiftSeo\Freshness
 */

declare(strict_types=1);

namespace EightshiftSeo\Freshness;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * FreshnessSignals — preserves post_modified across non-content saves when
 * the freshness.preserveModifiedOnNonContentSave setting is enabled.
 *
 * Use case: an editor flips a sidebar checkbox or quick-edits taxonomy
 * assignments. By default WordPress bumps post_modified, which becomes the
 * `dateModified` value in the Article schema. AI search engines and crawlers
 * read this as a content refresh — but nothing about the body actually changed.
 *
 * When the setting is on, this service compares post_content hashes and
 * restores the previous post_modified / post_modified_gmt values so freshness
 * signals reflect real content updates only.
 */
class FreshnessSignals implements ServiceInterface
{
	/**
	 * Snapshot of post_content hashes keyed by post ID, captured before the save.
	 *
	 * @var array<int, array{content: string, modified: string, modified_gmt: string}>
	 */
	private array $preSaveSnapshot = [];

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('pre_post_update', [$this, 'capturePreSaveSnapshot'], 10, 2);
		\add_filter('wp_insert_post_data', [$this, 'maybePreserveModified'], 99, 2);
	}

	/**
	 * Capture a snapshot of the post_content + modified timestamps before the
	 * incoming save replaces them.
	 *
	 * @param int                  $postId Post ID about to be updated.
	 * @param array<string, mixed> $data   Incoming sanitized post data (unused here).
	 *
	 * @return void
	 */
	public function capturePreSaveSnapshot(int $postId, array $data): void
	{
		unset($data); // marker for the unused param

		if (!Options::getOptionChecked(['freshness', 'preserveModifiedOnNonContentSave'])) {
			return;
		}

		$existing = \get_post($postId);
		if (!$existing) {
			return;
		}

		$this->preSaveSnapshot[$postId] = [
			'content'      => (string) $existing->post_content,
			'modified'     => (string) $existing->post_modified,
			'modified_gmt' => (string) $existing->post_modified_gmt,
		];
	}

	/**
	 * If only non-content fields changed, restore the previous post_modified.
	 *
	 * @param array<string, mixed> $data    Sanitized post data being written.
	 * @param array<string, mixed> $postArr Raw post array as submitted.
	 *
	 * @return array<string, mixed>
	 */
	public function maybePreserveModified(array $data, array $postArr): array
	{
		if (!Options::getOptionChecked(['freshness', 'preserveModifiedOnNonContentSave'])) {
			return $data;
		}

		$postId = (int) ($postArr['ID'] ?? 0);
		if ($postId <= 0 || !isset($this->preSaveSnapshot[$postId])) {
			return $data;
		}

		$previous = $this->preSaveSnapshot[$postId];

		// If the content hash changed, this is a real content update — let the
		// normal post_modified bump go through.
		$incomingContent = (string) ($data['post_content'] ?? '');
		if (\hash('sha256', $incomingContent) !== \hash('sha256', $previous['content'])) {
			return $data;
		}

		// Body unchanged → restore the previous modified timestamps.
		if ($previous['modified'] !== '') {
			$data['post_modified']     = $previous['modified'];
			$data['post_modified_gmt'] = $previous['modified_gmt'];
		}

		return $data;
	}
}
