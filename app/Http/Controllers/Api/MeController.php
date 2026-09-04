<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load('role.permissions', 'department');

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'department' => $user->department?->name,
            'role' => $user->role->name,
            'roleSlug' => $user->role->slug,
            'permissions' => $user->role->permissions->pluck('slug'),
        ]]);
    }
}
