<?php

namespace App\Http\Controllers\Admin;

class PartnerController extends ClubResourceController
{
    protected function resourceKey(): string
    {
        return 'partners';
    }
}
