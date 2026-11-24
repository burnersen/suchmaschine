<?php defined('PRIVATE_DIR') || exit; ?>
<form class="searchbox<?= !empty($big) ? ' searchbox--big' : '' ?>" method="get" action="<?= e(base_path()) ?>" role="search" data-search-form>
    <svg class="searchbox__icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
    <input type="search" name="q" value="<?= e($q ?? '') ?>" placeholder="Suchen …" aria-label="Suchbegriff"
           autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="400" required <?= !empty($big) ? 'autofocus' : '' ?>>
    <button type="button" class="searchbox__clear" data-clear aria-label="Eingabe löschen" title="Löschen">×</button>
    <button type="submit" class="searchbox__go" aria-label="Suchen">
        <span class="spinner" aria-hidden="true"></span>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
    </button>
</form>
