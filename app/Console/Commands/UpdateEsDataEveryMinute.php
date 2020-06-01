<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Jobs\UpdateEsDataJob;
use Elasticsearch\ClientBuilder;
use Illuminate\Support\Facades\Redis;

class UpdateEsDataEveryMinute extends Command
{
    const CHUNK_COUNT = 500;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:es-data-every-minute';

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
        $esHost = [
            env('ELASTICSEARCH_HOST', 'elasticsearch') . ':' . env('ELASTICSEARCH_PORT', '9200')
        ];
        $client = ClientBuilder::create()->setHosts($esHost)->build();
        $indexName =  env('ELASTICSEARCH_INDEX_NAME', 'mk_products');
        if (!$client->indices()->exists(['index' => $indexName])) {
            $params = [
                'index' => $indexName,
                'body' => [
                    "settings" => [
                        "analysis" => [
                            "filter" => [
                                "whitespace_replace" => [
                                    "type" => "pattern_replace",
                                    "pattern" => " ",
                                    "replacement" => "_"
                                ]
                            ],
                            "analyzer" => [
                                "analyzer_keywords" => [
                                    "type" => "custom",
                                    "tokenizer" => "keyword",
                                    "filter" => [
                                        "asciifolding",
                                        "whitespace_replace",
                                        "lowercase"
                                    ]
                                ]
                            ]
                        ]
                    ],
                    "mappings" => [
                        "properties" => [
                            "name" => [
                                "type" => "text",
                                "fields" => [
                                    "keyword" => [
                                        "type" => "text",
                                        "fielddata" => true,
                                        "analyzer" => "analyzer_keywords",
                                        "search_analyzer" => "analyzer_keywords"
                                    ]
                                ]
                            ],
                            "code" => [
                                "type" => "text",
                                "fields" => [
                                    "keyword" => [
                                        "type" => "text",
                                        "fielddata" => true,
                                        "analyzer" => "keyword",
                                        "search_analyzer" => "analyzer_keywords"
                                    ]
                                ]
                            ],
                            "attributes" => [
                                "type" => "nested",
                                "properties" => [
                                    "attributeName" => [
                                        "type" => "text",
                                        "fields" => [
                                            "keyword" => [
                                                "type" => "text",
                                                "fielddata" => true,
                                                "analyzer" => "keyword",
                                                "search_analyzer" => "analyzer_keywords"
                                            ]
                                        ],
                                    ],
                                    "attributeValue" => [
                                        "type" => "text",
                                        "fields" => [
                                            "keyword" => [
                                                "type" => "text",
                                                "fielddata" => true,
                                                "analyzer" => "keyword",
                                                "search_analyzer" => "analyzer_keywords"
                                            ]
                                        ],
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];
            $client->indices()->create($params);
        }
        $lastUpdateTime = Redis::get('LAST_UPDATE_TIME_ES_DATA') ?? '1970:01:01';
        $currentTime = Carbon::now();
        \DB::table('cache_products')
            ->join('products', function ($join) {
                $join->on( 'cache_products.refer_id', '=', 'products.refer_id');
                $join->on( 'cache_products.store_id', '=', 'products.store_id');
            })
            ->where('cache_products.updated_at', '>=', $lastUpdateTime)
            ->select('cache_products.id as id', 'cache_products.refer_id as refer_id', 'cache_products.data as data', 'products.views as views', 'products.sold_count as sold_count', 'products.status as status')
            ->chunkById(UpdateEsDataEveryMinute::CHUNK_COUNT, function ($products) use ($currentTime) {
            try {
                dispatch(new UpdateEsDataJob($products));
                Redis::set('LAST_UPDATE_TIME_ES_DATA', $currentTime);
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
        }, 'cache_products.id', 'id');
    }
}
