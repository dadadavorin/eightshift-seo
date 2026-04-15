<?php

/**
 * Admin settings page root component.
 *
 * Renders the mount point for the React admin application.
 *
 * @package EightshiftSeo
 */

use EightshiftSeoVendor\EightshiftLibs\Helpers\Helpers;

$componentId = $manifest['componentId'] ?? 'es-seo-admin-root';

$adminSettingsTitle = Helpers::checkAttr('adminSettingsTitle', $attributes, $manifest);
$adminSettingsType  = Helpers::checkAttr('adminSettingsType', $attributes, $manifest);

$attrs = [
	'id'         => $componentId,
	'data-title' => $adminSettingsTitle,
	'data-type'  => $adminSettingsType,
];
?>

<div
	class="wrap"
	<?php echo Helpers::getAttrsOutput($attrs); // phpcs:ignore Eightshift.Security.HelpersEscape.OutputNotEscaped
	?>>
	<?php echo \esc_html__('Loading…', 'eightshift-seo'); ?>
</div>
