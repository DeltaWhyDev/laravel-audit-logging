# Laravel Audit Log Package

A robust, configurable audit logging system for Laravel applications with seamless Nova integration.

## Features

- **Automatic Logging**: easily track changes via the `Auditable` trait.
- **Transaction-aware**: logs are only committed when the database transaction succeeds.
- **Nova Integration**: includes a ready-to-use Nova Resource and a `ChangelogField` for resource detail views.
- **Highly Configurable**: customize database connections, actors, subject models, and more.
- **Formatting**: built-in support for Enums, Dates, Booleans, and Relation links.
- **Security**: strict read-only access in Nova, sensitive field masking.

## Installation

1. Require the package via Composer:

```bash
composer require deltawhydev/laravel-audit-logging
```

2. Publish the configuration and migrations:

```bash
php artisan vendor:publish --tag=audit-log-config
php artisan vendor:publish --tag=audit-log-migrations
```

3. Run migrations:

```bash
php artisan migrate
```

## Configuration

The `config/audit-log.php` file allows you to customize almost every aspect of the package.

### Database Connection
You can store audit logs in a separate database (SQL or NoSQL) by setting the connection name:

```php
// config/audit-log.php
'connection' => env('AUDIT_LOG_CONNECTION', 'sqlite_logs'),
```

### Namespace Customization
Decouple the package from your specific app structure:

```php
'model_namespace' => 'App\\Models',
'nova' => [
    'namespace' => 'App\\Nova',
    // ...
],
```

### Actor Resolution
Define how the "Actor" (the user performing the action) is resolved:

```php
'actor' => [
    'user_model' => \App\Models\User::class,
],
```

## Usage

### 1. Add Trait to Models

Simply add the `Auditable` trait to any Eloquent model you want to track:

```php
use DeltaWhyDev\AuditLog\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use Auditable;
}
```

This will automatically log `created`, `updated`, `deleted`, and `restored` events, including changed attributes and relations (pivot events). Wait! If you are using Soft Deletes, the `deleted` event naturally triggers instead of `forceDeleted`. To capture restored events, make sure your model uses the `Illuminate\Database\Eloquent\SoftDeletes` trait.

### 2. Nova Integration

#### Audit Log Resource
Ensure `nova.resource_enabled` is set to `true` in config. The `Audit Logs` resource will appear in your Nova sidebar.

The built-in resource includes a highly-optimized **Index View** that shows a clean summary of the actor, action, and entity with clickable links, and a dedicated **Modified Fields** column that strictly lists what fields were modified without crowding the table.

**Built-In Filters:**
You can easily filter your audit logs directly in Nova using the dynamic filters included in the package:
- `AuditLogActorFilter`: Dynamically populates with users who have actually performed actions, directly retrieving their configured display names.
- `AuditLogEntityFilter`: Dynamically fetches all the different Eloquent models that have been historically audited in the system.

*(These filters are already applied to the default AuditLog Nova resource, but you can also use them in your own custom lenses).*

#### Changelog Field
To show a history of changes directly on a resource's detail page, add the `ChangelogField`:

```php
use DeltaWhyDev\AuditLog\NovaComponents\ChangelogField\ChangelogField;

public function fields(Request $request)
{
    return [
        // ... other fields
        
        ChangelogField::make('History')
            ->onlyOnDetail(),
    ];
}
```

The `ChangelogField` automatically handles pagination and provides a beautiful, collapsible row for every logged action. The headers cleanly show a 30-character truncated summary of what fields were changed (e.g., `Modified: status, password_hash`).

## Advanced Usage

### Custom Transformers
You can define custom transformers to format specific field values difference:

```php
// config/audit-log.php
'transformers' => [
    \App\Models\User::class => [
        'permissions' => \DeltaWhyDev\AuditLog\Transformers\JsonDiffTransformer::class,
    ],
],
```

> **Pro Tip: Custom HTML Layouts**
> If you want a transformer to take full control over its rendering (e.g. returning a raw HTML table instead of standard red/green text bubbles), you can add `'is_raw_html' => true` to the array returned by your `transformDiff()` method. The `ChangelogField` frontend will output your HTML unescaped spanning the full width of the row.

### Hidden Fields
To completely hide specific attributes or relationships from the Changelog UI display, add them to the `hidden_fields` configuration array. This is especially useful for hiding technical fields like polymorphic `_type` identifiers.

```php
// config/audit-log.php
'hidden_fields' => [
    \App\Models\Event\AlertReport::class => [
        'action_type', // Hides this attribute from the UI display entirely
    ],
],
```

### Search Configuration
By default, searching the Audit Log index is disabled to prevent performance issues on large datasets. Enable specific columns via config:

```php
// config/audit-log.php
'searchable_columns' => ['entity_type', 'action'],
```

### Queueing (Async Logging)
To prevent audit logging from slowing down your application (especially with remote databases), enable queueing:

```php
// config/audit-log.php
'queue' => [
    'enabled' => true,
    'connection' => 'redis',
    'queue' => 'audit-logs',
],
```

### Pruning (Auto-Cleanup)
The package uses Laravel's `Prunable` trait to automatically delete old logs.

1. Configure retention days:
```php
// config/audit-log.php
'pruning' => [
    'retention_days' => 365,
],
```

2. Schedule the pruning command in `app/Console/Kernel.php`:
```php
$schedule->command('model:prune', [
    '--model' => [\DeltaWhyDev\AuditLog\Models\AuditLog::class],
])->daily();
```

### BelongsToMany Relation Tracking (`track_relations`)

The package can automatically log additions and removals of `BelongsToMany` relations.

#### How it works

There are two mechanisms for detecting pivot changes:

1. **`pivotDetached` event** — fired on `$model->relation()->detach(...)`. Logged automatically. No configuration needed.

2. **Pivot model `created`/`deleted` events** — fired when using `->using(PivotModel::class)`. The observer resolves both sides of the pivot and uses `track_relations` to decide **which parent is the owner** of the log entry.

#### Configuration

Add `$auditConfig` with a `track_relations` array to the owning model:

```php
use DeltaWhyDev\AuditLog\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

// The custom Pivot model (optional, needed for event-based tracking)
class OrderTag extends Pivot
{
    protected $table = 'order_tag';
}

// The owning model
class Order extends Model
{
    use Auditable;

    protected $auditConfig = [
        // List the relation method names whose changes should appear in this model's log
        'track_relations' => ['tags'],
    ];

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'order_tag')
            ->using(OrderTag::class)
            ->withTimestamps();
    }
}

// The related model — no $auditConfig needed unless it also owns logged relations
class Tag extends Model
{
    // NOT declaring track_relations here prevents inverse duplicate logs
}
```

> **Rule:** Values in `track_relations` must exactly match the **method names** of the `belongsToMany` relations on the model (e.g. `tags`, `items`, `categories`).

#### Why `track_relations` is required for `->using()` relations

Without it, both sides of the pivot are treated as "owners" and the observer logs on **both** models, producing a double entry:

| Without `track_relations` | With `track_relations` |
|---|---|
| ❌ `Order` → "tags removed: Tag #5" | ✅ `Order` → "tags removed: Tag #5" |
| ❌ `Tag` → "orders removed: Order #12" | *(skipped — not in `track_relations`)* |

#### Tracking multiple relations

```php
protected $auditConfig = [
    'track_relations' => ['tags', 'categories', 'assignees'],
];
```

#### Inheritance

A base model's `$auditConfig` is inherited by child models via standard PHP property inheritance. A child model can override it by re-declaring `$auditConfig`, which takes full precedence.

---

## License
MIT
