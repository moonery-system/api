<?php

namespace App\Http\Controllers;

use App\Models\Invite;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use App\Utils\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private UserService $userService){}

    public function index()
    {
        $users = User::all();
        return ApiResponse::success($users);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|max:255|min:1',
            'email' => 'required|email|unique:users,email'
        ]);
        
        $user = $this->userService->createUserWithInvite(
            $validated['name'],
            $validated['email'],
            $request->role_id
        );

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
