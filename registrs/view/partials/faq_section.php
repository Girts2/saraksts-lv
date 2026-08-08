<?php /** @var array $page_data */ ?>
<?php if (py_truthy($page_data['faq_list'] ?? null)): ?>
<div class="faq-section">
    <h2>Biežāk uzdotie jautājumi</h2>
<?php foreach ($page_data['faq_list'] as $faq): ?>
    <details class="faq-item">
        <summary class="faq-question"><?= h($faq['question'] ?? '') ?></summary>
        <div class="faq-answer">
            <p><?= h($faq['answer'] ?? '') ?></p>
        </div>
    </details>
<?php endforeach; ?>
</div>
<?php endif; ?>
