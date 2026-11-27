<?php defined('SM_VERSION') || exit;
/**
 * Karten über den Treffern (nur Seite 1 bei „Alle“): Spiele, Kurs, KI-Übersicht und die Infobox am Rand.
 * Kommt mit der Trefferliste oder, bei „Neueste zuerst“, kurz danach über ?a=cards.
 */
$team = function (array $t, string $side = '') {
    $img = $t['logo'] !== '' ? '<img class="gteam__logo" src="' . h($t['logo']) . '" alt="" loading="lazy">'
         : '<span class="gteam__logo gteam__logo--txt" aria-hidden="true">' . h(mb_strtoupper(mb_substr($t['name'], 0, 1))) . '</span>';
    return '<span class="gteam' . ($side !== '' ? ' gteam--' . $side : '') . (!empty($t['win']) ? ' is-win' : '') . '">' . $img . '<span class="gteam__name">' . h($t['name']) . '</span></span>';
};
$isLive = function (string $status, string $score): bool {
    return $score !== '' && $status !== '' && !preg_match('/endstand|beendet|final|ende|^ft$|abgesagt|verschoben/iu', $status);
};
?>
<?php if (!empty($res['sports'])): $sp = $res['sports']; ?>
    <section class="lcard lcard--sports" aria-label="<?= h(t('Spiele')) ?>">
        <h2 class="lcard__title"><?= h($sp['title'] !== '' ? $sp['title'] : t('Spiele')) ?> <span class="lcard__src"><?= h(implode(' · ', array_filter([$sp['sub'] ?? '', t('laut Google')]))) ?></span></h2>
<?php if (!empty($sp['main'])): $m = $sp['main']; $live = !empty($m['live']) || $isLive($m['status'], $m['teams'][0]['score']); ?>
        <div class="gmatch<?= $live ? ' gmatch--live' : '' ?>">
            <p class="gmatch__meta"><?= $live ? '<b class="live">' . h(t('Live')) . '</b> ' : '' ?><?= h(implode(' · ', array_filter([$m['league'], $m['when'], $m['status']]))) ?></p>
            <div class="gmatch__row">
                <?= $team($m['teams'][0]) ?>
                <b class="gmatch__score"><?= h($m['teams'][0]['score'] !== '' ? $m['teams'][0]['score'] . ' : ' . $m['teams'][1]['score'] : '–') ?></b>
                <?= $team($m['teams'][1]) ?>
            </div>
        </div>
<?php endif; ?>
<?php if (!empty($sp['events'])): ?>
        <ul class="matches">
<?php foreach ($sp['events'] as $ev): $live = $isLive($ev['status'], $ev['teams'][0]['score']); ?>
            <li class="match<?= $live ? ' match--live' : '' ?>">
                <span class="match__meta"><?= $live ? '<b class="live">' . h(t('Live')) . '</b> ' : '' ?><?= h(implode(' · ', array_filter([$ev['league'] ?? '', $ev['when'] ?? ($ev['date'] ?? ''), $ev['status']]))) ?></span>
                <span class="match__row">
                    <?= $team($ev['teams'][0]) ?>
                    <b class="match__score"><?= h($ev['teams'][0]['score'] !== '' ? $ev['teams'][0]['score'] . ' : ' . $ev['teams'][1]['score'] : '– : –') ?></b>
                    <?= $team($ev['teams'][1], 'r') ?>
                </span>
            </li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
    </section>
<?php endif; ?>
<?php if (!empty($res['stock'])): $st = $res['stock']; ?>
    <section class="lcard lcard--stock" aria-label="<?= h(t('Kurs')) ?>">
        <h2 class="lcard__title"><?= h($st['title']) ?> <span class="lcard__src"><?= h(implode(': ', array_filter([$st['exchange'], $st['symbol']]))) ?></span></h2>
        <p class="stock__price"><b><?= h($st['price']) ?></b> <?= h($st['currency']) ?>
<?php if ($st['abs'] !== '' || $st['pct'] !== ''): ?>
            <span class="stock__chg stock__chg--<?= $st['up'] ? 'up' : 'down' ?>"><?= h(trim($st['abs'] . ($st['pct'] !== '' ? ' (' . $st['pct'] . ')' : ''))) ?> <?= h($st['period']) ?></span>
<?php endif; ?></p>
<?php if ($st['rows']): ?>
        <dl class="stock__rows"><?php foreach ($st['rows'] as $row): ?><div><dt><?= h($row[0]) ?></dt><dd><?= h($row[1]) ?></dd></div><?php endforeach; ?></dl>
<?php endif; ?>
        <p class="lcard__note"><?= h(t('Kurse können verzögert sein. Keine Anlageberatung.')) ?></p>
    </section>
<?php endif; ?>
<?php if (!empty($res['answer'])): ?>
    <section class="answer" aria-label="<?= h(t('KI-Übersicht')) ?>">
        <h2 class="answer__title"><?= h(t('KI-Übersicht')) ?> <span class="answer__src"><?= h(t('von Google erstellt, bitte nachprüfen')) ?></span></h2>
        <p class="answer__text" data-clamp><?= h($res['answer']) ?></p>
<?php if (!empty($res['answer_links'])): ?>
        <p class="answer__links"><?php foreach ($res['answer_links'] as $al): ?><a class="chip" href="<?= h($al['url']) ?>"<?= $rel . $target ?>><?= h($al['title'] !== '' ? $al['title'] : smHost((string)parse_url($al['url'], PHP_URL_HOST))) ?></a> <?php endforeach; ?></p>
<?php endif; ?>
    </section>
<?php endif; ?>
<?php if (!empty($res['infobox'])): $ib = $res['infobox']; ?>
    <aside class="kp" aria-label="<?= h(t('Kurzinfo')) ?>">
<?php if ($ib['thumb']): ?><img class="kp__img" src="<?= h($ib['thumb']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php endif; ?>
        <h2 class="kp__title"><?= h($ib['title']) ?></h2>
<?php if ($ib['subtitle']): ?><p class="kp__sub"><?= h($ib['subtitle']) ?></p><?php endif; ?>
<?php if ($ib['desc']): ?><p class="kp__desc" data-clamp><?= h($ib['desc']) ?>
<?php if ($ib['url']): ?> <a href="<?= h($ib['url']) ?>"<?= $rel . $target ?>><?= h($ib['source'] ?: smHost((string)parse_url($ib['url'], PHP_URL_HOST))) ?></a><?php endif; ?>
<?php if (!empty($ib['license'])): ?> <small class="muted"><?= h($ib['license']) ?></small><?php endif; ?></p><?php endif; ?>
<?php if ($ib['attrs']): ?>
        <dl class="kp__attrs">
<?php foreach ($ib['attrs'] as $at): ?><div><dt><?= h($at[0]) ?>:</dt> <dd><?= h($at[1]) ?></dd></div><?php endforeach; ?>
        </dl>
<?php endif; ?>
<?php if ($ib['profiles'] || $ib['website']): ?>
        <div class="kp__links">
<?php if ($ib['website']): ?><a class="chip" href="<?= h($ib['website']) ?>"<?= $rel . $target ?>><?= h(t('Website')) ?></a><?php endif; ?>
<?php foreach ($ib['profiles'] as $pr): ?><a class="chip" href="<?= h($pr['url']) ?>"<?= $rel . $target ?>><?= h($pr['name']) ?></a><?php endforeach; ?>
        </div>
<?php endif; ?>
    </aside>
<?php endif; ?>
