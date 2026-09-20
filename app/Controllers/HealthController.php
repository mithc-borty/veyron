<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

/**
 * Liveness endpoint. Public: no authentication required.
 */
final class HealthController
{
    /**
     * GET /api/v1/health
     */
    public function index(Request $request): void
    {
        Response::success('API is healthy');
    }
}