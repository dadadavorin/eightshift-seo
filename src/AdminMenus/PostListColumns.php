<?php

/**
 * The file that adds SEO columns to the post-list admin screens.
 *
 * @package EightshiftSeo\AdminMenus
 */

declare(strict_types=1);

namespace EightshiftSeo\AdminMenus;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;
use WP_Query;

/**
 * PostListColumns class — registers SEO columns (title, description, noindex,
 * keyphrase) on every supported post type's edit.php list table.
 *
 * Runs late on admin_init so that every post type is registered by the time we
 * wire up per-type filters.
 */
class PostListColumns implements ServiceInterface
{
	/**
	 * Recommended character lengths for the title indicator.
	 */
	private const TITLE_MIN = 30;
	private const TITLE_MAX = 60;

	/**
	 * Recommended character lengths for the meta description indicator.
	 */
	private const DESC_MIN = 120;
	private const DESC_MAX = 160;

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('admin_init', [$this, 'registerColumnsForAllPostTypes']);
		\add_action('admin_enqueue_scripts', [$this, 'enqueueColumnStyles']);
		\add_action('pre_get_posts', [$this, 'sortByNoindex']);
	}

	/**
	 * Hook filters for every supported post type.
	 *
	 * @return void
	 */
	public function registerColumnsForAllPostTypes(): void
	{
		$postTypes = \apply_filters(
			Options::getFilter('supportedPostTypes'),
			Options::getPublicPostTypes()
		);

		foreach ($postTypes as $postType) {
			\add_filter("manage_{$postType}_posts_columns", [$this, 'addColumns']);
			\add_action("manage_{$postType}_posts_custom_column", [$this, 'renderColumn'], 10, 2);
			\add_filter("manage_edit-{$postType}_sortable_columns", [$this, 'addSortableColumns']);
		}
	}

	/**
	 * Add SEO columns to a post type's list-table columns array.
	 *
	 * @param array<string, string> $columns Existing columns.
	 *
	 * @return array<string, string>
	 */
	public function addColumns(array $columns): array
	{
		$columns['es_seo_title']       = \esc_html__('SEO title', 'eightshift-seo');
		$columns['es_seo_description'] = \esc_html__('Meta description', 'eightshift-seo');
		$columns['es_seo_noindex']     = \esc_html__('Noindex', 'eightshift-seo');
		$columns['es_seo_keyphrase']   = \esc_html__('Focus keyphrase', 'eightshift-seo');

		return $columns;
	}

	/**
	 * Register sortable columns.
	 *
	 * @param array<string, string> $columns Existing sortable columns.
	 *
	 * @return array<string, string>
	 */
	public function addSortableColumns(array $columns): array
	{
		$columns['es_seo_noindex'] = 'es_seo_noindex';
		return $columns;
	}

	/**
	 * Render a single SEO column cell.
	 *
	 * @param string $column  Column ID.
	 * @param int    $postId  Post ID.
	 *
	 * @return void
	 */
	public function renderColumn(string $column, int $postId): void
	{
		switch ($column) {
			case 'es_seo_title':
				// Emit data-* attributes used by Quick Edit JS hydration.
				echo '<span hidden class="es-seo-row-data"'
					. ' data-seo-title="' . \esc_attr((string) \get_post_meta($postId, Options::getMetaKey('title'), true)) . '"'
					. ' data-seo-description="' . \esc_attr((string) \get_post_meta($postId, Options::getMetaKey('description'), true)) . '"'
					. ' data-seo-keyphrase="' . \esc_attr((string) \get_post_meta($postId, Options::getMetaKey('focusKeyphrase'), true)) . '"'
					. ' data-seo-noindex="' . \esc_attr(\get_post_meta($postId, Options::getMetaKey('noindex'), true) ? '1' : '0') . '"'
					. '></span>';
				$this->renderTextCell(
					(string) \get_post_meta($postId, Options::getMetaKey('title'), true),
					self::TITLE_MIN,
					self::TITLE_MAX
				);
				break;

			case 'es_seo_description':
				$this->renderTextCell(
					(string) \get_post_meta($postId, Options::getMetaKey('description'), true),
					self::DESC_MIN,
					self::DESC_MAX
				);
				break;

			case 'es_seo_noindex':
				$noindex = (bool) \get_post_meta($postId, Options::getMetaKey('noindex'), true);
				if ($noindex) {
					echo '<span class="es-seo-pill es-seo-pill--warn">' . \esc_html__('Noindex', 'eightshift-seo') . '</span>';
				} else {
					echo '<span class="es-seo-muted">—</span>';
				}
				break;

			case 'es_seo_keyphrase':
				$phrase = (string) \get_post_meta($postId, Options::getMetaKey('focusKeyphrase'), true);
				if ($phrase === '') {
					echo '<span class="es-seo-muted">—</span>';
				} else {
					echo \esc_html($phrase);
				}
				break;
		}
	}

	/**
	 * Render a text cell with a length indicator.
	 *
	 * @param string $value Cell value.
	 * @param int    $min   Minimum recommended length.
	 * @param int    $max   Maximum recommended length.
	 *
	 * @return void
	 */
	private function renderTextCell(string $value, int $min, int $max): void
	{
		if ($value === '') {
			echo '<span class="es-seo-muted">—</span>';
			return;
		}

		$length = \mb_strlen($value);

		$statusClass = 'ok';
		if ($length < $min) {
			$statusClass = 'short';
		} elseif ($length > $max) {
			$statusClass = 'long';
		}

		echo '<div class="es-seo-cell">';
		echo '<span class="es-seo-cell__value">' . \esc_html($value) . '</span>';
		echo '<span class="es-seo-length es-seo-length--' . \esc_attr($statusClass) . '">' .
			/* translators: %d: character count. */
			\esc_html(\sprintf(\_n('%d char', '%d chars', $length, 'eightshift-seo'), $length)) .
			'</span>';
		echo '</div>';
	}

	/**
	 * Make the noindex column sortable.
	 *
	 * @param WP_Query $query Current query.
	 *
	 * @return void
	 */
	public function sortByNoindex(WP_Query $query): void
	{
		if (!\is_admin() || !$query->is_main_query()) {
			return;
		}

		if ($query->get('orderby') !== 'es_seo_noindex') {
			return;
		}

		$query->set('meta_key', Options::getMetaKey('noindex'));
		$query->set('orderby', 'meta_value');
	}

	/**
	 * Enqueue column styles on edit.php screens only.
	 *
	 * @param string $hookSuffix Current admin page hook.
	 *
	 * @return void
	 */
	public function enqueueColumnStyles(string $hookSuffix): void
	{
		if ($hookSuffix !== 'edit.php') {
			return;
		}

		$css = <<<'CSS'
		:root {
			--es-seo-ok: #46b450;
			--es-seo-warn: #dba617;
			--es-seo-bad: #d63638;
			--es-seo-muted: #8c8f94;
		}
		.column-es_seo_title,
		.column-es_seo_description { min-width: 180px; max-width: 260px; }
		.column-es_seo_noindex,
		.column-es_seo_keyphrase { min-width: 90px; }
		.es-seo-cell { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
		.es-seo-cell__value { color: #1d2327; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; word-break: break-word; }
		.es-seo-length { font-size: 11px; line-height: 1.3; }
		.es-seo-length--ok { color: var(--es-seo-ok); }
		.es-seo-length--short { color: var(--es-seo-warn); }
		.es-seo-length--long { color: var(--es-seo-bad); }
		.es-seo-muted { color: var(--es-seo-muted); }
		.es-seo-pill {
			display: inline-block;
			padding: 1px 8px;
			border-radius: 10px;
			font-size: 11px;
			font-weight: 600;
			line-height: 1.6;
		}
		.es-seo-pill--warn { background: #fcf0d4; color: #8a6d19; }
		CSS;

		\wp_register_style('es-seo-admin-columns', false); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters
		\wp_enqueue_style('es-seo-admin-columns');
		\wp_add_inline_style('es-seo-admin-columns', $css);
	}
}
