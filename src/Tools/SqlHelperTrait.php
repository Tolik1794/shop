<?php

namespace App\Tools;

trait SqlHelperTrait
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
}