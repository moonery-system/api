<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
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

    public function store(UserRequest $userRequest)
    {
        $userValidated = $userRequest->validated();
        
        $user = $this->userService->createUserWithInvite(
            $userValidated['name'],
            $userValidated['email'],
            $userValidated['role_id'] ?? null
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
        $this->userService->deleteUser($id);

        return ApiResponse::success();
    }
}