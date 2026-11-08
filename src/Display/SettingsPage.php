<?php

declare(strict_types=1);

namespace ContentCredentials\Display;

use Closure;
use ContentCredentials\Trust\BundledLists;

/**
 * The module's configure page (SPEC-004 AC5, AC6, AC8, AC9), as markup
 * inside Omeka's own form. Every string goes through $escape; the module's
 * own strings through $translate first. The custom settings are the
 * administrator's text and may be anything: they appear only escaped,
 * inside the textarea.
 */
final class SettingsPage
{
    public const DIGICERT = 'content_credentials_digicert';

    public const CUSTOM = 'content_credentials_custom_settings';

    /**
     * @param  Closure(string): string  $translate
     * @param  Closure(string): string  $escape
     * @param  string|null  $problem  why the saved settings cannot be used, if they cannot
     * @param  bool  $customSaved  whether custom settings are saved, not only given back (AC13)
     */
    public static function html(Closure $translate, Closure $escape, bool $digicert, string $custom, bool $stale, ?string $problem, bool $customSaved): string
    {
        $t = static fn (string $source): string => $escape($translate($source));
        $html = '<div class="content-credentials-settings">';
        $list = 'C2PA trust list of %1$s (commit %2$s), last compared with the published list on %3$s'; // @translate
        $html .= '<p>'.$escape(Format::fill($translate($list), $list, [BundledLists::LIST_DATE, BundledLists::COMMIT, BundledLists::COMPARED])).'</p>';
        if ($stale) {
            $warning = 'The bundled trust lists were last compared with the published lists on %s, more than six months ago. A newer release of this module may have newer lists.'; // @translate
            $html .= '<p class="content-credentials-warning">'.$escape(Format::fill($translate($warning), $warning, [BundledLists::COMPARED])).'</p>';
        }
        if ($problem !== null) {
            $html .= '<p class="content-credentials-warning">'.($customSaved
                ? $t('The saved custom trust settings cannot be used; new uploads and runs are checked without a trust list.')
                : $t('The bundled trust lists cannot be used; new uploads and runs are checked without a trust list.'))
                .' '.$escape(Format::fill($translate('Reason: %s'), 'Reason: %s', [$problem])).'</p>';
        }
        $html .= '<div class="field"><div class="field-meta"><label for="content-credentials-digicert">'.$t('DigiCert timestamps').'</label></div>'
            .'<div class="inputs"><input type="hidden" name="'.self::DIGICERT.'" value="0">'
            .'<input type="checkbox" name="'.self::DIGICERT.'" id="content-credentials-digicert" value="1"'.($digicert ? ' checked' : '').'> '
            .$t("Accept the time DigiCert's timestamp authorities put on a signature (DigiCert Trusted Root G4). Used with the bundled lists only.")
            .'</div></div>';
        $html .= '<div class="field"><div class="field-meta"><label for="content-credentials-custom-settings">'.$t('Custom trust settings').'</label></div>'
            .'<div class="inputs"><textarea name="'.self::CUSTOM.'" id="content-credentials-custom-settings" rows="12">'.$escape($custom).'</textarea>'
            .'<p>'.$t('Trust settings in the JSON format of c2patool --settings. They replace the bundled lists entirely; leave empty to use the bundled lists.').'</p>'
            .'</div></div>';
        $html .= '<p>'.$t('Changes apply to new uploads and to runs started below; until such a run, media checked before keep their verdict.').'</p>';

        return $html.'</div>';
    }
}
