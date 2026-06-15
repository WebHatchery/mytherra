<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\AdminWorldEditorService;

class AdminWorldEditorActions
{
    public function __construct(
        private AdminWorldEditorService $worldEditorService
    ) {
    }

    public function status(): array
    {
        return $this->worldEditorService->status();
    }

    public function create(string $entityType, array $payload): array
    {
        return $this->worldEditorService->create($entityType, $payload);
    }

    public function update(string $entityType, string $id, array $payload): array
    {
        return $this->worldEditorService->update($entityType, $id, $payload);
    }
}
