<?php

namespace App\Services;

use App\Enums\LogEventTypeEnum;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use Carbon\Carbon;

class UserService
{
    public function __construct(
        private UserRepository $userRepository,
        private RoleRepository $roleRepository,

        private InviteService $inviteService,
        private LogService $logService
    ) {}

    public function createUserWithInvite($name, $email, $roleId)
    {
        $user = $this->userRepository->create([
            'name' => $name,
            'email' => $email
        ]);

        if ($roleId && $role = $this->roleRepository->findById($roleId)) {
            $role->users()->attach($user->id);
        }

        $this->inviteService->createForUserId($user->id);
        $this->logService->record(LogEventTypeEnum::USER_CREATED, [
            'user' => $user,
        ]);

        return $user;
    }

    public function updateUser($id, array $data)
    {
        $user = $this->userRepository->findById($id);
        if (!$user) return false;

        unset($data['email']);

        $user->update($data);

        $this->logService->record(LogEventTypeEnum::USER_UPDATED, [
            'user' => $user,
        ]);

        return $user;
    }

    public function deleteUser($userId)
    {
        $user = $this->userRepository->findById($userId);
        if (!$user) return false;

        $user->delete();

        $this->logService->record(LogEventTypeEnum::USER_DELETED, [
            'user' => $user
        ]);

        return true;
    }

    public function changePassword(array $data, $token)
    {
        $valid_invite = $this->inviteService->validateToken($token);
        if (!$valid_invite) return false;

        $user = $this->userRepository->findById(id: $valid_invite->user_id);
        if (!$user) return false;

        $user->password = bcrypt($data['password']);
        if (!$user->activated_at) $user->activated_at = Carbon::now();
        $user->save();

        $valid_invite->used_at = Carbon::now();
        $valid_invite->save();

        $this->logService->record(LogEventTypeEnum::USER_ACTIVATED, [
            'user' => $user
        ]);

        return true;
    }
}
