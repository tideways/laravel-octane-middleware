<?php

namespace Tideways\LaravelOctane;

use Closure;
use Illuminate\Http\Request;
use Throwable;

class OctaneMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (!class_exists('Tideways\Profiler') || !in_array(php_sapi_name(), ['cli', 'frankenphp'], true)) {
            // only run when Tideways is installed and the CLI/frankenphp sapi is used (thats how Swoole/RR work)
            return $next($request);
        }

        $developerSession = null;
        if ($request->query->has('_tideways')) {
            $developerSession = http_build_query((array) $request->query->get('_tideways'));
        } else if ($request->headers->has('X-TIDEWAYS-PROFILER')) {
            $developerSession = $request->headers->get('X-TIDEWAYS-PROFILER');
        } else if ($request->cookies->has('TIDEWAYS_SESSION')) {
            $developerSession = $request->cookies->get('TIDEWAYS_SESSION');
        }

        $service = ini_get('tideways.service') ?: 'web';

        \Tideways\Profiler::start(['service' => $service, 'developer_session' => $developerSession]);
        \Tideways\Profiler::setCustomVariable('http.host', $request->getHttpHost());
        \Tideways\Profiler::setCustomVariable('http.method', $request->getMethod());
        \Tideways\Profiler::setCustomVariable('http.url', $request->getPathInfo());

        if (method_exists('Tideways\Profiler', 'markAsWebTransaction')) {
            \Tideways\Profiler::markAsWebTransaction();
        }

        $referenceId = $request->query->get('_tideways_ref', $request->headers->get('X-Tideways-Ref'));
        if ($request->cookies->has('TIDEWAYS_REF')) {
            $referenceId = $request->cookies->get('TIDEWAYS_REF');
        }

        if ($referenceId) {
            \Tideways\Profiler::setCustomVariable('tw.ref', $referenceId);
        }

        try {
            return $this->doHandle($request, $next);
        } catch (Throwable $e) {
            \Tideways\Profiler::logException($e);
            throw $e;
        } finally {
            \Tideways\Profiler::stop();
        }
    }

    /**
     * Call the next middleware handler.
     *
     * We need this intermediate function as a hook for the Tideways PHP extension.
     *
     * @return mixed
     */
    private function doHandle(Request $request, Closure $next)
    {
        return $next($request);
    }
}
