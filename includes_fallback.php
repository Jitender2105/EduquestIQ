<?php
declare(strict_types=1);

/**
 * Render a premium static fallback section for empty data states.
 *
 * @param array{
 *   eyebrow?:string,
 *   title:string,
 *   description:string,
 *   points?:string[],
 *   cards?:array<int,array{title:string,meta?:string,text?:string,link?:string,link_label?:string}>,
 *   primary_label?:string,
 *   primary_link?:string,
 *   secondary_label?:string,
 *   secondary_link?:string
 * } $config
 */
function render_static_fallback(array $config): void
{
    $eyebrow = (string)($config['eyebrow'] ?? 'Coming Soon');
    $title = (string)($config['title'] ?? 'No Data Yet');
    $description = (string)($config['description'] ?? 'Content will appear here once data is added.');
    $points = is_array($config['points'] ?? null) ? $config['points'] : [];
    $cards = is_array($config['cards'] ?? null) ? $config['cards'] : [];
    $primaryLabel = (string)($config['primary_label'] ?? 'Go to Dashboard');
    $primaryLink = (string)($config['primary_link'] ?? url_for('dashboard.php'));
    $secondaryLabel = (string)($config['secondary_label'] ?? 'Open Backend');
    $secondaryLink = (string)($config['secondary_link'] ?? url_for('backend.php'));
    ?>
    <section class="eq-fallback">
        <div class="eq-fallback-hero">
            <span class="eq-fallback-eyebrow"><?php echo htmlspecialchars($eyebrow); ?></span>
            <h3><?php echo htmlspecialchars($title); ?></h3>
            <p><?php echo htmlspecialchars($description); ?></p>

            <?php if ($points): ?>
                <div class="eq-fallback-points">
                    <?php foreach ($points as $point): ?>
                        <div><?php echo htmlspecialchars((string)$point); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="eq-fallback-actions">
                <a class="btn btn-light btn-sm" href="<?php echo htmlspecialchars($primaryLink); ?>">
                    <?php echo htmlspecialchars($primaryLabel); ?>
                </a>
                <a class="btn btn-outline-light btn-sm" href="<?php echo htmlspecialchars($secondaryLink); ?>">
                    <?php echo htmlspecialchars($secondaryLabel); ?>
                </a>
            </div>
        </div>

        <?php if ($cards): ?>
            <div class="eq-fallback-grid">
                <?php foreach ($cards as $card): ?>
                    <article class="eq-fallback-card">
                        <h6><?php echo htmlspecialchars((string)($card['title'] ?? 'Untitled')); ?></h6>
                        <?php if (!empty($card['meta'])): ?>
                            <div class="eq-fallback-meta"><?php echo htmlspecialchars((string)$card['meta']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($card['text'])): ?>
                            <p><?php echo htmlspecialchars((string)$card['text']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($card['link'])): ?>
                            <a href="<?php echo htmlspecialchars((string)$card['link']); ?>">
                                <?php echo htmlspecialchars((string)($card['link_label'] ?? 'Learn more')); ?>
                            </a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php
}

