<?php

namespace App\Http\Controllers;

use App\Services\Rbac\RbacPayloadBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RbacController extends Controller
{
    public function me(Request $request, RbacPayloadBuilder $rbacPayload): JsonResponse
    {
        return response()->json($rbacPayload->build($request->user()));
    }
}
