<?php

namespace Pepgen\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Pepgen\Helper\Tokenizer;

class TokenizerTest extends TestCase
{

    public function testTokenize()
    {
        $date = new \DateTimeImmutable('now', new \DateTimeZone(Tokenizer::DEFAULT_TIMEZONE));

        $id = 'id';
        $secret = 'secret';
        $watermark = 'watermark';
        $token = Tokenizer::tokenize($id, $secret, $watermark);
        $this->assertEquals($token, md5(
            $id.
            $secret.
            $watermark.
            $date->format('d.m.Y')
        ));
    }

    /**
     * The token has to be the same as the one of the website, which builds it with the local date.
     */
    public function testTokenizeUsesLocalDateAfterMidnight()
    {
        // 00:30 in Berlin is still the previous day in UTC.
        $time = (new \DateTimeImmutable('2026-10-04 00:30', new \DateTimeZone('Europe/Berlin')))->getTimestamp();

        $this->assertSame(
            md5('27635secretErika Muster04.10.2026'),
            Tokenizer::tokenize('27635', 'secret', 'Erika Muster', 'Europe/Berlin', $time)
        );
        $this->assertSame(
            Tokenizer::tokenize('27635', 'secret', 'Erika Muster', 'Europe/Berlin', $time),
            Tokenizer::tokenize('27635', 'secret', 'Erika Muster', null, $time),
            'Europe/Berlin is the default timezone.'
        );
        $this->assertSame(
            md5('27635secretErika Muster03.10.2026'),
            Tokenizer::tokenize('27635', 'secret', 'Erika Muster', 'UTC', $time)
        );
    }

    /**
     * The token does not depend on the default timezone of PHP.
     */
    public function testTokenizeIgnoresDefaultTimezone()
    {
        $time = (new \DateTimeImmutable('2026-01-15 23:30', new \DateTimeZone('UTC')))->getTimestamp();
        $default_timezone = date_default_timezone_get();

        try {
            date_default_timezone_set('America/New_York');
            $this->assertSame(
                md5('27635secretErika Muster16.01.2026'),
                Tokenizer::tokenize('27635', 'secret', 'Erika Muster', 'Europe/Berlin', $time)
            );
        } finally {
            date_default_timezone_set($default_timezone);
        }
    }
}
