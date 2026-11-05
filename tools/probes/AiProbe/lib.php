<?php

declare(strict_types=1);

/*
 * M6.1 probe, never shipped: reads what toArray() says about AI, without
 * the module. The walk follows notes of Tracefern's SPEC-003 and SPEC-027.
 */

const AI_PREFIX = 'http://cv.iptc.org/newscodes/digitalsourcetype/';
const AI_TRAINED = AI_PREFIX.'trainedAlgorithmicMedia';
const AI_COMPOSITE = AI_PREFIX.'compositeWithTrainedAlgorithmicMedia';

/** The source types of a manifest's actions: list of [action, source type]. */
function aiActions(array $manifest): array
{
    $out = [];
    foreach (is_array($manifest['assertions'] ?? null) ? $manifest['assertions'] : [] as $assertion) {
        if (! is_array($assertion) || ! in_array($assertion['label'] ?? null, ['c2pa.actions', 'c2pa.actions.v2'], true)) {
            continue;
        }
        foreach (is_array($assertion['data']['actions'] ?? null) ? $assertion['data']['actions'] : [] as $action) {
            if (is_array($action) && array_key_exists('digitalSourceType', $action)) {
                $out[] = [$action['action'] ?? '?', $action['digitalSourceType']];
            }
        }
    }

    return $out;
}

/** Failures of the verifier's delta for one ingredient of a manifest. */
function aiDeltaFailures(array $report, string $label, array $ingredient): ?array
{
    $uri = 'self#jumbf=/c2pa/'.$label.'/c2pa.assertions/'.($ingredient['label'] ?? '');
    foreach ($report['validation_results']['ingredientDeltas'] ?? [] as $delta) {
        if (($delta['ingredientAssertionURI'] ?? null) === $uri) {
            return array_column($delta['validationDeltas']['failure'] ?? [], 'code');
        }
    }

    return null;
}

/**
 * Every manifest reached from the active one: label, depth, relationship
 * path, source types; each edge with its recorded and delta failures and
 * whether SPEC-027's gate lets it through.
 */
function aiWalk(array $report): array
{
    $active = $report['active_manifest'] ?? null;
    if (! is_string($active) && ! is_int($active)) {
        return [];
    }
    $active = (string) $active;
    $seen = [$active => true];
    $queue = [[$active, 0, [], true]];
    $out = [];
    while ($queue !== []) {
        [$label, $depth, $path, $gated] = array_shift($queue);
        $manifest = $report['manifests'][$label] ?? [];
        $edges = [];
        foreach (is_array($manifest['ingredients'] ?? null) ? $manifest['ingredients'] : [] as $ingredient) {
            $relationship = $ingredient['relationship'] ?? '?';
            $target = $ingredient['active_manifest'] ?? null;
            $recorded = $ingredient['validation_results']['activeManifest']['failure'] ?? null;
            $recordedCodes = is_array($recorded) ? array_column($recorded, 'code') : null;
            $delta = aiDeltaFailures($report, $label, $ingredient);
            $ok = static fn (?array $codes): bool => $codes !== null && array_diff($codes, ['signingCredential.untrusted']) === [];
            $gate = $ok($recordedCodes) && $ok($delta);
            $edges[] = ['relationship' => $relationship, 'target' => $target !== null, 'recorded' => $recordedCodes, 'delta' => $delta, 'gate' => $gate];
            if ($target !== null && ! isset($seen[(string) $target])) {
                $seen[(string) $target] = true;
                $queue[] = [(string) $target, $depth + 1, [...$path, $relationship], $gated && $gate];
            }
        }
        $out[] = ['depth' => $depth, 'path' => $path, 'gated' => $gated, 'actions' => aiActions(is_array($manifest) ? $manifest : []), 'edges' => $edges];
    }

    return $out;
}

/** SPEC-003's rule: the exact URI in the active manifest. */
function aiRuleActive(array $walk): bool
{
    foreach ($walk[0]['actions'] ?? [] as [, $type]) {
        if ($type === AI_TRAINED) {
            return true;
        }
    }

    return false;
}

/** SPEC-027's rule: 'generated', 'edited' or null. */
function aiRuleHistory(array $walk): ?string
{
    $generated = false;
    $edited = false;
    foreach ($walk as $node) {
        if (! $node['gated']) {
            continue;
        }
        $parentOnly = array_diff($node['path'], ['parentOf']) === [];
        foreach ($node['actions'] as [, $type]) {
            if ($type === AI_TRAINED) {
                $parentOnly ? $generated = true : $edited = true;
            } elseif ($type === AI_COMPOSITE) {
                $edited = true;
            }
        }
    }

    return $generated ? 'generated' : ($edited ? 'edited' : null);
}

function aiShort(mixed $type): string
{
    return is_string($type) ? (str_starts_with($type, AI_PREFIX) ? substr($type, strlen(AI_PREFIX)) : '!'.$type) : '!'.gettype($type);
}
