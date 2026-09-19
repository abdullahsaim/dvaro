<?php

namespace App\Modules\Customer\Events;

use App\Modules\Customer\Models\Customer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an identity document (licence front/back, proof of address) is
 * uploaded for a customer, exclusively from UploadCustomerDocumentAction.
 * $replaced is true when a previous file for the same type was superseded.
 *
 * No listeners yet (audit log in a later session).
 */
class CustomerDocumentUploaded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Customer $customer,
        public readonly string $type,
        public readonly bool $replaced,
    ) {}
}
