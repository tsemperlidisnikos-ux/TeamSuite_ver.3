<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\PublicLegalDocuments;
use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    public function __invoke(?string $doc = null): Response
    {
        $resolved = PublicLegalDocuments::resolveDoc($doc);

        return Inertia::render('Public/Legal', [
            'doc' => $resolved,
            'docs' => PublicLegalDocuments::docs(),
            'html' => PublicLegalDocuments::html($resolved),
        ]);
    }
}
