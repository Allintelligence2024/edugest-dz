<?php

namespace App\Enums;

enum StatutEleve: string
{
    case ACTIF = 'actif';
    case INACTIF = 'inactif';
    case SUSPENDU = 'suspendu';
    case ANCIEN = 'ancien';
}
