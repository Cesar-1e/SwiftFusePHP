<?php

declare(strict_types=1);

namespace SwiftFuse\Tests\Fixtures;

/**
 * Decorator fixture bound in the container in place of RecordingController.
 */
final class DecoratedRecordingController extends RecordingController
{
    /**
     * Record the decoration, then run the original action.
     *
     * @param string ...$params Route parameters.
     * @return void
     */
    public function plain(string ...$params): void
    {
        self::$events[] = 'decorated:before-plain';
        parent::plain(...$params);
    }
}
