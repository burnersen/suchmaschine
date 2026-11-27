<?php defined('SM_VERSION') || exit;
/** Trefferliste der Bang-Suche in den Einstellungen (auch einzeln abrufbar mit &frag=1). Braucht $me und $for. */
$all = ($for ?? '') === 'all';
$bq = mb_substr((string)($_GET['bq'] ?? ''), 0, 60);
$bf = (string)($_GET['bf'] ?? '');
if (!in_array($bf, ['', 'all', 'own', 'off', 'dead'], true)) $bf = '';
$bl = (string)($_GET['bl'] ?? '');
if (!isset(smBangLangs()[$bl])) $bl = '';
$bp = max(1, (int)($_GET['bp'] ?? 1));
$per = $bf === '' && $bl === '' && $bq === '' ? 30 : 100;
$res = smBangSearch($bq, $me, $all ? 'all' : 'mine', $bf, $per, $bl, $bp);
$keep = ['a' => 'settings', 'tab' => 'bangs', 'for' => $all ? 'all' : null, 'bq' => $bq !== '' ? $bq : null, 'bf' => $bf !== '' ? $bf : null, 'bl' => $bl !== '' ? $bl : null];
$act = smUrl($keep + ['bp' => $res['page'] > 1 ? $res['page'] : null]);
$ownLayer = $all ? 'global' : 'own';
$pager = function () use ($res, $keep): string {
    if ($res['pages'] < 2) return '';
    $o = '<nav class="bangpager small" aria-label="' . h(t('Seiten')) . '">';
    if ($res['page'] > 1) $o .= '<a class="btn btn--small" href="' . h(smUrl($keep + ['bp' => $res['page'] - 1])) . '">‹ ' . h(t('Zurück')) . '</a> ';
    $o .= '<span class="muted">' . h(t('Seite %d von %d', $res['page'], $res['pages'])) . '</span>';
    if ($res['page'] < $res['pages']) $o .= ' <a class="btn btn--small" href="' . h(smUrl($keep + ['bp' => $res['page'] + 1])) . '">' . h(t('Weiter')) . ' ›</a>';
    return $o . '</nav>';
};
$canDel = $bf !== 'off';
?>
<?php if (!$res['rows']): ?>
            <p class="muted"><?= h($bf === 'dead' ? t('Alle bisher geprüften Seiten antworten.') : ($bq !== '' || $bf !== '' || $bl !== '' ? t('Nichts gefunden.') : t('Noch keine eigenen Bangs.'))) ?></p>
<?php else: ?>
<?php if ($bq === '' && $bf === '' && $bl === ''): ?>
            <p class="muted small"><?= h(t('Hier stehen deine eigenen Bangs und die deutschen Extras. Alle anderen findest du über die Suche oben oder mit „Komplette Liste“.')) ?></p>
<?php else: ?>
            <p class="muted small"><?= h(t('%s Bangs gefunden.', number_format($res['total'], 0, ',', '.'))) ?></p>
<?php endif; ?>
<?php if ($canDel): ?>
            <form method="post" action="<?= h($act) ?>" id="bangbulk" class="bangbulk" data-busy>
                <?= smCsrfField($me) ?><input type="hidden" name="do" value="bang_bulkdel">
                <label class="check"><input type="checkbox" data-bang-all> <?= h(t('Alle auf dieser Seite markieren')) ?></label>
                <button type="submit" class="btn btn--small btn--danger" data-bang-bulk disabled><?= h(t('Markierte löschen')) ?> <span data-bang-count></span></button>
            </form>
<?php endif; ?>
            <?= $pager() ?>
            <ul class="list banglist__list">
<?php foreach ($res['rows'] as $b):
    $tags = [];
    if ($b['layer'] === $ownLayer && !$b['edited']) $tags[] = $all ? t('für alle') : t('eigenes');
    if ($b['layer'] === 'global' && !$all) $tags[] = t('für alle');
    if ($b['edited']) $tags[] = t('geändert');
    if ($b['c'] === 'Extra') $tags[] = t('Extra');
    if ($b['hidden']) $tags[] = t('gelöscht');
    ?>
                <li class="list__row bang<?= $b['hidden'] ? ' is-off' : '' ?>" data-bang="<?= h(json_encode(['t' => $b['t'], 'n' => $b['n'], 'u' => $b['u']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
<?php if ($canDel && !$b['hidden']): ?>
                    <input type="checkbox" class="bang__pick" name="ts[]" form="bangbulk" value="<?= h($b['layer'] === 'sys' ? ($b['main'] ?? $b['t']) : $b['t']) ?>" aria-label="<?= h(t('!%s markieren', $b['t'])) ?>">
<?php endif; ?>
                    <code class="bang__t">!<?= h($b['t']) ?></code>
                    <span class="list__main"><strong><?= h($b['n']) ?></strong><?php foreach ($tags as $tg): ?> <span class="tag"><?= h($tg) ?></span><?php endforeach; ?>
<?php if ($b['dead']): ?> <span class="tag tag--warn" title="<?= h(t('Die Startseite hat mehrmals hintereinander nicht geantwortet.')) ?>"><?= h(t('nicht erreichbar')) ?></span><?php endif; ?>
                        <br><small class="muted list__url"><?= h($b['d'] !== '' ? $b['d'] : $b['u']) ?><?php if ($b['a']): ?> · <?= h(t('auch')) ?> <?= h(implode(' ', array_map(function ($a) { return '!' . $a; }, $b['a']))) ?><?php endif; ?></small></span>
                    <span class="bang__acts">
<?php if ($b['hidden']): ?>
                        <form method="post" action="<?= h($act) ?>" data-busy><?= smCsrfField($me) ?><input type="hidden" name="do" value="bang_unhide"><input type="hidden" name="t" value="<?= h($b['main'] ?? $b['t']) ?>">
                            <button type="submit" class="btn btn--small"><?= h(t('Wiederherstellen')) ?></button></form>
<?php else: ?>
                        <a class="btn btn--small" href="<?= h(smUrl($keep + ['edit' => $b['t']])) ?>#bang-form" data-bang-edit><?= h(t('Bearbeiten')) ?></a>
<?php if ($b['edited']): ?>
                        <form method="post" action="<?= h($act) ?>" data-busy><?= smCsrfField($me) ?><input type="hidden" name="do" value="bang_revert"><input type="hidden" name="t" value="<?= h($b['t']) ?>">
                            <button type="submit" class="btn btn--small" title="<?= h(t('Zurück zum mitgelieferten Original')) ?>"><?= h(t('Original')) ?></button></form>
<?php endif; ?>
                        <form method="post" action="<?= h($act) ?>" data-busy><?= smCsrfField($me) ?><input type="hidden" name="do" value="bang_del"><input type="hidden" name="t" value="<?= h($b['layer'] === 'sys' ? ($b['main'] ?? $b['t']) : $b['t']) ?>">
                            <button type="submit" class="btn btn--small btn--danger"><?= h(t('Löschen')) ?></button></form>
<?php endif; ?>
                    </span>
                </li>
<?php endforeach; ?>
            </ul>
            <?= $pager() ?>
<?php if ($res['more'] && $res['pages'] < 2): ?>
            <p class="muted small"><?= h(t('Es gibt noch mehr Treffer. Gib mehr ein, um die Suche einzugrenzen.')) ?></p>
<?php endif; ?>
<?php endif; ?>
