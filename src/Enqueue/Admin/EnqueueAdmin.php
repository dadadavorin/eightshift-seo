<?php

/**
 * The Admin Enqueue specific functionality.
 *
 * @package EightshiftSeo\Enqueue\Admin
 */

declare(strict_types=1);

namespace EightshiftSeo\Enqueue\Admin;

use EightshiftSeo\AdminMenus\AdminSettingsAdminMenu;
use EightshiftSeo\Config\Config;
use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Enqueue\Admin\AbstractEnqueueAdmin;
use EightshiftSeoVendor\EightshiftLibs\Exception\InvalidBlock;

/**
 * EnqueueAdmin class — loads JS/CSS only on the SEO settings page and in the block editor.
 *
 * Crucially there is no wp_enqueue_scripts hook anywhere in this class.
 * It is structurally impossible for the plugin to load frontend assets.
 */
class EnqueueAdmin extends AbstractEnqueueAdmin
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('admin_enqueue_scripts', [$this, 'enqueueAdminStyles'], 50);
		\add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
		\add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorScripts']);
	}

	/**
	 * Returns the asset prefix used to name script/style handles.
	 *
	 * @return string
	 */
	public function getAssetsPrefix(): string
	{
		return Config::getProjectTextDomain();
	}

	/**
	 * Returns the asset version string.
	 *
	 * @return string
	 */
	public function getAssetsVersion(): string
	{
		return Config::getProjectVersion();
	}

	/**
	 * Determine whether the current admin page should load the settings assets.
	 *
	 * @return bool
	 */
	private function isSettingsPage(): bool
	{
		$page = isset($_GET['page']) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? \sanitize_text_field(\wp_unslash($_GET['page'])) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: '';

		return $page === Options::getAdminPageSlug(AdminSettingsAdminMenu::ADMIN_MENU_SLUG);
	}

	/**
	 * Enqueue styles for the settings page.
	 *
	 * No-op: the admin bundle has no separate CSS file (styles are inlined
	 * by webpack or handled by WP core). Override prevents the parent from
	 * throwing when applicationAdmin.css does not exist in the assets cache.
	 *
	 * @return void
	 */
	public function enqueueAdminStyles(): void
	{
		// Intentionally empty — no CSS bundle for the admin settings page.
	}

	/**
	 * Enqueue scripts for the settings page, with inline localization data.
	 *
	 * @return void
	 */
	public function enqueueAdminScripts(): void
	{
		if (!$this->isSettingsPage()) {
			return;
		}

		parent::enqueueAdminScripts();

		$localization = \wp_json_encode($this->buildLocalization());
		\wp_add_inline_script(
			$this->getAdminScriptHandle(),
			"const esSeoLocalization = {$localization}",
			'before'
		);
	}

	/**
	 * Enqueue the block editor bundle on every Gutenberg-enabled edit screen.
	 *
	 * Uses a separate file (applicationEditor.js) so admin and editor assets
	 * remain isolated. Zero frontend enqueue — this hook is editor-only.
	 *
	 * @return void
	 */
	public function enqueueBlockEditorScripts(): void
	{
		if (!\function_exists('use_block_editor_for_post_type')) {
			return;
		}

		$screen = \get_current_screen();
		if (!$screen) {
			return;
		}

		$postType = $screen->post_type;
		if (empty($postType) || !\use_block_editor_for_post_type($postType)) {
			return;
		}

		$handle  = "{$this->getAssetsPrefix()}-editor-scripts";
		$version = $this->getAssetsVersion();

		try {
			$editorJsPath = $this->setAssetsItem('applicationEditor.js');
		} catch (InvalidBlock $e) {
			$editorJsPath = '';
		}

		try {
			$editorCssPath = $this->setAssetsItem('applicationEditor.css');
		} catch (InvalidBlock $e) {
			$editorCssPath = '';
		}

		if ($editorJsPath) {
			\wp_register_script(
				$handle,
				$editorJsPath,
				$this->getEditorScriptDependencies(),
				$version,
				\is_wp_version_compatible('6.3') ? ['strategy' => 'defer'] : false
			);
			\wp_enqueue_script($handle);

			// Pass localization to the editor bundle.
			$localization = \wp_json_encode($this->buildEditorLocalization());
			\wp_add_inline_script($handle, "const esSeoEditorLocalization = {$localization}", 'before');
		}

		if ($editorCssPath) {
			\wp_register_style(
				"{$this->getAssetsPrefix()}-editor-styles",
				$editorCssPath,
				[],
				$version
			);
			\wp_enqueue_style("{$this->getAssetsPrefix()}-editor-styles");
		}
	}

	/**
	 * Load the admin script with defer.
	 *
	 * @return string
	 */
	protected function scriptStrategy(): string
	{
		return 'defer';
	}

	/**
	 * Script dependencies for the settings-page bundle.
	 *
	 * @return array<int, string>
	 */
	protected function getAdminScriptDependencies(): array
	{
		return ['wp-element', 'wp-i18n', 'wp-api-fetch', 'wp-core-data', 'wp-data'];
	}

	/**
	 * Script dependencies for the block editor bundle.
	 *
	 * @return array<int, string>
	 */
	private function getEditorScriptDependencies(): array
	{
		return [
			'wp-plugins',
			'wp-edit-post',
			'wp-element',
			'wp-data',
			'wp-core-data',
			'wp-components',
			'wp-i18n',
			'wp-api-fetch',
		];
	}

	/**
	 * Build the localization object injected into the admin settings bundle.
	 *
	 * @return array<string, mixed>
	 */
	private function buildLocalization(): array
	{
		$postTypes = \get_post_types(['public' => true], 'objects');
		unset($postTypes['attachment']);

		$postTypesList = \array_values(\array_map(
			static fn (\WP_Post_Type $pt) => [
				'name' => $pt->labels->singular_name ?? $pt->label,
				'slug' => $pt->name,
			],
			$postTypes
		));

		$taxonomies = \get_taxonomies(['public' => true], 'objects');
		$taxonomiesList = \array_values(\array_map(
			static fn (\WP_Taxonomy $tax) => [
				'name' => $tax->labels->singular_name ?? $tax->label,
				'slug' => $tax->name,
			],
			$taxonomies
		));

		return [
			'nonce'       => \wp_create_nonce('wp_rest'),
			'optionName'  => Options::getOptionsName(),
			'postTypes'   => $postTypesList,
			'taxonomies'  => $taxonomiesList,
			'canUpload'   => \current_user_can('upload_files'),
			'homeUrl'     => \home_url('/'),
		];
	}

	/**
	 * Build the localization object injected into the block editor bundle.
	 *
	 * @return array<string, mixed>
	 */
	private function buildEditorLocalization(): array
	{
		return [
			'nonce'      => \wp_create_nonce('wp_rest'),
			'optionName' => Options::getOptionsName(),
			'metaKeys'   => [
				'title'              => Options::getMetaKey('title'),
				'description'        => Options::getMetaKey('description'),
				'noindex'            => Options::getMetaKey('noindex'),
				'nofollow'           => Options::getMetaKey('nofollow'),
				'canonical'          => Options::getMetaKey('canonical'),
				'ogTitle'            => Options::getMetaKey('ogTitle'),
				'ogDescription'      => Options::getMetaKey('ogDescription'),
				'ogImage'            => Options::getMetaKey('ogImage'),
				'twitterTitle'       => Options::getMetaKey('twitterTitle'),
				'twitterDescription' => Options::getMetaKey('twitterDescription'),
				'twitterImage'       => Options::getMetaKey('twitterImage'),
				'focusKeyphrase'     => Options::getMetaKey('focusKeyphrase'),
				'maxSnippet'         => Options::getMetaKey('maxSnippet'),
				'maxImagePreview'    => Options::getMetaKey('maxImagePreview'),
				'maxVideoPreview'    => Options::getMetaKey('maxVideoPreview'),
			],
			'homeUrl'    => \home_url('/'),
			'siteName'   => \get_bloginfo('name'),
			'separator'  => Options::getOption(['separator']) ?: '–',
		];
	}
}
