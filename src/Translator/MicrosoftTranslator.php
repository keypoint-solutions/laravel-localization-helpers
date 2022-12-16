<?php

class KPSMicrosoftTranslatorAutoLoader
{
    /**
     * @param string $class the class name
     */
    public static function autoload(string $class): void
    {
        $elements = explode('\\', $class);

        if (@$elements[0] === 'MicrosoftTranslator') {
            /** @noinspection PhpIncludeInspection */
            require __DIR__ . DIRECTORY_SEPARATOR . 'MicrosoftTranslator' . DIRECTORY_SEPARATOR . @$elements[1] . '.php';
        }
    }
}

spl_autoload_register(['Translator\KPSMicrosoftTranslatorAutoLoader', 'autoload'], true, true);
