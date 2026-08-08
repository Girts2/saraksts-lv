<?php /** @var array $page_data */ ?>
<?php if (py_truthy($page_data['company_description'] ?? null)): ?>
<div class="balance-facts description-panel">
    <h2>Apraksts</h2>
    <p class="description-text"><?= $page_data['company_description'] /* | safe — raw HTML */ ?></p>
</div>
<?php endif; ?>
