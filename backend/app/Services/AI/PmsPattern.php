<?php

namespace App\Services\AI;

final class PmsPattern
{
    public function __construct(
        public readonly int $sampleCycles,
        /** @var string[] */
        public readonly array $topSymptoms,
        /** @var array<string,int> */
        public readonly array $symptomCounts,
        public readonly string $confidence,
        /** @var array<string,string> tag => 朵朵建議文案（已過 sanitizer） */
        public readonly array $suggestions = [],
        /**
         * 嚴重度趨勢（最近 3 週期 vs 之前同 tag 平均）：
         *   'worsening' / 'stable' / 'improving' / 'unknown'
         */
        public readonly string $severityTrend = 'unknown',
    ) {}

    public function toArray(): array
    {
        return [
            'sample_cycles' => $this->sampleCycles,
            'top_symptoms' => $this->topSymptoms,
            'symptom_counts' => $this->symptomCounts,
            'confidence' => $this->confidence,
            'suggestions' => $this->suggestions,
            'severity_trend' => $this->severityTrend,
        ];
    }
}
