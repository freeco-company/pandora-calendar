<?php

namespace App\Services\MedicalSafety;

/**
 * 醫療安全決策樹 evaluator。
 *
 * config 結構：top-level array 以 context key 分群（例：late_period / heavy_bleeding /
 * severe_pain / irregular_pattern / bbt_no_shift_3_cycles / spotting_between_periods），
 * 每個 group 是 rule list，每條 rule 含：condition / action / urgency / message /
 * suggest_test? / find_doctor_url?
 *
 * condition 是 mini-expression：支援
 *   - 運算子：>= > <= < == !=
 *   - 連接：&& ||（左到右，無括號）
 *   - 數字 / true / false / 變數名（snake_case）
 *
 * 比對策略：在該 context group 內由上而下取「第一個 condition 為 true」的 rule。
 * 若都沒中 → 回 default（low / no_action）。
 *
 * 紅線：所有 message 已在 config 寫好不含療效詞；evaluator 不自己生文案。
 */
class MedicalSafetyEvaluator
{
    /**
     * @param  array<string, mixed>  $input  e.g. ['days_late' => 14, 'sexually_active' => true]
     * @return array{rule_id: ?string, urgency: string, action: string, message: string, suggest_test: ?bool, find_doctor_url: ?string}
     */
    public function evaluate(string $context, array $input): array
    {
        $rules = (array) (config('medical-safety.'.$context, []) ?? []);

        foreach ($rules as $idx => $rule) {
            $condition = (string) ($rule['condition'] ?? '');
            if ($condition === '') {
                continue;
            }
            if ($this->evalExpression($condition, $input)) {
                return [
                    'rule_id' => $context.':'.$idx,
                    'urgency' => $rule['urgency'] ?? 'low',
                    'action' => $rule['action'] ?? 'no_action',
                    'message' => $rule['message'] ?? '',
                    'suggest_test' => $rule['suggest_test'] ?? null,
                    'find_doctor_url' => $rule['find_doctor_url'] ?? null,
                ];
            }
        }

        return [
            'rule_id' => null,
            'urgency' => 'low',
            'action' => 'no_action',
            'message' => '目前看起來在常見範圍內，朵朵會繼續陪妳記錄。',
            'suggest_test' => null,
            'find_doctor_url' => null,
        ];
    }

    /**
     * Mini-expression evaluator。
     * 安全：只允許白名單 token；不會 eval PHP code。
     *
     * @param  array<string, mixed>  $vars
     */
    private function evalExpression(string $expr, array $vars): bool
    {
        // 拆 || → 任一 true 即 true
        $orParts = array_map('trim', preg_split('/\s*\|\|\s*/', $expr));
        foreach ($orParts as $orPart) {
            if ($this->evalAndClause($orPart, $vars)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $vars
     */
    private function evalAndClause(string $clause, array $vars): bool
    {
        $andParts = array_map('trim', preg_split('/\s*&&\s*/', $clause));
        foreach ($andParts as $atom) {
            if (! $this->evalAtom($atom, $vars)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $vars
     */
    private function evalAtom(string $atom, array $vars): bool
    {
        // matches: lhs op rhs, op ∈ {>=, <=, ==, !=, >, <}
        if (! preg_match('/^\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*(>=|<=|==|!=|>|<)\s*(.+?)\s*$/', $atom, $m)) {
            return false;
        }

        $varName = $m[1];
        $op = $m[2];
        $rhsRaw = trim($m[3]);

        if (! array_key_exists($varName, $vars)) {
            // 變數沒給 → 視為 false（保守）
            return false;
        }

        $lhs = $vars[$varName];
        $rhs = $this->parseLiteral($rhsRaw);

        // bool 比較走 == / !=
        if (is_bool($rhs) || is_bool($lhs)) {
            return match ($op) {
                '==' => (bool) $lhs === (bool) $rhs,
                '!=' => (bool) $lhs !== (bool) $rhs,
                default => false,
            };
        }

        // 數字比較
        $lhsN = is_numeric($lhs) ? (float) $lhs : 0.0;
        $rhsN = is_numeric($rhs) ? (float) $rhs : 0.0;

        return match ($op) {
            '>=' => $lhsN >= $rhsN,
            '<=' => $lhsN <= $rhsN,
            '>' => $lhsN > $rhsN,
            '<' => $lhsN < $rhsN,
            '==' => $lhsN === $rhsN,
            '!=' => $lhsN !== $rhsN,
            default => false,
        };
    }

    private function parseLiteral(string $raw): bool|float|int|string
    {
        if ($raw === 'true') {
            return true;
        }
        if ($raw === 'false') {
            return false;
        }
        if (is_numeric($raw)) {
            return str_contains($raw, '.') ? (float) $raw : (int) $raw;
        }

        // 字串值（去引號）
        return trim($raw, "'\"");
    }
}
