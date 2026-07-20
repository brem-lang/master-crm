<?php

namespace App\Notifications;

use App\Models\JobRun;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class JobRunCompleted extends Notification
{
    use Queueable;

    public function __construct(private readonly JobRun $jobRun)
    {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'job_run_id' => $this->jobRun->id,
            'company_id' => $this->jobRun->company_id,
            'company_name' => $this->jobRun->company?->name,
            'success' => $this->jobRun->success,
            'pulled' => $this->jobRun->pulled,
            'message' => $this->jobRun->message,
            'triggered_by' => $this->jobRun->triggered_by,
        ];
    }
}
