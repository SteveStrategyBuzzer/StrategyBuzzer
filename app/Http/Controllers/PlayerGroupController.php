<?php

namespace App\Http\Controllers;

use App\Services\PlayerGroupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlayerGroupController extends Controller
{
    private PlayerGroupService $groupService;

    public function __construct(PlayerGroupService $groupService)
    {
        $this->groupService = $groupService;
    }

    public function index()
    {
        $user = Auth::user();
        $groups = $this->groupService->getGroups($user->id);

        return response()->json([
            'success' => true,
            'groups' => $groups,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'member_ids' => 'array',
            'member_ids.*' => 'integer|exists:users,id',
        ]);

        $user = Auth::user();
        
        $group = $this->groupService->createGroup(
            $user->id,
            $request->name,
            $request->member_ids ?? []
        );

        return response()->json([
            'success' => true,
            'group' => $this->groupService->getGroup($group->id, $user->id),
            'message' => __('Groupe créé avec succès'),
        ]);
    }

    public function show(int $groupId)
    {
        $user = Auth::user();
        $group = $this->groupService->getGroup($groupId, $user->id);

        if (!$group) {
            return response()->json([
                'success' => false,
                'message' => __('Groupe introuvable'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'group' => $group,
        ]);
    }

    public function update(Request $request, int $groupId)
    {
        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $user = Auth::user();
        $success = $this->groupService->renameGroup($groupId, $user->id, $request->name);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => __('Groupe introuvable'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => __('Groupe renommé'),
        ]);
    }

    public function destroy(int $groupId)
    {
        $user = Auth::user();
        $success = $this->groupService->deleteGroup($groupId, $user->id);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => __('Groupe introuvable'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => __('Groupe supprimé'),
        ]);
    }

    public function addMembers(Request $request, int $groupId)
    {
        $request->validate([
            'member_ids' => 'required|array',
            'member_ids.*' => 'integer|exists:users,id',
        ]);

        $user = Auth::user();
        $group = $this->groupService->getGroup($groupId, $user->id);

        if (!$group) {
            return response()->json([
                'success' => false,
                'message' => __('Groupe introuvable'),
            ], 404);
        }

        $this->groupService->addMembers($groupId, $request->member_ids);

        return response()->json([
            'success' => true,
            'group' => $this->groupService->getGroup($groupId, $user->id),
            'message' => __('Membres ajoutés'),
        ]);
    }

    public function removeMembers(Request $request, int $groupId)
    {
        $request->validate([
            'member_ids' => 'required|array',
            'member_ids.*' => 'integer|exists:users,id',
        ]);

        $user = Auth::user();
        $group = $this->groupService->getGroup($groupId, $user->id);

        if (!$group) {
            return response()->json([
                'success' => false,
                'message' => __('Groupe introuvable'),
            ], 404);
        }

        $this->groupService->removeMembers($groupId, $request->member_ids);

        return response()->json([
            'success' => true,
            'group' => $this->groupService->getGroup($groupId, $user->id),
            'message' => __('Membres retirés'),
        ]);
    }
}
