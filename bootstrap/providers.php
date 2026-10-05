<?php

use App\Providers\AppServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\PlatformCredentialOverrideServiceProvider;

return [
    AppServiceProvider::class,
    PlatformCredentialOverrideServiceProvider::class,
    EventServiceProvider::class,
];
