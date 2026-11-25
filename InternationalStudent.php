<?php
declare(strict_types=1);

final class InternationalStudent extends Student
{
    public function getType(): string
    {
        return 'international';
    }

    public function calculateFee(): float
    {
        return $this->getBaseFee() + 2000000;
    }

    public function summary(): string
    {
        return parent::summary() . ' [Internasional]';
    }
}
