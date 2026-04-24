<?php

/**
 * The interface for SEO health checks.
 *
 * @package EightshiftSeo\Health
 */

declare(strict_types=1);

namespace EightshiftSeo\Health;

/**
 * HealthCheckInterface — contract for individual SEO health checks.
 *
 * Each check returns a status of 'ok', 'warn', or 'fail' with a message
 * and an optional action URL linking to the relevant settings page.
 */
interface HealthCheckInterface
{
	/**
	 * Machine-readable identifier for this check.
	 *
	 * @return string
	 */
	public function getId(): string;

	/**
	 * Human-readable label for this check.
	 *
	 * @return string
	 */
	public function getLabel(): string;

	/**
	 * Run the check and return its result.
	 *
	 * @return array{status: 'ok'|'warn'|'fail', message: string, actionUrl: string}
	 */
	public function run(): array;
}
