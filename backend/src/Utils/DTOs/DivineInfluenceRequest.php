<?php

namespace App\Utils\DTOs;

/**
 * Data Transfer Object for divine influence requests
 */
class DivineInfluenceRequest
{
    public string $targetId;
    public string $targetType;
    public string $influenceType;
    public string $strength;
    public string $description;

    public static function fromArray(array $data): self
    {
        $request = new self();
        $request->targetId = $data['target_id'] ?? '';
        $request->targetType = $data['target_type'] ?? '';
        $request->influenceType = $data['influence_type'] ?? '';
        $request->strength = $data['strength'] ?? '';
        $request->description = $data['description'] ?? '';
        return $request;
    }

    public function validate(): array
    {
        $errors = [];
        if (empty($this->targetId)) {
            $errors[] = "target_id is required";
        }
        if (empty($this->targetType)) {
            $errors[] = "target_type is required";
        }
        if (empty($this->influenceType)) {
            $errors[] = "influence_type is required";
        }
        if (empty($this->strength)) {
            $errors[] = "strength is required";
        }
        return $errors;
    }
}
