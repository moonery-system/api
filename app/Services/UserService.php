<?php

namespace App\Services;

use App\Contracts\Repositories\RoleInterface;
use App\Contracts\Repositories\UserInterface;
use App\Enums\LogEventTypeEnum;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UserService
{
    public function __construct(
        private UserInterface $userRepository,
        private RoleInterface $roleRepository,

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

        $wasActivation = !$user->activated_at;

        // Senha e convite gravam juntos: erro no meio deixaria a senha trocada com o
        // token ainda valido, ou o contrario.
        DB::transaction(function () use ($user, $valid_invite, $data, $wasActivation) {
            $user->password = bcrypt($data['password']);
            if ($wasActivation) $user->activated_at = Carbon::now();
            $user->save();

            $valid_invite->used_at = Carbon::now();
            $valid_invite->save();

            $this->logService->record(
                $wasActivation ? LogEventTypeEnum::USER_ACTIVATED : LogEventTypeEnum::USER_PASSWORD_RESET,
                ['user' => $user],
                $user->id
            );
        });

        return true;
    }
}
