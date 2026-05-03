<?php

/*
| Wave 13 — Rank（節律段位）。
| 6 段：蒼月 / 玉月 / 金月 / 朱月 / 紫月 / 玄月。
| rank_xp 計算式（RankService）：cycles*100 + achievements*30 + days_active*1
| narrative agent 補 description / motto。
*/

return [
    'tiers' => [
        ['key' => 'cang', 'label' => '蒼月', 'min_xp' => 0, 'description' => 'TODO narrative', 'motto' => 'TODO'],
        ['key' => 'yu', 'label' => '玉月', 'min_xp' => 1000, 'description' => 'TODO narrative', 'motto' => 'TODO'],
        ['key' => 'jin', 'label' => '金月', 'min_xp' => 2500, 'description' => 'TODO narrative', 'motto' => 'TODO'],
        ['key' => 'zhu', 'label' => '朱月', 'min_xp' => 5000, 'description' => 'TODO narrative', 'motto' => 'TODO'],
        ['key' => 'zi', 'label' => '紫月', 'min_xp' => 10000, 'description' => 'TODO narrative', 'motto' => 'TODO'],
        ['key' => 'xuan', 'label' => '玄月', 'min_xp' => 20000, 'description' => 'TODO narrative', 'motto' => 'TODO'],
    ],
];
