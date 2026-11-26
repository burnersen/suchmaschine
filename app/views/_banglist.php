<?php defined('SM_VERSION') || exit;
/** Trefferliste der Bang-Suche in den Einstellungen (auch einzeln abrufbar mit &frag=1). Braucht $me und $for. */
$all = ($for ?? '') === 'all';
$bq = mb_substr((string)($_GET['bq'] ?? ''), 0, 60);
$bf = (string)($_GET['bf'] ?? '');
if (!in_array($bf, ['', 'own', 'off', 'dead'], true)) $bf = '';
$res = smBangSearch($bq, $me, $all ? 'all' : 'mine', $bf, $bf === '' ? 30 : 300);
$act = smUrl(['a' => 'settings', 'tab' => 'bangs', 'for' => $all ? 'all' : null, 'bq' => $bq !== '' ? $bq : null, 'bf' => $bf !== '' ? $bf : null]);
$ownLayer = $all ? 'global' : 'own';
?>
<?php if (!$res['rows']): ?>
            <p class="muted"><?= h($bf === 'dead' ? t('Alle bisher geprüften Seiten antworten.') : ($bq !== '' || $bf !== '' ? t('Nichts gefunden.') : t('Noch keine eigenen Bangs.'))) ?></p>
<?php else: ?>
<?php if ($bq === '' && $bf === ''): ?>
            <p class="muted small"><?= h(t('Hier stehen deine eigenen Bangs und die deutschen Extras. Alle anderen findest du über die Suche oben.')) ?></p>
<?php endif; ?>
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
                    <code class="bang__t">!<?= h($b['t']) ?></code>
                    <span class="list__main"><strong><?= h($b['n']) ?></strong><?php foreach ($tags as $tg): ?> <span class="tag"><?= h($tg) ?></span><?php endforeach; ?>
<?php if ($b['dead']): ?> <span class="tag tag--warn" title="<?= h(t('Die Startseite hat mehrmals hintereinander nicht geantwortet.')) ?>"><?= h(t('nicht erreichbar')) ?></span><?php endif; ?>
                        <br><small class="muted list__url"><?= h($b['d'] !== '' ? $b['d'] : $b['u']) ?><?php if ($b['a']): ?> · <?= h(t('auch')) ?> <?= h(implode(' ', array_map(function ($a) { return '!' . $a; }, $b['a']))) ?><?php endif; ?></small></span>
                    <span class="bang__acts">
<?php if ($b['hidden']): ?>
                        <form method="post" action="<?= h($act) ?>" data-busy><?= smCsrfField($me) ?><input type="hidden" name="do" value="bang_unhide"><input type="hidden" name="t" value="<?= h($b['main'] ?? $b['t']) ?>">
                            <button type="submit" class="btn btn--small"><?= h(t('Wiederherstellen')) ?></button></form>
<?php else: ?>
                        <a class="btn btn--small" href="<?= h(smUrl(['a' => 'settings', 'tab' => 'bangs', 'for' => $all ? 'all' : null, 'bq' => $bq !== '' ? $bq : null, 'bf' => $bf !== '' ? $bf : null, 'edit' => $b['t']])) ?>#bang-form" data-bang-edit><?= h(t('Bearbeiten')) ?></a>
<?php if ($b['edited']): ?>
                        <form method="post" action="<?= h($act) ?>" data-busy><?= smCsrfField($me) ?><input type="hidden" name="do" value="bang_revert"><input type="hidden" name="t" value="<?= h($b['t']) ?>">
                            <button type="submit" class="btn btn--small" title="<?= h(t('Zurück zum mitgelieferten Original')) ?>"><?= h(t('Original')) ?></button></form>
<?php endif; ?>
                        <form method="post" action="<?= h($act) ?>" data-busy data-confirm="<?= h(t('Bang !%s löschen?', $b['t'])) ?>"><?= smCsrfField($me) ?><input type="hidden" name="do" value="bang_del"><input type="hidden" name="t" value="<?= h($b['layer'] === 'sys' ? ($b['main'] ?? $b['t']) : $b['t']) ?>">
                            <button type="submit" class="btn btn--small btn--danger"><?= h(t('Löschen')) ?></button></form>
<?php endif; ?>
                    </span>
                </li>
<?php endforeach; ?>
            </ul>
<?php if ($res['more']): ?>
            <p class="muted small"><?= h(t('Es gibt noch mehr Treffer. Gib mehr ein, um die Suche einzugrenzen.')) ?></p>
<?php endif; ?>
<?php endif; ?>
