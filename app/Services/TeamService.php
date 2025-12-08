<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamInvitation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TeamService
{
    protected PlayerContactService $contactService;

    public function __construct(PlayerContactService $contactService)
    {
        $this->contactService = $contactService;
    }

    protected function addTeamMemberContacts(User $newMember, Team $team): void
    {
        foreach ($team->members as $existingMember) {
            if ($existingMember->id !== $newMember->id) {
                $this->contactService->ensureContactExists($newMember->id, $existingMember->id);
                $this->contactService->ensureContactExists($existingMember->id, $newMember->id);
            }
        }
    }

    public function createTeam(User $captain, string $name, ?string $emblemCategory = 'animals', ?int $emblemIndex = 1, ?string $customEmblemPath = null): Team
    {
        if ($captain->teams()->exists()) {
            throw new \Exception('Vous êtes déjà dans une équipe.');
        }

        if (strlen($name) > 10) {
            throw new \Exception('Le nom ne doit pas dépasser 10 caractères.');
        }

        return DB::transaction(function () use ($captain, $name, $emblemCategory, $emblemIndex, $customEmblemPath) {
            $team = Team::create([
                'name' => $name,
                'tag' => strtoupper(substr($name, 0, 4)),
                'captain_id' => $captain->id,
                'division' => 'bronze',
                'points' => 0,
                'level' => 1,
                'emblem_category' => $emblemCategory ?: 'animals',
                'emblem_index' => $emblemIndex ?: 1,
                'custom_emblem_path' => $customEmblemPath,
            ]);

            TeamMember::create([
                'team_id' => $team->id,
                'user_id' => $captain->id,
                'role' => 'captain',
                'joined_at' => now(),
            ]);

            return $team->fresh(['captain', 'teamMembers.user']);
        });
    }

    public function invitePlayer(Team $team, User $inviter, string $playerIdentifier): TeamInvitation
    {
        if ($team->captain_id !== $inviter->id) {
            throw new \Exception('Seul le capitaine peut inviter des joueurs.');
        }

        if ($team->teamMembers()->count() >= 5) {
            throw new \Exception('L\'équipe est complète (5 joueurs maximum).');
        }

        $player = User::where('player_code', $playerIdentifier)
            ->orWhere('name', $playerIdentifier)
            ->first();
        if (!$player) {
            throw new \Exception('Joueur non trouvé.');
        }

        if ($player->teams()->exists()) {
            throw new \Exception('Ce joueur est déjà dans une équipe.');
        }

        $existingInvitation = TeamInvitation::where('team_id', $team->id)
            ->where('user_id', $player->id)
            ->where('status', 'pending')
            ->first();

        if ($existingInvitation) {
            throw new \Exception('Une invitation est déjà en attente pour ce joueur.');
        }

        return TeamInvitation::create([
            'team_id' => $team->id,
            'user_id' => $player->id,
            'invited_by' => $inviter->id,
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function acceptInvitation(TeamInvitation $invitation, User $user): void
    {
        if ($invitation->user_id !== $user->id) {
            throw new \Exception('Cette invitation ne vous est pas destinée.');
        }

        if ($invitation->status !== 'pending') {
            throw new \Exception('Cette invitation n\'est plus valide.');
        }

        if ($invitation->expires_at && $invitation->expires_at->isPast()) {
            $invitation->update(['status' => 'expired']);
            throw new \Exception('Cette invitation a expiré.');
        }

        $team = $invitation->team;
        if ($team->teamMembers()->count() >= 5) {
            throw new \Exception('L\'équipe est complète.');
        }

        if ($user->teams()->exists()) {
            throw new \Exception('Vous êtes déjà dans une équipe.');
        }

        DB::transaction(function () use ($invitation, $user, $team) {
            TeamMember::create([
                'team_id' => $team->id,
                'user_id' => $user->id,
                'role' => 'member',
                'joined_at' => now(),
            ]);

            $invitation->update(['status' => 'accepted']);
        });

        $team->load('members');
        $this->addTeamMemberContacts($user, $team);
    }

    public function declineInvitation(TeamInvitation $invitation, User $user): void
    {
        if ($invitation->user_id !== $user->id) {
            throw new \Exception('Cette invitation ne vous est pas destinée.');
        }

        $invitation->update(['status' => 'declined']);
    }

    public function addMember(Team $team, User $user): void
    {
        if ($team->teamMembers()->count() >= 5) {
            throw new \Exception('L\'équipe est complète.');
        }

        if ($user->teams()->exists()) {
            throw new \Exception('Ce joueur est déjà dans une équipe.');
        }

        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $team->load('members');
        $this->addTeamMemberContacts($user, $team);
    }

    public function leaveTeam(Team $team, User $user): void
    {
        $member = $team->teamMembers()->where('user_id', $user->id)->first();
        if (!$member) {
            throw new \Exception('Vous n\'êtes pas dans cette équipe.');
        }

        if ($team->captain_id === $user->id) {
            if ($team->teamMembers()->count() > 1) {
                $newCaptain = $team->teamMembers()
                    ->where('user_id', '!=', $user->id)
                    ->first();
                
                $team->update(['captain_id' => $newCaptain->user_id]);
                $newCaptain->update(['role' => 'captain']);
            } else {
                $team->delete();
                return;
            }
        }

        $member->delete();
    }

    public function kickMember(Team $team, User $captain, int $memberId): void
    {
        if ($team->captain_id !== $captain->id) {
            throw new \Exception('Seul le capitaine peut expulser des membres.');
        }

        if ($memberId === $captain->id) {
            throw new \Exception('Le capitaine ne peut pas s\'auto-expulser.');
        }

        $member = $team->teamMembers()->where('user_id', $memberId)->first();
        if (!$member) {
            throw new \Exception('Ce joueur n\'est pas dans l\'équipe.');
        }

        $member->delete();
    }

    public function findTeamsByDivision(string $division, int $limit = 20): array
    {
        return Team::with(['captain', 'teamMembers.user'])
            ->where('division', $division)
            ->where('is_recruiting', true)
            ->has('teamMembers', '<', 5)
            ->orderBy('points', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function updateRecruitingStatus(Team $team, User $captain, bool $isRecruiting): void
    {
        if ($team->captain_id !== $captain->id) {
            throw new \Exception('Seul le capitaine peut modifier le statut de recrutement.');
        }

        $team->update(['is_recruiting' => $isRecruiting]);
    }
}
