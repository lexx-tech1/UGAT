<?php
/**
 * Icon Helper - Convert emojis to CSS-based icons
 * Usage: echo icon('check') . ' Success!';
 */

function icon($type, $label = '') {
    $icons = [
        'check'     => '<span class="icon icon-check" data-label="' . htmlspecialchars($label) . '"></span>',
        'x'         => '<span class="icon icon-x" data-label="' . htmlspecialchars($label) . '"></span>',
        'warning'   => '<span class="icon icon-warning" data-label="' . htmlspecialchars($label) . '"></span>',
        'rocket'    => '<span class="icon icon-rocket" data-label="' . htmlspecialchars($label) . '"></span>',
        'target'    => '<span class="icon icon-target" data-label="' . htmlspecialchars($label) . '"></span>',
        'chart'     => '<span class="icon icon-chart" data-label="' . htmlspecialchars($label) . '"></span>',
        'list'      => '<span class="icon icon-checklist" data-label="' . htmlspecialchars($label) . '"></span>',
        'tool'      => '<span class="icon icon-tool" data-label="' . htmlspecialchars($label) . '"></span>',
        'test'      => '<span class="icon icon-test" data-label="' . htmlspecialchars($label) . '"></span>',
        'email'     => '<span class="icon icon-email" data-label="' . htmlspecialchars($label) . '"></span>',
        'celebrate' => '<span class="icon icon-celebrate" data-label="' . htmlspecialchars($label) . '"></span>',
        'phone'     => '<span class="icon icon-phone" data-label="' . htmlspecialchars($label) . '"></span>',
        'folder'    => '<span class="icon icon-folder" data-label="' . htmlspecialchars($label) . '"></span>',
        'doc'       => '<span class="icon icon-doc" data-label="' . htmlspecialchars($label) . '"></span>',
        'new'       => '<span class="icon icon-new" data-label="' . htmlspecialchars($label) . '"></span>',
        'star'      => '<span class="icon icon-star" data-label="' . htmlspecialchars($label) . '"></span>',
        'idea'      => '<span class="icon icon-idea" data-label="' . htmlspecialchars($label) . '"></span>',
        'key'       => '<span class="icon icon-key" data-label="' . htmlspecialchars($label) . '"></span>',
        'lightning' => '<span class="icon icon-lightning" data-label="' . htmlspecialchars($label) . '"></span>',
        'stats'     => '<span class="icon icon-stats" data-label="' . htmlspecialchars($label) . '"></span>',
        'lock'      => '<span class="icon icon-lock" data-label="' . htmlspecialchars($label) . '"></span>',
        'sparkle'   => '<span class="icon icon-sparkle" data-label="' . htmlspecialchars($label) . '"></span>',
        'globe'     => '<span class="icon icon-globe" data-label="' . htmlspecialchars($label) . '"></span>',
        'search'    => '<span class="icon icon-search" data-label="' . htmlspecialchars($label) . '"></span>',
        'gift'      => '<span class="icon icon-gift" data-label="' . htmlspecialchars($label) . '"></span>',
        'hammer'    => '<span class="icon icon-hammer" data-label="' . htmlspecialchars($label) . '"></span>',
        'timer'     => '<span class="icon icon-timer" data-label="' . htmlspecialchars($label) . '"></span>',
    ];
    
    return isset($icons[$type]) ? $icons[$type] : '';
}

/**
 * Convert emoji string to icon span
 * For use in replacing emoji text
 */
function emoji_to_icon($emoji_text) {
    $replacements = [
        '✅' => icon('check'),
        '❌' => icon('x'),
        '⚠️'  => icon('warning'),
        '🎯' => icon('target'),
        '📊' => icon('chart'),
        '📋' => icon('list'),
        '🔧' => icon('tool'),
        '🧪' => icon('test'),
        '📧' => icon('email'),
        '🎉' => icon('celebrate'),
        '📱' => icon('chart'),
        '📂' => icon('folder'),
        '📄' => icon('doc'),
        '🆕' => icon('new'),
        '⭐' => icon('star'),
        '💡' => icon('idea'),
        '🔑' => icon('key'),
        '⚡' => icon('lightning'),
        '📈' => icon('stats'),
        '🔒' => icon('lock'),
        '✨' => icon('sparkle'),
        '🌐' => icon('globe'),
        '🔍' => icon('search'),
        '🎁' => icon('gift'),
        '🔨' => icon('hammer'),
        '⏰' => icon('timer'),
        '🚀' => icon('rocket'),
        '📞' => icon('phone'),
    ];
    
    return str_replace(array_keys($replacements), array_values($replacements), $emoji_text);
}
?>
