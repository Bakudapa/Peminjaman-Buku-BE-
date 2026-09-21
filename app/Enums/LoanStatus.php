<?php

namespace App\Enums;

enum LoanStatus: string
{
    case Active = 'active';
    case Returned = 'returned';
    case Overdue = 'overdue';
}
