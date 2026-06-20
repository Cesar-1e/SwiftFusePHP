<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Person;
use SwiftFuse\Http\Controller;

/**
 * People controller.
 *
 * Demonstrates an AJAX-driven list: index() renders an empty shell, and list()
 * returns the people as JSON, which the browser fetches with the helpers in
 * public/js/main.js. This complements HomeController, which renders the same
 * data server-side.
 */
final class PeopleController extends Controller
{
    /**
     * The view folder for this controller.
     *
     * @var string
     */
    protected string $folder = 'people';

    /**
     * Render the page shell; the table is filled later via AJAX.
     *
     * @param string $view Unused default view segment.
     * @param string ...$params Unused extra route parameters.
     * @return void
     */
    public function index(string $view = 'index', string ...$params): void
    {
        $this->view('people.index');
    }

    /**
     * Return the list of people as JSON for the AJAX request.
     *
     * @return never
     */
    public function list(): never
    {
        /** @var Person $people */
        $people = $this->model('Person');
        $data = $people->all();

        $this->json([
            'ok' => $people->getMessage() === null,
            'data' => $data,
            'message' => $people->getMessage(),
        ]);
    }
}
