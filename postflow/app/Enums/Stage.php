<?php

namespace App\Enums;

/** Production pipeline: Brief -> Shoot -> Edit -> Design -> Review -> Scheduled -> Posted. */
enum Stage: string
{
    case Brief = 'brief';
    case Shoot = 'shoot';
    case Edit = 'edit';
    case Design = 'design';
    case Review = 'review';
    case Scheduled = 'scheduled';
    case Posted = 'posted';

    public function label(): string
    {
        return match ($this) {
            self::Brief => __('Brief'),
            self::Shoot => __('Shoot'),
            self::Edit => __('Editing'),
            self::Design => __('Design'),
            self::Review => __('Review'),
            self::Scheduled => __('Scheduled'),
            self::Posted => __('Posted'),
        };
    }

    public function position(): int
    {
        return array_search($this, self::cases(), true);
    }

    /** The community manager can move cards up to Review; Scheduled and Posted need the admin's approval. */
    public function needsApproval(): bool
    {
        return $this->position() > self::Review->position();
    }
}
