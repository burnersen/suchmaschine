<?php defined('SM_VERSION') || exit;
/** Suchfeld. $big = groß auf der Startseite. Vorschläge aus dem eigenen Verlauf (nur im Browser gespeichert). */
$big = !empty($big);
?>
<?php $bangOk = !empty($me) || smSetting('public') === '1'; ?>
<form class="sbox<?= $big ? ' sbox--big' : '' ?>" method="get" action="<?= h(smBase()) ?>" role="search" data-search
      data-history="<?= !empty($prefs['history']) ? '1' : '0' ?>" data-label-remove="<?= h(t('Aus dem Verlauf entfernen')) ?>"
<?php if ($bangOk): ?>      data-bangs="<?= h(smUrl(['a' => 'bangs'])) ?>" data-label-dead="<?= h(t('zuletzt nicht erreichbar')) ?>" data-label-go="<?= h(t('Weiter zu')) ?>" data-label-type="<?= h(t('jetzt Suchbegriff eingeben')) ?>"<?php endif; ?>>
    <div class="sbox__field">
        <svg class="sbox__icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m20 20-4.8-4.8"/></svg>
        <input type="search" name="q" value="<?= h($q ?? '') ?>" placeholder="<?= h($big ? t('Suchen oder Adresse eingeben') : '') ?>"
               aria-label="<?= h(t('Suchbegriff')) ?>" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false"
               maxlength="400" enterkeyhint="search" required<?= $big ? ' autofocus' : '' ?> role="combobox" aria-expanded="false" aria-controls="sbox-list" aria-autocomplete="list">
        <button type="button" class="sbox__clear" data-clear aria-label="<?= h(t('Eingabe löschen')) ?>"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
<?php if ($bangOk): ?>
        <button type="button" class="sbox__bang" data-bang-btn title="<?= h(t('Bangs: direkt zu einer anderen Seite springen')) ?>" aria-label="<?= h(t('Bangs: direkt zu einer anderen Seite springen')) ?>">!</button>
        <a class="sbox__scan" href="<?= h(smToolUrl('scanner', ['go' => 'search'])) ?>" title="<?= h(t('Code scannen und suchen')) ?>" aria-label="<?= h(t('Code scannen und suchen')) ?>"><svg viewBox="0 0 24 24"><path d="M4 8V6a2 2 0 0 1 2-2h2M16 4h2a2 2 0 0 1 2 2v2M20 16v2a2 2 0 0 1-2 2h-2M8 20H6a2 2 0 0 1-2-2v-2"/><path d="M7 12h10"/></svg></a>
<?php endif; ?>
        <?php if (!$big): ?>
        <button type="submit" class="sbox__go" aria-label="<?= h(t('Suchen')) ?>"><svg viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m20 20-4.8-4.8"/></svg><span class="spinner"></span></button>
        <?php endif; ?>
    </div>
    <ul class="sbox__list" id="sbox-list" role="listbox" hidden></ul>
    <p class="sbox__target" data-bang-target aria-live="polite" hidden></p>
    <?php if (!empty($tab) && $tab !== 'web'): ?><input type="hidden" name="t" value="<?= h($tab) ?>"><?php endif; ?>
    <?php if (!empty($lens)): ?><input type="hidden" name="l" value="<?= h($lens) ?>"><?php endif; ?>
    <?php if (!empty($ft)): ?><input type="hidden" name="ft" value="<?= h($ft) ?>"><?php endif; ?>
    <?php if ($big): ?>
    <div class="sbox__buttons">
        <button type="submit" class="btn btn--soft"><?= h(t('Suchen')) ?></button>
        <button type="submit" class="btn btn--soft" name="a" value="lucky"><?= h(t('Auf gut Glück')) ?></button>
    </div>
    <?php endif; ?>
</form>
