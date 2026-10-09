<?php

if (!function_exists('nav_icon')) {
function icon_paths(): array
{
    return [
        'home' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.5 1.5 0 012.122 0L22.28 12M4.5 9.75V19.5a.75.75 0 00.75.75H9a.75.75 0 00.75-.75v-4.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75v4.5a.75.75 0 00.75.75h3.75a.75.75 0 00.75-.75V9.75" />',
        'box' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9.75v9.75" />',
        'search' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />',
        'truck' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 10-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 10-3 0m3 0h1.125c.621 0 1.125-.504 1.125-1.125V14.25m-17.25 0h17.25m-17.25 0V9m17.25 5.25V9M21 9V6.75A2.25 2.25 0 0018.75 4.5h-1.5a.75.75 0 00-.75.75v3.75m4.5 0h-4.5m0 0h-13.5" />',
        'receipt' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25h6m-6 3h6m-9 5.25l1.5-1.5h13.5a2.25 2.25 0 002.25-2.25V5.25a2.25 2.25 0 00-2.25-2.25H6a2.25 2.25 0 00-2.25 2.25v15l1.5-1.5z" />',
        'chart' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.5h3.75v6.75H3v-6.75zm6.75-6.75h3.75v13.5H9.75V6.75zm6.75-3h3.75v16.5H16.5v-16.5z" />',
        'logout' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />',
        'users' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />',
        'shield' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />',
        'eye' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />',
        'pencil' => '<path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 19.5H4.5" />',
        'trash' => '<path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />',
        'plus' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />',
        'clock' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />',
        'check' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />',
        'x' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />',
        'filter' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />',
        'globe' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 100-18 9 9 0 000 18z" /><path stroke-linecap="round" stroke-linejoin="round" d="M3.6 9h16.8M3.6 15h16.8M12 3a14.98 14.98 0 013.5 9A14.98 14.98 0 0112 21a14.98 14.98 0 01-3.5-9A14.98 14.98 0 0112 3z" />',
        'printer' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.32 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.06 2.06 0 00-1.58-.86 109.13 109.13 0 00-7.284 0 2.06 2.06 0 00-1.58.86A17.902 17.902 0 003.755 16.876c-.04.62.468 1.124 1.089 1.124H6.34m11.32 0H6.34m8.42-13.5H9.24a2.25 2.25 0 00-2.25 2.25V6h9V4.5a2.25 2.25 0 00-2.25-2.25z" />',
        'arrow-left' => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />',
        'mail' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />',
        'cog' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />',
        'bell' => '<path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />',
        'building' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />',
        'lock' =>'<path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />',
        'eye-slash' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />',
    ];
}

function nav_icon(string $name): string
{
    return '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-5 h-5">'
        . (icon_paths()[$name] ?? '') . '</svg>';
}

function icon_small(string $name, string $class = 'w-4 h-4'): string
{
    return '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="' . htmlspecialchars($class) . '">'
        . (icon_paths()[$name] ?? '') . '</svg>';
}

/**
 * Item del menu lateral. Queda activo si la ruta actual es $href o una subruta
 * (p.ej. /usuarios/nuevo), salvo con $exacto (para /configuracion, que tiene
 * sus propias subrutas en el menu).
 */
function nav_item(string $href, string $icon, string $label, string $currentPath, bool $disabled = false, string $iconColor = 'text-white/60', bool $exacto = false): string
{
    if ($disabled) {
        return '<span class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-white/30 cursor-not-allowed" title="Proximamente">'
            . '<span class="text-white/30">' . nav_icon($icon) . '</span><span>' . htmlspecialchars($label) . '</span></span>';
    }

    $active = $currentPath === $href || (!$exacto && str_starts_with($currentPath, rtrim($href, '/') . '/'));
    $classes = $active
        ? 'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-semibold bg-blue-600 text-white shadow-lg shadow-blue-900/40'
        : 'flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-white/75 hover:bg-white/10 hover:text-white transition';
    $iconColor = $active ? 'text-white' : $iconColor;

    return '<a href="' . htmlspecialchars($href) . '" class="' . $classes . '">'
        . '<span class="' . $iconColor . '">' . nav_icon($icon) . '</span><span>' . htmlspecialchars($label) . '</span></a>';
}

function badge(string $texto, string $color = 'slate'): string
{
    return '<span class="px-2 py-1 rounded-full text-xs font-medium bg-' . $color . '-100 text-' . $color . '-700">'
        . htmlspecialchars($texto) . '</span>';
}

function icon_button(string $href, string $icon, string $title, string $color = 'blue'): string
{
    return '<a href="' . htmlspecialchars($href) . '" title="' . htmlspecialchars($title) . '" '
        . 'class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-' . $color . '-500 text-white hover:bg-' . $color . '-600 transition">'
        . icon_small($icon) . '</a>';
}

function icon_submit_button(string $icon, string $title, string $color = 'red'): string
{
    return '<button type="submit" title="' . htmlspecialchars($title) . '" '
        . 'class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-' . $color . '-500 text-white hover:bg-' . $color . '-600 transition">'
        . icon_small($icon) . '</button>';
}

function card_header(string $icon, string $titulo, string $color = 'blue'): string
{
    return '<div class="flex items-center gap-2 mb-4">'
        . '<span class="w-9 h-9 rounded-full bg-' . $color . '-500 text-white flex items-center justify-center shrink-0">' . icon_small($icon, 'w-5 h-5') . '</span>'
        . '<h2 class="font-semibold text-black">' . htmlspecialchars($titulo) . '</h2>'
        . '</div>';
}

function header_button(string $href, string $icon, string $label, string $color = 'blue'): string
{
    $textColor = $color === 'amber' ? 'black' : 'white';
    return '<a href="' . htmlspecialchars($href) . '" class="inline-flex items-center gap-2 bg-' . $color . '-500 text-' . $textColor . ' rounded-lg px-4 py-2 text-sm font-medium hover:bg-' . $color . '-400 transition">'
        . icon_small($icon) . '<span>' . htmlspecialchars($label) . '</span></a>';
}
}
