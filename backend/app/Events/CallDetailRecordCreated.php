<?php

namespace App\Events;

use App\Models\CallDetailRecord;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallDetailRecordCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public CallDetailRecord $cdr
    ) {}
}
