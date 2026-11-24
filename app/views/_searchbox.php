<?php defined('SM_VERSION') || exit;
/** Suchfeld. $big = groß auf der Startseite. Vorschläge aus dem eigenen Verlauf (nur im Browser gespeichert). */
$big = !empty($big);
?>
<form class="sbox<?= $big ? ' sbox--big' : '' ?>" method="get" action="<?= h(smBase()) ?>" role="search" data-search
      data-history="<?= !empty($prefs['history']) ? '1' : '0' ?>" data-label-remove="<?= h(t('Aus dem Verlauf entfernen')) ?>">
    <div class="sbox__field">
        <svg class="sbox__icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m20 20-4.8-4.8"/></svg>
        <input type="search" name="q" value="<?= h($q ?? '') ?>" placeholder="<?= h($big ? t('Suchen oder Adresse eingeben') : '') ?>"
               aria-label="<?= h(t('Suchbegriff')) ?>" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false"
               maxlength="400" enterkeyhint="search" required<?= $big ? ' autofocus' : '' ?> role="combobox" aria-expanded="false" aria-controls="sbox-list" aria-autocomplete="list">
        <button type="button" class="sbox__clear" data-clear aria-label="<?= h(t('Eingabe löschen')) ?>"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
        <?php if (!$big): ?>
        <button type="submit" class="sbox__go" aria-label="<?= h(t('Suchen')) ?>"><svg viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m20 20-4.8-4.8"/></svg><span class="spinner"></span></button>
        <?php endif; ?>
    </div>
    <ul class="sbox__list" id="sbox-list" role="listbox" hidden></ul>
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
