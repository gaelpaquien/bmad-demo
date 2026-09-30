<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class ThemeController extends Controller
{
    /**
     * Theme settings page — the choice (clair / sombre / personnalisé) and
     * the custom colours live entirely in the browser's localStorage, so
     * this renders the page with no props.
     */
    public function index(): Response
    {
        return Inertia::render('Documents/Themes');
    }
}
