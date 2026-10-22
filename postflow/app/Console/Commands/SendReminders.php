<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Enums\VariantStatus;
use App\Models\PostVariant;
use App\Models\SentReminder;
use App\Models\User;
use App\Notifications\PostReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Runs every 5 minutes (see routes/console.php):
 * - a reminder X minutes before each platform's publish time (X = each person's setting)
 * - one nudge when a platform is 15 minutes late and still not marked as posted
 */
class SendReminders extends Command
{
    protected $signature = 'postflow:remind';

    protected $description = 'Send post reminders by email and browser notification';

    public function handle(): int
    {
        $now = now();
        $sent = 0;

        $upcoming = PostVariant::with(['platform', 'post.brand.users', 'post.owner'])
            ->where('status', VariantStatus::Planned)
            ->whereBetween('publish_at', [$now, $now->copy()->addHours(12)])
            ->whereHas('post')
            ->get();

        foreach ($upcoming as $variant) {
            foreach ($this->recipients($variant) as $user) {
                if ($now->gte($variant->publish_at->copy()->subMinutes($user->reminder_minutes))) {
                    $sent += $this->sendOnce($user, $variant, 'upcoming');
                }
            }
        }

        $late = PostVariant::with(['platform', 'post.brand.users', 'post.owner'])
            ->where('status', VariantStatus::Planned)
            ->whereBetween('publish_at', [$now->copy()->subDays(2), $now->copy()->subMinutes(15)])
            ->whereHas('post')
            ->get();

        foreach ($late as $variant) {
            foreach ($this->recipients($variant) as $user) {
                $sent += $this->sendOnce($user, $variant, 'overdue');
            }
        }

        $this->info($sent.' reminder(s) sent.');

        return self::SUCCESS;
    }

    /** The community managers of the brand and the owner of the post (admins when nobody is assigned). */
    private function recipients(PostVariant $variant): Collection
    {
        $people = $variant->post->brand->users->concat([$variant->post->owner])->filter()->unique('id');

        if ($people->isEmpty()) {
            $people = User::where('role', Role::Admin)->get();
        }

        return $people->filter(fn (User $user) => $user->notify_email || $user->notify_push);
    }

    private function sendOnce(User $user, PostVariant $variant, string $kind): int
    {
        $reminder = SentReminder::firstOrCreate(['user_id' => $user->id, 'post_variant_id' => $variant->id, 'kind' => $kind]);

        if (! $reminder->wasRecentlyCreated) {
            return 0;
        }

        $user->notify(new PostReminder($variant, $kind));

        return 1;
    }
}
