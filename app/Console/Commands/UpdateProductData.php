<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Jobs\UpdateEsDataJob;
use Illuminate\Support\Facades\Redis;

class UpdateProductData extends Command
{
    const CHUNK_COUNT = 500;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:product-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update es data';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $lastUpdateTime = Redis::get('LAST_UPDATE_TIME_PRODUCT_DATA') ?? '1970:01:01';
        $currentTime = Carbon::now();
        \DB::table('cache_products')
            ->join('products', function ($join) {
                $join->on( 'cache_products.refer_id', '=', 'products.refer_id');
                $join->on( 'cache_products.store_id', '=', 'products.store_id');
            })
            ->where('products.updated_at', '>=', $lastUpdateTime)
            ->select('cache_products.id as id', 'cache_products.refer_id as refer_id', 'cache_products.data as data', 'products.views as views', 'products.sold_count as sold_count')
            ->orderBy('cache_products.id')->chunk(UpdateProductData::CHUNK_COUNT, function ($products) use ($currentTime) {
                try {
                    dispatch(new UpdateEsDataJob($products));
                    Redis::set('LAST_UPDATE_TIME_PRODUCT_DATA', $currentTime);
                } catch (\Exception $e) {
                    Log::error($e->getMessage());
                }
            });
    }
}
