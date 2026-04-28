<?php

/**
 * The file that registers the es_seo_faq postmeta field.
 *
 * @package EightshiftSeo\CustomMeta
 */

declare(strict_types=1);

namespace EightshiftSeo\CustomMeta;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * SeoFaqMeta class — registers the per-post FAQ array field.
 *
 * Each FAQ item is an object with:
 *   - question (string, max 200, required) — plain text only; HTML stripped on save
 *   - answer   (string, max 1500, required) — basic HTML allowed; scripts stripped on save
 *
 * Consumed by FaqSchema to emit a FAQPage structured-data node.
 */
class SeoFaqMeta implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('init', [$this, 'registerMeta']);
	}

	/**
	 * Register the postmeta field for all SEO-enabled post types.
	 *
	 * @return void
	 */
	public function registerMeta(): void
	{
		$postTypes = \apply_filters(
			Options::getFilter('supportedPostTypes'),
			Options::getPublicPostTypes()
		);

		foreach ($postTypes as $postType) {
			\register_post_meta($postType, Options::getMetaKey('faq'), [
				'type'              => 'array',
				'single'            => true,
				'show_in_rest'      => [
					'schema' => [
						'type'  => 'array',
						'items' => [
							'type'                 => 'object',
							'additionalProperties' => false,
							'required'             => ['question', 'answer'],
							'properties'           => [
								'question' => [
									'type'      => 'string',
									'maxLength' => 200,
								],
								'answer'   => [
									'type'      => 'string',
									'maxLength' => 1500,
								],
							],
						],
					],
				],
				'sanitize_callback' => [$this, 'sanitizeFaqItems'],
				'auth_callback'     => static fn () => \current_user_can('edit_posts'),
			]);
		}
	}

	/**
	 * Sanitize FAQ items: strip HTML from questions, allow safe HTML in answers.
	 *
	 * @param mixed $value Raw value from REST API or update_post_meta.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function sanitizeFaqItems(mixed $value): array
	{
		if (!\is_array($value)) {
			return [];
		}

		$sanitized = [];

		foreach ($value as $item) {
			if (!\is_array($item)) {
				continue;
			}

			$question = \wp_strip_all_tags((string) ($item['question'] ?? ''));
			$answer   = \wp_kses_post((string) ($item['answer'] ?? ''));

			// Strip any remaining script/style content that wp_kses_post may have missed.
			$answer = \preg_replace('#<script[^>]*>.*?</script>#is', '', $answer) ?? $answer;
			$answer = \preg_replace('#<style[^>]*>.*?</style>#is', '', $answer) ?? $answer;

			if ($question === '' && $answer === '') {
				continue;
			}

			$sanitized[] = [
				'question' => $question,
				'answer'   => $answer,
			];
		}

		return $sanitized;
	}
}
