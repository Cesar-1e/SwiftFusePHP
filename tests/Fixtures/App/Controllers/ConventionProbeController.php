<?php

declare(strict_types=1);

namespace App\Controllers;

use SwiftFuse\Http\Controller;

/**
 * Convention-routed controller fixture, served at "/conventionProbe".
 *
 * It lives under tests/ and test scripts require it explicitly, because the
 * App\ namespace maps to app/.
 */
final class ConventionProbeController extends Controller
{
    /**
     * Actions recorded by every instance, oldest first.
     *
     * @var array<int, string>
     */
    public static array $events = [];

    /**
     * Record the default action with its view segment and parameters.
     *
     * @param string $view View segment taken from the URL.
     * @param string ...$params Remaining route parameters.
     * @return void
     */
    public function index(string $view = 'index', string ...$params): void
    {
        self::$events[] = 'index:' . implode(',', [$view, ...$params]);
    }

    /**
     * Record a regular action.
     *
     * @param string $value Route parameter.
     * @return void
     */
    public function ping(string $value): void
    {
        self::$events[] = "ping:{$value}";
    }
}
