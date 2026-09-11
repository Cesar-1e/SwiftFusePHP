<?php

declare(strict_types=1);

namespace SwiftFuse\Tests\Fixtures;

use SwiftFuse\Http\Controller;

/**
 * Controller fixture that records its lifecycle, for the in-process router tests.
 *
 * It is not final, so a test can decorate it through a container binding.
 */
class RecordingController extends Controller
{
    /**
     * Lifecycle events recorded by every instance, oldest first.
     *
     * @var array<int, string>
     */
    public static array $events = [];

    /**
     * The instance that handled the last "plain" action.
     *
     * @var RecordingController|null
     */
    public static ?RecordingController $lastInstance = null;

    /**
     * Record the before-hook and block the "forbidden" action.
     *
     * @param string $action The action about to run.
     * @param array<int, string> $params Route parameters.
     * @return bool
     */
    public function before(string $action, array $params): bool
    {
        self::$events[] = "before:{$action}";

        return $action !== 'forbidden' && parent::before($action, $params);
    }

    /**
     * Record the after-hook.
     *
     * @param string $action The action that ran.
     * @param array<int, string> $params Route parameters.
     * @return void
     */
    public function after(string $action, array $params): void
    {
        self::$events[] = "after:{$action}";
        parent::after($action, $params);
    }

    /**
     * Action that returns normally, recording the handling class and its parameters.
     *
     * @param string ...$params Route parameters.
     * @return void
     */
    public function plain(string ...$params): void
    {
        self::$lastInstance = $this;
        self::$events[] = sprintf('action:plain:%s:%s', static::class, implode(',', $params));
    }

    /**
     * Action blocked by the before-hook; recording it would reveal a lifecycle bug.
     *
     * @return void
     */
    public function forbidden(): void
    {
        self::$events[] = 'action:forbidden';
    }
}
