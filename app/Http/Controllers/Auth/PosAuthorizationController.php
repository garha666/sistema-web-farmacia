<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class PosAuthorizationController
{
    public function edit(): View
    {
        return view('auth.pos-authorization');
    }

    public function updatePin(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('pin', [
            'current_password' => ['required', 'current_password'],
            'pharmacist_pin' => ['required', 'string', 'confirmed', 'regex:/\A[0-9]{8,12}\z/'],
        ], ['pharmacist_pin.regex' => __('pos.rx_review.pin_format')]);

        $user = $request->user();
        if (Hash::check($data['pharmacist_pin'], $user->password)) {
            throw ValidationException::withMessages(['pharmacist_pin' => __('pos.rx_review.pin_format')])->errorBag('pin');
        }

        $user->forceFill(['pharmacist_pin_hash' => Hash::make($data['pharmacist_pin'])])->save();

        return to_route('account.pos-authorization.edit')->with('pharmacist_pin_updated', true);
    }
}
