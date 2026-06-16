<?php

namespace App\Services;

/**
 * Base class for all application services.
 *
 * Per DVARO architecture rules, ALL business logic lives in Services.
 * Controllers may only validate input, call a Service, and return a response.
 * Concrete services should expose an `execute()` (or domain-specific) method
 * and accept a DTO rather than a raw Request.
 */
abstract class BaseService
{
    //
}
