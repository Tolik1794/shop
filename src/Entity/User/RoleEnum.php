<?php

namespace App\Entity\User;

enum RoleEnum: string
{
	case ROLE_SUPER_ADMIN = 'Super admin';
	case ROLE_ADMIN = 'Admin';
	case ROLE_STORE_ADMIN = 'Store admin';
	case ROLE_STORE_MANAGER = 'Store manager';
	case ROLE_USER = 'User';

	public static function getDescription(self $role): string
	{
		return match ($role) {
			self::ROLE_SUPER_ADMIN => 'Role with all permissions',
			self::ROLE_ADMIN => 'Role admin',
			self::ROLE_STORE_ADMIN => 'Store admin',
			self::ROLE_STORE_MANAGER => 'Store manager',
			self::ROLE_USER => 'Simple user with minimum privileges'
		};
	}

	public static function names(): array
	{
		return array_column(self::cases(), 'name');
	}

	public static function values(): array
	{
		return array_column(self::cases(), 'value');
	}

	public static function array(): array
	{
		return array_combine(self::values(), self::names());
	}
}
