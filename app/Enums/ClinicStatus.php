<?php

namespace App\Enums;

enum ClinicStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case ACTIVE = 'active';
    case ARCHIVED = 'archived';
}