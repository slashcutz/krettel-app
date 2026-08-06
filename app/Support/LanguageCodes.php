<?php

namespace App\Support;

class LanguageCodes
{
    /**
     * Display name => ISO 639-2 (3-letter) code, as used by HLS masters.
     */
    protected const NAMES = [
        'English' => 'eng',
        'Hindi' => 'hin',
        'Telugu' => 'tel',
        'Tamil' => 'tam',
        'Kannada' => 'kan',
        'Malayalam' => 'mal',
        'Bengali' => 'ben',
        'Marathi' => 'mar',
        'Gujarati' => 'guj',
        'Punjabi' => 'pun',
        'Urdu' => 'urd',
        'Odia' => 'ori',
        'Assamese' => 'asm',
        'Spanish' => 'spa',
        'French' => 'fra',
        'German' => 'deu',
        'Japanese' => 'jpn',
        'Korean' => 'kor',
        'Chinese' => 'chi',
        'Arabic' => 'ara',
        'Italian' => 'ita',
        'Portuguese' => 'por',
        'Russian' => 'rus',
        'Thai' => 'tha',
        'Indonesian' => 'ind',
        'Vietnamese' => 'vie',
        'Malay' => 'msa',
    ];

    /**
     * Display names for the upload form's language dropdown.
     */
    public static function names(): array
    {
        return array_keys(self::NAMES);
    }

    /**
     * Convert a display name (e.g. "English" or "English (CC)") to a 3-letter
     * code. Falls back to the first three characters, then to 'und'.
     */
    public static function code(string $name): string
    {
        $key = strtolower(trim($name));
        $key = preg_replace('/\s*\(.*\)$/', '', $key);

        foreach (self::NAMES as $display => $code) {
            if (strtolower($display) === $key) {
                return $code;
            }
        }

        return substr($key, 0, 3) ?: 'und';
    }
}
