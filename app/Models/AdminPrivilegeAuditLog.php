<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminPrivilegeAuditLog extends Model
{
    protected $table = 'admin_privilege_audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'action',
        'target_user_id',
        'origin',
        'created_at',
        'metadata',
    ];

    protected $casts = [
        'target_user_id' => 'integer',
        'created_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}