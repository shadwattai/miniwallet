<?php

namespace App\Http\Controllers\Artifacts;

use Inertia\Inertia;
use App\Services\AppService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\{DB, Log, Auth, Cache, Storage};
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class ConstantsController extends Controller
{
    public function getAppConstants()
    {
        $user = Auth::user();
        if (!$user) {
            throw new UnauthorizedHttpException('You must be logged in to access this resource.');
        }

        
    }
 
}
