<?php

namespace Database\Seeders;

use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seed welcome posts attributed to "dodo-team" so a brand new user opening the
 * community board sees content (cold-start UX). All copy passes the compliance
 * sanitizer rules — no therapeutic claims, no MLM, no commerce.
 */
class CommunityWelcomePostsSeeder extends Seeder
{
    public function run(): void
    {
        // Find or create a system user to own these posts (user_id required by FK).
        $system = User::firstOrCreate(
            ['email' => 'dodo-team@pandora-calendar.test'],
            [
                'name' => '朵朵小編',
                'display_name' => '朵朵小編',
                'password' => bcrypt(str()->random(40)),
            ],
        );

        $posts = [
            [
                'category' => 'tip',
                'title' => '第一次用月曆 App 的小提醒',
                'body' => "歡迎妳加入潘朵拉月曆。第一週可以這樣開始：\n\n".
                    "1. 在月曆上把這次經期的第一天打勾\n".
                    "2. 想到的時候，記下心情和身體感覺\n".
                    "3. 不用記滿，記得一點是一點\n\n".
                    '兩個週期之後，朵朵就能幫妳預測下一次的時間。慢慢來，這是妳和身體相處的時間。',
            ],
            [
                'category' => 'tip',
                'title' => 'BBT 怎麼測比較準',
                'body' => "BBT（基礎體溫）是了解週期很實用的訊號。給想嘗試的朋友：\n\n".
                    "・每天早上醒來、還沒下床前測\n".
                    "・盡量同一個時間（前後 30 分鐘內）\n".
                    "・用專門的 BBT 體溫計，刻度比較細\n".
                    "・舌下放 5 分鐘\n\n".
                    '至少連續 2-3 個週期才看得出規律。如果忘記了也沒關係，缺一兩天不影響大方向。',
            ],
            [
                'category' => 'experience',
                'title' => 'PMS 來臨時可以做什麼',
                'body' => "經前一週情緒低落、煩躁、想哭——這些都是身體的訊號，不是妳的錯。\n\n".
                    "我自己會做的事：\n".
                    "・提早睡 30 分鐘\n".
                    "・把那週的行程排鬆一點\n".
                    "・少喝咖啡\n".
                    "・讓自己安心吃喜歡的東西，不糾結\n\n".
                    '每個人的 PMS 樣貌不同，找到適合妳的節奏最重要。',
            ],
            [
                'category' => 'support',
                'title' => '給備孕朋友的悄悄話',
                'body' => "備孕的路有時候很安靜。月曆 App 可以幫忙看排卵窗口，但結果出來之前的等待，是最難的。\n\n".
                    "想分享幾件對我有用的事：\n".
                    "・不要把每次月經來都當成失敗\n".
                    "・該休息的時候休息，不用每件事都最佳化\n".
                    "・有伴侶的話，輪流負責記錄，分擔壓力\n\n".
                    '如果想找朋友聊聊，這個社群在。',
            ],
            [
                'category' => 'question',
                'title' => '延經幾天才需要驗孕？',
                'body' => "看到很多朋友問這個。一般的建議是：\n\n".
                    "・週期規律的人，延經 5-7 天可以驗\n".
                    "・週期不規律，可以等到比平常週期長 1 週左右\n".
                    "・想早一點知道，可以選敏感度高的試紙\n\n".
                    '這只是參考，每個人狀況不同。如果延經太久或有不舒服，記得去看婦產科。',
            ],
            [
                'category' => 'experience',
                'title' => '經期第一天，妳會做什麼？',
                'body' => "我自己有個小儀式：經期第一天會泡熱水袋、煮一杯薑茶、不排重要會議。\n\n".
                    "把第一天當成「給自己的休息日」，後面幾天反而更輕鬆。\n\n".
                    '想知道大家都怎麼度過經期第一天，留言分享吧。',
            ],
            [
                'category' => 'tip',
                'title' => '記錄症狀的小訣竅',
                'body' => "症狀記錄不用每天都打開 App，朵朵建議的節奏：\n\n".
                    "・有「特別」的感覺才記（特別痛、特別累、特別想哭）\n".
                    "・睡前順手記，比白天記更穩\n".
                    "・心情和身體分開記，回顧時看得清楚\n\n".
                    '記錄是給自己看的，不是給朵朵看的。',
            ],
            [
                'category' => 'support',
                'title' => '經痛到影響生活，怎麼辦？',
                'body' => "如果妳每次經痛都讓妳請假、躺床、什麼都做不了，那不是「正常的經痛」。\n\n".
                    "可以做的事：\n".
                    "・記下痛的位置、時間、程度（App 可以幫妳）\n".
                    "・帶記錄去看婦產科，醫師會更好判斷\n".
                    "・不要忍——疼痛是身體的訊號，不是磨練\n\n".
                    '如果妳正在痛，先抱抱自己，這不是妳的錯。',
            ],
            [
                'category' => 'experience',
                'title' => '黃體期的我 vs 濾泡期的我',
                'body' => "用了月曆 App 半年，我發現：\n\n".
                    "・濾泡期：精神好、想出門、想嘗試新事物\n".
                    "・排卵期前後：社交慾望最高\n".
                    "・黃體期：想宅在家、想吃甜的、容易煩躁\n".
                    "・經期：低能量但想被理解\n\n".
                    '了解自己的節奏之後，安排事情就更有彈性。妳呢？',
            ],
            [
                'category' => 'question',
                'title' => '週期忽長忽短正常嗎？',
                'body' => "21-35 天都算正常範圍，前後差個幾天也不用擔心。\n\n".
                    "比較需要留意的狀況：\n".
                    "・連續 3 個週期超過 35 天\n".
                    "・連續 3 個週期短於 21 天\n".
                    "・突然停經 3 個月以上\n".
                    "・出血量突然變很多或很少\n\n".
                    '有以上狀況建議去看婦產科聊聊，記錄帶著去更方便。',
            ],
        ];

        foreach ($posts as $i => $p) {
            CommunityPost::firstOrCreate(
                ['title' => $p['title']],
                [
                    'user_id' => $system->id,
                    'anonymous_handle' => 'dodo-team',
                    'category' => $p['category'],
                    'body' => $p['body'],
                    'status' => 'published',
                    'published_at' => now()->subDays(10 - $i),
                    'like_count' => random_int(5, 40),
                    'reply_count' => 0,
                    'reported_count' => 0,
                    'moderation_score' => 0,
                ],
            );
        }
    }
}
