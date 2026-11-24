<?php

namespace App\Enums;

enum Specialty: string
{
    case General       = 'general';
    case Internal      = 'internal';
    case Cardiology    = 'cardiology';
    case Dermatology   = 'dermatology';
    case Pediatrics    = 'pediatrics';
    case Orthopedics   = 'orthopedics';
    case Ophthalmology = 'ophthalmology';
    case ENT           = 'ent';
}
