<?php

/**
 * The file that contributes Person (author) nodes to the schema graph.
 *
 * @package EightshiftSeo\Schema
 */

declare(strict_types=1);

namespace EightshiftSeo\Schema;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;
use WP_Post;
use WP_User;

/**
 * AuthorSchema — contributes a Person node for the post author on singular views
 * and for the queried user on author archive pages.
 *
 * The Person @id is stable: home_url('/?author={id}#person').
 * This is the same @id used by ArticleSchema for its author cross-reference.
 *
 * The node is enriched with E-E-A-T fields registered by AuthorProfileMeta:
 *   - es_seo_author_credentials
 *   - es_seo_author_job_title
 *   - es_seo_author_organization
 *   - es_seo_author_same_as (array of URIs)
 *
 * worksFor cross-references the site Organization @id, so both nodes should
 * appear in the graph for the reference to resolve.
 */
class AuthorSchema implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_filter(Options::getFilter('schemaGraph'), [$this, 'addAuthorNode'], 25, 2);
	}

	/**
	 * Contribute a Person node for singular posts or author archive pages.
	 *
	 * @param array<int, array<string, mixed>> $graph   Current graph nodes.
	 * @param array<string, mixed>             $context Request context from GraphEmitter.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function addAuthorNode(array $graph, array $context): array
	{
		$userId = $this->resolveUserId($context);

		if ($userId <= 0) {
			return $graph;
		}

		$user = \get_userdata($userId);
		if (!$user instanceof WP_User) {
			return $graph;
		}

		$node = $this->buildPersonNode($user, $context);
		$node = \apply_filters(Options::getFilter('schemaNodePerson'), $node, $user);

		if (!empty($node)) {
			$graph[] = $node;
		}

		return $graph;
	}

	/**
	 * Determine the user ID for the current request.
	 *
	 * @param array<string, mixed> $context Request context.
	 *
	 * @return int User ID, or 0 if not applicable.
	 */
	private function resolveUserId(array $context): int
	{
		// Singular post — use post author.
		if ($context['isSingular'] ?? false) {
			$post = $context['post'] ?? null;
			if ($post instanceof WP_Post) {
				return (int) $post->post_author;
			}
		}

		// Author archive — use queried user.
		if (\is_author()) {
			$queriedObject = $context['queriedObject'] ?? null;
			if ($queriedObject instanceof WP_User) {
				return $queriedObject->ID;
			}
		}

		return 0;
	}

	/**
	 * Build the Person schema node for a given user.
	 *
	 * @param WP_User              $user    WordPress user object.
	 * @param array<string, mixed> $context Request context.
	 *
	 * @return array<string, mixed>
	 */
	private function buildPersonNode(WP_User $user, array $context): array
	{
		$node = [
			'@type' => 'Person',
			'@id'   => \home_url('/?author=' . $user->ID . '#person'),
			'name'  => $user->display_name,
			'url'   => \get_author_posts_url($user->ID),
		];

		// Avatar image.
		$avatarUrl = \get_avatar_url($user->ID, ['size' => 512]);
		if (!empty($avatarUrl)) {
			$node['image'] = [
				'@type' => 'ImageObject',
				'url'   => $avatarUrl,
			];
		}

		// WP native bio.
		$bio = \trim($user->description ?? '');
		if ($bio !== '') {
			$node['description'] = $bio;
		}

		// E-E-A-T extended fields (registered by AuthorProfileMeta).
		$credentials = (string) \get_user_meta($user->ID, 'es_seo_author_credentials', true);
		if ($credentials !== '') {
			$node['description'] = ($bio !== '' ? $bio . ' ' : '') . $credentials;
		}

		$jobTitle = (string) \get_user_meta($user->ID, 'es_seo_author_job_title', true);
		if ($jobTitle !== '') {
			$node['jobTitle'] = $jobTitle;
		}

		// worksFor: reference the site Organization node.
		$orgName = (string) \get_user_meta($user->ID, 'es_seo_author_organization', true);
		if ($orgName === '') {
			$orgName = (string) Options::getOption(['siteRepresentation', 'name']);
		}
		if ($orgName === '') {
			$orgName = (string) \get_bloginfo('name');
		}

		$node['worksFor'] = [
			'@type' => 'Organization',
			'@id'   => \home_url('/#organization'),
			'name'  => $orgName,
		];

		// Public email.
		$emailPublic = (bool) \get_user_meta($user->ID, 'es_seo_author_email_public', true);
		if ($emailPublic && $user->user_email !== '') {
			$node['email'] = $user->user_email;
		}

		// sameAs.
		$sameAs = $this->collectSameAs($user->ID);
		if (!empty($sameAs)) {
			$node['sameAs'] = $sameAs;
		}

		// On author archive: make this person the mainEntity of the page.
		if (\is_author()) {
			$node['mainEntityOfPage'] = [
				'@type' => 'ProfilePage',
				'@id'   => \get_author_posts_url($user->ID),
			];
		}

		return $node;
	}

	/**
	 * Collect sameAs URLs from the user's social profile meta.
	 *
	 * @param int $userId WordPress user ID.
	 *
	 * @return array<int, string>
	 */
	private function collectSameAs(int $userId): array
	{
		$raw = \get_user_meta($userId, 'es_seo_author_same_as', true);

		if (!\is_array($raw)) {
			return [];
		}

		$urls = [];
		foreach ($raw as $url) {
			$url = \trim((string) $url);
			if ($url !== '' && \filter_var($url, \FILTER_VALIDATE_URL)) {
				$urls[] = $url;
			}
		}

		return \array_values(\array_unique($urls));
	}
}
