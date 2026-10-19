<?php

namespace App\Jobs;

use App\Models\WarehouseProduct;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class WarehouseLowStockJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $low = WarehouseProduct::query()->withoutGlobalScopes()
            ->whereColumn('stock_qty', '<=', 'low_stock_threshold')
            ->count();

        Log::info('WarehouseLowStockJob', ['low_stock_products' => $low]);
    }
}
