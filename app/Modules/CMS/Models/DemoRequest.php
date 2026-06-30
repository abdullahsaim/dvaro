<?php

namespace App\Modules\CMS\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * DemoRequest — a pre-tenant lead captured from the public landing site.
 *
 * PLATFORM-WIDE: no tenant_id. These prospects do not belong to any tenant
 * yet. Both the "request a demo" CTA and the public contact form write here
 * (a contact enquiry carries its message in `message`). The super admin
 * reviews them and walks the status from new → contacted → converted.
 */
class DemoRequest extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_CONVERTED = 'converted';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_CONTACTED,
        self::STATUS_CONVERTED,
    ];

    protected $fillable = [
        'company_name',
        'contact_name',
        'email',
        'phone',
        'message',
        'status',
    ];
}
