<?php

namespace App\Enums;

enum WebhookLeadStatus: int
{
    case PENDING = 0;
    case PROCESSED = 1;
    case FAILED = 2;
    case RETRY = 3;
    case DUPLICATE = 4;
    case SKIPPED = 5;
}
