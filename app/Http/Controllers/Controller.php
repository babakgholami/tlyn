<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="TLYN API LIVE TEST",
 *      description="TLYN API V1",
 * )
 * @OA\SecurityScheme(
 *       type="http",
 *       description=" Use login to get the passport token",
 *       name="Authorization",
 *       in="header",
 *       scheme="bearer",
 *       bearerFormat="Passport",
 *       securityScheme="bearerAuth",
 *   )
 */
class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
}
