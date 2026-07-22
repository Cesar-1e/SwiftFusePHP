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
}
?>
