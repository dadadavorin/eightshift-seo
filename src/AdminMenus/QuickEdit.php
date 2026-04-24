<?php

/**
 * The file that adds Quick Edit and Bulk Edit fields for SEO meta.
 *
 * @package EightshiftSeo\AdminMenus
 */

declare(strict_types=1);

namespace EightshiftSeo\AdminMenus;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * QuickEdit class — adds SEO fields to Quick Edit and Bulk Edit rows on edit.php,
 * and handles saving those fields on post save.
 *
 * Security: every save is guarded by nonce + current_user_can('edit_post').
 */
class QuickEdit implements ServiceInterface
{
	private const NONCE_ACTION = 'es_seo_quick_edit';
	private const NONCE_FIELD  = 'es_seo_quick_edit_nonce';

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('quick_edit_custom_box', [$this, 'renderQuickEditFields'], 10, 2);
		\add_action('bulk_edit_custom_box',  [$this, 'renderBulkEditFields'],  10, 2);
		\add_action('save_post',             [$this, 'saveFields'],            10, 2);
		\add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
	}

	/**
	 * Render the SEO fields inside the Quick Edit row.
	 *
	 * @param string $columnName The column currently being processed.
	 * @param string $postType   The current post type.
	 *
	 * @return void
	 */
	public function renderQuickEditFields(string $columnName, string $postType): void
	{
		if ($columnName !== 'es_seo_title') {
			return;
		}

		if (!$this->isSupportedPostType($postType)) {
			return;
		}

		\wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
		?>
		<fieldset class="inline-edit-col-right es-seo-quick-edit">
			<div class="inline-edit-col">
				<h4><?php \esc_html_e('SEO', 'eightshift-seo'); ?></h4>

				<label>
					<span class="title"><?php \esc_html_e('SEO title', 'eightshift-seo'); ?></span>
					<input type="text" name="<?php echo \esc_attr(Options::getMetaKey('title')); ?>" class="es-seo-qe-title" value="" />
				</label>

				<label>
					<span class="title"><?php \esc_html_e('Meta description', 'eightshift-seo'); ?></span>
					<textarea name="<?php echo \esc_attr(Options::getMetaKey('description')); ?>" class="es-seo-qe-description" rows="2"></textarea>
				</label>

				<label>
					<span class="title"><?php \esc_html_e('Focus keyphrase', 'eightshift-seo'); ?></span>
					<input type="text" name="<?php echo \esc_attr(Options::getMetaKey('focusKeyphrase')); ?>" class="es-seo-qe-keyphrase" value="" />
				</label>

				<label class="alignleft">
					<input type="checkbox" name="<?php echo \esc_attr(Options::getMetaKey('noindex')); ?>" class="es-seo-qe-noindex" value="1" />
					<span class="checkbox-title"><?php \esc_html_e('Noindex', 'eightshift-seo'); ?></span>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Render the SEO fields inside the Bulk Edit row.
	 *
	 * @param string $columnName The column currently being processed.
	 * @param string $postType   The current post type.
	 *
	 * @return void
	 */
	public function renderBulkEditFields(string $columnName, string $postType): void
	{
		if ($columnName !== 'es_seo_title') {
			return;
		}

		if (!$this->isSupportedPostType($postType)) {
			return;
		}

		\wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
		?>
		<fieldset class="inline-edit-col-right es-seo-bulk-edit">
			<div class="inline-edit-col">
				<h4><?php \esc_html_e('SEO', 'eightshift-seo'); ?></h4>

				<label>
					<span class="title"><?php \esc_html_e('Noindex', 'eightshift-seo'); ?></span>
					<select name="<?php echo \esc_attr(Options::getMetaKey('noindex')); ?>_bulk">
						<option value=""><?php \esc_html_e('— No change —', 'eightshift-seo'); ?></option>
						<option value="1"><?php \esc_html_e('Set noindex', 'eightshift-seo'); ?></option>
						<option value="0"><?php \esc_html_e('Remove noindex', 'eightshift-seo'); ?></option>
					</select>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Save SEO fields on post save, for both Quick Edit and Bulk Edit.
	 *
	 * @param int      $postId The post ID being saved.
	 * @param \WP_Post $post   The post object.
	 *
	 * @return void
	 */
	public function saveFields(int $postId, \WP_Post $post): void
	{
		// Bail on autosave, revisions, and non-supported post types.
		if (\wp_is_post_autosave($postId) || \wp_is_post_revision($postId)) {
			return;
		}

		if (!$this->isSupportedPostType($post->post_type)) {
			return;
		}

		// Nonce check.
		$nonce = \sanitize_text_field(\wp_unslash($_POST[self::NONCE_FIELD] ?? ''));
		if (empty($nonce) || !\wp_verify_nonce($nonce, self::NONCE_ACTION)) {
			return;
		}

		// Capability check.
		if (!\current_user_can('edit_post', $postId)) {
			return;
		}

		// Quick Edit fields.
		$titleKey = Options::getMetaKey('title');
		if (isset($_POST[$titleKey])) {
			\update_post_meta($postId, $titleKey, \sanitize_text_field(\wp_unslash($_POST[$titleKey])));
		}

		$descKey = Options::getMetaKey('description');
		if (isset($_POST[$descKey])) {
			\update_post_meta($postId, $descKey, \sanitize_textarea_field(\wp_unslash($_POST[$descKey])));
		}

		$keyphraseKey = Options::getMetaKey('focusKeyphrase');
		if (isset($_POST[$keyphraseKey])) {
			\update_post_meta($postId, $keyphraseKey, \sanitize_text_field(\wp_unslash($_POST[$keyphraseKey])));
		}

		$noindexKey = Options::getMetaKey('noindex');
		if (isset($_POST[$noindexKey])) {
			\update_post_meta($postId, $noindexKey, (bool) \sanitize_text_field(\wp_unslash($_POST[$noindexKey])));
		}

		// Bulk Edit — noindex dropdown (leave / 1 / 0).
		$bulkNoindexKey = $noindexKey . '_bulk';
		if (isset($_POST[$bulkNoindexKey]) && $_POST[$bulkNoindexKey] !== '') {
			$val = \sanitize_text_field(\wp_unslash($_POST[$bulkNoindexKey]));
			\update_post_meta($postId, $noindexKey, (bool) $val);
		}
	}

	/**
	 * Enqueue the quick-edit JavaScript on edit.php only.
	 *
	 * Plain jQuery — inlined so no extra webpack entry is needed.
	 *
	 * @param string $hookSuffix Current admin page hook.
	 *
	 * @return void
	 */
	public function enqueueScripts(string $hookSuffix): void
	{
		if ($hookSuffix !== 'edit.php') {
			return;
		}

		\wp_add_inline_script('jquery', $this->getInlineScript());
	}

	/**
	 * Return the inline Quick Edit JS that hydrates fields from data-* attributes.
	 *
	 * @return string
	 */
	private function getInlineScript(): string
	{
		$titleKey      = \esc_js(Options::getMetaKey('title'));
		$descKey       = \esc_js(Options::getMetaKey('description'));
		$keyphraseKey  = \esc_js(Options::getMetaKey('focusKeyphrase'));
		$noindexKey    = \esc_js(Options::getMetaKey('noindex'));

		return <<<JS
(function ($) {
	'use strict';

	var inlineEditPost = window.inlineEditPost;
	if (!inlineEditPost) { return; }

	var origEdit = inlineEditPost.edit;

	inlineEditPost.edit = function (id) {
		origEdit.apply(this, arguments);

		var postId = typeof id === 'object' ? parseInt(this.getId(id), 10) : parseInt(id, 10);
		if (!postId) { return; }

		var dataSpan = $('#post-' + postId).find('.es-seo-row-data');
		var editRow  = $('#edit-' + postId);

		// Hydrate text fields from data-* attributes written by PostListColumns.
		editRow.find('input[name="{$titleKey}"]').val(dataSpan.data('seo-title') || '');
		editRow.find('textarea[name="{$descKey}"]').val(dataSpan.data('seo-description') || '');
		editRow.find('input[name="{$keyphraseKey}"]').val(dataSpan.data('seo-keyphrase') || '');
		editRow.find('input[name="{$noindexKey}"]').prop('checked', dataSpan.data('seo-noindex') === '1');
	};
}(jQuery));
JS;
	}

	/**
	 * Check whether the post type is in the supported list.
	 *
	 * @param string $postType Post type slug.
	 *
	 * @return bool
	 */
	private function isSupportedPostType(string $postType): bool
	{
		$supported = \apply_filters(
			Options::getFilter('supportedPostTypes'),
			Options::getPublicPostTypes()
		);
		return \in_array($postType, $supported, true);
	}
}
