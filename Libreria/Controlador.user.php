<?php

/**
 * Custom controller extension hooks.
 *
 * Controllers can override beforeAction and afterAction without modifying
 * the framework core.
 *
 * @deprecated 0.9.9 Superseded by SwiftFuse\Http\Controller::before()/after() and
 *             the SwiftFuse\Support\Hooks event dispatcher. Removed in 1.0.
 */
abstract class ControladorUser
{
    public function hello_word()
    {
        return "Hello Word";
    }

    /**
     * Called before the controller action runs.
     * Return false to block the request.
     *
     * @param string $action
     * @param array $params
     * @return bool
     */
    public function beforeAction(string $action, array $params): bool
    {
        return true;
    }

    /**
     * Called after the controller action runs.
     *
     * @param string $action
     * @param array $params
     * @return void
     */
    public function afterAction(string $action, array $params): void
    {
    }
}
?>
