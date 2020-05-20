<?php

namespace App\Jobs;

use Elasticsearch\ClientBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateEsDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $products;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($products)
    {
        $this->products = $products;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $esHost = [
            env('ELASTICSEARCH_HOST', 'elasticsearch') . ':9200'
        ];
        $client = ClientBuilder::create()->setHosts($esHost)->build();
        $indexName = 'mk_products';
        foreach ($this->products as $product) {
            $data = json_decode($product->data, true);
            $params = [
                'index' => $indexName,
                'id' => $product->refer_id,
                'body' => $data ? $data : [],
                'type' => '_doc'
            ];

            try {
                $client->index($params);
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
        }
    }
}
