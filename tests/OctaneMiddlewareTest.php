<?php

namespace Tideways\LaravelOctane;

use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class OctaneMiddlewareTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        if (!class_exists('Tideways\Profiler')) {
            $this->markTestSkipped('Tideways\Profiler is not installed.');
        }

        \Tideways\Profiler::stop();
    }

    public function testMiddleware(): void
    {
        $payload = withTidewaysDaemon(function () {
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

function withTidewaysDaemon(\Closure $callback)
{
    $address = "tcp://127.0.0.1:64111";
    ini_set('tideways.connection', $address);
    ini_set('tideways.api_key', 'abcdefg');
    ini_set('tideways.sample_rate', 0);
    ini_set('tideways.monitor', 'basic');
    putenv('TIDEWAYS_CONNECTION=' . $address);
    $_SERVER['TIDEWAYS_CONNECTION'] = $address;
    if (ini_get("tideways.connection") !== $address) {
        throw new \RuntimeException('Could not set tideways.connection to ' . $address);
    }

    $phpBinaryFinder = new PhpExecutableFinder();
    $daemon = new Process([
        $phpBinaryFinder->find(),
        __DIR__ . '/daemon.inc',
        $address,
    ]);
    $daemon->setTimeout(1);
    $daemon->start();

    $i = 0;
    while (($i++ < 30) && !($fp = @stream_socket_client($address, $errno, $errstr, 1))) {
        usleep(10000);
    }
    if ($fp) {
        fwrite($fp, json_encode(['type' => 'ping']));
        fclose($fp);
    } else {
        $daemon->stop();
        throw new \Exception('Daemon did not start properly.');
    }

    try {
        $callback();
    } catch (\Throwable) {

    }

    $daemon->wait();

    return json_decode($daemon->getOutput(), true, flags: JSON_THROW_ON_ERROR);
}
