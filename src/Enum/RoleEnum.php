<?php

namespace App\Enum;

enum RoleEnum: string
{
	case ROLE_SUPER_ADMIN = 'Super admin';
	case ROLE_ADMIN = 'Admin';
	case ROLE_STORE_ADMIN = 'Store admin';
	case ROLE_STORE_MANAGER = 'Store manager';
	case ROLE_USER = 'User';
}
