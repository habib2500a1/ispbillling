<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Mobile\MobileAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function ask(Request $request, MobileAiService $ai): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $data = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
        ]);

        return response()->json($ai->reply($customer, $data['question']));
    }
}
