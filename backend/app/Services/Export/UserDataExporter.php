<?php

namespace App\Services\Export;

use App\Models\BbtReading;
use App\Models\Cycle;
use App\Models\CycleSymptom;
use App\Models\DodoCheckin;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * 給用戶匯出自己的資料 — 用於回診帶給醫生看 / 自留備份。
 *
 * 紅線：
 * - 文案中性、不寫療效詞（已過 sanitizer-safe 設計，不主動產生 health-claim）
 * - 只匯出該 user 的資料（嚴守租戶邊界）
 * - 檔案放 storage/app/exports/{userId}/ 用 signed URL 1 hr 過期
 */
class UserDataExporter
{
    /**
     * @return array{path: string, disk: string, filename: string}
     */
    public function exportToPdf(int $userId, ?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        [$user, $data] = $this->collect($userId, $from, $to);

        $pdf = Pdf::loadView('exports.user-data-pdf', [
            'user' => $user,
            'from' => $from,
            'to' => $to,
            'cycles' => $data['cycles'],
            'symptoms' => $data['symptoms'],
            'dodo_checkins' => $data['dodo_checkins'],
            'bbt' => $data['bbt'],
            'generated_at' => now(),
        ])->setPaper('A4');

        $filename = $this->buildFilename($user, $from, $to, 'pdf');
        $path = "exports/{$userId}/{$filename}";

        Storage::disk('local')->put($path, $pdf->output());

        return ['path' => $path, 'disk' => 'local', 'filename' => $filename];
    }

    /**
     * @return array{path: string, disk: string, filename: string}
     */
    public function exportToCsv(int $userId, ?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        [$user, $data] = $this->collect($userId, $from, $to);

        $rows = [];
        $rows[] = ['# pandora-calendar export', 'user_id='.$user->id, 'generated='.now()->toAtomString()];
        $rows[] = [];
        $rows[] = ['# CYCLES'];
        $rows[] = ['id', 'start_date', 'end_date', 'length_days', 'peak_flow', 'notes'];
        foreach ($data['cycles'] as $c) {
            $rows[] = [$c->id, $c->start_date?->toDateString(), $c->end_date?->toDateString(), $c->lengthInDays(), $c->peak_flow, $c->notes];
        }

        $rows[] = [];
        $rows[] = ['# SYMPTOMS'];
        $rows[] = ['id', 'logged_on', 'tags', 'mood', 'basal_temperature', 'note'];
        foreach ($data['symptoms'] as $s) {
            $rows[] = [$s->id, $s->logged_on?->toDateString(), implode('|', $s->tags ?? []), $s->mood, $s->basal_temperature, $s->note];
        }

        $rows[] = [];
        $rows[] = ['# DODO_CHECKINS'];
        $rows[] = ['id', 'checked_on', 'mood', 'phase', 'cycle_day', 'response'];
        foreach ($data['dodo_checkins'] as $d) {
            $rows[] = [$d->id, $d->checked_on?->toDateString(), $d->mood, $d->phase_at_checkin, $d->cycle_day_at_checkin, $d->dodo_response];
        }

        $rows[] = [];
        $rows[] = ['# BBT'];
        $rows[] = ['id', 'measured_on', 'temperature_c', 'note'];
        foreach ($data['bbt'] as $b) {
            $rows[] = [$b->id, $b->measured_on?->toDateString(), $b->temperature_c, $b->note];
        }

        $csv = $this->arrayToCsv($rows);

        $filename = $this->buildFilename($user, $from, $to, 'csv');
        $path = "exports/{$userId}/{$filename}";
        Storage::disk('local')->put($path, $csv);

        return ['path' => $path, 'disk' => 'local', 'filename' => $filename];
    }

    /**
     * @return array{0: User, 1: array{cycles: Collection, symptoms: Collection, dodo_checkins: Collection, bbt: Collection}}
     */
    private function collect(int $userId, ?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        $user = User::findOrFail($userId);

        $cyclesQ = Cycle::where('user_id', $userId);
        $symQ = CycleSymptom::where('user_id', $userId);
        $dodoQ = DodoCheckin::where('user_id', $userId);
        $bbtQ = BbtReading::where('user_id', $userId);

        if ($from) {
            $cyclesQ->where('start_date', '>=', $from->toDateString());
            $symQ->where('logged_on', '>=', $from->toDateString());
            $dodoQ->where('checked_on', '>=', $from->toDateString());
            $bbtQ->where('measured_on', '>=', $from->toDateString());
        }
        if ($to) {
            $cyclesQ->where('start_date', '<=', $to->toDateString());
            $symQ->where('logged_on', '<=', $to->toDateString());
            $dodoQ->where('checked_on', '<=', $to->toDateString());
            $bbtQ->where('measured_on', '<=', $to->toDateString());
        }

        return [$user, [
            'cycles' => $cyclesQ->orderBy('start_date')->get(),
            'symptoms' => $symQ->orderBy('logged_on')->get(),
            'dodo_checkins' => $dodoQ->orderBy('checked_on')->get(),
            'bbt' => $bbtQ->orderBy('measured_on')->get(),
        ]];
    }

    private function buildFilename(User $user, ?CarbonImmutable $from, ?CarbonImmutable $to, string $ext): string
    {
        $stamp = now()->format('Ymd_His');
        $range = $from && $to
            ? $from->format('Ymd').'-'.$to->format('Ymd')
            : 'all';

        return "pandora-calendar_{$user->id}_{$range}_{$stamp}.{$ext}";
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function arrayToCsv(array $rows): string
    {
        $fp = fopen('php://temp', 'r+');
        // BOM 給 Excel 中文不亂碼
        fwrite($fp, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        rewind($fp);
        $out = stream_get_contents($fp);
        fclose($fp);

        return (string) $out;
    }

    /**
     * 清掉 7 天前的舊匯出檔。
     */
    public function purgeOldExports(int $olderThanDays = 7): int
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subDays($olderThanDays)->getTimestamp();
        $count = 0;

        foreach ($disk->allFiles('exports') as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
                $count++;
            }
        }

        return $count;
    }
}
