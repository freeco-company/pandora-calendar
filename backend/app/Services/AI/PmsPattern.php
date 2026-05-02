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
    ) {}

    public function toArray(): array
    {
        return [
            'sample_cycles' => $this->sampleCycles,
            'top_symptoms' => $this->topSymptoms,
            'symptom_counts' => $this->symptomCounts,
            'confidence' => $this->confidence,
        ];
    }
}
