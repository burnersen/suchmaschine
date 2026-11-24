<?php defined('PRIVATE_DIR') || exit;
$newTab = cfg('open_in_new_tab') ? ' target="_blank"' : '';
?>
<section class="results" aria-live="polite">
    <?php if ($error): ?>
        <div class="alert alert--error">
            <strong>Suche fehlgeschlagen.</strong> <?= e($error) ?>
            <div class="alert__actions"><a class="btn btn--small" href="<?= e(current_url()) ?>">Nochmal versuchen</a></div>
        </div>
    <?php elseif ($result): ?>

        <?php if ($result['altered'] && !$exact): ?>
            <p class="notice">
                Ergebnisse für <strong><?= e($result['altered']) ?></strong>.
                Stattdessen suchen nach <a href="<?= e(url(['q' => $q, 'exact' => '1'])) ?>">„<?= e($q) ?>“</a>
            </p>
        <?php endif; ?>

        <?php if (!$result['results']): ?>
            <div class="empty">
                <p>Keine Treffer für <strong>„<?= e($q) ?>“</strong>.</p>
                <p class="muted">Andere Begriffe oder weniger Wörter probieren.</p>
            </div>
        <?php else: ?>
            <p class="meta muted">
                Seite <?= (int) $page ?><?= $result['cached'] ? ' · aus dem Cache' : '' ?>
            </p>
            <ol class="result-list">
                <?php foreach ($result['results'] as $r): ?>
                    <li class="result">
                        <a class="result__link" href="<?= e($r['url']) ?>" rel="noopener noreferrer"<?= $newTab ?>>
                            <span class="result__site">
                                <span class="fav" data-letter="<?= e(mb_strtoupper(mb_substr(preg_replace('/^www\./', '', $r['host']), 0, 1))) ?>">
                                    <?php if ($r['favicon']): ?>
                                        <img src="<?= e($r['favicon']) ?>" alt="" width="16" height="16" loading="lazy" referrerpolicy="no-referrer">
                                    <?php endif; ?>
                                </span>
                                <span class="result__host"><?= e($r['host']) ?></span>
                                <?php if ($r['path']): ?><span class="result__path"><?= e($r['path']) ?></span><?php endif; ?>
                            </span>
                            <span class="result__title"><?= highlight($r['title']) ?></span>
                        </a>
                        <?php if ($r['desc'] || $r['age']): ?>
                            <p class="result__desc">
                                <?php if ($r['age']): ?><span class="result__age"><?= e($r['age']) ?></span><?php endif; ?>
                                <?= highlight($r['desc']) ?>
                            </p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>

            <?php
            $maxPages = min(10, (int) cfg('max_pages'));
            $hasNext  = $result['more'] && $page < $maxPages;
            $last     = $hasNext ? min($maxPages, $page + 1) : $page;
            $base     = ['q' => $q, 'exact' => $exact ? '1' : null];
            ?>
            <?php if ($page > 1 || $hasNext): ?>
                <nav class="pager" aria-label="Seiten">
                    <?php if ($page > 1): ?>
                        <a class="pager__btn" href="<?= e(url($base + ['p' => $page - 1 > 1 ? $page - 1 : null])) ?>" rel="prev">← Zurück</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $last; $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="pager__num is-current" aria-current="page"><?= $i ?></span>
                        <?php else: ?>
                            <a class="pager__num" href="<?= e(url($base + ['p' => $i > 1 ? $i : null])) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($hasNext): ?>
                        <a class="pager__btn" href="<?= e(url($base + ['p' => $page + 1])) ?>" rel="next">Weiter →</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</section>
