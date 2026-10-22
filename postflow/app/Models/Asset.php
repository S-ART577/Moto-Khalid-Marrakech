<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['post_id', 'post_variant_id', 'label', 'url'])]
class Asset extends Model
{
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(PostVariant::class, 'post_variant_id');
    }

    /** Google Drive file id, when the link is a Drive file. */
    public function driveFileId(): ?string
    {
        if (preg_match('~drive\.google\.com/(?:file/d/|open\?id=|uc\?(?:.*&)?id=)([\w-]{10,})~', $this->url, $m)) {
            return $m[1];
        }

        return null;
    }

    /** Direct download link for Drive files; null for folders and other links. */
    public function downloadUrl(): ?string
    {
        $id = $this->driveFileId();

        return $id ? 'https://drive.google.com/uc?export=download&id='.$id : null;
    }
}
