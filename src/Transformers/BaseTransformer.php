<?php

namespace DeltaWhyDev\AuditLog\Transformers;

interface BaseTransformer
{
    /**
     * Transform a single value for display.
     *
     * @param mixed $value
     * @param string $type 'old' or 'new'
     * @return string HTML or text
     */
    public function transform($value, string $type, array $context = []): string;

    /**
     * Transform the difference between old and new values.
     * Returns null if standard diff logic should be used.
     * Returns string for HTML block.
     * Returns array for flattened list of changes [['label' => '...', 'old' => '...', 'new' => '...']].
     *
     * @param mixed $old
     * @param mixed $new
     * @param array $context Additional context like log, model, and other attributes
     * @return array|string|null
     */
    public function transformDiff($old, $new, array $context = []): array|string|null;
}
