<?php

namespace App\Notifications;

use App\Notifications\Channels\WebPushChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/** Morning summary: everything to post today, in time order. */
class DailyDigest extends Notification
{
    /** @param  Collection<int, \App\Models\PostVariant>  $variants */
    public function __construct(public Collection $variants) {}

    public function via(object $notifiable): array
    {
        return array_values(array_filter([
            $notifiable->notify_email ? 'mail' : null,
            $notifiable->notify_push ? WebPushChannel::class : null,
        ]));
    }

    private function title(): string
    {
        return trans_choice('Today: :count post to publish|Today: :count posts to publish', $this->variants->count());
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->title())->greeting($this->title());

        foreach ($this->variants as $variant) {
            $mail->line($variant->publish_at->format('H:i').' · '.$variant->platform->name.' · '.$variant->post->brand->name.' · '.$variant->post->title);
        }

        return $mail->action(__('Open Today'), route('today'));
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->variants->map(fn ($v) => $v->publish_at->format('H:i').' '.$v->platform->name)->implode(' · '),
            'url' => route('today', absolute: false),
            'tag' => 'digest-'.now()->toDateString(),
        ];
    }
}
