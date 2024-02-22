<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Zipkin\Propagation\Map;
use Zipkin\TracingBuilder;
use Zipkin\Samplers\BinarySampler;
use Zipkin\Endpoint;
use Zipkin\Reporters\Http;
use Zipkin\Reporters\Http\CurlFactoryBuilder;
use Zipkin\Reporters\Http\Request\TracingHeaders;
use Zipkin\Kind;
use Zipkin\Server;
use Illuminate\Support\Facades\Log;

class ValueController extends Controller
{
    function create_tracing($localServiceName, $localServiceIPv4, $localServicePort = null)
    {
        $httpReporterURL = env('HTP_REPTORTER_URL', 'http://localhost:9411/api/v2/spans');
        $endpoint = Endpoint::create($localServiceName, $localServiceIPv4, null, $localServicePort);
        $reporter = new Http(['endpoint_url' => $httpReporterURL]);
        $sampler = BinarySampler::createAsAlwaysSample();

        $tracing = TracingBuilder::create()
            ->havingLocalEndpoint($endpoint)
            ->havingSampler($sampler)
            ->havingReporter($reporter)
            ->build();

        return $tracing;
    }

    public function getValue(Request $request)
    {

        Log::info('Start Tracer:');
        $tracing = $this->create_tracing('ServiceA', '127.0.0.2');
        $carrier = array_map(function ($header) {
            return $header[0];
        }, $request->headers->all());

        /* Extracts the context from the HTTP headers */
        $extractor = $tracing->getPropagation()->getExtractor(new Map());
        $extractedContext = $extractor($carrier);
        Log::info($carrier);

        $tracer = $tracing->getTracer();
        $span = $tracer->nextSpan($extractedContext);
        $span->start();
        $span->setKind(Kind\SERVER);
        $span->setName('parse_request');

        $childSpan = $tracer->newChild($span->getContext());
        $childSpan->start();
        $childSpan->setKind(Kind\CLIENT);
        $childSpan->setName('user:get_list:mysql_query');

        usleep(50000);
        $childSpan->finish();
        $span->finish();

        register_shutdown_function(function () use ($tracer) {
            $tracer->flush();
        });
        Log::info('End Tracer:');

        return '0';

    }
}
