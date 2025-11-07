<?php
if (!function_exists('app_load_env')) {
    /**
     * Load environment variables from a .env file into getenv()/$_ENV.
     */
    function app_load_env(?string $path = null): void
    {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        $loaded = true;

        if ($path === null || $path === '') {
            $path = __DIR__ . '/.env';
        }

        if (!is_string($path) || $path === '') {
            return;
        }

        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $firstChar = $line[0];
            if ($firstChar === '#' || $firstChar === ';') {
                continue;
            }

            if (stripos($line, 'export ') === 0) {
                $line = trim(substr($line, 7));
            }

            if ($line === '' || strpos($line, '=') === false) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if ($name === '') {
                continue;
            }

            if ($value !== '') {
                if (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
