<?php

namespace App\Http\Controllers\Admin;

class ProtocolController extends ClubResourceController
{
    protected function resourceKey(): string
    {
        return 'protocol';
    }
}
