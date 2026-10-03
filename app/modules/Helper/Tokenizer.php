<?php
/**
 * Tokenizer Helper Class
 *
 * Handles all actions for the token
 *
 */

namespace Pepgen\Helper;

class Tokenizer
{
    /**
     * The timezone used for the date within the token, if none is configured.
     *
     * It has to be the same timezone the website or shop uses to build the token.
     */
    public const DEFAULT_TIMEZONE = 'Europe/Berlin';

    /**
     * tokenize - the unique implementation of how to build the token
     *            by now this is the md5 version of the epub_id, the secret key
     *            and the watermark combined with the currend day.
     *
     * @param string $epub_id The id of the epub
     * @param string $secret The secret shared with the website or shop
     * @param string $watermark The watermark
     * @param string|null $timezone The timezone the current day is determined in, e.g. Europe/Berlin
     * @param int|null $time The unix timestamp to build the token for, defaults to now
     */
    public static function tokenize($epub_id, $secret, $watermark, $timezone = null, $time = null)
    {
        // A date created from a unix timestamp is always UTC, so it needs to be converted into the timezone of
        // the website or shop. Otherwise the tokens differ between midnight and 1 or 2 am.
        $date = (new \DateTimeImmutable('@' . ($time ?? time())))
            ->setTimezone(new \DateTimeZone($timezone ?: self::DEFAULT_TIMEZONE));

        return md5(
            $epub_id.
            $secret.
            $watermark.
            $date->format('d.m.Y')
        );
    }
}
