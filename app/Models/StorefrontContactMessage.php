<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Private customer correspondence; never directly serialized to public routes. */
final class StorefrontContactMessage extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'email', 'phone', 'subject', 'message', 'status', 'admin_note', 'read_at'];

    protected $casts = [
        'name' => 'encrypted',
        'email' => 'encrypted',
        'phone' => 'encrypted',
        'message' => 'encrypted',
        'admin_note' => 'encrypted',
        'read_at' => 'datetime',
    ];
}
