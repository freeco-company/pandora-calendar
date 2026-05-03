<?php

namespace App\Services\Qna;

use Illuminate\Support\Facades\DB;

/**
 * QnaRetriever — 從 daily_insights 表撿出 top N 篇與問題最相關的衛教文章作 RAG context。
 *
 * 這是 lightweight RAG（無 vector DB）：
 *   - score = title 命中關鍵詞 × 3 + body 命中 × 1（去掉 stopwords / 1-char 字）
 *   - 同分以 phase match 加成（user 當前 phase 對應）
 *   - 上限 5 篇 + 各 ~200 字 truncate，避免 prompt 過長
 *
 * 不丟 user 任何 PII 進去做檢索（query 只是用戶問題本身）。
 */
class QnaRetriever
{
    private const MAX_RESULTS = 5;
    private const SNIPPET_CHARS = 200;
    private const STOPWORDS = ['的', '了', '是', '我', '你', '妳', '他', '她', '這', '那', '在', '有', '怎', '麼', '為', '甚', '什', '嗎', '?', '？'];

    /**
     * @return array<int, array{id:int, title:string, body:string, phase:string, day_offset:int}>
     */
    public function retrieve(string $question, ?string $currentPhase = null): array
    {
        $tokens = $this->tokenize($question);
        if ($tokens === []) {
            return [];
        }

        $rows = DB::table('daily_insights')
            ->select(['id', 'phase', 'day_offset', 'title', 'body'])
            ->get();

        $scored = [];
        foreach ($rows as $row) {
            $score = 0;
            foreach ($tokens as $tok) {
                $tlen = mb_strlen($tok);
                if ($tlen < 2) {
                    continue;
                }
                $tHits = mb_substr_count((string) $row->title, $tok);
                $bHits = mb_substr_count((string) $row->body, $tok);
                $score += $tHits * 3 + $bHits;
            }
            if ($currentPhase !== null && $row->phase === $currentPhase) {
                $score += 1; // tie-breaker boost for current phase
            }
            if ($score > 0) {
                $scored[] = [
                    'id' => (int) $row->id,
                    'title' => (string) $row->title,
                    'body' => $this->truncate((string) $row->body),
                    'phase' => (string) $row->phase,
                    'day_offset' => (int) $row->day_offset,
                    '_score' => $score,
                ];
            }
        }

        usort($scored, fn ($a, $b) => $b['_score'] <=> $a['_score']);
        $top = array_slice($scored, 0, self::MAX_RESULTS);

        // strip internal score
        return array_map(function ($a) {
            unset($a['_score']);
            return $a;
        }, $top);
    }

    /** Cheap tokenizer: 拆 2-gram + 漢字片段 + 英數詞 */
    private function tokenize(string $q): array
    {
        $q = mb_strtolower($q);
        $q = preg_replace('/[\s,，。.!\?？！、；;:：]+/u', ' ', $q) ?? $q;
        $words = array_filter(explode(' ', trim($q)));

        $tokens = [];
        foreach ($words as $w) {
            $len = mb_strlen($w);
            // 英數詞直接收
            if (preg_match('/^[a-z0-9]+$/u', $w) && $len >= 2) {
                $tokens[] = $w;
                continue;
            }
            // 中文 2-gram
            for ($i = 0; $i < $len - 1; $i++) {
                $bg = mb_substr($w, $i, 2);
                if (! in_array(mb_substr($bg, 0, 1), self::STOPWORDS, true)
                    && ! in_array(mb_substr($bg, 1, 1), self::STOPWORDS, true)) {
                    $tokens[] = $bg;
                }
            }
        }
        return array_values(array_unique($tokens));
    }

    private function truncate(string $body): string
    {
        if (mb_strlen($body) <= self::SNIPPET_CHARS) {
            return $body;
        }
        return mb_substr($body, 0, self::SNIPPET_CHARS) . '…';
    }
}
