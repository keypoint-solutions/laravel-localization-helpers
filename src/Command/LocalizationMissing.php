<?php

namespace Keypoint\LaravelLocalizationHelpers\Command;

use Illuminate\Config\Repository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Keypoint\LaravelLocalizationHelpers\Factory\Exception;
use Keypoint\LaravelLocalizationHelpers\Factory\LangFile;
use Keypoint\LaravelLocalizationHelpers\Factory\Localization;
use Keypoint\LaravelLocalizationHelpers\Factory\Tools;
use Keypoint\LaravelLocalizationHelpers\Object\LangFileAbstract;
use Keypoint\LaravelLocalizationHelpers\Object\LangFileGenuine;
use Keypoint\LaravelLocalizationHelpers\Object\LangFileJson;
use Symfony\Component\Console\Input\InputOption;

class LocalizationMissing extends LocalizationAbstract
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'localization:missing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Parse all translations in app directory and build all lang files';

    /**
     * functions and method to catch translations
     *
     * @var  array
     */
    protected $trans_methods = [];

    /**
     * functions and method to catch translations
     *
     * @var  array
     */
    protected $editor = '';

    /**
     * Folders to seek for missing translations
     *
     * @var  array
     */
    protected $folders = [];

    /**
     * Never make lemmas containing these keys obsolete
     *
     * @var  array
     */
    protected $never_obsolete_keys = [];

    /**
     * Never manage these lang files
     *
     * @var  array
     */
    protected $ignore_lang_files = [];

    /**
     * The lang folder path where are stored lang files in locale sub-directory
     *
     * @var  array
     */
    protected $lang_folder_path = [];

    /**
     * The code style list of rules to apply
     *
     * @var  array
     */
    protected $code_style_rules = [];

    /**
     * The obsolete lemma array key in which to store obsolete lemma
     *
     * @var  string
     *
     * @since 2.x.2
     */
    protected $obsolete_array_key = 'LLH:obsolete';

    protected $todo_prefix = 'TODO: ';

    /**
     * The dot notation split regex
     *
     * @var  string
     *
     * @since 2.x.5
     */
    protected $dot_notation_split_regex = null;

    /**
     * The JSON languages to handle
     *
     * @var  string
     *
     * @since 2.x.6
     */
    protected $json_languages = null;

    protected $encode_json_unicode_characters = true;

    /**
     * Create a new command instance.
     *
     * @param  \Illuminate\Config\Repository  $configRepository
     */
    public function __construct(Repository $configRepository)
    {
        parent::__construct($configRepository);

        $this->trans_methods = config(Localization::PREFIX_LARAVEL_CONFIG.'trans_methods');
        $this->folders = config(Localization::PREFIX_LARAVEL_CONFIG.'folders');
        $this->ignore_lang_files = config(Localization::PREFIX_LARAVEL_CONFIG.'ignore_lang_files');
        $this->lang_folder_path = config(Localization::PREFIX_LARAVEL_CONFIG.'lang_folder_path');
        $this->never_obsolete_keys = config(Localization::PREFIX_LARAVEL_CONFIG.'never_obsolete_keys');
        $this->editor = config(Localization::PREFIX_LARAVEL_CONFIG.'editor_command_line');
        $this->code_style_rules = config(Localization::PREFIX_LARAVEL_CONFIG.'code_style.rules');
        $this->dot_notation_split_regex = config(Localization::PREFIX_LARAVEL_CONFIG.'dot_notation_split_regex');
        $this->json_languages = config(Localization::PREFIX_LARAVEL_CONFIG.'json_languages');

        if (!is_string($this->dot_notation_split_regex)) {
            // fallback to dot if provided regex is not a string
            $this->dot_notation_split_regex = '/\\./';
        }

        // @since 2.x.2
        // Users who have not upgraded their configuration file must have a default
        // but users may want to set it to null to keep the old buggy behaviour
        $this->obsolete_array_key = config(Localization::PREFIX_LARAVEL_CONFIG.'obsolete_array_key',
            $this->obsolete_array_key);

        $this->todo_prefix = config(Localization::PREFIX_LARAVEL_CONFIG.'todo_prefix', $this->todo_prefix);

        $this->encode_json_unicode_characters = config(Localization::PREFIX_LARAVEL_CONFIG.'encode_json_unicode_characters', $this->encode_json_unicode_characters);
    }

    /**
     * Execute the console command.
     *
     * @return int
     * @throws \Exception
     */
    public function handle(): int
    {
        $folders = $this->manager->getPath($this->folders);
        $this->display = !$this->option('silent');
        $extension = $this->option('php-file-extension');
        $obsolete_prefix = (empty($this->obsolete_array_key)) ? '' : $this->obsolete_array_key.'.';

        //////////////////////////////////////////////////
        // Display where translations are searched in //
        //////////////////////////////////////////////////
        if ($this->option('verbose')) {
            $this->writeLine("Lemmas will be searched in the following directories:");

            foreach ($folders as $path) {
                $this->writeLine('    <info>'.$path.'</info>');
            }

            $this->writeLine('');
        }

        ////////////////////////////////
        // Parse all lemmas from code //
        ////////////////////////////////
        $lemmas = $this->manager->extractTranslationsFromFolders($folders, $this->trans_methods, $extension);

        if (count($lemmas) === 0) {
            $this->writeComment("No lemma has been found in code.");
            $this->writeLine("I have searched recursively in PHP files in these directories:");

            foreach ($this->manager->getPath($this->folders) as $path) {
                $this->writeLine("    ".$path);
            }

            $this->writeLine("for these functions/methods:");

            foreach ($this->trans_methods as $k => $v) {
                $this->writeLine("    ".$k);
            }

            return self::SUCCESS;
        }

        $this->writeLine((count($lemmas) > 1) ? count($lemmas)." lemmas have been found in code" : "1 lemma has been found in code");

        if ($this->option('verbose')) {
            foreach ($lemmas as $key => $value) {
                if (strpos($key, '.') !== false) {
                    $this->writeLine('    <info>'.$key.'</info> in file <comment>'.$this->manager->getShortPath($value).'</comment>');
                }
            }
        }

        /////////////////////////////////////////////
        // Convert dot lemmas to structured lemmas //
        /////////////////////////////////////////////
        if ($this->option('output-flat')) {
            $lemmas_structured = $this->manager->convertLemmaToStructuredArray($lemmas, $this->dot_notation_split_regex, 2);
        } else {
            $lemmas_structured = $this->manager->convertLemmaToStructuredArray($lemmas, $this->dot_notation_split_regex,
                null);
        }

        $this->writeLine('');

        /////////////////////////////////////
        // Generate lang files :           //
        // - add missing lemmas on top     //
        // - keep already defined lemmas   //
        // - add obsolete lemmas on bottom //
        /////////////////////////////////////
        try {
            $dir_lang = $this->manager->getLangPath($this->lang_folder_path);
        } catch (Exception $e) {
            switch ($e->getCode()) {
                //@codeCoverageIgnoreStart
                case Localization::NO_LANG_FOLDER_FOUND_IN_THESE_PATHS:
                    $this->writeError("No lang folder found in these paths:");
                    foreach ($e->getParameter() as $path) {
                        $this->writeError("- ".$path);
                    }
                    break;
                //@codeCoverageIgnoreEnd

                case Localization::NO_LANG_FOLDER_FOUND_IN_YOUR_CUSTOM_PATH:
                    $this->writeError('No lang folder found in your custom path: "'.$e->getParameter().'"');
                    break;
            }

            $this->writeLine('');

            return self::ERROR;
        }

        $job = [];
        $there_are_new = false;

        $this->writeLine('Scan files:');

        /**
         * Parse all lang file types
         *
         * @var LangFileAbstract $langFileType
         */
        foreach (LangFile::getLangFiles($dir_lang, $this->json_languages) as $langFileType) {
            if ($langFileType->getTypeVendor()) {
                continue;
            }

            $lang = $langFileType->getLang();

            /**
             * Parse now all extracted families
             */
            foreach ($lemmas_structured as $family => $array) {
                $needsJSONOutput = $family === Localization::JSON_HEADER;

                if ($needsJSONOutput) {
                    $lang_file = new LangFileJson($dir_lang, $lang);
                } else {
                    $lang_file = new LangFileGenuine($dir_lang, $lang, $family);

                    if (in_array($family, $this->ignore_lang_files) || in_array($lang_file->getShortFilePath(),
                            $this->ignore_lang_files)) {
                        if ($this->option('verbose')) {
                            $this->writeLine('');
                            $this->writeInfo("    ! Skip lang file '$family' !");
                        }
                        continue;
                    }
                }

                if ($this->option('verbose')) {
                    $this->writeLine('');
                }

                $this->writeLine('    '.$lang_file->getShortFilePath());

                if (!$this->option('dry-run')) {
                    if (!$lang_file->ensureFolder()) {
                        // @codeCoverageIgnoreStart
                        $this->writeError("    > Unable to create directory ".$lang_file->getFileFolderPath());

                        return self::ERROR;
                        // @codeCoverageIgnoreEnd
                    }

                    if (!$lang_file->isFolderWritable()) {
                        // @codeCoverageIgnoreStart
                        $this->writeError("    > Unable to write file in directory ".$lang_file->getFileFolderPath());

                        return self::ERROR;
                        // @codeCoverageIgnoreEnd
                    }

                    if (!$lang_file->fileExists()) {
                        // @codeCoverageIgnoreStart
                        $this->writeInfo("    > File has been created");
                        // @codeCoverageIgnoreEnd
                    }

                    if (!$lang_file->touch()) {
                        // @codeCoverageIgnoreStart
                        $this->writeError("    > Unable to touch file ".$lang_file->getFilePath());

                        return self::ERROR;
                        // @codeCoverageIgnoreEnd
                    }

                    if (!$lang_file->isReadable()) {
                        // @codeCoverageIgnoreStart
                        $this->writeError("    > Unable to read file ".$lang_file->getFilePath());

                        return self::ERROR;
                        // @codeCoverageIgnoreEnd
                    }

                    if (!$lang_file->isWritable()) {
                        // @codeCoverageIgnoreStart
                        $this->writeError("    > Unable to write in file ".$lang_file->getFilePath());

                        return self::ERROR;
                        // @codeCoverageIgnoreEnd
                    }
                }

                /** @noinspection PhpIncludeInspection */
                $a = $lang_file->load();
                $old_lemmas_with_obsolete = (is_array($a)) ? Arr::dot($a) : [];
                $new_lemmas = Arr::dot($array);
                $final_lemmas = [];
                $display_already_comment = false;
                $something_to_do = false;
                $i = 0;

                // Remove the obsolete prefix key
                $old_lemmas = [];
                $obsolete_prefix_length = strlen($obsolete_prefix);
                foreach ($old_lemmas_with_obsolete as $key => $value) {
                    if (str_starts_with($key, $obsolete_prefix)) {
                        $key = substr($key, $obsolete_prefix_length);
                        if (!isset($old_lemmas[$key])) {
                            $old_lemmas[$key] = $value;
                        }
                    } else {
                        $old_lemmas[$key] = $value;
                    }
                }

                // Check if keys from new lemmas are not sub-keys from old_lemma (#47)
                // Ignore them if this is the case
                $new_lemmas_clean = $new_lemmas;
                $old_lemmas_clean = $old_lemmas;

                foreach ($new_lemmas as $new_key => $new_value) {
                    foreach ($old_lemmas as $old_key => $old_value) {
                        if (str_starts_with($old_key, $new_key.'.')) {
                            if ($this->option('verbose')) {
                                $this->writeLine("            <info>".$new_key."</info> seems to be used to access an array and is already defined in lang file as ".$old_key);
                                $this->writeLine("            <info>".$new_key."</info> not handled!");
                            }

                            unset($new_lemmas_clean[$new_key]);
                            unset($old_lemmas_clean[$old_key]);
                        }
                    }
                }

                $obsolete_lemmas = array_diff_key($old_lemmas_clean, $new_lemmas_clean);
                $welcome_lemmas = array_diff_key($new_lemmas_clean, $old_lemmas_clean);
                $already_lemmas = array_intersect_key($old_lemmas_clean, $new_lemmas_clean);

                // disable check for obsolete lemma and consolidate with already_lemmas
                if ($this->option('disable-obsolete-check')) {
                    $already_lemmas = array_unique($obsolete_lemmas + $already_lemmas);
                    $obsolete_lemmas = [];
                }

                ksort($obsolete_lemmas);
                ksort($welcome_lemmas);
                ksort($already_lemmas);

                //////////////////////////
                // Deal with new lemmas //
                //////////////////////////
                if (count($welcome_lemmas) > 0) {
                    $display_already_comment = true;
                    $something_to_do = true;
                    $there_are_new = true;
                    $final_lemmas["LLH___NEW___LLH"] = "LLH___NEW___LLH";

                    $this->writeInfo('        '.($c = count($welcome_lemmas)).' new string'.Tools::getPlural($c).' to translate');

                    foreach ($welcome_lemmas as $key => $value) {
                        if ($this->option('verbose')) {
                            $this->writeLine("            <info>".$key."</info> in ".$this->manager->getShortPath($value));
                        }
                        if (!$this->option('no-comment')) {
                            $final_lemmas['LLH___COMMENT___LLH'.$i] = "Defined in file $value";
                            $i = $i + 1;
                        }

                        $key_last_token = preg_split($this->dot_notation_split_regex, $key);

                        if ($this->option('translation')) {
                            $translation = $this->manager->translate(end($key_last_token), $lang);
                        } else {
                            $translation = end($key_last_token);
                        }

                        if (strtolower($this->option('new-value')) === 'null') {
                            $translation = null;
                        } else {
                            if ($lang == App::getFallbackLocale()) {
                                $translation = str_replace($this->todo_prefix.'%LEMMA', $translation, $this->option('new-value'));
                            } else {
                                $translation = str_replace('%LEMMA', $translation, $this->option('new-value'));
                            }
                        }

                        Tools::arraySet($final_lemmas, $key, $translation, $this->dot_notation_split_regex);
                    }
                }

                ///////////////////////////////
                // Deal with existing lemmas //
                ///////////////////////////////
                if (count($already_lemmas) > 0) {
                    if ($this->option('verbose')) {
                        $this->writeLine('        '.($c = count($already_lemmas)).' already translated string'.Tools::getPlural($c));
                    }

                    $final_lemmas["LLH___OLD___LLH"] = "LLH___OLD___LLH";

                    foreach ($already_lemmas as $key => $value) {
                        Tools::arraySet($final_lemmas, $key, $value, $this->dot_notation_split_regex);
                    }
                }

                ///////////////////////////////
                // Deal with obsolete lemmas //
                ///////////////////////////////
                if (count($obsolete_lemmas) > 0) {
                    $protected_already_included = false;

                    // Remove all dynamic fields
                    foreach ($obsolete_lemmas as $key => $value) {
                        foreach ($this->never_obsolete_keys as $remove) {
                            if ((strpos($key, '.'.$remove.'.') !== false) || str_starts_with($key, $remove.'.')) {
                                if ($this->option('verbose')) {
                                    $this->writeLine("        <comment>".$key."</comment> is protected as a dynamic lemma");
                                }

                                unset($obsolete_lemmas[$key]);

                                if ($protected_already_included === false) {
                                    $final_lemmas["LLH___PROTECTED___LLH"] = "LLH___PROTECTED___LLH";
                                    $protected_already_included = true;
                                }

                                // Given that this lemma is never obsolete, we need to send it back to the final lemma array
                                Tools::arraySet($final_lemmas, $key, $value, $this->dot_notation_split_regex);
                            }
                        }
                    }
                }

                /////////////////////////////////////
                // Fill the final lemmas array now //
                /////////////////////////////////////
                if (count($obsolete_lemmas) > 0) {
                    $display_already_comment = true;
                    $something_to_do = true;

                    if ($this->option('no-obsolete')) {
                        $this->writeComment("        ".($c = count($obsolete_lemmas)).' obsolete string'.Tools::getPlural($c).' (will be deleted)');
                    } else {
                        $this->writeComment("        ".($c = count($obsolete_lemmas)).' obsolete string'.Tools::getPlural($c).' (can be deleted manually in the generated file)');

                        $final_lemmas["LLH___OBSOLETE___LLH"] = "LLH___OBSOLETE___LLH";

                        foreach ($obsolete_lemmas as $key => $value) {
                            if ($this->option('verbose')) {
                                $this->writeLine("            <comment>".$key."</comment>");
                            }

                            Tools::arraySet($final_lemmas, $obsolete_prefix.$key, $value,
                                $this->dot_notation_split_regex);
                        }
                    }
                }

                // Flat style
                if ($this->option('output-flat')) {
                    $final_lemmas = Arr::dot($final_lemmas);
                }

                if (($something_to_do === true) || ($this->option('force'))) {
                    if ($needsJSONOutput) {
                        $final_lemmas = Arr::except($final_lemmas,
                            array_filter(array_keys($final_lemmas), fn($l) => str_starts_with($l, 'LLH___')));
                    }

                    $content = $needsJSONOutput ? json_encode($final_lemmas,
                        JSON_PRETTY_PRINT|($this->encode_json_unicode_characters ? 0 : JSON_UNESCAPED_UNICODE)) : var_export($final_lemmas, true);

                    if (!$needsJSONOutput) {
                        $content = preg_replace("@'LLH___COMMENT___LLH[0-9]*' => '(.*)',@", '// $1', $content);
                        $content = str_replace([
                            "'LLH___NEW___LLH' => 'LLH___NEW___LLH',",
                            "'LLH___OLD___LLH' => 'LLH___OLD___LLH',",
                            "'LLH___PROTECTED___LLH' => 'LLH___PROTECTED___LLH',",
                            "'LLH___OBSOLETE___LLH' => 'LLH___OBSOLETE___LLH',",
                        ], [
                            '//============================== New strings to translate ==============================//',
                            ($display_already_comment === true) ? '//==================================== Translations ====================================//' : '',
                            '//============================== Dynamic protected strings =============================//',
                            '//================================== Obsolete strings ==================================//',
                        ], $content);
                    }

                    if ($needsJSONOutput) {
                        $file_content = $content;
                    } else {
                        $file_content = "<?php\n";

                        if (!$this->option('no-date')) {
                            $a = " Generated via \"php artisan ".$this->argument('command')."\" at ".date("Y/m/d H:i:s")." ";
                            $file_content .= "/".str_repeat('*', strlen($a))."\n".$a."\n".str_repeat('*',
                                    strlen($a))."/\n";
                        }

                        $file_content .= "\nreturn ".$content.";";
                    }
                    $job[$lang_file->getFilePath()] = $file_content;
                } else {
                    if ($this->option('verbose')) {
                        $this->writeLine("        > <comment>Nothing to do for this file</comment>");
                    }
                }
            }
        }

        ///////////////////////////////////////////
        // Silent mode                           //
        // only return an exit code on new lemma //
        ///////////////////////////////////////////
        if ($this->option('silent')) {
            if ($there_are_new === true) {
                return self::ERROR;
            } else {
                // @codeCoverageIgnoreStart
                return self::SUCCESS;
                // @codeCoverageIgnoreEnd
            }
        }

        ///////////////////////////////////////////
        // Normal mode                           //
        ///////////////////////////////////////////
        if (count($job) > 0) {
            if ($this->option('no-interaction')) {
                $do = true;
            } // @codeCoverageIgnoreStart
            else {
                $this->writeLine('');
                $do = ($this->ask('Do you wish to apply these changes now? [yes|no]') === 'yes');
                $this->writeLine('');
            }
            // @codeCoverageIgnoreEnd

            if ($do === true) {
                if (!$this->option('no-backup')) {
                    $this->writeLine('Backup files:');

                    $now = $this->manager->getBackupDate();

                    foreach ($job as $file_lang_path => $file_content) {
                        $backup_path = $this->manager->getBackupPath($file_lang_path, $now, $extension);

                        if (!$this->option('dry-run')) {
                            rename($file_lang_path, $backup_path);
                        }

                        $this->writeLine("    <info>".$this->manager->getShortPath($file_lang_path)."</info> -> <info>".$this->manager->getShortPath($backup_path)."</info>");
                    }

                    $this->writeLine('');
                }

                $this->writeLine('Save files:');
                $open_files = '';

                foreach ($job as $file_lang_path => $file_content) {
                    if (!$this->option('dry-run')) {
                        file_put_contents($file_lang_path, $file_content);
                    }

                    $this->writeLine("    <info>".$this->manager->getShortPath($file_lang_path)."</info>");

                    // Fix code style
                    try {
                        $this->manager->fixCodeStyle($file_lang_path, $this->code_style_rules);
                    } // @codeCoverageIgnoreStart
                    catch (Exception $e) {
                        $this->writeError("    Cannot fix code style (".$e->getMessage().")");
                    }
                    // @codeCoverageIgnoreEnd

                    // @codeCoverageIgnoreStart
                    if ($this->option('editor')) {
                        $open_files .= ' '.escapeshellarg($file_lang_path);
                    }
                    // @codeCoverageIgnoreEnd
                }

                $this->writeLine('');
                $this->writeInfo('Process done!');

                // @codeCoverageIgnoreStart
                if ($this->option('editor')) {
                    exec($this->editor.$open_files);
                }
                // @codeCoverageIgnoreEnd

                // @codeCoverageIgnoreStart
            } else {
                $this->writeLine('');
                $this->writeComment('Process aborted. No file has been changed.');
            }
        } // @codeCoverageIgnoreEnd
        else {
            $this->writeLine('');
            $this->writeInfo('Drink a Piña colada and/or smoke Super Skunk, you have nothing to do!');
        }
        $this->writeLine('');

        return self::SUCCESS;
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments(): array
    {
        return [];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions(): array
    {
        return [
            ['dry-run', 'r', InputOption::VALUE_NONE, 'Dry run: run process but do not write anything'],
            ['editor', 'e', InputOption::VALUE_NONE, 'Open files which need to be edited at the end of the process'],
            ['force', 'f', InputOption::VALUE_NONE, 'Force files to be rewritten even if there is nothing to do'],
            ['translation', 't', InputOption::VALUE_NONE, 'Try to translate the lemma to the target language'],
            [
                'new-value',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Value of new found lemmas (use %LEMMA for the lemma value or translation). Set it to null to provide translation fallbacks.',
                $this->todo_prefix.'%LEMMA',
            ],
            ['no-backup', 'b', InputOption::VALUE_NONE, 'Do not backup lang file (be careful, I am not a good coder)'],
            ['no-comment', 'c', InputOption::VALUE_NONE, 'Do not add comments in lang files for lemma definition'],
            ['no-date', 'd', InputOption::VALUE_NONE, 'Do not add the date of execution in the lang files'],
            [
                'no-obsolete', 'o', InputOption::VALUE_NONE,
                'Do not write obsolete lemma (obsolete lemma will be removed)'
            ],
            [
                'output-flat', 'w', InputOption::VALUE_NONE,
                'Output arrays are flat (do not use sub-arrays and keep dots in lemma)'
            ],
            [
                'silent', 's', InputOption::VALUE_NONE,
                'Use this option to only return the exit code (use $? in shell to know whether there are missing lemma or nt)'
            ],
            ['php-file-extension', 'x', InputOption::VALUE_OPTIONAL, 'PHP file extension', 'php'],
            [
                'disable-obsolete-check', 'z', InputOption::VALUE_NONE,
                'Use this option to disable check for obsolete lemmas (obsolete lemma will be kept)'
            ],
        ];
    }
}
