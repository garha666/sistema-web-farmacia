<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Models\Prescription;
use App\Models\PrescriptionReview;
use App\Models\User;
use App\Pos\Exception\PrescriptionRejected;
use App\Pos\Value\Cart;
use App\Pos\Value\PrescriptionInput;
use Illuminate\Support\Facades\DB;

final class PrescriptionReviewService
{
    public function request(int $cashier, string $token, Cart $cart, PrescriptionInput $prescription): void
    {
        $registered = Prescription::query()->where('number', $prescription->number)
            ->where('prescriber_rut', $prescription->prescriberRut)->first();
        if ($registered !== null) {
            $this->assertConsistent($registered, $prescription);
        }

        $snapshot = $this->snapshot($cart, $prescription);
        $fingerprint = $this->fingerprint($snapshot);
        $review = new PrescriptionReview;
        $review->snapshot = $snapshot;

        DB::transaction(function () use ($cashier, $token, $fingerprint, $review): void {
            // Expired reviews no longer need to hold prescription data.
            PrescriptionReview::query()->where('expires_at', '<=', now())->delete();
            $existing = $this->find($cashier, $token);
            if ($existing !== null && $existing->fingerprint === $fingerprint) {
                return;
            }
            PrescriptionReview::query()->upsert([[
                'cashier_id' => $cashier,
                'checkout_token' => $token,
                'fingerprint' => $fingerprint,
                'snapshot' => $review->getAttributes()['snapshot'],
                'pharmacist_id' => null,
                'expires_at' => now()->addMinutes(15),
            ]], ['cashier_id', 'checkout_token'], ['fingerprint', 'snapshot', 'pharmacist_id', 'expires_at', 'updated_at']);
        });
    }

    public function assertConsistent(Prescription $registered, PrescriptionInput $input): void
    {
        $errors = [];
        if ($registered->prescriber_name !== $input->prescriberName) {
            $errors['prescriber_name'] = 'registered_mismatch';
        }
        if ($registered->issued_on?->format('Y-m-d') !== $input->issuedAt->format('Y-m-d')) {
            $errors['issued_at'] = 'registered_mismatch';
        }
        if ($errors !== []) {
            throw new PrescriptionRejected($errors);
        }
    }

    public function find(int $cashier, string $token): ?PrescriptionReview
    {
        return PrescriptionReview::query()->where('cashier_id', $cashier)->where('checkout_token', $token)
            ->where('expires_at', '>', now())->first();
    }

    public function findForCart(int $cashier, string $token, Cart $cart): ?PrescriptionReview
    {
        $review = $this->find($cashier, $token);

        return $review !== null && ($review->snapshot['products'] ?? []) === $this->products($cart) ? $review : null;
    }

    public function pharmacistFor(int $cashier, string $token, Cart $cart, PrescriptionInput $prescription): ?int
    {
        $review = PrescriptionReview::query()->where('cashier_id', $cashier)->where('checkout_token', $token)
            ->where('expires_at', '>', now())->lockForUpdate()->first();
        if ($review === null || $review->pharmacist_id === null
            || $review->fingerprint !== $this->fingerprint($this->snapshot($cart, $prescription))) {
            return null;
        }

        return (int) $review->pharmacist_id;
    }

    public function pending(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return PrescriptionReview::query()->with('cashier')->whereNull('pharmacist_id')
            ->where('expires_at', '>', now())->oldest()->orderBy('id')
            ->paginate(20, ['*'], 'page', 1);
    }

    public function approve(PrescriptionReview $review, User $pharmacist, string $fingerprint): bool
    {
        return $pharmacist->role === User::ROLE_QUIMICO && $pharmacist->disabled_at === null
            && PrescriptionReview::query()->whereKey($review->id)->whereNull('pharmacist_id')
                ->where('fingerprint', $fingerprint)->where('expires_at', '>', now())
                ->update(['pharmacist_id' => $pharmacist->id]) === 1;
    }

    public function snapshot(Cart $cart, PrescriptionInput $prescription): array
    {
        return ['products' => $this->products($cart), 'prescription' => [
            'prescription_number' => $prescription->number,
            'prescription_prescriber_name' => $prescription->prescriberName,
            'prescription_prescriber_rut' => $prescription->prescriberRut,
            'prescription_issued_at' => $prescription->issuedAt->format('Y-m-d'),
        ]];
    }

    private function products(Cart $cart): array
    {
        $products = [];
        foreach ($cart->lines() as $line) {
            $products[$line->productId()] = [
                'name' => $line->name(), 'category' => $line->category()->value, 'quantity' => $line->quantity(),
            ];
        }
        ksort($products);

        return $products;
    }

    private function fingerprint(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }
}
