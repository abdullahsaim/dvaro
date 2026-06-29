<?php

return [
    // Returned by LogAiProvider (no provider configured for the tenant).
    'not_configured' => 'The AI assistant is not configured yet. Ask your administrator to select an AI provider in settings.',

    // Returned by the HTTP providers on transport/API failure (never thrown).
    'provider_error' => 'Sorry — the AI assistant could not complete that request. Please try again in a moment.',
    'provider_unavailable' => 'The selected AI provider is not available. Please contact your administrator.',

    // EnsureTenantHasModule abort message when the plan excludes the AI module.
    'module_not_in_plan' => 'The AI Assistant is not included in your current plan.',
];
