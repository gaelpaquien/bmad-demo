<?php

namespace App\Support;

use Inertia\Inertia;

/**
 * Single write point for the success toast shown after an important action
 * (creation, modification, deletion). Flashed through Inertia's native flash
 * data, so it survives the redirect that follows and is read once by the
 * client (`router.on('flash')` in app.js → ToastContainer.vue).
 */
final class Toast
{
    public static function success(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
    }
}
