<?php

/**
 * File that holds the Security Route interface.
 *
 * @package EightshiftLibs\Rest
 *
 * @license MIT
 * Modified by eightshift-meilisearch on 01-April-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace EightshiftSeoVendor\EightshiftLibs\Rest;

/**
 * Rest security interface
 *
 * An object that defines the authentication checks for REST API routes.
 */
interface RouteSecurityInterface
{
	/**
	 * Authenticate the access of the endpoint
	 *
	 * A register method holds authenticationCheck function for the route.
	 *
	 * @return void
	 */
	public function authenticationCheck(): void;
}
