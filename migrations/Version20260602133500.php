<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seed customer label RBAC permissions and assign them to default groups.
 */
final class Version20260602133500 extends AbstractMigration
{
	private const array PERMISSIONS = [
		['customer_label.view', 'Parties', 'View customer labels', 'Can view customer labels.'],
		['customer_label.manage', 'Parties', 'Manage customer labels', 'Can create, edit and archive customer labels.'],
	];

	private const array GROUP_PERMISSION_CODES = [
		'admin' => ['customer_label.view', 'customer_label.manage'],
		'store_admin' => ['customer_label.view', 'customer_label.manage'],
		'manager' => ['customer_label.view', 'customer_label.manage'],
	];

	public function getDescription(): string
	{
		return 'Seed customer label RBAC permissions and assign them to default groups.';
	}

	public function up(Schema $schema): void
	{
		$sortOffset = 10;
		foreach (self::PERMISSIONS as [$code, $category, $name, $description]) {
			$this->addSql(
				'INSERT INTO permission (code, name, category, description, sort_order, created_at, updated_at) SELECT ?, ?, ?, ?, COALESCE(MAX(sort_order), 0) + ?, NOW(), NOW() FROM permission ON CONFLICT (code) DO UPDATE SET name = EXCLUDED.name, category = EXCLUDED.category, description = EXCLUDED.description, updated_at = NOW()',
				[$code, $name, $category, $description, $sortOffset],
			);
			$sortOffset += 10;
		}

		foreach (self::GROUP_PERMISSION_CODES as $groupCode => $permissionCodes) {
			foreach ($permissionCodes as $permissionCode) {
				$this->addSql(
					'INSERT INTO user_group_permission (user_group_id, permission_id) SELECT user_group.id, permission.id FROM user_group, permission WHERE user_group.code = ? AND permission.code = ? ON CONFLICT DO NOTHING',
					[$groupCode, $permissionCode],
				);
			}
		}
	}

	public function down(Schema $schema): void
	{
		foreach (array_reverse(self::GROUP_PERMISSION_CODES) as $groupCode => $permissionCodes) {
			foreach ($permissionCodes as $permissionCode) {
				$this->addSql('DELETE FROM user_group_permission USING user_group, permission WHERE user_group_permission.user_group_id = user_group.id AND user_group_permission.permission_id = permission.id AND user_group.code = ? AND permission.code = ?', [$groupCode, $permissionCode]);
			}
		}

		foreach (array_reverse(self::PERMISSIONS) as [$code]) {
			$this->addSql('DELETE FROM permission WHERE code = ?', [$code]);
		}
	}
}
