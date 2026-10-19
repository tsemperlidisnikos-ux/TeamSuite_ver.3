<?php

namespace App\Http\Controllers\Admin;

class MatchController extends ClubResourceController
{
    protected function resourceKey(): string
    {
        return 'matches';
    }
}
