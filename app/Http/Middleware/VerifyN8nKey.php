<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyN8nKey
{
    /**
     * Lets only n8n through to its callbacks: the request must carry the
     * shared key from N8N_SHOPAR_KEY in the X-ShopAR-Key header.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) config('services.n8n.shopar_key');

        abort_if($key === '', Response::HTTP_SERVICE_UNAVAILABLE, 'N8N_SHOPAR_KEY is not set.');
        abort_unless(hash_equals($key, (string) $request->header('X-ShopAR-Key')), Response::HTTP_UNAUTHORIZED, 'Wrong or missing X-ShopAR-Key.');

        return $next($request);
    }
}
