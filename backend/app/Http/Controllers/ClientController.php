<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Services\ClientService;
use App\Services\UserService;
use App\Utils\ApiResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function __construct(private UserService $userService, private ClientService $clientService) {}

    public function index()
    {
        //
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|max:255|min:1',
            'email' => 'required|email|unique:users,email',
            'address_line' => 'required|max:255',
            'neighborhood' => 'required|max:255',
            'city' => 'required|max:255',
            'state' => 'required|max:255',
            'zip_code' => 'required|max:255',
            'complement' => 'max:255',
        ]);

        $clientRole = Role::where('name', 'Client')->firstOrFail();

        $user = $this->userService->createUserWithInvite(
            $validated['name'],
            $validated['email'],
            $clientRole->id
        );

        $address = [
            'address_line' => $validated['address_line'],
            'neighborhood' => $validated['neighborhood'],
            'city' => $validated['city'],
            'state' => $validated['state'],
            'zip_code' => $validated['zip_code'],
            'complement' => $validated['complement'],
        ];

        $this->clientService->createClientAddress($user->id, $address);

        return ApiResponse::success($user);
    }

    public function show($id)
    {
        //
    }

    public function update(Request $request, $id)
    {
        //
    }

    public function destroy($id)
    {
        //
    }
}
