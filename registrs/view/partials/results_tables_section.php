<?php /** @var array $page_data */ ?>
<?php // Atkāpju spiede (audits 2026-08-26): veidnes atkāpes deva līdz 52 atstarpēm
      // pirms katras tabulas šūnas — 33–53 % no visas lapas HTML bija atstarpes
      // (112 KB tabulas ⇒ ~16 KB). Savācam izvadi un noņemam TIKAI rindu sākuma
      // atkāpes; teksts un atstarpes starp vārdiem paliek neskarti.
      ob_start(); ?>
<details class="collapsible-tables-section">
    <summary class="collapsible-tables-summary">
        Izmantotie dati
        <span class="plus-minus-icon"></span>
    </summary>
    <div class="collapsible-tables-content">
<?php if (py_truthy($page_data['processed_tables_for_display'] ?? null)): ?>
<?php foreach ($page_data['processed_tables_for_display'] as $table_info): ?>
<?php if (py_truthy($table_info['rows'] ?? null)): ?>
                    <div class='table-container' data-table-name="<?= h($table_info['mysql_table_name_raw'] ?? '') ?>">
                        <div class='table-title-block'>
                            <h2>
                                <?= h($table_info['title'] ?? '') ?>
<?php if (py_truthy($table_info['link_url'] ?? null)): ?>
                                    <a href="<?= h($table_info['link_url']) ?>" target="_blank" rel="nofollow noopener noreferrer" title="Atvērt datu kopu ārējā saitē" class="data-source-link-icon">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
<?php endif; ?>
                            </h2>
<?php if (py_truthy($table_info['mysql_table_name_subtitle'] ?? null)): ?>
                            <p class='mysql-table-name-subtitle'><?= h($table_info['mysql_table_name_subtitle']) ?></p>
<?php endif; ?>
                        </div>
                        <div class='table-responsive-wrapper'>
                            <table>
                                <thead>
                                    <tr>
<?php foreach ($table_info['headers'] as $header): ?>
                                        <th data-original-name="<?= h($header['original'] ?? '') ?>" data-full-translation="<?= h($header['full'] ?? '') ?>">
                                            <?= h($header['short'] ?? '') ?>
                                        </th>
<?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
<?php foreach ($table_info['rows'] as $row_data): ?>
                                        <tr <?php foreach (($row_data['data_attributes'] ?? []) as $attr_name => $attr_val): ?><?= h($attr_name) ?>="<?= h($attr_val) ?>" <?php endforeach; ?>>
<?php foreach ($row_data['values'] as $cell): ?>
                                                <td>
<?php $cv = $cell['value'] ?? null; if ($cv === null || $cv === ''): ?>
                                                    <i>NULL</i>
<?php elseif (py_truthy($cell['is_link'] ?? false)): ?>
                                                    <a href="<?= h($cell['url'] ?? '') ?>" target="_blank" rel="noopener noreferrer"><?= h($cv) ?></a>
<?php else: ?>
                                                    <?= h($cv) ?>
<?php endif; ?>
                                                </td>
<?php endforeach; ?>
                                        </tr>
<?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
<?php endif; ?>
<?php endforeach; ?>
<?php endif; ?>
    </div>
</details>
<?php echo preg_replace('/\n[ \t]+/', "\n", ob_get_clean()); ?>
