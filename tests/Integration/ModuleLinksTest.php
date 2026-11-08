<?php

declare(strict_types=1);

/*
 * SPEC-008 AC19: Omeka's modules page links the module's name to the repository
 * and its author to the author link, from config/module.ini.
 */

use ContentCredentials\Tests\Integration\Support\OmekaInstallation;

it('AC19: the modules page links to the repository and the author', function (): void {
    $omeka = OmekaInstallation::php83();
    $omeka->logIn();
    $page = $omeka->get('/admin/module')->body;

    $start = strpos($page, 'Content Credentials (C2PA)');
    expect($start)->not->toBeFalse();
    // Omeka escapes the href with escapeHtmlAttr (https&#x3A;&#x2F;&#x2F;…); decoded, it is the link.
    $module = html_entity_decode(substr($page, (int) strrpos(substr($page, 0, (int) $start), '<div class="module">'), 2000));

    expect($module)->toMatch('#<a [^>]*href="https://github\.com/provemark/omeka-s-content-credentials"[^>]*>Content Credentials \(C2PA\)</a>#')
        ->and($module)->toMatch('#<a [^>]*href="https://github\.com/provemark"[^>]*>Maurice van Loon</a>#');
})->group('SPEC-008');
