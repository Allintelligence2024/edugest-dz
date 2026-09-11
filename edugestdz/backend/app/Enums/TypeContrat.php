<?php

namespace App\Enums;

enum TypeContrat: string
{
    case CDI = 'cdi';
    case CDD = 'cdd';
    case PRESTATION = 'prestation';
    case STAGE = 'stage';
}
