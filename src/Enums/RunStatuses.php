<?php

namespace Javaabu\BulkActions\Enums;

enum RunStatuses: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return __("bulk-actions::strings.statuses.{$this->value}");
    }

    /**
     * The javaabu/forms status color
     */
    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'secondary',
            self::PROCESSING => 'info',
            self::COMPLETED => 'success',
            self::FAILED => 'danger',
            self::CANCELLED => 'warning',
        };
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::COMPLETED, self::FAILED, self::CANCELLED], true);
    }

    public function isCancellable(): bool
    {
        return in_array($this, [self::PENDING, self::PROCESSING], true);
    }
}
