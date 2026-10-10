<?php

namespace App\Enums;

/** Support ticket states shared by customer and admin workflows. */
enum SupportTicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';
}
