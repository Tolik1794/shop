<?php

namespace App\Controller\Admin;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait QuickActionResponseTrait
{
	private function wantsQuickActionJson(Request $request): bool
	{
		return $request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json');
	}

	/**
	 * @param array<string, string> $fragments
	 */
	private function quickActionJsonResponse(Request $request, array $fragments, int $status = Response::HTTP_OK): JsonResponse
	{
		return $this->json([
			'flashes' => $this->consumeQuickActionFlashes($request),
			'fragments' => $fragments,
		], $status);
	}

	/**
	 * @return array<int, array{type: string, title: string, message: string}>
	 */
	private function consumeQuickActionFlashes(Request $request): array
	{
		$session = $request->hasSession() ? $request->getSession() : null;

		if ($session === null) {
			return [];
		}

		$flashes = [];

		foreach ($session->getFlashBag()->all() as $type => $messages) {
			$alertType = $type === 'error' ? 'danger' : $type;

			foreach ($messages as $message) {
				$flashes[] = [
					'type' => $alertType,
					'title' => $this->quickActionFlashTitle($alertType),
					'message' => (string) $message,
				];
			}
		}

		return $flashes;
	}

	private function quickActionFlashTitle(string $type): string
	{
		return match ($type) {
			'success' => 'Success',
			'danger' => 'Error',
			'warning' => 'Warning',
			'info' => 'Info',
			default => ucfirst($type),
		};
	}
}
