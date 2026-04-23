<?php

namespace App\Enums;

enum WorkOrderStatus: string
{
    case Created = 'created';
    case Pending = 'pending';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case WaitingParts = 'waiting_parts';
    case Completed = 'completed';
    case Invoiced = 'invoiced';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::Pending => 'Pending',
            self::Scheduled => 'Scheduled',
            self::InProgress => 'In Progress',
            self::WaitingParts => 'Waiting for Parts',
            self::Completed => 'Completed',
            self::Invoiced => 'Invoiced',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Created => 'gray',
            self::Pending => 'yellow',
            self::Scheduled => 'indigo',
            self::InProgress => 'blue',
            self::WaitingParts => 'orange',
            self::Completed => 'green',
            self::Invoiced => 'purple',
            self::Cancelled => 'red',
        };
    }
}
