<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Jobs\UpdateEsDataJob;
use Elasticsearch\ClientBuilder;

class UpdateEsData extends Command
{
    const CHUNK_COUNT = 1000;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:es-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
                            ],
                            "inventories" => [
                                'type' => 'nested'
                            ],
                            "priceBooks" => [
                                'type' => 'nested'
                            ]
                        ]
                    ]
                ]
            ];
            $client->indices()->create($params);
        }
        \DB::table('cache_products')->chunkById(UpdateEsData::CHUNK_COUNT, function ($products) {
            try {
                dispatch(new UpdateEsDataJob($products));
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
        });
    }
}
