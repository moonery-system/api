<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;

class UserService
{
    public function __construct(private InviteService $inviteService) {}

    public function createUserWithInvite($name, $email, $roleId)
    {
        $user = User::create([
            'name' => $name,
            'email' => $email
        ]);

        if ($roleId && $role = Role::find($roleId)) {
            $role->users()->attach($user->id);
        }

        $this->inviteService->createForUser($user->id);

        return $user;
    }
}
