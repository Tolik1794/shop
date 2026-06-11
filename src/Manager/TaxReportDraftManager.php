<?php

namespace App\Manager;

use App\Entity\TaxReportDraft;
use App\Enum\TaxReportDraftStatusEnum;
use Doctrine\ORM\EntityManagerInterface;

final class TaxReportDraftManager
{
	public function __construct(
		private readonly EntityManagerInterface $em,
	)
	{
	}

	/**
	 * Applies user-edited field values on top of the calculated payload.
	 * A field that matches its calculated value goes back to source=calculated;
	 * anything else is stored with source=manual so the print form can flag it.
	 *
	 * @param array<string, string> $submittedValues field key => raw value
	 */
	public function applyManualCorrections(TaxReportDraft $draft, array $submittedValues): void
	{
		$payload = $draft->getPayload() ?? [];
		$fields = $payload['fields'] ?? [];

		foreach ($submittedValues as $key => $value) {
			if (!isset($fields[$key])) {
				continue;
			}

			$value = trim($value);
			if ($value === '') {
				continue;
			}

			$fields[$key]['value'] = $value;
			$fields[$key]['source'] = $value === $fields[$key]['calculated'] ? 'calculated' : 'manual';
		}

		$payload['fields'] = $fields;
		$draft->setPayload($payload);

		$this->em->flush();
	}

	public function markExported(TaxReportDraft $draft): void
	{
		if ($draft->getStatus() === TaxReportDraftStatusEnum::EXPORTED) {
			return;
		}

		$draft->setStatus(TaxReportDraftStatusEnum::EXPORTED);
		$this->em->flush();
	}
}
