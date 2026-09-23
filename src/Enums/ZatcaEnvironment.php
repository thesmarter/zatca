<?php

declare(strict_types=1);

namespace Smart\Zatca\Enums;

enum ZatcaEnvironment
{
    case PRODUCTION;
    case SIMULATION;
    case SANDBOX;
}
