<?php defined('SM_VERSION') || exit;
/** Menü ⋮ an einem Treffer: nur auf dieser Domain suchen, Archiv, QR-Code; mit Konto auch Domain hoch-/herabstufen oder blockieren */
$dom = smCleanDomain($r['host']);
$qNoSite = trim(preg_replace('/(^|\s)-?site:\S+/i', ' ', $q));
$acts = ['raise' => t('Hochstufen'), 'lower' => t('Herabstufen'), 'block' => t('Blockieren')]; ?>
            <details class="resmenu">
                <summary class="iconbtn resmenu__btn" aria-label="<?= h(t('Optionen für %s', $r['host'])) ?>" title="<?= h(t('Optionen für %s', $r['host'])) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="5.5" r="1.3"/><circle cx="12" cy="12" r="1.3"/><circle cx="12" cy="18.5" r="1.3"/></svg></summary>
                <div class="resmenu__panel">
                    <span class="resmenu__dom"><?= h($dom !== '' ? $dom : $r['host']) ?></span>
<?php if ($dom !== ''): ?>
                    <a href="<?= h(smUrl(['q' => 'site:' . $dom . ($qNoSite !== '' ? ' ' . $qNoSite : '')] + array_diff_key($base, ['q' => 1]))) ?>" data-load><?= h(t('Nur auf dieser Domain suchen')) ?></a>
<?php endif; ?>
<?php if ($me): ?>
                    <a href="<?= h(smUrl(['a' => 'read', 'u' => $r['url']])) ?>" class="resmenu__read"><?= h(t('Ohne Werbung lesen')) ?></a>
<?php endif; ?>
                    <a href="https://web.archive.org/web/2/<?= h($r['url']) ?>" rel="noopener noreferrer" target="_blank"><?= h(t('Archiv: web.archive.org')) ?></a>
                    <a href="https://archive.today/newest/<?= h($r['url']) ?>" rel="noopener noreferrer" target="_blank"><?= h(t('Archiv: archive.today')) ?></a>
                    <button type="button" data-qr="<?= h($r['url']) ?>" data-qr-src="<?= h(smUrl(['a' => 'qr', 'd' => $r['url']])) ?>"><?= h(t('Als QR-Code anzeigen')) ?></button>
                    <button type="button" data-copy-text="<?= h($r['url']) ?>" data-done="<?= h(t('Kopiert')) ?>"><?= h(t('Adresse kopieren')) ?></button>
<?php if ($me && $dom !== ''): ?>
                    <span class="resmenu__sep"></span>
<?php foreach ($acts as $k => $label): ?>
                    <form method="post" action="<?= h(smUrl(['a' => 'domain'])) ?>"><?= smCsrfField($me) ?><input type="hidden" name="d" value="<?= h($dom) ?>"><input type="hidden" name="back" value="<?= h($back) ?>">
                        <button type="submit" name="act" value="<?= $k ?>" class="resmenu__<?= $k ?>"><?= h($label) ?></button></form>
<?php endforeach; ?>
<?php if ((int)($r['rank'] ?? 0) !== 0): ?>
                    <form method="post" action="<?= h(smUrl(['a' => 'domain'])) ?>"><?= smCsrfField($me) ?><input type="hidden" name="d" value="<?= h($dom) ?>"><input type="hidden" name="back" value="<?= h($back) ?>">
                        <button type="submit" name="act" value="clear"><?= h(t('Zurücksetzen')) ?></button></form>
<?php endif; ?>
                    <a href="<?= h(smUrl(['a' => 'settings', 'tab' => 'domains'])) ?>"><?= h(t('Listen bearbeiten')) ?></a>
<?php endif; ?>
                </div>
            </details>
<?php
