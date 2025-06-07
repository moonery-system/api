<?php

namespace App\Http\Controllers;

use App\Models\Invite;
use App\Models\Role;
use App\Models\User;
use App\Utils\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Str;

class UserController extends Controller
{
    public function index()
    {
        $users = User::all();
        return ApiResponse::success($users);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|max:255|min:1',
            'email' => 'required|email|unique:users,email'
        ]);

        $name = $request->name;
        $email = $request->email;
        $role = Role::find($request->role_id);

        $user = new User();
        $user->name = $name;
        $user->email = $email;
        $user->save();

        if ($role) {
            $role->users()->attach($user->id);
        }

        return ApiResponse::success($user);
    }

    public function show($id)
    {
        $user = User::find($id);
        if(!$user) return ApiResponse::notFound();

        return ApiResponse::success($user);
    }

    public function update(Request $request, $id)
    {
        $user = User::find($id);
        if(!$user) return ApiResponse::notFound();

        //

        return true;
    }

    public function destroy($id)
    {
        $user = User::find($id);
        if(!$user) return ApiResponse::notFound();

        $user->delete();

        return ApiResponse::success();
    }
}
