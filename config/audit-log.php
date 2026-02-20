<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | The database connection that should be used to store audit logs.
    | Set to null to use the default database connection.
    |
    */
    'connection' => env('AUDIT_LOG_CONNECTION', null),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure queue settings for asynchronous logging.
    |
    */
    'queue' => [
        'enabled' => env('AUDIT_LOG_QUEUE_ENABLED', false),
        'connection' => env('AUDIT_LOG_QUEUE_CONNECTION', 'sync'),
        'queue' => env('AUDIT_LOG_QUEUE_NAME', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pruning / Archiving
    |--------------------------------------------------------------------------
    |
    | Configure automatic pruning of old audit logs.
    | To enable, schedule the `model:prune` command in your application.
    |
    */
    'pruning' => [
        'enabled' => true, // Helper flag (logic handled by Prunable trait presence)
        'retention_days' => env('AUDIT_LOG_RETENTION_DAYS', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Field Labels
    |--------------------------------------------------------------------------
    |
    | Default labels for common field names. These can be overridden
    | per resource when using ChangelogField.
    |
    */
    'field_labels' => [
        'name' => 'Name',
        'email' => 'Email Address',
        'status' => 'Status',
        'phone' => 'Phone Number',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
        'deleted_at' => 'Deleted At',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensitive Fields
    |--------------------------------------------------------------------------
    |
    | Fields that should be masked in audit logs. Supports:
    | - Exact field names: 'password', 'api_key'
    | - Wildcard patterns: '*password*', '*secret*', '*token*'
    |
    */
    'sensitive_fields' => [
        'password',
        'password_confirmation',
        'remember_token',
        'google2fa_secret',
        'api_key',
        'api_secret',
        'access_token',
        'refresh_token',
        '*password*',
        '*secret*',
        '*token*',
        '*key*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Audit Configuration
    |--------------------------------------------------------------------------
    |
    | Default configuration for models using the Auditable trait.
    | Can be overridden per model.
    |
    */
    'default_config' => [
        'exclude_attributes' => ['created_at', 'updated_at', 'deleted_at'],
        'sensitive_fields' => [], // Inherits from global config if empty
        'track_relations' => [],
        'log_created' => true,
        'log_updated' => true,
        'log_deleted' => true,
        'log_restored' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Actor Resolution
    |--------------------------------------------------------------------------
    |
    | Configure how actors are resolved. You can customize the user model
    | and how actors are identified.
    |
    */
    'actor' => [
        'user_model' => '\App\Models\User\User', // Correct model path
    ],

    /*
    |--------------------------------------------------------------------------
    | Boolean Display
    |--------------------------------------------------------------------------
    |
    | Configure how boolean fields are displayed in audit logs.
    |
    */
    'boolean_display' => [
        'style' => 'icon', // 'icon' or 'text'
        'true_icon' => '✓',
        'false_icon' => '✗',
        'true_color' => 'green',
        'false_color' => 'red',
        'true_label' => 'Yes',
        'false_label' => 'No',
    ],

    /*
    |--------------------------------------------------------------------------
    | Enum Display
    |--------------------------------------------------------------------------
    |
    | Configure how enum fields are displayed in audit logs.
    |
    */
    'enum_display' => [
        'method' => 'text', // Method to call on enum for display (text, label, name)
        'show_value' => false, // Show enum value alongside display text
    ],

    /*
    |--------------------------------------------------------------------------
    | Date Formatting
    |--------------------------------------------------------------------------
    |
    | Configure date formatting for audit logs.
    |
    */
    'date_format' => 'Y-m-d H:i:s',
    'date_timezone' => null, // null = use user timezone or app timezone
    
    'date_field_formats' => [
        'created_at' => 'Y-m-d',
        'updated_at' => 'Y-m-d H:i',
        'expires_at' => 'F j, Y',
        'deleted_at' => 'Y-m-d H:i:s',
    ],

    /*
    |--------------------------------------------------------------------------
    | JSON Field Formatting
    |--------------------------------------------------------------------------
    |
    | Configure custom formatters for JSON fields.
    |
    */
    /*
    |--------------------------------------------------------------------------
    | Attribute Transformers
    |--------------------------------------------------------------------------
    |
    | Configure custom transformers for specific model attributes.
    |
    */
    'transformers' => [
        // Example:
        // \App\Models\User::class => [
        //     'permissions' => \DeltaWhyDev\AuditLog\Transformers\JsonDiffTransformer::class,
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Number Field Formatters
    |--------------------------------------------------------------------------
    |
    | Configure how number fields are formatted for display
    |
    */
    'number_formatters' => [
        // Example:
        // 'weight' => [
        //     'decimals' => 6,
        //     'unit' => 'kg',
        //     'formatter' => function ($value) {
        //         return \App\Helpers\WeightHelper::formatWeightWithUnit($value, 'kg');
        //     },
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Relational Field Links
    |--------------------------------------------------------------------------
    |
    | Configure which fields should be displayed as links to resources.
    | You can map any field ending with _id to a model.
    |
    */
    'relational_links' => [
        // Example:
        // 'user_id' => [
        //     'resource' => 'users',
        //     'model' => \App\Models\User\User::class,
        //     'display_field' => 'name',
        //     'label' => 'User',
        // ],
        // 'customer_id' => [
        //     'resource' => 'contacts',
        //     'model' => \App\Models\Contact\Contact::class,
        //     'display_field' => 'legal_entity_name',
        //     'label' => 'Customer',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Views
    |--------------------------------------------------------------------------
    |
    | Configure which fields should be rendered using custom Blade views.
    | Useful for complex JSON fields that need special formatting.
    |
    */
    'custom_views' => [
        // Example:
        // 'contacts' => [
        //     'view' => 'audit-log.custom.contacts-table',
        //     'transformer' => \App\Services\Audit\Transformers\ContactTransformer::class,
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Photo Relations
    |--------------------------------------------------------------------------
    |
    | Configure which relations are photos (for display with photo icon).
    |
    */
    'photo_relations' => [
        // Example: ['photos', 'images']
    ],

    /*
    |--------------------------------------------------------------------------
    | Document Relations
    |--------------------------------------------------------------------------
    |
    | Configure which relations are documents (for display with document icon).
    |
    */
    'document_relations' => [
        // Example: ['documents', 'files']
    ],

    /*
    |--------------------------------------------------------------------------
    | Nova Integration
    |--------------------------------------------------------------------------
    |
    | Configure Nova integration settings.
    |
    */
    'model_namespace' => 'App\\Models', // Default namespace for models

    'nova' => [
        'enabled' => class_exists(\Laravel\Nova\Nova::class),
        'resource_enabled' => true,
        'changelog_field_enabled' => true,
        'namespace' => 'App\\Nova', // Default namespace for Nova resources
        'show_metadata' => false,
        'show_raw_json' => false,
    ],
];
