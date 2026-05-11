<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CommunityPost;
use App\Models\CommunityReply;
use App\Models\Feedback;
use Illuminate\Console\Command;
use Pandora\Shared\Compliance\LegalContentSanitizer;

/**
 * 集團合規硬規則（docs/group-fp-product-compliance.md）— pandora-calendar 端的稽核。
 *
 * Audit 範圍：所有 user-generated 短文本（CommunityPost / CommunityReply / Feedback）。
 * Calendar repo 沒有自家 product / article DB，所有產品 / 衛教文都從母艦 read-through，
 * 由母艦的 compliance:audit 負責。本 command 只關 user-generated 內容。
 *
 * 共用 sanitizer：freeco/pandora-shared LegalContentSanitizer。
 *
 * 排程：routes/console.php 每日 04:30 Asia/Taipei。
 */
class ComplianceAudit extends Command
{
    protected $signature = 'compliance:audit
        {--dry-run : Report only, do not modify content}';

    protected $description = 'Audit user-generated text (community / feedback) for 食安法 / 健食法 / 公平法 compliance, auto-rewrite';

    public function __construct(private readonly LegalContentSanitizer $sanitizer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->info($dryRun ? '=== Compliance audit (dry-run) ===' : '=== Compliance audit ===');

        $stats = [
            'community_posts'   => $this->auditCollection(CommunityPost::query()->whereNotNull('body'), 'body', $dryRun, ['title']),
            'community_replies' => $this->auditCollection(CommunityReply::query()->whereNotNull('body'), 'body', $dryRun),
            'feedbacks'         => $this->auditCollection(Feedback::query()->whereNotNull('message'), 'message', $dryRun),
        ];

        $this->table(
            ['Kind', 'Scanned', 'Flagged', 'Fixed', 'Top Terms'],
            [
                ['community_post',  $stats['community_posts']['scanned'],   $stats['community_posts']['flagged'],   $stats['community_posts']['fixed'],   $this->topTerms($stats['community_posts']['terms'])],
                ['community_reply', $stats['community_replies']['scanned'], $stats['community_replies']['flagged'], $stats['community_replies']['fixed'], $this->topTerms($stats['community_replies']['terms'])],
                ['feedback',        $stats['feedbacks']['scanned'],         $stats['feedbacks']['flagged'],         $stats['feedbacks']['fixed'],         $this->topTerms($stats['feedbacks']['terms'])],
            ],
        );

        return self::SUCCESS;
    }

    /**
     * @param  string[]  $alsoSanitizeTextFields
     * @return array{scanned:int,flagged:int,fixed:int,terms:array<string,int>}
     */
    private function auditCollection($query, string $textField, bool $dryRun, array $alsoSanitizeTextFields = []): array
    {
        $scanned = 0;
        $flagged = 0;
        $fixed = 0;
        $terms = [];

        foreach ($query->cursor() as $row) {
            $scanned++;
            $text = $row->{$textField} ?? '';
            if ($text === '' || $text === null) {
                continue;
            }

            $risks = $this->sanitizer->riskReport($text);
            foreach ($alsoSanitizeTextFields as $f) {
                $val = $row->{$f} ?? '';
                if ($val === '' || $val === null) {
                    continue;
                }
                foreach ($this->sanitizer->riskReport($val) as $t) {
                    $risks[] = $t;
                }
            }
            $risks = array_values(array_unique($risks));

            if (! empty($risks)) {
                $flagged++;
                foreach ($risks as $t) {
                    $terms[$t] = ($terms[$t] ?? 0) + 1;
                }
                if (! $dryRun) {
                    $row->{$textField} = $this->sanitizer->sanitizeText($text);
                    foreach ($alsoSanitizeTextFields as $f) {
                        $val = $row->{$f} ?? '';
                        if ($val !== '' && $val !== null) {
                            $row->{$f} = $this->sanitizer->sanitizeText($val);
                        }
                    }
                    $row->save();
                    $fixed++;
                }
            }
        }

        return compact('scanned', 'flagged', 'fixed', 'terms');
    }

    private function topTerms(array $terms): string
    {
        if (empty($terms)) {
            return '—';
        }
        arsort($terms);
        $top = array_slice($terms, 0, 5, true);
        $out = [];
        foreach ($top as $t => $n) {
            $out[] = "{$t}:{$n}";
        }

        return implode(', ', $out);
    }
}
