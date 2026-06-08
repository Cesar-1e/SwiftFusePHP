<?php

/**
 * Custom controller extension hooks.
 *
 * Controllers can override beforeAction and afterAction without modifying
 * the framework core.
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
