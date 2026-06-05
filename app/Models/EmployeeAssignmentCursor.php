<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeAssignmentCursor extends Model
{
    protected $fillable = [
        'key',
        'last_employee_id',
    ];

    protected $casts = [
        'last_employee_id' => 'integer',
    ];
}
