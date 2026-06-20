<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Person;
use SwiftFuse\Http\Controller;

/**
 * Home controller.
 *
 * Renders the landing page, listing people loaded from the database to
 * demonstrate the end-to-end MVC + PDO flow.
 */
final class HomeController extends Controller
{
    /**
     * The view folder for this controller.
     *
     * @var string
     */
    protected string $folder = 'home';

    /**
     * Show the landing page with the list of people.
     *
     * @param string $view Unused default view segment.
     * @param string ...$params Unused extra route parameters.
     * @return void
     */
    public function index(string $view = 'index', string ...$params): void
    {
        /** @var Person $people */
        $people = $this->model('Person');

        $this->view('home.index', [
            'people' => $people->all(),
            'error'  => $people->getMessage(),
        ]);
    }
}
