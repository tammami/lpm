<?php

namespace App\Enums;

enum SuggestionStatus: string
{
    use HasOptions;

    case New = 'new';
    case InReview = 'in_review';
    case FollowedUp = 'followed_up';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::InReview => 'Sedang ditinjau',
            self::FollowedUp => 'Ditindaklanjuti',
            self::Closed => 'Ditutup',
        };
    }
}
