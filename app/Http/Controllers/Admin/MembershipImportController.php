<?php

namespace App\Http\Controllers\Admin;

use App\Services\Pos\ConventionService;
use App\Services\Pos\MembershipImporter;
use Illuminate\Http\Request;

final class MembershipImportController
{
    public function create(ConventionService $service)
    {
        return view('admin.conventions.import', ['conventions' => $service->applicable()]);
    }

    public function store(Request $request, MembershipImporter $importer, ConventionService $service)
    {
        $data = $request->validate(['convention_id' => ['required', 'integer'], 'csv' => ['required', 'file', 'extensions:csv', 'max:1024']]);
        $summary = $importer->import($data['csv'], (int) $data['convention_id'], $request->user()->id);

        return view('admin.conventions.import', ['summary' => $summary, 'conventions' => $service->applicable()]);
    }

    public function template()
    {
        return response("rut,nombre\r\n11111111-1,Cliente Demo Uno\r\n12345678-5,Cliente Demo Dos\r\n", 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="afiliados.csv"']);
    }
}
