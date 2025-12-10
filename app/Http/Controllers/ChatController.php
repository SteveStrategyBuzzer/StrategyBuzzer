<?php

namespace App\Http\Controllers;

use App\Services\PlayerMessageService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    protected PlayerMessageService $messageService;

    public function __construct(PlayerMessageService $messageService)
    {
        $this->messageService = $messageService;
    }

    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'receiver_id' => 'required|integer|exists:users,id',
            'message' => 'required|string|max:500',
            'related_match_id' => 'nullable|integer|exists:duo_matches,id',
        ]);

        $senderId = Auth::id();
        $receiverId = $request->input('receiver_id');

        if ($senderId === $receiverId) {
            return response()->json([
                'success' => false,
                'message' => __('Vous ne pouvez pas vous envoyer un message à vous-même'),
            ], 400);
        }

        $message = $this->messageService->sendMessage(
            $senderId,
            $receiverId,
            $request->input('message'),
            $request->input('related_match_id')
        );

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $message->id,
                'sender_id' => $message->sender_id,
                'message' => $message->message,
                'is_mine' => true,
                'is_read' => false,
                'created_at' => $message->created_at->toIso8601String(),
                'time_ago' => $message->created_at->diffForHumans(),
            ],
        ]);
    }

    public function getConversation(Request $request, int $contactId): JsonResponse
    {
        $userId = Auth::id();
        
        $contactExists = User::where('id', $contactId)->exists();
        if (!$contactExists) {
            return response()->json([
                'success' => false,
                'message' => __('Contact non trouvé'),
            ], 404);
        }

        $this->messageService->markAsRead($userId, $contactId);

        $messages = $this->messageService->getConversation($userId, $contactId);

        $contact = User::select('id', 'name', 'player_code', 'profile_settings')->find($contactId);

        return response()->json([
            'success' => true,
            'contact' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'player_code' => $contact->player_code,
                'avatar_url' => $contact->avatar_url ?? '/images/avatars/standard/default.png',
            ],
            'messages' => $messages,
        ]);
    }

    public function getUnreadCount(): JsonResponse
    {
        $userId = Auth::id();
        $totalUnread = $this->messageService->getUnreadCount($userId);
        $unreadPerContact = $this->messageService->getUnreadCountPerContact($userId);

        return response()->json([
            'success' => true,
            'total_unread' => $totalUnread,
            'per_contact' => $unreadPerContact,
        ]);
    }

    public function markAsRead(Request $request, int $contactId): JsonResponse
    {
        $userId = Auth::id();
        $markedCount = $this->messageService->markAsRead($userId, $contactId);

        return response()->json([
            'success' => true,
            'marked_count' => $markedCount,
        ]);
    }

    public function getRecentConversations(): JsonResponse
    {
        $userId = Auth::id();
        $conversations = $this->messageService->getRecentConversations($userId);

        return response()->json([
            'success' => true,
            'conversations' => $conversations,
        ]);
    }
}
