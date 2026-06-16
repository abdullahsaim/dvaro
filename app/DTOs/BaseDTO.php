<?php

namespace App\DTOs;

/**
 * Base class for all Data Transfer Objects.
 *
 * DTOs carry validated, immutable data between layers. Concrete DTOs should
 * provide a `fromRequest()` factory so controllers never pass raw Requests
 * into Services.
 */
abstract class BaseDTO
{
    //
}
