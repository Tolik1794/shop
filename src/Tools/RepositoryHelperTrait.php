<?php

namespace App\Tools;

trait RepositoryHelperTrait
{
	protected function columnsToStr(array $columns, string $tableAlias = null, string $columnAliasPrefix = null): string
	{
		$arr = [];
		foreach ($columns as $column) {
			$col = $tableAlias ? sprintf('%s.%s', $tableAlias, $column) : $column;
			if ($columnAliasPrefix) $col = sprintf('%s as %s_%s', $col, $columnAliasPrefix, $column);
			$arr[] = $col;
		}
		unset($col);


		return implode(', ', $arr);
	}

	protected function implodeToSql(iterable $collection): string
	{
		$arr = [];

		foreach ($collection as $item) {
			if (is_object($item)) {
				$arr[] = $item->getId();
				continue;
			}

			if (is_numeric($item) || is_string($item)) {
				$arr[] = $item;
			}
		}
		unset($item);

		return implode(', ', $arr);
	}
}