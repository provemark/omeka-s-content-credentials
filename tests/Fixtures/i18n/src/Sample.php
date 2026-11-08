<?php

// SPEC-009 AC4: the template script's input. Not loaded by anything.
final class Sample
{
    public const LABELS = [
        'key-not-taken' => 'A value on a marked line', // @translate
        'Not marked, not taken',
    ];

    public function lines(object $text, object $messenger, \Closure $t, \Closure $translate): void
    {
        $text->translate('A literal call');
        $t('Through a closure');
        $translate('Through the variable');
        $messenger->addSuccess('A message');
        $format = 'A marked format %s'; // @translate
        $pair = ['Also on the marked line', 'A value on a marked line', $this->row['index-not-taken']]; // @translate
        $text->translate($format);
        $text->other('Not a translate call');
        $t('%s');
    }
}
