<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\CashRegister;
use App\Models\User;
use App\Pos\Value\TurnSummary;
use App\Services\Cash\CashRegisterService;

/**
 * Datos del panel del Técnico (RF-56, RF-57, RF-61).
 *
 * Devuelve el turno abierto del cajero y, si existe, su resumen agregado. El
 * arqueo y el cierre siguen viviendo en `CashRegisterService`.
 */
final class CashierPanelService
{
    public function __construct(
        private readonly CashRegisterService $registers,
    ) {}

    /**
     * @return array{register: CashRegister|null, summary: TurnSummary|null}
     */
    public function for(User $cashier): array
    {
        $register = $this->registers->openFor($cashier);

        return [
            'register' => $register,
            'summary' => $register === null ? null : $this->registers->summary($register),
        ];
    }
}
