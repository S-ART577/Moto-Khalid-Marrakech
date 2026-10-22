<?php

namespace App\Policies;

use App\Enums\Stage;
use App\Models\Post;
use App\Models\User;

/**
 * Admin: everything.
 * Community manager: copy, assets, checklist, comments, stage up to Review,
 * mark as posted, metrics. No dates, owners, deleting or approving.
 */
class PostPolicy
{
    public function view(User $user, Post $post): bool
    {
        return $user->canAccessBrand($post->brand_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /** Title, format, date, owner, series... */
    public function update(User $user, Post $post): bool
    {
        return $user->isAdmin();
    }

    /** Captions, hashtags, assets, checklist, comments. */
    public function editCopy(User $user, Post $post): bool
    {
        return $user->canAccessBrand($post->brand_id);
    }

    public function changeStage(User $user, Post $post, Stage $to): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->canAccessBrand($post->brand_id)
            && ! $to->needsApproval()
            && ! $post->stage->needsApproval();
    }

    public function markPosted(User $user, Post $post): bool
    {
        return $user->canAccessBrand($post->brand_id);
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->isAdmin();
    }
}
