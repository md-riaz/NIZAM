<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class QueueMember extends Pivot
{
    use HasUuids;

    protected $table = 'queue_members';
}
