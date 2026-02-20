<?php

namespace DeltaWhyDev\AuditLog\Services\Audit;

use DeltaWhyDev\AuditLog\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AuditContext
{
    protected static ?self $instance = null;
    protected array $pendingLogs = [];
    protected int $transactionLevel = 0;
    protected array $sensitiveFields = [];
    
    protected function __construct()
    {
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Begin a transaction context
     */
    public static function begin(): self
    {
        $instance = self::getInstance();
        $instance->transactionLevel++;
        return $instance;
    }
    
    /**
     * Add a change to the context
     */
    public function addChange(Model $entity, string $action, array $data): void
    {
        $this->pendingLogs[] = [
            'entity' => $entity,
            'action' => $action,
            'data' => $data,
        ];
    }
    
    /**
     * Flush all pending logs
     */
    public function flush(): void
    {
        foreach ($this->pendingLogs as $log) {
            AuditLog::create([
                'entity_type' => get_class($log['entity']),
                'entity_id' => $log['entity']->id,
                'action' => $log['action'],
                'actor_type' => $log['data']['actor_type'] ?? 'system',
                'actor_id' => $log['data']['actor_id'] ?? null,
                'attributes' => $log['data']['attributes'] ?? null,
                'relations' => $log['data']['relations'] ?? null,
                'metadata' => $log['data']['metadata'] ?? null,
                'created_at' => now(),
            ]);
        }
        
        $this->clear();
    }
    
    /**
     * Clear pending logs
     */
    public function clear(): void
    {
        $this->pendingLogs = [];
        $this->transactionLevel = 0;
    }
    
    /**
     * Check if in transaction
     */
    public function inTransaction(): bool
    {
        return DB::transactionLevel() > 0;
    }
}
