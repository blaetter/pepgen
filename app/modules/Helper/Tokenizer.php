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
        return md5(
            $epub_id.
            $secret.
            $watermark.
            strftime("%d.%m.%Y")
        );
    }
}
