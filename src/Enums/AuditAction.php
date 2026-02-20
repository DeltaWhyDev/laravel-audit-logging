<?php

namespace DeltaWhyDev\AuditLog\Enums;

enum AuditAction: int
{
    case CREATED = 1;
    case UPDATED = 2;
    case DELETED = 3;
    case RESTORED = 4;
    case RELATIONS_UPDATED = 5;
    
    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match($this) {
            self::CREATED => 'Created',
            self::UPDATED => 'Updated',
            self::DELETED => 'Deleted',
            self::RESTORED => 'Restored',
            self::RELATIONS_UPDATED => 'Relations Updated',
        };
    }
    
    /**
     * Get string representation (for backward compatibility)
     */
    public function value(): string
    {
        return match($this) {
            self::CREATED => 'created',
            self::UPDATED => 'updated',
            self::DELETED => 'deleted',
            self::RESTORED => 'restored',
            self::RELATIONS_UPDATED => 'relations_updated',
        };
    }
    
    /**
     * Create from string (for backward compatibility)
     */
    public static function fromString(string $value): self
    {
        return match(strtolower($value)) {
            'created' => self::CREATED,
            'updated' => self::UPDATED,
            'deleted' => self::DELETED,
            'restored' => self::RESTORED,
            'relations_updated' => self::RELATIONS_UPDATED,
            default => throw new \ValueError("Unknown action: {$value}"),
        };
    }
}

