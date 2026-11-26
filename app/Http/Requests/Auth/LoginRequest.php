<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Acceso al sistema (RF-78, RF-79, RF-82).
 *
 * Valida la forma y autentica contra el guard de sesión. El freno por fuerza
 * bruta se lleva por la pareja correo + dirección de origen: cinco intentos por
 * minuto; un acierto limpia el contador (D-13). Un mismo mensaje cubre el correo
 * inexistente y la contraseña incorrecta para no enumerar cuentas (D-12).
 */
final class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Autentica con las credenciales recibidas (RF-78, RF-79).
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password') + ['disabled_at' => null], $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);

            // T28 - RF-84: el intento fallido queda en la bitácora sin credenciales.
            AuditLog::recordLoginFailure(
                User::query()->where('email', (string) $this->string('email'))->first(),
            );

            throw ValidationException::withMessages([
                'email' => __('pos.login.error.invalid'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'email' => __('pos.login.error.throttled', [
                'seconds' => RateLimiter::availableIn($this->throttleKey()),
            ]),
        ]);
    }

    /**
     * Contador por correo y dirección de origen (RF-82).
     */
    private function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower((string) $this->string('email')).'|'.$this->ip(),
        );
    }
}
