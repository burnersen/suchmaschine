<?php defined('SM_VERSION') || exit;
/** Kleines Menü an einem Treffer: Domain blockieren, hochstufen, herabstufen (nur für das eigene Konto) */
$dom = smCleanDomain($r['host']);
if ($dom !== ''): $acts = ['raise' => t('Hochstufen'), 'lower' => t('Herabstufen'), 'block' => t('Blockieren')]; ?>
            <details class="resmenu">
                <summary class="iconbtn resmenu__btn" aria-label="<?= h(t('Optionen für %s', $dom)) ?>" title="<?= h(t('Optionen für %s', $dom)) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="5.5" r="1.3"/><circle cx="12" cy="12" r="1.3"/><circle cx="12" cy="18.5" r="1.3"/></svg></summary>
                <div class="resmenu__panel">
                    <span class="resmenu__dom"><?= h($dom) ?></span>
<?php foreach ($acts as $k => $label): ?>
                    <form method="post" action="<?= h(smUrl(['a' => 'domain'])) ?>"><?= smCsrfField($me) ?><input type="hidden" name="d" value="<?= h($dom) ?>"><input type="hidden" name="back" value="<?= h($back) ?>">
                        <button type="submit" name="act" value="<?= $k ?>" class="resmenu__<?= $k ?>"><?= h($label) ?></button></form>
<?php endforeach; ?>
<?php if ((int)($r['rank'] ?? 0) !== 0): ?>
                    <form method="post" action="<?= h(smUrl(['a' => 'domain'])) ?>"><?= smCsrfField($me) ?><input type="hidden" name="d" value="<?= h($dom) ?>"><input type="hidden" name="back" value="<?= h($back) ?>">
                        <button type="submit" name="act" value="clear"><?= h(t('Zurücksetzen')) ?></button></form>
<?php endif; ?>
                    <a href="<?= h(smUrl(['a' => 'settings', 'tab' => 'domains'])) ?>"><?= h(t('Listen bearbeiten')) ?></a>
                </div>
            </details>
<?php endif;
