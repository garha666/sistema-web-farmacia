<?php

namespace App\Services\Pos;

use App\Models\Convention;
use App\Models\PosClient;
use App\Pos\Value\ChileanRut;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MembershipImporter
{
    public function import(UploadedFile $file, int $conventionId, int $admin): array
    {
        $text = $file->getContent();
        if (! mb_check_encoding($text, 'UTF-8') || str_contains($text, "\0")) {
            throw ValidationException::withMessages(['csv' => __('benefits.encoding')]);
        }
        $csv = $file->openFile();
        $offset = str_starts_with($text, "\xef\xbb\xbf") ? 3 : 0;
        foreach ([',', ';'] as $delimiter) {
            $csv->fseek($offset);
            if ($csv->fgetcsv($delimiter, '"', '') === ['rut', 'nombre']) {
                break;
            }
            if ($delimiter === ';') {
                throw ValidationException::withMessages(['csv' => __('benefits.header')]);
            }
        }

        return DB::transaction(function () use ($csv, $delimiter, $conventionId, $admin) {
            if (! Convention::query()->applicableOn(now(config('pos.timezone'))->toDateTimeImmutable())->lockForUpdate()->find($conventionId)) {
                throw ValidationException::withMessages(['convention_id' => __('pos.authorization.convention_unavailable')]);
            }
            $created = $updated = $linked = $existing = 0;
            $skipped = [];
            for ($row = 2; ! $csv->eof(); ++$row) {
                $fields = $csv->fgetcsv($delimiter, '"', '');
                if ($fields === false || $fields === [null]) {
                    continue;
                }
                $rut = ChileanRut::normalize($fields[0] ?? '');
                $name = trim($fields[1] ?? '');
                if ($rut === null || count($fields) !== 2 || $name === '' || mb_strlen($name) > 120) {
                    $skipped[$row] = $rut === null ? __('pos.checkout.benefit_invalid') : __('benefits.columns');
                    continue;
                }
                $client = PosClient::updateOrCreate(['rut' => $rut], ['name' => $name]);
                $client->wasRecentlyCreated ? ++$created : ++$updated;
                $inserted = DB::table('convention_memberships')->insertOrIgnore(['rut' => $rut, 'convention_id' => $conventionId, 'authorized_by' => $admin]);
                $inserted ? ++$linked : ++$existing;
            }

            return compact('created', 'updated', 'linked', 'existing', 'skipped');
        });
    }
}
