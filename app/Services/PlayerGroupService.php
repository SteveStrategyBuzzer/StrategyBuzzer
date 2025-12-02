<?php

namespace App\Services;

use App\Models\PlayerGroup;
use App\Models\PlayerGroupMember;
use App\Models\User;
use Illuminate\Support\Collection;

class PlayerGroupService
{
    public function createGroup(int $userId, string $name, array $memberIds = []): PlayerGroup
    {
        $group = PlayerGroup::create([
            'user_id' => $userId,
            'name' => $name,
        ]);

        if (!empty($memberIds)) {
            $this->addMembers($group->id, $memberIds);
        }

        return $group;
    }

    public function addMembers(int $groupId, array $memberIds): void
    {
        foreach ($memberIds as $memberId) {
            PlayerGroupMember::firstOrCreate([
                'group_id' => $groupId,
                'member_user_id' => $memberId,
            ]);
        }
    }

    public function removeMembers(int $groupId, array $memberIds): void
    {
        PlayerGroupMember::where('group_id', $groupId)
            ->whereIn('member_user_id', $memberIds)
            ->delete();
    }

    public function deleteGroup(int $groupId, int $userId): bool
    {
        $group = PlayerGroup::where('id', $groupId)
            ->where('user_id', $userId)
            ->first();

        if (!$group) {
            return false;
        }

        $group->delete();
        return true;
    }

    public function renameGroup(int $groupId, int $userId, string $newName): bool
    {
        $group = PlayerGroup::where('id', $groupId)
            ->where('user_id', $userId)
            ->first();

        if (!$group) {
            return false;
        }

        $group->name = $newName;
        $group->save();
        return true;
    }

    public function getGroups(int $userId): Collection
    {
        return PlayerGroup::where('user_id', $userId)
            ->with(['memberUsers'])
            ->get()
            ->map(function ($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'member_count' => $group->memberUsers->count(),
                    'members' => $group->memberUsers->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'name' => $user->name,
                            'player_code' => $user->player_code,
                        ];
                    }),
                    'created_at' => $group->created_at->format('d/m/Y'),
                ];
            });
    }

    public function getGroup(int $groupId, int $userId): ?array
    {
        $group = PlayerGroup::where('id', $groupId)
            ->where('user_id', $userId)
            ->with(['memberUsers'])
            ->first();

        if (!$group) {
            return null;
        }

        return [
            'id' => $group->id,
            'name' => $group->name,
            'member_count' => $group->memberUsers->count(),
            'members' => $group->memberUsers->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'player_code' => $user->player_code,
                ];
            })->toArray(),
        ];
    }
}
