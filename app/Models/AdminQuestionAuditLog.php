<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Task #94 — One row per admin AI composition call.
 *
 * Written by App\Services\QuestionApi\QuestionApiClient when a short-lived
 * JWT is minted (with caller_user_id, endpoint, payload_hash, jti) and
 * then updated with http_status / accepted / responded_at after the
 * question-api responds.
 */
class AdminQuestionAuditLog extends Model
{
    protected $table = 'admin_question_audit_log';

    public $timestamps = false;

    protected $fillable = [
        'jti',
        'caller_user_id',
        'endpoint',
        'payload_hash',
        'source',
        'accepted',
        'http_status',
        'error',
        'created_at',
        'responded_at',
    ];

    protected $casts = [
        'accepted' => 'bool',
        'http_status' => 'int',
        'caller_user_id' => 'int',
        'created_at' => 'datetime',
        'responded_at' => 'datetime',
    ];
}
