<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeadLogRequest;
use App\Models\LeadLog;
use Illuminate\Http\JsonResponse;

class LeadLogController extends Controller
{
    public function store(StoreLeadLogRequest $request): JsonResponse
    {
        $leadLog = LeadLog::create($request->validated());

        return response()->json([
            'data' => [
                'id' => $leadLog->id,
                'created_at' => $leadLog->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
