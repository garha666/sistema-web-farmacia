<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Pos\Policy\LandingPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Inicio y cierre de sesión (RF-78 a RF-81).
 *
 * El controlador sólo orquesta: valida y autentica en `LoginRequest`, calcula el
 * destino con `LandingPolicy` (spec 002, RF-41) y cierra la sesión invalidándola.
 * No arrastra la pantalla pedida antes de entrar, para que nadie termine en un
 * panel ajeno (D-14).
 */
final class LoginController extends Controller
{
    /**
     * Pantalla de acceso; un usuario con sesión va directo a su panel (RF-80).
     */
    public function show(Request $request, LandingPolicy $landing): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route($this->panelRoute($landing, $request));
        }

        return view('auth.login');
    }

    /**
     * Autentica y lleva al panel del rol (RF-78).
     */
    public function authenticate(LoginRequest $request, LandingPolicy $landing): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->route($this->panelRoute($landing, $request));
    }

    /**
     * Cierra la sesión por completo (RF-81, RNF-24).
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Panel del rol; un rol no reconocido cae en `dashboard.home`, que responde
     * 403 sin mostrar ningún panel (spec 002, caso límite 1).
     */
    private function panelRoute(LandingPolicy $landing, Request $request): string
    {
        return $landing->panelRoute($request->user()?->role) ?? 'dashboard.home';
    }
}
