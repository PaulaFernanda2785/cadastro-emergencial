<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    private array $errors = [];

    public function required(string $field, mixed $value, string $label): self
    {
        if (!is_string($value) || trim($value) === '') {
            $this->errors[$field][] = "{$label} e obrigatorio.";
        }

        return $this;
    }

    public function email(string $field, mixed $value, string $label): self
    {
        if (is_string($value) && trim($value) !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->errors[$field][] = "{$label} invalido.";
        }

        return $this;
    }

    public function max(string $field, mixed $value, int $max, string $label): self
    {
        if (is_string($value) && mb_strlen($value) > $max) {
            $this->errors[$field][] = "{$label} deve ter no maximo {$max} caracteres.";
        }

        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
