<?php

namespace App\Notifications\Channels;

use App\Models\PushSubscription;
use Illuminate\Notifications\Notification;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/** Sends a notification to every browser / phone where the person turned notifications on. */
class WebPushChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! config('webpush.public_key') || $notifiable->pushSubscriptions->isEmpty()) {
            return;
        }

        $webPush = new WebPush(['VAPID' => [
            'subject' => config('webpush.subject'),
            'publicKey' => config('webpush.public_key'),
            'privateKey' => config('webpush.private_key'),
        ]]);

        $payload = json_encode($notification->toWebPush($notifiable));

        foreach ($notifiable->pushSubscriptions as $subscription) {
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
                'contentEncoding' => 'aes128gcm',
            ]), $payload);
        }

        foreach ($webPush->flush() as $report) {
            // The browser was reset or the permission removed: forget this device.
            if ($report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint_hash', hash('sha256', $report->getEndpoint()))->delete();
            }
        }
    }
}
