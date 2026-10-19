<?php

namespace App\Http\Controllers\Admin;

class ClubMemberController extends ClubResourceController
{
    protected function resourceKey(): string
    {
        return 'members';
    }
}
