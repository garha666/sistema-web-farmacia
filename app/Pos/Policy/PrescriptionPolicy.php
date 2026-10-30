<?php

declare(strict_types=1);

namespace App\Pos\Policy;

use App\Pos\Enum\SaleCategory;
use App\Pos\Value\Cart;
use App\Pos\Value\CartLine;
use App\Pos\Value\PrescriptionInput;
use App\Pos\Value\PrescriptionResult;
use DateTimeImmutable;

/**
 * Reglas de receta y productos controlados.
 *
 * RF-7: categorías libre, bajo receta y controlado.
 * RF-8: número, prescriptor y RUT antes de cobrar.
 * RF-9: vigencia configurable (30 días por defecto); vencida o incompleta bloquea.
 * RF-10: el controlado exige registro en el libro.
 * RF-11: receta reutilizada exige autorización de supervisor.
 */
final class PrescriptionPolicy
{
    public function evaluate(
        SaleCategory $category,
        ?PrescriptionInput $input,
        int $validityDays,
        bool $alreadyUsed,
        DateTimeImmutable $today,
    ): PrescriptionResult {
        if (! $category->requiresPrescription()) {
            return PrescriptionResult::valid();
        }

        if ($input === null) {
            return PrescriptionResult::invalid([
                'number' => 'required',
                'prescriber_name' => 'required',
                'prescriber_rut' => 'required',
                'issued_at' => 'required',
            ]);
        }

        $errors = $this->validateFields($input, $validityDays, $today);

        if ($errors !== []) {
            return PrescriptionResult::invalid($errors);
        }

        if ($alreadyUsed) {
            return PrescriptionResult::forSupervisor(['number' => 'prescription_already_used']);
        }

        return PrescriptionResult::valid();
    }

    public function requiresControlledRegister(SaleCategory $category): bool
    {
        return $category === SaleCategory::controlled;
    }

    public function cartRequiresPrescription(Cart $cart): bool
    {
        return $cart->hasPrescriptionItems();
    }

    /**
     * La categoría más restrictiva del carrito manda sobre la venta completa.
     */
    public function effectiveCategory(Cart $cart): SaleCategory
    {
        $category = SaleCategory::free;

        /** @var CartLine $line */
        foreach ($cart->lines() as $line) {
            $category = $this->stricter($category, $line->category());
        }

        return $category;
    }

    private function stricter(SaleCategory $current, SaleCategory $candidate): SaleCategory
    {
        $rank = [
            SaleCategory::free->value => 0,
            SaleCategory::prescription->value => 1,
            SaleCategory::controlled->value => 2,
        ];

        return $rank[$candidate->value] > $rank[$current->value] ? $candidate : $current;
    }

    /**
     * @return array<string, string>
     */
    private function validateFields(
        PrescriptionInput $input,
        int $validityDays,
        DateTimeImmutable $today,
    ): array {
        $errors = [];

        if (trim($input->number) === '') {
            $errors['number'] = 'required';
        }

        if (trim($input->prescriberName) === '') {
            $errors['prescriber_name'] = 'required';
        }

        if (trim($input->prescriberRut) === '') {
            $errors['prescriber_rut'] = 'required';
        }

        $issuedDay = $input->issuedAt->setTime(0, 0, 0);
        $todayDay = $today->setTime(0, 0, 0);

        if ($issuedDay > $todayDay) {
            $errors['issued_at'] = 'issued_in_future';
        } elseif ($issuedDay->diff($todayDay)->days > $validityDays) {
            $errors['issued_at'] = 'expired';
        }

        return $errors;
    }
}
