<?php
/** Application pages keep the existing Quodara header, theme and sidebar. */
function chrono_page(string $pageTitle): void {
    global $conn, $user_id;
    $strictAppUi = true;
    require __DIR__ . '/../header.php';
    ?><main class="container mx-auto px-4 py-8 chrono-page"><h1 class="text-2xl font-bold mb-6"><?= h($pageTitle) ?></h1><?php
}
function chrono_page_end(): void {
    echo '</main></div></body></html>';
}
