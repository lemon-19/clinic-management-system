<?php

namespace App\Enums;

enum AppointmentType: string
{
    case IN_PERSON = 'in_person';
    case TELEMEDICINE = 'telemedicine';
}