<?php defined('SM_VERSION') || exit;
/** Kontingent eines Kontos bearbeiten ($quser: Konto-ID) */
$qq = smQuotaOf($quser);
$qset = $qq['allow'] !== null || $qq['lim'];
?>
                    <details class="pop pop--wide">
                        <summary class="btn btn--small<?= $qset ? ' is-set' : '' ?>"><?= h(t('Kontingent')) ?></summary>
                        <form method="post" action="<?= h(smUrl(['a' => 'admin', 'tab' => 'users'])) ?>" class="pop__panel quota">
                            <?= smCsrfField($me) ?><input type="hidden" name="do" value="quota"><input type="hidden" name="id" value="<?= (int)$quser ?>">
                            <label class="check"><input type="checkbox" name="allow_all" value="1"<?= $qq['allow'] === null ? ' checked' : '' ?>> <?= h(t('Brave erlaubt (ohne Einschränkung)')) ?></label>
                            <table class="table table--quota">
                                <thead><tr><th><?= h(t('Dienst')) ?></th><th><?= h(t('erlaubt')) ?></th><th><?= h(t('Höchstens')) ?></th><th><?= h(t('verbraucht')) ?></th></tr></thead>
                                <tbody>
<?php foreach (smProvOrder() as $sid): $cy = smProvCycle($sid); ?>
                                <tr><td><?= h(smProviders()[$sid]['badge']) ?></td>
                                    <td><input type="checkbox" name="allow[]" value="<?= h($sid) ?>"<?= $qq['allow'] === null || in_array($sid, $qq['allow'], true) ? ' checked' : '' ?> aria-label="<?= h(t('%s erlaubt', smProviders()[$sid]['name'])) ?>"></td>
                                    <td><input type="number" name="lim[<?= h($sid) ?>]" min="0" value="<?= isset($qq['lim'][$sid]) ? (int)$qq['lim'][$sid] : '' ?>" placeholder="∞" aria-label="<?= h(t('Höchstens %s', smProviders()[$sid]['unit'])) ?>"></td>
                                    <td><?= h(smNumF(smUsageSum($sid, $cy['from'], $quser))) ?></td></tr>
<?php endforeach; ?>
                                </tbody>
                            </table>
                            <small class="muted"><?= h(t('Leer = ohne Grenze. Gezählt wird im Abrechnungszeitraum von Brave. Ohne Haken oben gilt der Haken in der Tabelle.')) ?></small>
                            <button type="submit" class="btn btn--primary btn--small"><?= h(t('Speichern')) ?></button>
                        </form>
                    </details>
