<?php

declare(strict_types=1);

namespace ContentCredentials\Omeka;

use ContentCredentials\Display\SettingsPage;
use ContentCredentials\Trust\BuiltTrust;
use ContentCredentials\Trust\BundledLists;
use ContentCredentials\Trust\TrustChoice;
use DateTimeImmutable;
use DateTimeZone;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Settings\Settings;
use UnexpectedValueException;

/**
 * The trust settings in Omeka S (SPEC-004): two Omeka settings, read for
 * every check and edited on the module's configure page (global admins
 * only, CSRF-checked by Omeka, step 15). Nothing is stored on install; an
 * absent setting is its default (DigiCert on, no custom settings). The
 * custom text is never logged.
 */
final class TrustConfig
{
    public const SETTINGS = [SettingsPage::DIGICERT, SettingsPage::CUSTOM];

    public function __construct(
        private readonly Settings $settings,
        private readonly string $moduleDirectory,
    ) {}

    public static function create(ServiceLocatorInterface $services, string $moduleDirectory): self
    {
        $settings = $services->get('Omeka\Settings');
        if (! $settings instanceof Settings) {
            throw new UnexpectedValueException('Omeka S did not provide its settings.');
        }

        return new self($settings, $moduleDirectory);
    }

    public function digicert(): bool
    {
        return $this->settings->get(SettingsPage::DIGICERT, true) !== false;
    }

    public function custom(): string
    {
        $custom = $this->settings->get(SettingsPage::CUSTOM, '');

        return is_string($custom) ? $custom : '';
    }

    /** The settings for a check; the verifier is made loadable first. */
    public function built(): BuiltTrust
    {
        $this->loadVerifier();

        return TrustChoice::build($this->custom(), $this->digicert(), $this->moduleDirectory.'/trust');
    }

    /**
     * The configure page; $refused and $refusedDigicert are what the
     * administrator just submitted and was not saved (AC5, AC12), shown
     * instead of the stored.
     */
    public function form(PhpRenderer $view, ?string $refused, ?bool $refusedDigicert): string
    {
        $text = new ViewText($view);
        $custom = $this->custom();

        return SettingsPage::html(
            static fn (string $source): string => $text->translate($source),
            static fn (string $value): string => $text->escape($value),
            $refusedDigicert ?? $this->digicert(),
            $refused ?? $custom,
            BundledLists::isStale(new DateTimeImmutable('now', new DateTimeZone('UTC'))),
            $this->built()->problem,
        );
    }

    /**
     * The checkbox's value in a submitted form: on only as the page sends it.
     *
     * @param  array<mixed>  $post
     */
    public static function submittedDigicert(array $post): bool
    {
        return ($post[SettingsPage::DIGICERT] ?? null) === '1';
    }

    /**
     * Saves the submitted form, or returns why not (AC5). Nothing is saved
     * when the custom text is refused.
     *
     * @param  array<mixed>  $post
     */
    public function save(array $post): ?string
    {
        $custom = $post[SettingsPage::CUSTOM] ?? '';
        $custom = is_string($custom) ? $custom : '';
        $this->loadVerifier();
        $refusal = TrustChoice::refusal($custom);
        if ($refusal !== null) {
            return $refusal;
        }
        $this->settings->set(SettingsPage::DIGICERT, self::submittedDigicert($post));
        $this->settings->set(SettingsPage::CUSTOM, trim($custom) === '' ? null : $custom);

        return null;
    }

    private function loadVerifier(): void
    {
        VerifierLoader::register($this->moduleDirectory);
    }
}
