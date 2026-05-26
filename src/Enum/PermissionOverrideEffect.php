<?php

namespace App\Enum;

enum PermissionOverrideEffect: string
{
	case ALLOW = 'allow';
	case DENY = 'deny';
}
