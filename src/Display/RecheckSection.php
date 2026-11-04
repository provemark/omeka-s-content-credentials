<?php

declare(strict_types=1);

namespace ContentCredentials\Display;

use Closure;

/**
 * The "Check existing media" section of the configure page (SPEC-005):
 * the latest run and the start buttons, which are left out while a run is
 * going on (AC9). Every string goes through $escape; the job's status and
 * times are Omeka's, nothing comes from a file.
 */
final class RecheckSection
{
    public const FIELD = 'content_credentials_recheck';

    public const ALL = 'all';

    public const WITHOUT_RESULT = 'without_result';

    /** Omeka's job statuses, in the module's words. */
    public const STATUSES = [
        'starting' => 'Starting',
        'in_progress' => 'In progress',
        'stopping' => 'Stopping',
        'completed' => 'Completed',
        'stopped' => 'Stopped',
        'error' => 'Failed',
    ];

    public const RUNNING = ['starting', 'in_progress', 'stopping'];

    /**
     * @param  Closure(string): string  $translate
     * @param  Closure(string): string  $escape
     * @param  array{status: string, started: ?string, ended: ?string}|null  $latest
     */
    public static function html(Closure $translate, Closure $escape, ?array $latest, ?string $jobUrl): string
    {
        $t = static fn (string $source): string => $escape($translate($source));
        $html = '<div class="content-credentials-recheck"><h3>'.$t('Check existing media').'</h3>'
            .'<p>'.$t('Checks media that are already in Omeka S again, in the background, with the saved trust settings. The buttons below start a run; they do not save changes made above.').'</p>';
        $running = $latest !== null && in_array($latest['status'], self::RUNNING, true);
        if ($latest !== null) {
            $html .= '<p>'.$t('Latest run:').' '.$t(self::STATUSES[$latest['status']] ?? 'Unknown');
            if ($latest['started'] !== null) {
                $html .= ', '.$escape(sprintf($translate('started %s'), $latest['started']));
            }
            if ($latest['ended'] !== null) {
                $html .= ', '.$escape(sprintf($translate('ended %s'), $latest['ended']));
            }
            $html .= '.';
            if ($jobUrl !== null) {
                $html .= ' <a href="'.$escape($jobUrl).'">'.$t('Job page').'</a>';
            }
            $html .= '</p>';
            if ($running) {
                $html .= '<p>'.$t('A run is in progress. To stop it, use Stop on its job page.').'</p>';
            }
        }
        if (! $running) {
            $html .= '<p><button type="submit" name="'.self::FIELD.'" value="'.self::ALL.'">'.$t('Check all media').'</button> '
                .'<button type="submit" name="'.self::FIELD.'" value="'.self::WITHOUT_RESULT.'">'.$t('Check media without a result').'</button></p>';
        }

        return $html.'</div><!-- /content-credentials-recheck -->';
    }
}
