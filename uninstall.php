<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * @package EightshiftSeo
 */

declare(strict_types=1);

if (! current_user_can('activate_plugins')) {
	return;
}

// If uninstall is not called from WordPress, then exit.
if (! defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

// Clean up plugin options.
delete_option('es-seo-settings');

// Clean up all post meta added by the plugin.
$postMetaKeys = [
	'es_seo_title',
	'es_seo_description',
	'es_seo_noindex',
	'es_seo_nofollow',
	'es_seo_canonical',
	'es_seo_og_title',
	'es_seo_og_description',
	'es_seo_og_image',
	'es_seo_twitter_title',
	'es_seo_twitter_description',
	'es_seo_twitter_image',
	'es_seo_focus_keyphrase',
	'es_seo_max_snippet',
	'es_seo_max_image_preview',
	'es_seo_max_video_preview',
];

foreach ($postMetaKeys as $key) {
	delete_post_meta_by_key($key);
}

// Clean up all term meta added by the plugin.
global $wpdb;

$termMetaKeys = [
	'es_seo_term_title',
	'es_seo_term_description',
	'es_seo_term_noindex',
	'es_seo_term_nofollow',
	'es_seo_term_canonical',
	'es_seo_term_og_title',
	'es_seo_term_og_description',
	'es_seo_term_og_image',
	'es_seo_term_twitter_title',
	'es_seo_term_twitter_description',
	'es_seo_term_twitter_image',
];

foreach ($termMetaKeys as $key) {
	$wpdb->delete($wpdb->termmeta, ['meta_key' => $key]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
}
