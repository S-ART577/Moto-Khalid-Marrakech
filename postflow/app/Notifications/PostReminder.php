<?php

namespace App\Notifications;

use App\Models\PostVariant;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "20:00 · Instagram: Wait... same price?" before the publish time, or a nudge when it is late. */
class PostReminder extends Notification
{
    public function __construct(public PostVariant $variant, public string $kind = 'upcoming') {}

    public function via(object $notifiable): array
    {
        return array_values(array_filter([
            $notifiable->notify_email ? 'mail' : null,
            $notifiable->notify_push ? WebPushChannel::class : null,
        ]));
    }

    private function title(): string
    {
        $platform = $this->variant->platform->name;
        $time = $this->variant->publish_at->format('H:i');

        return $this->kind === 'overdue'
            ? __('Late: :platform was due at :time', ['platform' => $platform, 'time' => $time])
            : __(':time · :platform to post', ['time' => $time, 'platform' => $platform]);
    }

    private function body(): string
    {
        $post = $this->variant->post;

        return $post->brand->name.' · '.$post->title;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title())
            ->greeting($this->title())
            ->line($this->body());

        if (filled($this->variant->caption)) {
            $mail->line(__('Caption:'))->line($this->variant->caption);
        } else {
            $mail->line(__('The caption is still empty.'));
        }

        return $mail->action(__('Open Today'), route('today'));
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => route('today', absolute: false),
            'tag' => 'variant-'.$this->variant->id.'-'.$this->kind,
        ];
    }
}
