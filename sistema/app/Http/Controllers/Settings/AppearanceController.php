<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AppearanceRequest;
use Illuminate\Http\JsonResponse;

class AppearanceController extends Controller
{
    public function update(AppearanceRequest $request): JsonResponse
    {
        $appearance = $request->validated('appearance');
        $request->user()->forceFill(['appearance' => $appearance])->save();

        return response()->json(['appearance' => $appearance])->cookie('appearance', $appearance, 525600, '/', null, $request->isSecure(), false, false, 'lax');
    }
}
