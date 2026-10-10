<?php

namespace App\Enums;

/** Payment methods currently offered by the bakery checkout. */
enum PaymentMethod: string
{
    case Upi = 'upi';
    case Card = 'card';
    case Cash = 'cash';
}
