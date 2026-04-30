<?php

/**
 * The file that records daily AI-bot hit counters.
 *
 * @package EightshiftSeo\BotInsights
 */

declare(strict_types=1);

namespace EightshiftSeo\BotInsights;

use EightshiftSeo\Config\AiBotRegistry;
use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * BotCounters — bounded, retention-capped tracking of AI-bot crawls.
 *
 * Records exactly one row per (day, bot) in a custom table (~20 bots × 90 days
 * ≤ 1800 rows). Human user-agents bail before any work is done — zero overhead
 * on the front-end for non-bot traffic. Bot hits are buffered in-process and
 * flushed in a single INSERT ... ON DUPLICATE KEY UPDATE on shutdown.
 *
 * Privacy: only counts hits per user-agent per day. No IPs, URLs, query strings,
 * or request bodies are recorded.
 */
class BotCounters implements ServiceInterface
{
	public const TABLE_NAME      = 'es_seo_bot_counters';
	public const PRUNE_HOOK      = 'es_seo_bot_counters_prune';
	public const HARD_ROW_CAP    = 5000;
	public const SCHEMA_VERSION  = 1;
	public const SCHEMA_OPTION   = 'es_seo_bot_counters_schema';

	/**
	 * Buffered hits for the current request, keyed by bot id.
	 *
	 * @var array<string, int>
	 */
	private array $buffer = [];

	/**
	 * Compiled bot user-agent matchers, keyed by bot id.
	 *
	 * @var array<string, string>|null
	 */
	private static ?array $matchers = null;

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('init', [$this, 'maybeUpgradeSchema'], 5);
		\add_action(self::PRUNE_HOOK, [$this, 'prune']);
		\add_action('init', [$this, 'maybeScheduleCron']);

		if (!Options::getOptionChecked(['botInsights', 'enabled'])) {
			return;
		}

		\add_action('init', [$this, 'maybeRecordHit'], 0);
		\add_action('shutdown', [$this, 'flush']);
	}

	/**
	 * Returns the fully-qualified table name (includes the wpdb prefix).
	 *
	 * @return string
	 */
	public static function tableName(): string
	{
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Create or upgrade the bot counters table via dbDelta.
	 *
	 * @return void
	 */
	public static function installSchema(): void
	{
		global $wpdb;

		$table   = self::tableName();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			day DATE NOT NULL,
			bot_id VARCHAR(64) NOT NULL,
			hits INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_day_bot (day, bot_id)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		\dbDelta($sql);

		\update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, false);
	}

	/**
	 * Run schema upgrades when the stored version is out of date.
	 *
	 * @return void
	 */
	public function maybeUpgradeSchema(): void
	{
		if ((int) \get_option(self::SCHEMA_OPTION, 0) >= self::SCHEMA_VERSION) {
			return;
		}

		self::installSchema();
	}

	/**
	 * Ensure the daily prune cron is scheduled while the feature is enabled.
	 *
	 * @return void
	 */
	public function maybeScheduleCron(): void
	{
		$enabled   = Options::getOptionChecked(['botInsights', 'enabled']);
		$scheduled = \wp_next_scheduled(self::PRUNE_HOOK);

		if ($enabled && !$scheduled) {
			\wp_schedule_event(\time() + \HOUR_IN_SECONDS, 'daily', self::PRUNE_HOOK);
			return;
		}

		if (!$enabled && $scheduled) {
			\wp_unschedule_event($scheduled, self::PRUNE_HOOK);
		}
	}

	/**
	 * Match the incoming user-agent against the bot registry and buffer a hit.
	 *
	 * Bails immediately on non-bot user-agents — no DB or cache access.
	 *
	 * @return void
	 */
	public function maybeRecordHit(): void
	{
		// Skip admin, AJAX, and WP-Cron — bots only hit public surfaces.
		if (\is_admin() || (\defined('DOING_CRON') && DOING_CRON) || (\defined('DOING_AJAX') && DOING_AJAX)) {
			return;
		}

		$ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		if ($ua === '') {
			return;
		}

		$botId = self::matchUserAgent($ua);
		if ($botId === null) {
			return;
		}

		$rate = (float) Options::getOption(['botInsights', 'samplingRate']);
		if ($rate <= 0.0) {
			return;
		}
		if ($rate < 1.0 && \mt_rand(1, 1000000) > (int) \round($rate * 1000000)) {
			return;
		}

		$this->buffer[$botId] = ($this->buffer[$botId] ?? 0) + 1;
	}

	/**
	 * Match a user-agent string to a bot id from the registry. Returns null when
	 * no bot matches.
	 *
	 * @param string $userAgent The User-Agent header.
	 *
	 * @return string|null
	 */
	public static function matchUserAgent(string $userAgent): ?string
	{
		if ($userAgent === '') {
			return null;
		}

		$matchers = self::getMatchers();
		$lower    = \strtolower($userAgent);

		foreach ($matchers as $botId => $needle) {
			if (\str_contains($lower, $needle)) {
				return $botId;
			}
		}

		return null;
	}

	/**
	 * Build (and cache for the request) the lowercase user-agent needles per bot.
	 *
	 * @return array<string, string>
	 */
	private static function getMatchers(): array
	{
		if (self::$matchers !== null) {
			return self::$matchers;
		}

		$registry = AiBotRegistry::getBots();
		$registry = \apply_filters(Options::getFilter('aiBotRegistry'), $registry);

		$matchers = [];
		if (\is_array($registry)) {
			foreach ($registry as $slug => $bot) {
				$name = \is_array($bot) ? (string) ($bot['name'] ?? '') : '';
				if ($name === '') {
					continue;
				}
				$matchers[(string) $slug] = \strtolower($name);
			}
		}

		self::$matchers = $matchers;
		return self::$matchers;
	}

	/**
	 * Flush buffered hits to the database in a single upsert per bot.
	 *
	 * @return void
	 */
	public function flush(): void
	{
		if (empty($this->buffer)) {
			return;
		}

		global $wpdb;

		$table = self::tableName();
		$today = \current_time('Y-m-d');

		foreach ($this->buffer as $botId => $hits) {
			$botId = (string) $botId;
			$hits  = (int) $hits;

			if ($botId === '' || $hits <= 0) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query($wpdb->prepare(
				"INSERT INTO {$table} (day, bot_id, hits) VALUES (%s, %s, %d)
				 ON DUPLICATE KEY UPDATE hits = hits + VALUES(hits)",
				$today,
				$botId,
				$hits
			));
		}

		$this->buffer = [];
	}

	/**
	 * Prune rows older than the configured retention window. Also enforces the
	 * hard row cap as a safety net.
	 *
	 * @return void
	 */
	public function prune(): void
	{
		global $wpdb;

		$retention = (int) Options::getOption(['botInsights', 'retentionDays']);
		if ($retention <= 0) {
			$retention = 90;
		}

		$cutoff = \gmdate('Y-m-d', \strtotime("-{$retention} days") ?: \time());
		$table  = self::tableName();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE day < %s", $cutoff));

		// Hard safety net: cap total rows.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

		if ($total > self::HARD_ROW_CAP) {
			$overflow = $total - self::HARD_ROW_CAP;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query($wpdb->prepare("DELETE FROM {$table} ORDER BY day ASC, id ASC LIMIT %d", $overflow));
		}
	}

	/**
	 * Return aggregated counters for the most recent N days.
	 *
	 * @param int $days Number of days to include (1–365).
	 *
	 * @return array{
	 *     days: int,
	 *     totals: array<string, int>,
	 *     daily: array<int, array{day: string, bot_id: string, hits: int}>,
	 * }
	 */
	public function getStats(int $days = 30): array
	{
		global $wpdb;

		$days = \max(1, \min(365, $days));
		$from = \gmdate('Y-m-d', \strtotime("-{$days} days") ?: \time());
		$table = self::tableName();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT day, bot_id, hits FROM {$table} WHERE day >= %s ORDER BY day ASC, bot_id ASC",
				$from
			),
			ARRAY_A
		);

		$totals = [];
		$daily  = [];

		if (\is_array($rows)) {
			foreach ($rows as $row) {
				$botId = (string) ($row['bot_id'] ?? '');
				$hits  = (int) ($row['hits'] ?? 0);
				$day   = (string) ($row['day'] ?? '');

				if ($botId === '' || $day === '') {
					continue;
				}

				$totals[$botId] = ($totals[$botId] ?? 0) + $hits;
				$daily[]        = ['day' => $day, 'bot_id' => $botId, 'hits' => $hits];
			}
		}

		\arsort($totals);

		return [
			'days'   => $days,
			'totals' => $totals,
			'daily'  => $daily,
		];
	}

	/**
	 * Reset all counter rows. Used by the "Reset counters" admin action.
	 *
	 * @return void
	 */
	public function reset(): void
	{
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query('TRUNCATE TABLE ' . self::tableName());
	}
}
