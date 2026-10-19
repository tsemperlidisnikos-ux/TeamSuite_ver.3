<?php

namespace App\Http\Controllers\Admin;

class PhotoController extends ClubResourceController
{
    protected function resourceKey(): string
    {
        return 'photos';
    }
}
