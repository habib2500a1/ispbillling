<?php

namespace App\Http\Controllers;

use App\Services\Saas\SaasDomain;
use Illuminate\Http\Response;

class SaasTlsAskController extends Controller
{
    public function __invoke(): Response
    {
        $domain = request()->query('domain', request()->query('host'));
        if (SaasDomain::isRegistered(is_string($domain) ? $domain : null)) {
            return response('ok', 200);
        }

        return response('no', 404);
    }
}
