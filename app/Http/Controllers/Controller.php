<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * @var list<int>
     */
    protected const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    protected function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', self::PER_PAGE_OPTIONS[0]);

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::PER_PAGE_OPTIONS[0];
    }
}
