<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    private const LIMIT = 5;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Company::class);

        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['companies' => [], 'users' => [], 'leads' => []]);
        }

        return response()->json([
            'companies' => Company::where('name', 'like', "%{$q}%")
                ->orWhere('slug', 'like', "%{$q}%")
                ->limit(self::LIMIT)
                ->get(['id', 'name', 'slug']),
            'users' => User::where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->with('company:id,name')
                ->limit(self::LIMIT)
                ->get(['id', 'name', 'email', 'company_id']),
            'leads' => Lead::where('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('mobile', 'like', "%{$q}%")
                ->with('company:id,name')
                ->limit(self::LIMIT)
                ->get(['id', 'first_name', 'last_name', 'email', 'company_id']),
        ]);
    }
}
