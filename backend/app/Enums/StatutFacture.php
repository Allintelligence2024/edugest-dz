<?php

namespace App\Enums;

enum StatutFacture: string
{
    case PAYEE = 'payee';
    case IMPAYEE = 'impayee';
    case ANNULEE = 'annulee';
    case EN_ATTENTE = 'en_attente';
}
