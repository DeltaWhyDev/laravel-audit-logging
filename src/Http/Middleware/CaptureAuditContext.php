<?php

namespace DeltaWhyDev\AuditLog\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureAuditContext
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Generate request ID if not present
        if (!$request->header('X-Request-ID')) {
            $request->headers->set('X-Request-ID', uniqid('req_', true));
        }
        
        return $next($request);
    }
}
