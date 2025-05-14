<?php

namespace App\Enum;

enum TransactionType: int
{
    case PAYMENT = 1;
    case DEPOSIT = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::PAYMENT => 'payment',
            self::DEPOSIT => 'deposit',
        };
    }

    public static function getValueFromLabel(string $value): self
    {
        return match ($value) {
            'payment' => self::PAYMENT,
            'deposit' => self::DEPOSIT
        };
    }

    public function getValue(): int
    {
        return $this->value;
    }
}
