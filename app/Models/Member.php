<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    use HasFactory;

    protected $table = 'members'; // Table name

    protected $fillable = [
        'name',
        'email',
        'avatar',
        'joinDate',
        'endDate',
        'workTime',
        'status'
    ];

    /**
     * Relationship: A Member has many Reports.
     */
    public function reports()
    {
        return $this->hasMany(Report::class, 'memberId');
    }
}
