<?php

declare(strict_types=1);

namespace SwiftFuse\Tests\Fixtures;

use SwiftFuse\Http\Controller;

/**
 * Controller fixture whose actions answer with json(), for the JSON lifecycle tests.
 *
 * json() ends the process, so tests drive it through tests/Fixtures/json-dispatch.php.
 */
final class JsonProbeController extends Controller
{
    /**
     * Reject the "blocked" action from the before-hook with a JSON response.
     *
     * @param string $action The action about to run.
     * @param array<int, string> $params Route parameters.
     * @return bool
     */
    public function before(string $action, array $params): bool
    {
        if ($action === 'blocked') {
            $this->json(['error' => 'denied'], 401);
        }

        return parent::before($action, $params);
    }

    /**
     * Answer from the after-hook of "answerFromAfter", proving the hook cannot run twice.
     *
     * @param string $action The action that ran.
     * @param array<int, string> $params Route parameters.
     * @return void
     */
    public function after(string $action, array $params): void
    {
        parent::after($action, $params);

        if ($action === 'answerFromAfter') {
            $this->json(['from' => 'after']);
        }
    }

    /**
     * Answer with a JSON payload containing the route parameter.
     *
     * @param string $id Route parameter.
     * @return never
     */
    public function respond(string $id): never
    {
        $this->json(['id' => $id], 201);
    }

    /**
     * Action that never runs, because the before-hook answers first.
     *
     * @return void
     */
    public function blocked(): void
    {
    }

    /**
     * Action that returns normally; its after-hook sends the response.
     *
     * @return void
     */
    public function answerFromAfter(): void
    {
    }

    /**
     * Answer with json() outside any router dispatch.
     *
     * @return never
     */
    public function respondDirectly(): never
    {
        $this->json(['standalone' => true]);
    }
}
