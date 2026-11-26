<?php defined('SM_VERSION') || exit;
/** Umschalter für Administratoren: eigene Einstellung oder Standard für alle ($tab = look oder bangs, $for = '' oder 'all') */
$scopeUrl = function (string $f) use ($tab) { return smUrl(['a' => 'settings', 'tab' => $tab, 'for' => $f !== '' ? $f : null]); };
?>
    <div class="scope" role="group" aria-label="<?= h(t('Was du bearbeitest')) ?>">
        <nav class="seg seg--links">
            <a href="<?= h($scopeUrl('')) ?>"<?= $for === '' ? ' class="is-on" aria-current="page"' : '' ?>><?= h($tab === 'look' ? t('Mein Aussehen') : t('Meine Bangs')) ?></a>
            <a href="<?= h($scopeUrl('all')) ?>"<?= $for === 'all' ? ' class="is-on" aria-current="page"' : '' ?>><?= h(t('Standard für alle')) ?></a>
        </nav>
        <small class="muted"><?= h($for === 'all'
            ? ($tab === 'look' ? t('Du bearbeitest das Aussehen für alle Konten und für Besucher ohne Anmeldung.') : t('Du bearbeitest die Bangs für alle Konten. Jedes Konto kann darüber hinaus für sich selbst ändern.'))
            : ($tab === 'look' ? t('Du bearbeitest nur dein eigenes Aussehen.') : t('Du bearbeitest nur deine eigenen Bangs.'))) ?></small>
    </div>
