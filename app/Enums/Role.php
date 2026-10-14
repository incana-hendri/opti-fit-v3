<?php

namespace App\Enums;

// BR-1.7: exactly two roles.
enum Role: string
{
    case Customer = 'customer';
    case Admin = 'admin';
}
