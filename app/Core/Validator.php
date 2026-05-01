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

    public function cpf(string $field, mixed $value, string $label): self
    {
        if (!is_string($value) || trim($value) === '') {
            return $this;
        }

        if (!$this->isValidCpf($value)) {
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

    public function integer(string $field, mixed $value, string $label): self
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->errors[$field][] = "{$label} deve ser um numero inteiro.";
        }

        return $this;
    }

    public function minInt(string $field, mixed $value, int $min, string $label): self
    {
        if (filter_var($value, FILTER_VALIDATE_INT) !== false && (int) $value < $min) {
            $this->errors[$field][] = "{$label} deve ser no minimo {$min}.";
        }

        return $this;
    }

    public function decimalRange(string $field, mixed $value, float $min, float $max, string $label): self
    {
        if ($value === null || $value === '') {
            return $this;
        }

        if (!is_numeric($value) || (float) $value < $min || (float) $value > $max) {
            $this->errors[$field][] = "{$label} esta fora do intervalo permitido.";
        }

        return $this;
    }

    public function date(string $field, mixed $value, string $label): self
    {
        if (!is_string($value) || trim($value) === '') {
            return $this;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            $this->errors[$field][] = "{$label} deve ser uma data valida.";
        }

        return $this;
    }

    public function in(string $field, mixed $value, array $allowed, string $label): self
    {
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            $this->errors[$field][] = "{$label} possui valor invalido.";
        }

        return $this;
    }

    public function add(string $field, string $message): self
    {
        $this->errors[$field][] = $message;

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

    private function isValidCpf(string $value): bool
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (strlen($digits) !== 11 || preg_match('/^(\d)\1{10}$/', $digits) === 1) {
            return false;
        }

        for ($position = 9; $position <= 10; $position++) {
            $sum = 0;

            for ($index = 0; $index < $position; $index++) {
                $sum += (int) $digits[$index] * (($position + 1) - $index);
            }

            $checkDigit = ($sum * 10) % 11;
            $checkDigit = $checkDigit === 10 ? 0 : $checkDigit;

            if ($checkDigit !== (int) $digits[$position]) {
                return false;
            }
        }

        return true;
    }
}
