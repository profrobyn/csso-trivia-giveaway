<?php
/*
 * RizQuiz question types.
 *   Scored (speed points for a correct answer): mc, tf, typeans, estimate, ranking
 *   Opinion (no right answer; optional participation points): poll, rating, wordcloud, ideas
 * Each question is a plain array stored in a question bank (JSON).
 */
if (!defined('DB_PATH')) exit;

const QTYPES = [
    'mc'        => ['label' => 'Multiple choice', 'scored' => true],
    'tf'        => ['label' => 'True or false', 'scored' => true],
    'typeans'   => ['label' => 'Type the answer', 'scored' => true],
    'estimate'  => ['label' => 'Closest number', 'scored' => true],
    'ranking'   => ['label' => 'Put in order', 'scored' => true],
    'poll'      => ['label' => 'Poll', 'scored' => false],
    'rating'    => ['label' => 'Rating scale', 'scored' => false],
    'wordcloud' => ['label' => 'Word cloud', 'scored' => false],
    'ideas'     => ['label' => 'Idea board', 'scored' => false],
];

function q_type($q) { $t = $q['type'] ?? 'mc'; return isset(QTYPES[$t]) ? $t : 'mc'; }
function q_scored($q) { return QTYPES[q_type($q)]['scored']; }
function clamp_int($v, $min, $max, $def) { $v = is_numeric($v) ? (int) $v : $def; return max($min, min($max, $v)); }
function clean_list($v, $maxItems, $maxLen = 120) {
    if (is_string($v)) $v = preg_split('/\r?\n|\|/', $v);
    if (!is_array($v)) return [];
    $out = [];
    foreach ($v as $x) { $x = trim((string) $x); if ($x !== '') $out[] = mb_substr_safe($x, $maxLen); }
    return array_slice($out, 0, $maxItems);
}

/** Validate and normalize one question (from the editor or an imported JSON file). Returns null if unusable. */
function normalize_question($q, $i) {
    if (!is_array($q)) return null;
    $type = $q['type'] ?? 'mc';
    if (!isset(QTYPES[$type])) $type = 'mc';
    $text = trim((string) ($q['text'] ?? $q['prompt'] ?? $q['question'] ?? ''));
    if ($text === '') return null;
    $t = (int) ($q['timeLimit'] ?? $q['time'] ?? $q['timer'] ?? ($type === 'ideas' ? 60 : 15));
    $pm = (string) ($q['prizeMode'] ?? 'default');
    if (!in_array($pm, array_merge(PRIZE_MODES, ['default', 'none']), true)) $pm = 'default';
    $out = [
        'id' => (string) ($q['id'] ?? ('q_' . time() . '_' . $i)),
        'type' => $type,
        'name' => mb_substr_safe(trim((string) ($q['name'] ?? $q['title'] ?? $q['label'] ?? '')), 60),
        'note' => mb_substr_safe(trim((string) ($q['note'] ?? $q['comment'] ?? $q['explanation'] ?? '')), 400),
        'text' => mb_substr_safe($text, 300),
        'timeLimit' => max(5, min(600, $t ?: 15)),
        'prizeMode' => $pm,
        'points' => clamp_int($q['points'] ?? 0, 0, 1000, 0),   // participation points (opinion types)
    ];
    $opts = clean_list($q['options'] ?? $q['choices'] ?? $q['answers'] ?? [], 6, 120);
    switch ($type) {
        case 'mc':
            if (count($opts) < 2) return null;
            $out['options'] = $opts;
            $out['correctIndex'] = clamp_int($q['correctIndex'] ?? $q['correct'] ?? 0, 0, count($opts) - 1, 0);
            break;
        case 'tf':
            $out['options'] = count($opts) === 2 ? $opts : ['True', 'False'];
            $ci = $q['correctIndex'] ?? $q['correct'] ?? 0;
            if (is_bool($ci)) $ci = $ci ? 0 : 1;
            if (is_string($ci) && !is_numeric($ci)) $ci = strtolower($ci) === 'false' ? 1 : 0;
            $out['correctIndex'] = clamp_int($ci, 0, 1, 0);
            break;
        case 'poll':
            if (count($opts) < 2) return null;
            $out['options'] = $opts;
            break;
        case 'typeans':
            $acc = clean_list($q['accepted'] ?? $q['answer'] ?? $opts, 10, 80);
            if (!$acc) return null;
            $out['accepted'] = $acc;
            break;
        case 'estimate':
            $min = is_numeric($q['min'] ?? null) ? (float) $q['min'] : 0;
            $max = is_numeric($q['max'] ?? null) ? (float) $q['max'] : 100;
            if ($max <= $min) $max = $min + 100;
            $ans = is_numeric($q['answer'] ?? null) ? (float) $q['answer'] : null;
            if ($ans === null) return null;
            $out += ['min' => $min, 'max' => $max, 'answer' => max($min, min($max, $ans)),
                'tolerance' => max(0, (float) ($q['tolerance'] ?? 0)), 'step' => max(0.0001, (float) ($q['step'] ?? 1)),
                'unit' => mb_substr_safe(trim((string) ($q['unit'] ?? '')), 12)];
            break;
        case 'ranking':
            $items = clean_list($q['items'] ?? $opts, 6, 80);
            if (count($items) < 3) return null;
            $out['items'] = $items;            // correct order, top to bottom
            break;
        case 'rating':
            $out['scale'] = in_array((int) ($q['scale'] ?? 5), [5, 10], true) ? (int) $q['scale'] : 5;
            $out['lowLabel'] = mb_substr_safe(trim((string) ($q['lowLabel'] ?? 'Not at all')), 30);
            $out['highLabel'] = mb_substr_safe(trim((string) ($q['highLabel'] ?? 'Absolutely')), 30);
            break;
        case 'wordcloud':
            $out['maxEntries'] = clamp_int($q['maxEntries'] ?? 3, 1, 5, 3);
            break;
        case 'ideas':
            $out['maxIdeas'] = clamp_int($q['maxIdeas'] ?? 2, 1, 5, 2);
            $out['voteTime'] = clamp_int($q['voteTime'] ?? 60, 10, 600, 60);
            $out['votesPerPlayer'] = clamp_int($q['votesPerPlayer'] ?? 1, 1, 5, 1);
            $out['removeAfter'] = clamp_int($q['removeAfter'] ?? 3, 0, 100, 3);   // thumbs-down votes that remove an idea (0 = never)
            // points: -1 = automatic (best idea ≈ a timed question's top score, voters ≈ a quarter of that)
            $out['bestIdeaPoints'] = clamp_int($q['bestIdeaPoints'] ?? -1, -1, 100000, -1);
            $out['voterPoints'] = clamp_int($q['voterPoints'] ?? -1, -1, 100000, -1);
            break;
    }
    return $out;
}

/** Deterministic shuffle so every phone shows ranking items in the same mixed-up order. */
function ranking_display($q, $roundId) {
    $order = range(0, count($q['items']) - 1);
    mt_srand(crc32((string) $roundId));
    shuffle($order);
    mt_srand();
    if ($order === range(0, count($order) - 1)) $order = array_reverse($order);
    return array_map(function ($i) use ($q) { return ['id' => $i, 'text' => $q['items'][$i]]; }, $order);
}

/** What phones and the stage may see. Answers stay hidden until the reveal. */
function public_question($sq, $s) {
    $type = q_type($sq);
    $revealed = $s['phase'] !== 'QUESTION';
    $q = ['type' => $type, 'scored' => q_scored($sq), 'text' => $sq['text'], 'options' => $sq['options'] ?? []];
    switch ($type) {
        case 'estimate': $q += ['min' => $sq['min'], 'max' => $sq['max'], 'step' => $sq['step'], 'unit' => $sq['unit']]; break;
        case 'ranking': $q['items'] = ranking_display($sq, $s['roundId']); break;
        case 'rating': $q += ['scale' => $sq['scale'], 'lowLabel' => $sq['lowLabel'], 'highLabel' => $sq['highLabel']]; break;
        case 'wordcloud': $q['maxEntries'] = $sq['maxEntries']; break;
        case 'ideas':
            $q += ['maxIdeas' => $sq['maxIdeas'], 'votesPerPlayer' => $sq['votesPerPlayer'], 'removeAfter' => $sq['removeAfter'],
                'bestPts' => (int) ($sq['bestPtsCalc'] ?? 0), 'voterPts' => (int) ($sq['voterPtsCalc'] ?? 0),
                'ideaStage' => $s['ideaStage'] ?? 'submit', 'voteTime' => $sq['voteTime']];
            break;
    }
    if ($revealed) {
        $q['note'] = (string) ($sq['note'] ?? '');
        if (in_array($type, ['mc', 'tf'], true)) $q['correctIndex'] = (int) $sq['correctIndex'];
        if ($type === 'typeans') $q['accepted'] = $sq['accepted'];
        if ($type === 'estimate') $q += ['answer' => $sq['answer'], 'tolerance' => $sq['tolerance']];
        if ($type === 'ranking') $q['correctOrder'] = $sq['items'];
    }
    return $q;
}

function norm_text($s) {
    $s = function_exists('mb_strtolower') ? mb_strtolower(trim((string) $s), 'UTF-8') : strtolower(trim((string) $s));
    if (function_exists('iconv')) { $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s); if ($t !== false && $t !== '') $s = $t; }
    $s = preg_replace('/[^\p{L}\p{N} ]+/u', ' ', $s);
    $s = preg_replace('/^(the|a|an) /', '', trim(preg_replace('/\s+/', ' ', $s)));
    return trim($s);
}

/**
 * Check an answer. Returns [choice (int|null), answer (json-able|null), correct (0/1)].
 * Opinion types always count as "correct" (= took part) so participants can win random prizes.
 */
function evaluate_answer($q, $choice, $value) {
    $type = q_type($q);
    switch ($type) {
        case 'mc': case 'tf': case 'poll':
            $c = (int) $choice;
            if ($c < 0 || $c >= count($q['options'])) throw new UserError('Invalid choice.');
            return [$c, null, $type === 'poll' ? 1 : (int) ($c === (int) $q['correctIndex'])];
        case 'rating':
            $c = (int) $choice;
            if ($c < 1 || $c > $q['scale']) throw new UserError('Pick a number from 1 to ' . $q['scale'] . '.');
            return [$c, null, 1];
        case 'typeans':
            $a = mb_substr_safe(trim((string) $value), 80);
            if ($a === '') throw new UserError('Type an answer first.');
            $ok = in_array(norm_text($a), array_map('norm_text', $q['accepted']), true);
            return [null, $a, (int) $ok];
        case 'estimate':
            if (!is_numeric($value)) throw new UserError('Enter a number.');
            $v = (float) $value;
            return [null, $v, (int) (abs($v - $q['answer']) <= $q['tolerance'] + 1e-9)];
        case 'ranking':
            $order = is_array($value) ? array_map('intval', $value) : [];
            $n = count($q['items']);
            $sorted = $order; sort($sorted);
            if ($sorted !== range(0, $n - 1)) throw new UserError('Put every item in order first.');
            return [null, $order, (int) ($order === range(0, $n - 1))];
        case 'wordcloud':
            $words = [];
            foreach ((array) $value as $w) {
                $w = trim(preg_replace('/\s+/u', ' ', (string) $w));
                if ($w !== '') $words[mb_strtolower($w)] = mb_substr_safe($w, 25);
            }
            $words = array_slice(array_values($words), 0, $q['maxEntries']);
            if (!$words) throw new UserError('Type at least one word.');
            return [null, $words, 1];
    }
    throw new UserError('Use the idea buttons for this question.');
}

/* -------------------------------------------------------------- idea board */

function idea_rows($roundId, $q) {
    $rows = qall('SELECT i.id, i.text, i.player_id, i.created_at, p.name, p.emoji,
                   COALESCE(SUM(CASE WHEN v.vote = 1 THEN 1 ELSE 0 END), 0) AS up,
                   COALESCE(SUM(CASE WHEN v.vote = -1 THEN 1 ELSE 0 END), 0) AS down
                  FROM ideas i LEFT JOIN players p ON p.id = i.player_id LEFT JOIN idea_votes v ON v.idea_id = i.id
                  WHERE i.round_id = ? GROUP BY i.id ORDER BY i.id', [(int) $roundId]);
    $limit = (int) ($q['removeAfter'] ?? 0);
    foreach ($rows as &$r) {
        foreach (['id', 'player_id', 'up', 'down'] as $k) $r[$k] = (int) $r[$k];
        $r['removed'] = $limit > 0 && $r['down'] >= $limit;
    }
    return $rows;
}

function ensure_participation($player, $s, $q) {
    $c = ctx();
    qexec('INSERT OR IGNORE INTO responses (event_id, session_id, round_id, bank_id, q_index, player_id, choice, answer, correct, elapsed_ms, points, created_at)
           VALUES (?,?,?,?,?,?,NULL,NULL,1,?,?,?)', [$c['eventId'], $c['sessionId'], $s['roundId'], $s['bankId'], $s['qIndex'], $player['id'],
        max(0, now_ms() - $s['startedAt']), (int) ($q['points'] ?? 0), now_ms()]);
}

function idea_submit($player, $roundId, $text) {
    $s = get_state();
    $q = $s['q'];
    if ($s['phase'] !== 'QUESTION' || q_type($q) !== 'ideas' || (int) $roundId !== (int) $s['roundId'] || ($s['ideaStage'] ?? 'submit') !== 'submit')
        throw new UserError('Idea submissions are closed.');
    if (now_ms() < $s['startedAt'] - 250) throw new UserError('Not open yet!');
    $text = trim(preg_replace('/\s+/u', ' ', (string) $text));
    if ($text === '') throw new UserError('Type your idea first.');
    $text = mb_substr_safe($text, 140);
    $mine = (int) qval('SELECT COUNT(*) FROM ideas WHERE round_id = ? AND player_id = ?', [$s['roundId'], $player['id']]);
    if ($mine >= $q['maxIdeas']) throw new UserError('You\'ve shared the most ideas allowed (' . $q['maxIdeas'] . ').');
    if (q1('SELECT id FROM ideas WHERE round_id = ? AND lower(text) = lower(?)', [$s['roundId'], $text])) throw new UserError('Someone already shared that idea!');
    qexec('INSERT INTO ideas (round_id, event_id, player_id, text, created_at) VALUES (?,?,?,?,?)', [$s['roundId'], ctx()['eventId'], $player['id'], $text, now_ms()]);
    ensure_participation($player, $s, $q);
}

function idea_vote($player, $roundId, $ideaId, $vote) {
    $s = get_state();
    $q = $s['q'];
    if ($s['phase'] !== 'QUESTION' || q_type($q) !== 'ideas' || (int) $roundId !== (int) $s['roundId'] || ($s['ideaStage'] ?? '') !== 'vote')
        throw new UserError('Voting is closed.');
    $idea = q1('SELECT * FROM ideas WHERE id = ? AND round_id = ?', [(int) $ideaId, $s['roundId']]);
    if (!$idea) throw new UserError('That idea is gone.');
    if ((int) $idea['player_id'] === (int) $player['id']) throw new UserError('You can\'t vote on your own idea.');
    $vote = (int) $vote;
    if (!in_array($vote, [-1, 0, 1], true)) throw new UserError('Invalid vote.');
    tx(function () use ($s, $q, $idea, $player, $vote) {
        foreach (idea_rows($s['roundId'], $q) as $r) {
            if ($r['id'] === (int) $idea['id'] && $r['removed'] && $vote !== 0) throw new UserError('That idea was voted off the board.');
        }
        if ($vote === 1) {
            $used = qall('SELECT v.idea_id FROM idea_votes v JOIN ideas i ON i.id = v.idea_id WHERE i.round_id = ? AND v.player_id = ? AND v.vote = 1 AND v.idea_id <> ? ORDER BY v.created_at',
                [$s['roundId'], $player['id'], $idea['id']]);
            if (count($used) >= $q['votesPerPlayer']) {
                if ($q['votesPerPlayer'] === 1) qexec('DELETE FROM idea_votes WHERE idea_id = ? AND player_id = ?', [$used[0]['idea_id'], $player['id']]);
                else throw new UserError('You\'ve used all ' . $q['votesPerPlayer'] . ' nominations. Tap a ⭐ again to take one back.');
            }
        }
        if ($vote === 0) qexec('DELETE FROM idea_votes WHERE idea_id = ? AND player_id = ?', [$idea['id'], $player['id']]);
        else qexec('INSERT OR REPLACE INTO idea_votes (idea_id, player_id, vote, created_at) VALUES (?,?,?,?)', [$idea['id'], $player['id'], $vote, now_ms()]);
    });
    ensure_participation($player, $s, $q);
}

/* -------------------------------------------------------------- live results */

/** Aggregated results for the stage. Scored types only after the reveal (no peeking). */
function round_live($s) {
    if (!$s['q'] || !$s['roundId']) return null;
    $q = $s['q'];
    $type = q_type($q);
    $rid = (int) $s['roundId'];
    $revealed = $s['phase'] !== 'QUESTION';
    $out = ['type' => $type];
    if (q_scored($q) && !$revealed) return $out;
    switch ($type) {
        case 'mc': case 'tf': case 'poll':
            $per = array_fill(0, count($q['options']), 0);
            foreach (qall('SELECT choice, COUNT(*) n FROM responses WHERE round_id = ? AND choice IS NOT NULL GROUP BY choice', [$rid]) as $r)
                if (isset($per[(int) $r['choice']])) $per[(int) $r['choice']] = (int) $r['n'];
            $out['counts'] = $per;
            break;
        case 'rating':
            $per = array_fill(1, $q['scale'], 0); $sum = 0; $n = 0;
            foreach (qall('SELECT choice, COUNT(*) n FROM responses WHERE round_id = ? AND choice IS NOT NULL GROUP BY choice', [$rid]) as $r) {
                $c = (int) $r['choice']; if (isset($per[$c])) { $per[$c] = (int) $r['n']; $sum += $c * $r['n']; $n += $r['n']; }
            }
            $out['counts'] = array_values($per); $out['avg'] = $n ? round($sum / $n, 1) : null; $out['n'] = $n;
            break;
        case 'wordcloud':
            $bag = [];
            foreach (qall('SELECT answer FROM responses WHERE round_id = ? AND answer IS NOT NULL', [$rid]) as $r) {
                foreach ((array) json_decode($r['answer'], true) as $w) {
                    $k = mb_strtolower($w);
                    if (!isset($bag[$k])) $bag[$k] = ['w' => $w, 'n' => 0];
                    $bag[$k]['n']++;
                }
            }
            usort($bag, function ($a, $b) { return $b['n'] <=> $a['n'] ?: strcmp($a['w'], $b['w']); });
            $out['words'] = array_slice(array_values($bag), 0, 80);
            $out['n'] = (int) qval('SELECT COUNT(*) FROM responses WHERE round_id = ?', [$rid]);
            break;
        case 'typeans':
            $bag = [];
            foreach (qall('SELECT answer, correct FROM responses WHERE round_id = ? AND answer IS NOT NULL', [$rid]) as $r) {
                $a = (string) json_decode($r['answer'], true); $k = norm_text($a) ?: $a;
                if (!isset($bag[$k])) $bag[$k] = ['a' => $a, 'n' => 0, 'correct' => (bool) $r['correct']];
                $bag[$k]['n']++;
            }
            usort($bag, function ($a, $b) { return $b['n'] <=> $a['n']; });
            $out['answers'] = array_slice(array_values($bag), 0, 12);
            $out['correct'] = (int) qval('SELECT COUNT(*) FROM responses WHERE round_id = ? AND correct = 1', [$rid]);
            $out['n'] = (int) qval('SELECT COUNT(*) FROM responses WHERE round_id = ?', [$rid]);
            break;
        case 'estimate':
            $rows = qall('SELECT r.answer, p.name, p.emoji FROM responses r JOIN players p ON p.id = r.player_id WHERE r.round_id = ? AND r.answer IS NOT NULL', [$rid]);
            $guesses = array_map(function ($r) use ($q) { $v = (float) json_decode($r['answer'], true); return ['v' => $v, 'd' => abs($v - $q['answer']), 'name' => $r['name'], 'emoji' => $r['emoji']]; }, $rows);
            usort($guesses, function ($a, $b) { return $a['d'] <=> $b['d']; });
            $out['closest'] = array_slice($guesses, 0, 3);
            $out['values'] = array_map(function ($g) { return $g['v']; }, $guesses);
            $out['correct'] = (int) qval('SELECT COUNT(*) FROM responses WHERE round_id = ? AND correct = 1', [$rid]);
            $out['n'] = count($guesses);
            break;
        case 'ranking':
            $out['correct'] = (int) qval('SELECT COUNT(*) FROM responses WHERE round_id = ? AND correct = 1', [$rid]);
            $out['n'] = (int) qval('SELECT COUNT(*) FROM responses WHERE round_id = ?', [$rid]);
            break;
        case 'ideas':
            $rows = idea_rows($rid, $q);
            $showNames = $revealed;
            $out['ideas'] = array_map(function ($r) use ($showNames) {
                return ['id' => $r['id'], 'text' => $r['text'], 'up' => $r['up'], 'down' => $r['down'], 'removed' => $r['removed'],
                    'name' => $showNames ? $r['name'] : null, 'emoji' => $showNames ? $r['emoji'] : null];
            }, $rows);
            break;
    }
    return $out;
}

/**
 * Points for an idea board in a scored quiz. "Typical" = the most a timed question in the loaded bank
 * can earn (seconds × 100, averaged). The best idea earns that; anyone who voted earns a quarter.
 */
function idea_points($q, $bankQs) {
    $limits = [];
    foreach ($bankQs as $b) if (q_scored($b)) $limits[] = (int) ($b['timeLimit'] ?? 15) * 100;
    $typical = $limits ? (int) round(array_sum($limits) / count($limits)) : 1500;
    $best = ($q['bestIdeaPoints'] ?? -1) >= 0 ? (int) $q['bestIdeaPoints'] : $typical;
    $voter = ($q['voterPoints'] ?? -1) >= 0 ? (int) $q['voterPoints'] : (int) round($typical / 4);
    return [$best, $voter];
}

/** At the reveal: give the top idea's author(s) the best-idea points and every voter the voter points. */
function score_idea_round($s) {
    $q = $s['q']; $rid = (int) $s['roundId'];
    $best = (int) ($q['bestPtsCalc'] ?? 0); $voter = (int) ($q['voterPtsCalc'] ?? 0);
    $base = (int) ($q['points'] ?? 0);
    $rows = array_values(array_filter(idea_rows($rid, $q), function ($r) { return !$r['removed'] && $r['up'] > 0; }));
    $topUp = $rows ? max(array_column($rows, 'up')) : 0;
    $winners = $topUp ? array_column(array_filter($rows, function ($r) use ($topUp) { return $r['up'] === $topUp; }), 'player_id') : [];
    $voters = array_map('intval', array_column(qall('SELECT DISTINCT v.player_id FROM idea_votes v JOIN ideas i ON i.id = v.idea_id WHERE i.round_id = ?', [$rid]), 'player_id'));
    foreach (qall('SELECT id, player_id FROM responses WHERE round_id = ?', [$rid]) as $r) {
        $pid = (int) $r['player_id'];
        $pts = $base + (in_array($pid, $voters, true) ? $voter : 0) + (in_array($pid, $winners, true) ? $best : 0);
        qexec('UPDATE responses SET points = ? WHERE id = ?', [$pts, $r['id']]);
    }
}

/** The best idea (most nominations, not removed) — used for the "best idea" prize. */
function top_idea($s) {
    $rows = array_values(array_filter(idea_rows($s['roundId'], $s['q']), function ($r) { return !$r['removed'] && $r['up'] > 0; }));
    usort($rows, function ($a, $b) { return $b['up'] <=> $a['up'] ?: $a['down'] <=> $b['down'] ?: $a['id'] <=> $b['id']; });
    return $rows[0] ?? null;
}

/** Human-readable answer for exports. */
function answer_text($q, $choice, $answerJson) {
    $type = q_type($q);
    $a = $answerJson !== null ? json_decode($answerJson, true) : null;
    switch ($type) {
        case 'mc': case 'tf': case 'poll': return $choice === null ? '' : ($q['options'][(int) $choice] ?? '');
        case 'rating': return $choice === null ? '' : (string) $choice;
        case 'typeans': return (string) $a;
        case 'estimate': return $a === null ? '' : (string) $a . ($q['unit'] ? ' ' . $q['unit'] : '');
        case 'ranking': return implode(' > ', array_map(function ($i) use ($q) { return $q['items'][$i] ?? '?'; }, (array) $a));
        case 'wordcloud': return implode(', ', (array) $a);
        case 'ideas': return '(idea board)';
    }
    return '';
}

/* ------------------------------------------------------------------ sounds */

/** Audio files in the RizQuiz folder (or sounds/) whose names start with "Question" or "Podium". */
function scan_sounds() {
    $out = ['question' => [], 'podium' => []];
    foreach (['' => __DIR__, 'sounds/' => __DIR__ . '/sounds'] as $prefix => $dir) {
        if (!is_dir($dir)) continue;
        foreach (scandir($dir) as $f) {
            if (!preg_match('/^(question|podium).*\.(mp3|wav|ogg|m4a|aac)$/i', $f, $m)) continue;
            $out[strtolower($m[1])][] = $prefix . $f;
        }
    }
    foreach ($out as &$list) natcasesort($list);
    return ['question' => array_values($out['question']), 'podium' => array_values($out['podium'])];
}
/** Branding settings that apply to this account: an organization member always uses the organization's official branding. */
function brand_config($cfg) {
    $me = owner_account();
    if (!is_org_member($me)) return $cfg;
    $root = org_root($me);
    $v = qval('SELECT v FROM settings WHERE k = ?', ['a' . (int) $root['id'] . ':config']);
    return array_merge(default_config(), $v ? (json_decode($v, true) ?: []) : []);
}
function brand_public($cfg) {
    if (!plan()['branding']) return null;
    $cfg = brand_config($cfg);
    $b = ['logo' => $cfg['brandLogo'] ?: null, 'primary' => $cfg['brandPrimary'] ?: null, 'accent' => $cfg['brandAccent'] ?: null, 'bg' => $cfg['brandBg'] ?: null];
    return array_filter($b) ? $b : null;
}
function chosen_sounds($cfg) {
    $all = scan_sounds();
    $pick = function ($want, $list, $fallback) { return in_array($want, $list, true) ? $want : ($list[0] ?? $fallback); };
    return ['question' => $pick($cfg['questionSound'] ?? '', $all['question'], 'Question.mp3'),
            'podium' => $pick($cfg['podiumSound'] ?? '', $all['podium'], 'Podium.mp3')];
}
