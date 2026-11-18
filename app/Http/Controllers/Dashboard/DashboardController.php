<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Pos\Policy\LandingPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Punto de entrada del sistema (RF-41).
 *
 * La raíz ya no lleva siempre a la caja: cada rol aterriza en su panel. Un
 * usuario sin rol reconocido recibe una página que le dice a quién pedir
 * acceso, sin mostrar ningún panel (caso límite 1).
 */
final class DashboardController extends Controller
{
    public function home(Request $request): RedirectResponse|Response
    {
        $role = $request->user()?->role;
        $panel = (new LandingPolicy)->panelRoute($role);

        if ($panel === null) {
            return response()->view('dashboard.unsupported', ['role' => $role], 403);
        }

        return redirect()->route($panel);
    }
}
