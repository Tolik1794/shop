<?php

namespace App\Security;

enum RoleEnum: string
{
	case ROLE_SUPER_ADMIN = 'super admin';
	case ROLE_ADMIN = 'admin';
	case ROLE_USER = 'user';
}
