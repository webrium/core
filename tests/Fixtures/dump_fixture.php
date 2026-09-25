<?php

/**
 * Tiny router for DumpHelperTest's built-in-server tests: calls dump() under
 * a real, non-CLI SAPI ("cli-server") so the HTML-wrapped branch actually
 * runs, exactly as it would behind a real web request.
 */

require __DIR__ . '/../../vendor/autoload.php';

$case = $_GET['case'] ?? 'simple';

switch ($case) {
    case 'xss':
        dump('<script>alert(1)</script>');
        break;
    case 'multi':
        dump('first', ['a' => 1]);
        break;
    default:
        dump('hello');
}
