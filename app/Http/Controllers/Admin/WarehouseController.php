<?php

namespace App\Http\Controllers\Admin;

class WarehouseController extends ClubResourceController
{
    protected function resourceKey(): string
    {
        return 'warehouse';
    }
}
