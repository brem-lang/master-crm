<?php

namespace App\Observers;

use App\Models\JobRun;
use App\Models\User;
use App\Notifications\JobRunCompleted;
use Illuminate\Support\Facades\Notification;

class JobRunObserver
{
    public function created(JobRun $jobRun): void
    {
        Notification::send(User::role('parent-admin')->get(), new JobRunCompleted($jobRun));
    }
}
