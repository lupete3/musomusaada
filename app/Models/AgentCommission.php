<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Jenssegers\Agent\Agent;

class AgentCommission extends Model
{
    //
    protected $fillable = [
        'agent_id',
        'type',
        'amount',
        'member_id',
        'commission_date',
    ];

    public function member()
    {
    	return $this->belongsTo(User::class,'member_id');
    }

    public function agent()
    {
    	return $this->belongsTo(User::class,'agent_id');
    }
}
