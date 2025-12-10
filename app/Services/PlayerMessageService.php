<?php

namespace App\Services;

use App\Models\PlayerMessage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class PlayerMessageService
{
    public function sendMessage(int $senderId, int $receiverId, string $message, ?int $relatedMatchId = null): PlayerMessage
    {
        return PlayerMessage::create([
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'message' => $this->sanitizeMessage($message),
            'is_read' => false,
            'related_match_id' => $relatedMatchId,
        ]);
    }

    public function getConversation(int $userId, int $contactId, int $limit = 50): Collection
    {
        return PlayerMessage::conversation($userId, $contactId)
            ->with(['sender:id,name,player_code,profile_settings', 'receiver:id,name,player_code,profile_settings'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(function ($message) use ($userId) {
                return [
                    'id' => $message->id,
                    'sender_id' => $message->sender_id,
                    'sender_name' => $message->sender->name,
                    'sender_avatar' => $message->sender->avatar_url ?? '/images/avatars/standard/default.png',
                    'message' => $message->message,
                    'is_mine' => $message->sender_id === $userId,
                    'is_read' => $message->is_read,
                    'created_at' => $message->created_at->toIso8601String(),
                    'time_ago' => $message->created_at->diffForHumans(),
                ];
            });
    }

    public function markAsRead(int $receiverId, int $senderId): int
    {
        return PlayerMessage::where('receiver_id', $receiverId)
            ->where('sender_id', $senderId)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    public function getUnreadCount(int $userId): int
    {
        return PlayerMessage::where('receiver_id', $userId)
            ->where('is_read', false)
            ->count();
    }

    public function getUnreadCountPerContact(int $userId): Collection
    {
        return PlayerMessage::where('receiver_id', $userId)
            ->where('is_read', false)
            ->selectRaw('sender_id, COUNT(*) as unread_count')
            ->groupBy('sender_id')
            ->pluck('unread_count', 'sender_id');
    }

    public function getRecentConversations(int $userId, int $limit = 20): Collection
    {
        $latestMessages = PlayerMessage::where('sender_id', $userId)
            ->orWhere('receiver_id', $userId)
            ->with(['sender:id,name,player_code,profile_settings', 'receiver:id,name,player_code,profile_settings'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique(function ($message) use ($userId) {
                return $message->sender_id === $userId 
                    ? $message->receiver_id 
                    : $message->sender_id;
            })
            ->take($limit);

        $unreadCounts = $this->getUnreadCountPerContact($userId);

        return $latestMessages->map(function ($message) use ($userId, $unreadCounts) {
            $contactId = $message->sender_id === $userId 
                ? $message->receiver_id 
                : $message->sender_id;
            $contact = $message->sender_id === $userId 
                ? $message->receiver 
                : $message->sender;

            return [
                'contact_id' => $contactId,
                'contact_name' => $contact->name,
                'contact_player_code' => $contact->player_code,
                'contact_avatar' => $contact->avatar_url ?? '/images/avatars/standard/default.png',
                'last_message' => $this->truncateMessage($message->message, 50),
                'last_message_time' => $message->created_at->diffForHumans(),
                'unread_count' => $unreadCounts->get($contactId, 0),
                'is_last_mine' => $message->sender_id === $userId,
            ];
        })->values();
    }

    protected function sanitizeMessage(string $message): string
    {
        $message = trim($message);
        $message = strip_tags($message);
        $message = mb_substr($message, 0, 500);
        return $message;
    }

    protected function truncateMessage(string $message, int $maxLength): string
    {
        if (mb_strlen($message) <= $maxLength) {
            return $message;
        }
        return mb_substr($message, 0, $maxLength) . '...';
    }
}
