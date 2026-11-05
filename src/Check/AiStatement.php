<?php

declare(strict_types=1);

namespace ContentCredentials\Check;

/**
 * What a report's manifests say about generative AI (SPEC-007): `generated`,
 * `edited` or null, from the shape of VerificationReport::toArray(), never from
 * the verifier's `@internal` store.
 *
 * The answer is a signed statement in the file, and says nothing unless the
 * file verifies: Verdict keeps it only for `Trusted` and `Valid`. An ingredient
 * is followed only when what its signer recorded and what the verifier found
 * both hold at most `signingCredential.untrusted`. Everything is read as
 * untrusted: whatever cannot be read counts as no statement.
 *
 * Parseable on PHP 8.1, as Verdict is (SPEC-002 AC12).
 */
final class AiStatement
{
    public const GENERATED = 'generated';

    public const EDITED = 'edited';

    public const VALUES = [self::GENERATED, self::EDITED];

    private const TRAINED = 'http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia';

    private const COMPOSITE = 'http://cv.iptc.org/newscodes/digitalsourcetype/compositeWithTrainedAlgorithmicMedia';

    private const ACTIONS_LABELS = ['c2pa.actions', 'c2pa.actions.v2'];

    private const PARENT = 'parentOf';

    private const TOLERATED = 'signingCredential.untrusted';

    /**
     * @param  array<mixed>  $report  toArray()'s shape, read as untrusted
     */
    public static function read(array $report): ?string
    {
        // Two walks: a manifest reached as a component first can still be a parent.
        if (in_array(self::TRAINED, self::sourceTypes($report, true), true)) {
            return self::GENERATED;
        }
        $all = self::sourceTypes($report, false);

        return in_array(self::TRAINED, $all, true) || in_array(self::COMPOSITE, $all, true) ? self::EDITED : null;
    }

    /**
     * The source types in every manifest reached from the active one, each read
     * once.
     *
     * @param  array<mixed>  $report
     * @return list<mixed>
     */
    private static function sourceTypes(array $report, bool $parentsOnly): array
    {
        $manifests = $report['manifests'] ?? null;
        $active = self::label($report['active_manifest'] ?? null);
        if (! is_array($manifests) || $active === null) {
            return [];
        }
        $types = [];
        $seen = [$active => true];
        $queue = [$active];
        while ($queue !== []) {
            $label = array_shift($queue);
            $manifest = $manifests[$label] ?? null;
            if (! is_array($manifest)) {
                continue;
            }
            array_push($types, ...self::typesIn($manifest));
            foreach (self::listAt($manifest, 'ingredients') as $ingredient) {
                $target = is_array($ingredient) ? self::label($ingredient['active_manifest'] ?? null) : null;
                $relationship = is_array($ingredient) ? ($ingredient['relationship'] ?? null) : null;
                if (! is_array($ingredient) || $target === null || isset($seen[$target]) || ! is_string($relationship)
                    || ($parentsOnly && $relationship !== self::PARENT)
                    || ! self::followed($report, $label, $ingredient)) {
                    continue;
                }
                $seen[$target] = true;
                $queue[] = $target;
            }
        }

        return $types;
    }

    /**
     * @param  array<mixed>  $manifest
     * @return list<mixed>
     */
    private static function typesIn(array $manifest): array
    {
        $types = [];
        foreach (self::listAt($manifest, 'assertions') as $assertion) {
            if (! is_array($assertion) || ! in_array($assertion['label'] ?? null, self::ACTIONS_LABELS, true)) {
                continue;
            }
            $data = $assertion['data'] ?? null;
            foreach (is_array($data) ? self::listAt($data, 'actions') : [] as $action) {
                if (is_array($action) && array_key_exists('digitalSourceType', $action)) {
                    $types[] = $action['digitalSourceType'];
                }
            }
        }

        return $types;
    }

    /**
     * Both must be present and tolerable: the signer's record, and the
     * verifier's own delta for this ingredient.
     *
     * @param  array<mixed>  $report
     * @param  array<mixed>  $ingredient
     */
    private static function followed(array $report, string $label, array $ingredient): bool
    {
        $name = self::label($ingredient['label'] ?? null);
        $results = $ingredient['validation_results'] ?? null;
        $active = is_array($results) ? ($results['activeManifest'] ?? null) : null;
        if ($name === null || ! is_array($active) || ! self::tolerable($active['failure'] ?? null)) {
            return false;
        }
        $uri = 'self#jumbf=/c2pa/'.$label.'/c2pa.assertions/'.$name;
        $found = false;
        $validation = $report['validation_results'] ?? null;
        foreach (is_array($validation) ? self::listAt($validation, 'ingredientDeltas') : [] as $delta) {
            if (! is_array($delta)) {
                // Unreadable, so it could be this ingredient's.
                return false;
            }
            if (($delta['ingredientAssertionURI'] ?? null) !== $uri) {
                continue;
            }
            $deltas = $delta['validationDeltas'] ?? null;
            if (! is_array($deltas) || ! self::tolerable($deltas['failure'] ?? null)) {
                return false;
            }
            $found = true;
        }

        return $found;
    }

    /** A list of statuses that is empty or holds only the tolerated code. */
    private static function tolerable(mixed $failures): bool
    {
        if (! is_array($failures)) {
            return false;
        }
        foreach ($failures as $failure) {
            if (! is_array($failure) || ($failure['code'] ?? null) !== self::TOLERATED) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    private static function listAt(array $values, string $key): array
    {
        $list = $values[$key] ?? null;

        return is_array($list) ? $list : [];
    }

    /** PHP makes a label of digits only an integer array key: read either as text. */
    private static function label(mixed $value): ?string
    {
        return is_string($value) || is_int($value) ? (string) $value : null;
    }
}
