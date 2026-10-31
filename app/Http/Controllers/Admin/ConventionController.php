<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Convention;
use App\Services\Pos\ConventionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Catálogo de convenios de salud (RF-39, RF-40).
 */
final class ConventionController extends Controller
{
    public function __construct(
        private readonly ConventionService $conventions,
    ) {
    }

    public function index(): View
    {
        return view('admin.conventions.index', [
            'conventions' => $this->conventions->all(),
        ]);
    }

    public function create(): View
    {
        return view('admin.conventions.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateConvention($request);

        $convention = $this->conventions->create($data);

        return redirect()
            ->route('admin.conventions.index')
            ->with('success', "Convenio '{$convention->name}' creado.");
    }

    public function edit(Convention $convention): View
    {
        return view('admin.conventions.edit', [
            'convention' => $convention,
        ]);
    }

    public function update(Request $request, Convention $convention): RedirectResponse
    {
        $data = $this->validateConvention($request);

        $convention = $this->conventions->update($convention->id, $data);

        return redirect()
            ->route('admin.conventions.index')
            ->with('success', "Convenio '{$convention->name}' actualizado.");
    }

    public function destroy(Convention $convention): RedirectResponse
    {
        $convention = $this->conventions->deactivate($convention->id);

        return redirect()
            ->route('admin.conventions.index')
            ->with('success', "Convenio '{$convention->name}' desactivado.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validateConvention(Request $request): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('conventions', 'name')->ignore($request->route('convention')),
            ],
            'percent' => ['required', 'numeric', 'gt:0', 'max:100'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:valid_from'],
            'active' => ['sometimes', 'boolean'],
        ]);
    }
}
