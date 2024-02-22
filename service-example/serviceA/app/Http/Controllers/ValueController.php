<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Zipkin\Endpoint;
use Zipkin\Propagation\Map;
use Zipkin\Reporters\Http;
use Zipkin\Samplers\BinarySampler;
use Zipkin\TracingBuilder;;
use Zipkin\Reporters\Http\CurlFactoryBuilder;
use Zipkin\Reporters\Http\Request\TracingHeaders;
use Zipkin\Kind;
use Zipkin\Server;
use Zipkin\Propagation\DefaultSamplingFlags;
use Zipkin\Timestamp;
use GuzzleHttp\Client;



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

    public function get_value()
    {

        $tracing = $this->create_tracing('ServiceB', '127.0.0.1');
        $tracer = $tracing->getTracer();
        $defaultSamplingFlags = DefaultSamplingFlags::createAsSampled();

        /* Creates the main span */
        $span = $tracer->newTrace($defaultSamplingFlags);
        $span->start(Timestamp\now());
        $span->setName('parse_request');
        $span->setKind(Kind\SERVER);

        usleep(100 * mt_rand(1, 3));

        /* Creates the span for getting the users list */
        $childSpan = $tracer->newChild($span->getContext());
        $childSpan->start();
        $childSpan->setKind(Kind\CLIENT);
        $childSpan->setName('users:get_list');
        $headers = [];

        /* Injects the context into the wire */
        $injector = $tracing->getPropagation()->getInjector(new Map());
        $injector($childSpan->getContext(), $headers);

        $client = new Client();
        $serviceUrl = env('serviceUrl', 'http://127.0.0.1:8003/api/value');
        $childSpan->annotate('request_started', Timestamp\now());
        $response = $client->request('POST', $serviceUrl, ['headers' => $headers]);
        $data = $response->getBody()->getContents();

        $childSpan->annotate('request_finished', Timestamp\now());
        $childSpan->finish();
        $span->finish();

        /* Sends the trace to zipkin once the response is served */
        register_shutdown_function(function () use ($tracer) {
            $tracer->flush();
        });
        return $data;
    }
}
