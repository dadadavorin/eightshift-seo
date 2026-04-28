<?php

/**
 * The file that registers author E-E-A-T user-meta fields and the profile screen UI.
 *
 * @package EightshiftSeo\UserMeta
 */

declare(strict_types=1);

namespace EightshiftSeo\UserMeta;

use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;
use WP_User;

/**
 * AuthorProfileMeta — registers user-meta for E-E-A-T signals and extends the
 * WordPress user profile / edit-user screens with the corresponding fields.
 *
 * Registered meta keys:
 *   es_seo_author_credentials    — short credentials / bio addendum (≤140 chars)
 *   es_seo_author_job_title      — job title or role
 *   es_seo_author_organization   — employer / organisation name (overrides site default)
 *   es_seo_author_same_as        — array of social / authority profile URLs
 *   es_seo_author_email_public   — boolean: expose email in Person schema
 *
 * All fields are show_in_rest so they are writable via the REST API (admin only).
 * The auth_callback requires edit_user on the target user.
 */
class AuthorProfileMeta implements ServiceInterface
{
	/**
	 * Known social platforms for the sameAs repeater.
	 *
	 * @var array<string, string>
	 */
	private const SOCIAL_PLATFORMS = [
		'linkedin'   => 'LinkedIn',
		'twitter'    => 'Twitter / X',
		'mastodon'   => 'Mastodon',
		'github'     => 'GitHub',
		'orcid'      => 'ORCID',
		'website'    => 'Personal website',
		'youtube'    => 'YouTube',
	];

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('init', [$this, 'registerMeta']);
		\add_action('show_user_profile', [$this, 'renderProfileFields']);
		\add_action('edit_user_profile', [$this, 'renderProfileFields']);
		\add_action('personal_options_update', [$this, 'saveProfileFields']);
		\add_action('edit_user_profile_update', [$this, 'saveProfileFields']);
	}

	/**
	 * Register user-meta fields with the REST API.
	 *
	 * @return void
	 */
	public function registerMeta(): void
	{
		$authCallback = static fn (bool $allowed, string $metaKey, int $objectId): bool => \current_user_can('edit_user', $objectId);

		\register_meta('user', 'es_seo_author_credentials', [
			'type'          => 'string',
			'single'        => true,
			'default'       => '',
			'show_in_rest'  => ['schema' => ['type' => 'string', 'maxLength' => 140]],
			'auth_callback' => $authCallback,
		]);

		\register_meta('user', 'es_seo_author_job_title', [
			'type'          => 'string',
			'single'        => true,
			'default'       => '',
			'show_in_rest'  => ['schema' => ['type' => 'string', 'maxLength' => 100]],
			'auth_callback' => $authCallback,
		]);

		\register_meta('user', 'es_seo_author_organization', [
			'type'          => 'string',
			'single'        => true,
			'default'       => '',
			'show_in_rest'  => ['schema' => ['type' => 'string', 'maxLength' => 120]],
			'auth_callback' => $authCallback,
		]);

		\register_meta('user', 'es_seo_author_same_as', [
			'type'          => 'array',
			'single'        => true,
			'default'       => [],
			'show_in_rest'  => [
				'schema' => [
					'type'  => 'array',
					'items' => ['type' => 'string', 'format' => 'uri'],
				],
			],
			'auth_callback' => $authCallback,
		]);

		\register_meta('user', 'es_seo_author_email_public', [
			'type'          => 'boolean',
			'single'        => true,
			'default'       => false,
			'show_in_rest'  => ['schema' => ['type' => 'boolean']],
			'auth_callback' => $authCallback,
		]);
	}

	/**
	 * Render E-E-A-T fields on the profile / edit-user screen.
	 *
	 * @param WP_User $user The user being edited.
	 *
	 * @return void
	 */
	public function renderProfileFields(WP_User $user): void
	{
		if (!\current_user_can('edit_user', $user->ID)) {
			return;
		}

		$credentials   = (string) \get_user_meta($user->ID, 'es_seo_author_credentials', true);
		$jobTitle      = (string) \get_user_meta($user->ID, 'es_seo_author_job_title', true);
		$organization  = (string) \get_user_meta($user->ID, 'es_seo_author_organization', true);
		$emailPublic   = (bool) \get_user_meta($user->ID, 'es_seo_author_email_public', true);
		$sameAs        = (array) \get_user_meta($user->ID, 'es_seo_author_same_as', true);

		// Map known platforms to their stored values.
		$socialValues = [];
		foreach (\array_keys(self::SOCIAL_PLATFORMS) as $key) {
			foreach ($sameAs as $url) {
				$url = \trim((string) $url);
				if ($url !== '' && $this->urlMatchesPlatform($url, $key)) {
					$socialValues[$key] = $url;
					break;
				}
			}
		}

		\wp_nonce_field('es_seo_author_profile_' . $user->ID, 'es_seo_author_profile_nonce');
		?>
		<h2><?php \esc_html_e('SEO — Author E-E-A-T', 'eightshift-seo'); ?></h2>
		<p class="description">
			<?php \esc_html_e('These fields help AI search engines identify you as an authority. All fields are optional.', 'eightshift-seo'); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="es_seo_author_job_title"><?php \esc_html_e('Job title / role', 'eightshift-seo'); ?></label>
				</th>
				<td>
					<input
						type="text"
						name="es_seo_author_job_title"
						id="es_seo_author_job_title"
						class="regular-text"
						maxlength="100"
						value="<?php echo \esc_attr($jobTitle); ?>"
					>
					<p class="description"><?php \esc_html_e('Emitted as jobTitle in the Author schema node.', 'eightshift-seo'); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="es_seo_author_credentials"><?php \esc_html_e('Credentials / expertise note', 'eightshift-seo'); ?></label>
				</th>
				<td>
					<input
						type="text"
						name="es_seo_author_credentials"
						id="es_seo_author_credentials"
						class="regular-text"
						maxlength="140"
						value="<?php echo \esc_attr($credentials); ?>"
					>
					<p class="description"><?php \esc_html_e('Short credential note (e.g. "PhD in Medicine, 10 years experience"). Max 140 chars.', 'eightshift-seo'); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="es_seo_author_organization"><?php \esc_html_e('Organisation', 'eightshift-seo'); ?></label>
				</th>
				<td>
					<input
						type="text"
						name="es_seo_author_organization"
						id="es_seo_author_organization"
						class="regular-text"
						maxlength="120"
						value="<?php echo \esc_attr($organization); ?>"
					>
					<p class="description"><?php \esc_html_e('Leave empty to inherit the site organisation name.', 'eightshift-seo'); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php \esc_html_e('Social / authority profiles', 'eightshift-seo'); ?></th>
				<td>
					<?php foreach (self::SOCIAL_PLATFORMS as $key => $label) : ?>
						<p>
							<label for="es_seo_same_as_<?php echo \esc_attr($key); ?>">
								<?php echo \esc_html($label); ?>
							</label><br>
							<input
								type="url"
								name="es_seo_same_as[<?php echo \esc_attr($key); ?>]"
								id="es_seo_same_as_<?php echo \esc_attr($key); ?>"
								class="regular-text"
								value="<?php echo \esc_attr($socialValues[$key] ?? ''); ?>"
								placeholder="https://"
							>
						</p>
					<?php endforeach; ?>
					<p class="description"><?php \esc_html_e('These URLs are emitted as sameAs in your Author schema, helping AI engines confirm your identity.', 'eightshift-seo'); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php \esc_html_e('Public email', 'eightshift-seo'); ?></th>
				<td>
					<label>
						<input
							type="checkbox"
							name="es_seo_author_email_public"
							value="1"
							<?php \checked($emailPublic); ?>
						>
						<?php \esc_html_e('Include email address in Author schema', 'eightshift-seo'); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save E-E-A-T profile fields on form submission.
	 *
	 * @param int $userId The user being updated.
	 *
	 * @return void
	 */
	public function saveProfileFields(int $userId): void
	{
		if (!\current_user_can('edit_user', $userId)) {
			return;
		}

		$nonce = \sanitize_text_field(\wp_unslash($_POST['es_seo_author_profile_nonce'] ?? ''));
		if (!\wp_verify_nonce($nonce, 'es_seo_author_profile_' . $userId)) {
			return;
		}

		// Scalar fields.
		\update_user_meta(
			$userId,
			'es_seo_author_credentials',
			\substr(\sanitize_text_field(\wp_unslash($_POST['es_seo_author_credentials'] ?? '')), 0, 140)
		);

		\update_user_meta(
			$userId,
			'es_seo_author_job_title',
			\substr(\sanitize_text_field(\wp_unslash($_POST['es_seo_author_job_title'] ?? '')), 0, 100)
		);

		\update_user_meta(
			$userId,
			'es_seo_author_organization',
			\substr(\sanitize_text_field(\wp_unslash($_POST['es_seo_author_organization'] ?? '')), 0, 120)
		);

		\update_user_meta(
			$userId,
			'es_seo_author_email_public',
			isset($_POST['es_seo_author_email_public']) && $_POST['es_seo_author_email_public'] === '1'
		);

		// sameAs URL array.
		$rawSameAs = $_POST['es_seo_same_as'] ?? []; // phpcs:ignore WordPress.Security.NonceVerification
		$sameAs = [];

		if (\is_array($rawSameAs)) {
			foreach ($rawSameAs as $url) {
				$url = \esc_url_raw(\trim(\wp_unslash((string) $url)));
				if ($url !== '' && \filter_var($url, \FILTER_VALIDATE_URL)) {
					$sameAs[] = $url;
				}
			}
		}

		\update_user_meta($userId, 'es_seo_author_same_as', \array_values(\array_unique($sameAs)));
	}

	/**
	 * Loosely match a URL to a known social platform key.
	 *
	 * @param string $url URL to test.
	 * @param string $key Platform key (linkedin, github, etc.).
	 *
	 * @return bool
	 */
	private function urlMatchesPlatform(string $url, string $key): bool
	{
		$domains = [
			'linkedin'  => 'linkedin.com',
			'twitter'   => ['twitter.com', 'x.com'],
			'mastodon'  => '', // any host — detected by /@user@host pattern; skip auto-match
			'github'    => 'github.com',
			'orcid'     => 'orcid.org',
			'youtube'   => 'youtube.com',
		];

		if (!isset($domains[$key]) || $domains[$key] === '') {
			return false;
		}

		$allowed = (array) $domains[$key];

		foreach ($allowed as $domain) {
			if (\str_contains($url, $domain)) {
				return true;
			}
		}

		return false;
	}
}
