<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    public function terms(): Response
    {
        $content = File::get(resource_path('content/terms-of-use.md'));

        return Inertia::render('legal/Terms', [
            'content' => $content,
        ]);
    }

    public function privacy(): Response
    {
        $content = File::get(resource_path('content/privacy-policy.md'));

        return Inertia::render('legal/Privacy', [
            'content' => $content,
        ]);
    }
}
