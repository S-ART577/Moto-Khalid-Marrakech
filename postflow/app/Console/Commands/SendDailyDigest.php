<?php

namespace App\Console\Commands;

use App\Enums\VariantStatus;
use App\Models\PostVariant;
use App\Models\User;
use App\Notifications\DailyDigest;
use Illuminate\Console\Command;

/** Every morning (see routes/console.php): what each person has to post today. */
class SendDailyDigest extends Command
{
    protected $signature = 'postflow:digest';

    protected $description = 'Send the morning summary of today\'s posts';

    public function handle(): int
    {
        $count = 0;

        foreach (User::where(fn ($q) => $q->where('notify_email', true)->orWhere('notify_push', true))->get() as $user) {
            $variants = PostVariant::with(['platform', 'post.brand'])
                ->where('status', VariantStatus::Planned)
                ->whereBetween('publish_at', [today(), today()->endOfDay()])
                ->whereHas('post', fn ($q) => $q->inBrands($user->accessibleBrands()->pluck('id')->all()))
                ->orderBy('publish_at')
                ->get();

            // Once community managers are set up, they get the summary and the admin does not.
            if ($user->isAdmin() && User::where('role', 'community_manager')->whereHas('brands')->exists()) {
                continue;
            }

            if ($variants->isNotEmpty()) {
                $user->notify(new DailyDigest($variants));
                $count++;
            }
        }

        $this->info($count.' summary(ies) sent.');

        return self::SUCCESS;
    }
}
