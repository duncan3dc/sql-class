<?php

namespace duncan3dc\SqlClass;

class Json
{

    /**
     * Check if the last json operation returned an error and convert it to an exception.
     *
     * @return void
     */
    public static function checkLastError()
    {
        $error = json_last_error();

        if ($error === \JSON_ERROR_NONE) {
            return;
        }

        throw new \Exception("JSON Error: " . json_last_error_msg(), $error);
    }


    /**
     * Convert an array to a serial string, and then write it to a file.
     *
     * @param string The path to the file to write
     * @param array The data to decode
     *
     * @return void
     */
    public static function encodeToFile($path, $array)
    {
        $string = json_encode($array);

        static::checkLastError();

        # Ensure the directory exists
        $directory = pathinfo($path, \PATHINFO_DIRNAME);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        if (file_put_contents($path, $string) === false) {
            throw new \Exception("Failed to write the file: {$path}");
        }
    }


    /**
     * Read a serial string from a file and convert it to an array.
     *
     * @param string The path of the file to read
     *
     * @return array
     */
    public static function decodeFromFile($path)
    {
        if (!is_file($path)) {
            throw new \Exception("File does not exist: {$path}");
        }

        $string = file_get_contents($path);

        if ($string === false) {
            throw new \Exception("Failed to read the file: {$path}");
        }

        if (!$string) {
            return [];
        }

        $array = json_decode($string, true);

        static::checkLastError();

        return $array;
    }
}
