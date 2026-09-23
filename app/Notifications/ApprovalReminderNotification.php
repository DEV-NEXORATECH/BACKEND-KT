<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly array $pendingItems,
        public readonly int $overdueDays = 3,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = count($this->pendingItems);
        $subject = "[Kaoem Telapak ERP] {$count} pengajuan menunggu persetujuan Anda (>{$this->overdueDays} hari)";

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting("Halo {$notifiable->name},")
            ->line("Terdapat **{$count} pengajuan** yang telah menunggu persetujuan Anda selama lebih dari **{$this->overdueDays} hari**.")
            ->line('Berikut ringkasannya:');

        foreach (array_slice($this->pendingItems, 0, 10) as $item) {
            $mail->line("• [{$item['module_label']}] {$item['reference_number']} — {$item['title_summary']}");
        }

        if ($count > 10) {
            $more = $count - 10;
            $mail->line("...dan {$more} pengajuan lainnya.");
        }

        $mail->action('Buka Approval Center', config('app.frontend_url', config('app.url')) . '/approvals')
            ->line('Mohon segera ditindaklanjuti. Terima kasih.')
            ->salutation('Salam, Sistem ERP Kaoem Telapak');

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'approval_reminder',
            'title' => count($this->pendingItems) . ' pengajuan menunggu persetujuan',
            'message' => 'Ada ' . count($this->pendingItems) . " pengajuan yang telah menunggu lebih dari {$this->overdueDays} hari.",
            'pending_count' => count($this->pendingItems),
            'overdue_days' => $this->overdueDays,
        ];
    }
}
