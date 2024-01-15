<?php

namespace Tideways\LaravelOctane;

use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class OctaneMiddlewareTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        if (!class_exists('Tideways\Profiler')) {
            $this->markTestSkipped('Tideways\Profiler is not installed.');
        }

        $this->assertEquals('basic', ini_get('tideways.monitor'));

        \Tideways\Profiler::stop();
    }

    public function testMiddleware(): void
    {
        $payload = withTideawysDaemon(function () {
            $request = Request::create('/', 'GET');
            $request->cookies->set('TIDEWAYS_SESSION', 'foo');
            $request->cookies->set('TIDEWAYS_REF', 'bar');

            $middleware = new OctaneMiddleware();
            $middleware->handle($request, function () {
                throw new InvalidArgumentException('failing');
            });
        });

       $this->assertCount(2, $payload);
       $this->assertEquals('1', $payload[0]['a']['tw.web']);
       $this->assertEquals('cli', $payload[0]['a']['php.sapi']);

       $this->assertEquals(InvalidArgumentException::class, $payload[1]['a']['error.type']);
    }
}

function withTideawysDaemon(\Closure $callback = null, $flags = 0)
{
    $callback = $callback ?: function() {};
    $address = "tcp://127.0.0.1:64111";
    ini_set("tideways.connection", $address);
    ini_set('tideways.api_key', 'abcdefg');
    ini_set('tideways.sample_rate', 0);
    if (ini_get("tideways.connection") !== $address) {
        throw new \RuntimeException('Could not set tideways.connection to ' . $address);
    }
    $server = @stream_socket_server($address, $error);
    if (!$server) {
        throw new \RuntimeException('Unable to create AF_INET socket [server]: Already running on ' . $address);
    }

    try {
        $callback();
    } catch (InvalidArgumentException $e) {
        // ignore
    }
    \Tideways\Profiler::stop();

    /* Accept that connection */
    $socket = stream_socket_accept($server, 1);
    if (!$socket) {
        throw new \RuntimeException('Unable to accept connection');
    }

    $response = '';
    do {
        $response .= fread($socket, 65355);
    } while (!feof($socket));

    fclose($socket);
    fclose($server);

    $data = json_decode($response, true);

    if (isset($data['payload'])) {
        return $data['payload'];
    }
}
