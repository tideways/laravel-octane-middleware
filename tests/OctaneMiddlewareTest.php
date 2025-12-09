<?php

namespace Tideways\LaravelOctane;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
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
        }, function (\Throwable $e) {
            // ignore
        });

       $this->assertCount(2, $payload);
       $this->assertEquals('1', $payload[0]['a']['tw.web']);
       $this->assertEquals('cli', $payload[0]['a']['php.sapi']);

       $this->assertEquals(InvalidArgumentException::class, $payload[1]['a']['error.type']);
    }

    public function testTidewaysQueryIsValid(): void
    {
        $payload = withTidewaysDaemon(function () {
            $request = Request::create('/?_tideways[method]=&_tideways[user]=foo&_tideways[time]=2145913200&_tideways[hash]=8627e21a5dd6bc1f97106b61c2b8d914147f207cc4c00d65592ec6463fd93583', 'GET');

            $middleware = new OctaneMiddleware();
            $response = $middleware->handle($request, function () {
                return new Response(\Tideways\Profiler::isProfiling() ? 'valid' : 'invalid');
            });

            $this->assertEquals('valid', $response->getContent());
        });

        $this->assertEquals('foo', $payload[0]['a']['tw.uid']);
        $this->assertEquals('cli', $payload[0]['a']['php.sapi']);
    }

    public function testTidewaysQueryIsInvalid(): void
    {
        $payload = withTidewaysDaemon(function () {
            $request = Request::create('/?_tideways[method]=&_tideways[user]=foo&_tideways[time]=2145913200&_tideways[hash]=invalid', 'GET');

            $middleware = new OctaneMiddleware();
            $response = $middleware->handle($request, function () {
                return new Response(\Tideways\Profiler::isProfiling() ? 'valid' : 'invalid');
            });

            $this->assertEquals('invalid', $response->getContent());
        });

        $this->assertNull($payload[0]['a']['tw.uid']);
        $this->assertEquals('cli', $payload[0]['a']['php.sapi']);
    }

    public function testTidewaysQueryIsIncorrectType(): void
    {
        $payload = withTidewaysDaemon(function () {
            $request = Request::create('/?_tideways=string', 'GET');

            $middleware = new OctaneMiddleware();
            $response = $middleware->handle($request, function () {
                return new Response(\Tideways\Profiler::isProfiling() ? 'valid' : 'invalid');
            });

            $this->assertEquals('invalid', $response->getContent());
        });

        $this->assertNull($payload[0]['a']['tw.uid']);
        $this->assertEquals('cli', $payload[0]['a']['php.sapi']);
    }
}

function withTidewaysDaemon(\Closure $callback, ?\Closure $onError = null)
{
    $address = "tcp://127.0.0.1:64111";
    ini_set('tideways.connection', $address);
    ini_set('tideways.api_key', 'foo');
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
    } catch (\Throwable $e) {
        if ($onError !== null) {
            $onError($e);
        } else {
            throw $e;
        }
    } finally {
        try {
            $daemon->wait();
        } catch (ProcessTimedOutException $e) {
            $daemon->stop(1.0, SIGKILL);

            throw $e;
        }
    }

    return json_decode($daemon->getOutput(), true, flags: JSON_THROW_ON_ERROR);
}
