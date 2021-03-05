<?php

namespace LiquidWeb\HtaccessValidator;

use LiquidWeb\HtaccessValidator\Exceptions\ValidationException;

class Validator
{
    /**
     * @var string|Resource The file being validated.
     */
    protected $file;

    /**
     * Construct a new instance of the class.
     *
     * @param string|resource $file The file being validated. This may be an actual file or a
     *                              stream resource.
     */
    public function __construct($file)
    {
        $this->file = $file;
    }

    /**
     * Retrieve the underlying filepath.
     *
     * @return string The path to $this->file.
     */
    public function getFilePath()
    {
        return is_resource($this->file)
            ? stream_get_meta_data($this->file)['uri']
            : (string) $this->file;
    }

    /**
     * Simply return whether or not the given file's syntax is valid.
     *
     * @return bool True if validation passes, false if an error is encountered.
     */
    public function isValid()
    {
        try {
            $this->validate();
        } catch (ValidationException $e) {
            return false;
        }

        return true;
    }

    /**
     * Validate the given file.
     *
     * Validation is handled by the underlying Apache instance, using a stripped-down configuration
     * so the entire Apache configuration consists of a) the necessary bootstrapping and b) the
     * contents of $this->file.
     *
     * @throws ValidationException
     */
    public function validate()
    {
        try {
            $result = $this->runValidator();
        } catch (\Exception $e) {
            throw new ValidationException(
                sprintf('There was an error running the validate-htaccess script: %s', $e->getMessage()),
                $e->getCode(),
                $e
            );
        }

        if (0 !== $result->exitCode) {
            throw new ValidationException(
                sprintf('Validation errors were encountered: %s', $result->errors),
                $result->exitCode
            );
        }

        return true;
    }

    /**
     * Create a new validator instance using a temporary file.
     *
     * @param string $contents The file contents.
     *
     * @return self
     */
    public static function createFromString($contents)
    {
        $fh = tmpfile();
        fwrite($fh, $contents);

        return new static($fh);
    }

    /**
     * Call the underlying validate-htaccess script.
     *
     * @throws \RuntimeException if unable run the script.
     *
     * @return object {
     *    Details about the validation run.
     *
     *    @type int    $exitCode The script's exit code.
     *    @type string $errors   The contents of STDERR.
     * }
     */
    protected function runValidator()
    {
        // Locate the validate-htaccess shell script.
        $script = '';
        $paths  = [
            getenv('HTACCESS_VALIDATOR_SCRIPT'),
            dirname(__DIR__) . '/vendor/bin/validate-htaccess',
            dirname(dirname(dirname(__DIR__))) . '/bin/validate-htaccess',
        ];

        foreach ($paths as $path) {
            if ($path && file_exists($path)) {
                $script = $path;
                break;
            }
        }

        if (! $script) {
            throw new \RuntimeException(
                sprintf('Cannot find validation script at %s, have you installed Composer dependencies?', $script),
                E_COMPILE_ERROR
            );
        }

        if (! is_executable($script)) {
            throw new \RuntimeException(
                sprintf(
                    'The permissions on %s do not permit it to be run by the current user (%s)',
                    $script,
                    posix_getpwuid(posix_geteuid())['name']
                ),
                E_ERROR
            );
        }

        // Assemble and call the script.
        $cmd  = sprintf('%1$s %2$s', escapeshellarg($script), escapeshellarg($this->getFilePath()));
        $spec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(escapeshellcmd($cmd), $spec, $pipes);

        if (! is_resource($process)) {
            throw new ValidationException('Unable to run validation script.');
        }

        $errors   = stream_get_contents($pipes[2]);
        $exitCode = proc_close($process);

        return (object) [
            'exitCode' => $exitCode,
            'errors'   => $errors,
        ];
    }
}
