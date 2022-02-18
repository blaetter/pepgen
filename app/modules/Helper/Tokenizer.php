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
     * tokenize - the unique implementation of how to build the token
     *            by now this is the md5 version of the epub_id, the secret key
     *            and the watermark combined with the currend day.
     *
     */
    public static function tokenize($epub_id, $secret, $watermark)
    {
        $date = \DateTimeImmutable::createFromFormat('U', time());

        return md5(
            $epub_id.
            $secret.
            $watermark.
            $date->format('d.m.Y')
        );
    }
}
