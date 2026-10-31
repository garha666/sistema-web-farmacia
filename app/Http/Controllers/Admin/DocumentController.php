<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Pos\Enum\DocumentStatus;
use App\Services\Pos\DocumentService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Consulta de documentos y reintento de pendientes/rechazados (RF-26).
 */
final class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentService $documents,
    ) {
    }

    public function index(Request $request): View
    {
        $data = $request->validate([
            'status' => ['nullable', 'in:pending,issued,rejected'],
        ]);

        $documents = Document::query()
            ->with('sale')
            ->when(
                isset($data['status']),
                fn ($query) => $query->where('status', $data['status']),
            )
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.documents.index', [
            'documents' => $documents,
            'statuses' => DocumentStatus::cases(),
            'status' => $data['status'] ?? null,
        ]);
    }

    public function retry(Document $document): RedirectResponse
    {
        try {
            $this->documents->retry($document);
        } catch (DomainException $exception) {
            return back()->withErrors(['document' => $exception->getMessage()]);
        }

        return back()->with('success', "Documento {$document->folio} reemitido.");
    }
}
