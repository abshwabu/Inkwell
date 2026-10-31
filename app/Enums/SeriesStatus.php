<?php

namespace App\Enums;

enum SeriesStatus: string
{
    case ONGOING = 'ongoing';
    case COMPLETED = 'completed';
    case HIATUS = 'hiatus';

    public function label(): string
    {
        return match ($this) {
            self::ONGOING => 'Ongoing',
            self::COMPLETED => 'Completed',
            self::HIATUS => 'On Hiatus',
        };
    }
}
