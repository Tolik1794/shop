<?php

namespace App\Enum;

enum PaymentDirectionEnum: string
{
	case INCOMING = 'incoming';
	case OUTGOING = 'outgoing';
}
