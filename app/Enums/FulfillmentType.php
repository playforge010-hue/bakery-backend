<?php

namespace App\Enums;

/** Supported ways for a Dwarkesh Bakers customer to receive an order. */
enum FulfillmentType: string
{
    case Delivery = 'delivery';
    case Takeaway = 'takeaway';
}
