<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;



    protected $table = 'reports'; // Specify the table name if necessary

    protected $fillable = [
        'memberId',
        'date',
        'workTime',
        'inTime',
        'outTime',
        'shortLeaveTime',
        'totalWorkTime',
        'status'
    ];

    /**
     * Relationship: Report belongs to a Member.
     */
    public function member()
    {
        return $this->belongsTo(Member::class, 'memberId', "id");
    }
}
