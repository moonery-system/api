<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddressRequest;
use App\Http\Requests\UserRequest;
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

    public function store(UserRequest $userRequest, AddressRequest $addressRequest)
    {

        $userValidated = $userRequest->validated();
        $addressValidated = $addressRequest->validated();

        $clientRole = $this->userService->getClientRoleId();

        $user = $this->userService->createUserWithInvite(
            $userValidated['name'],
            $userValidated['email'],
            $clientRole->id
        );

        $address = [
            'address_line' => $addressValidated['address_line'],
            'neighborhood' => $addressValidated['neighborhood'],
            'city' => $addressValidated['city'],
            'state' => $addressValidated['state'],
            'zip_code' => $addressValidated['zip_code'],
            'complement' => $addressValidated['complement'],
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
