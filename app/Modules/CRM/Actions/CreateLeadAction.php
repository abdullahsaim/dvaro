<?php

namespace App\Modules\CRM\Actions;

use App\Actions\BaseAction;
use App\Modules\CRM\DTOs\CreateLeadDTO;
use App\Modules\CRM\Events\LeadCreated;
use App\Modules\CRM\Models\Lead;
use Illuminate\Support\Str;

/**
 * Creates a lead for the bound tenant and returns it alongside its shareable
 * signed intake link.
 *
 * The token is a server-generated UUID — never user-supplied — so the public
 * intake URL is unguessable. created_by is taken from the authenticated tenant
 * user. Fires LeadCreated.
 */
class CreateLeadAction extends BaseAction
{
    public function __construct(
        private readonly GenerateLeadLinkAction $generateLink,
    ) {}

    /**
     * @return array{lead: Lead, link: string}
     */
    public function execute(CreateLeadDTO $dto): array
    {
        $lead = Lead::create([
            ...$dto->toAttributes(),
            'status' => Lead::STATUS_NEW,
            'token' => (string) Str::uuid(),
            'created_by' => auth('tenant')->id(),
        ]);

        $link = $this->generateLink->execute($lead);

        LeadCreated::dispatch($lead);

        return ['lead' => $lead, 'link' => $link];
    }
}
