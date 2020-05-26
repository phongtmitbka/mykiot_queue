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
            env('ELASTICSEARCH_HOST', 'elasticsearch') . ':' . env('ELASTICSEARCH_PORT', '9200')
        ];
        $client = ClientBuilder::create()->setHosts($esHost)->build();
        $indexName =  env('ELASTICSEARCH_INDEX_NAME', 'mk_products');
        foreach ($this->products as $product) {
            $data = json_decode($product->data, true);
            $data['referId'] = intval($product->refer_id);
            $params = [
                'index' => $indexName,
                'id' => $product->id,
                'body' => $data ? $data : [],
                'type' => '_doc'
            ];
            $client->index($params);
        }
    }
}
