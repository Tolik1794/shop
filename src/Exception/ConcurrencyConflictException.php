<?php

namespace App\Exception;

use RuntimeException;

class ConcurrencyConflictException extends RuntimeException
{
	public function __construct(string $message = 'Document was changed by another user. Refresh and try again.')
	{
		parent::__construct($message);
	}
}
