<?php

namespace App\Enums;

enum UserType: string
{
    case ADMIN = 'admin';
    case DOCTOR = 'doctor';
    case SECRETARY = 'secretary';
    case PATIENT = 'patient';
}