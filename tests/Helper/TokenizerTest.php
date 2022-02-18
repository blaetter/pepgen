<?php

namespace Pepgen\Tests\Helper;

use PHPUnit\Framework\TestCase;

class TokenizerTest extends TestCase
{

    public function testTokenize()
    {
        $date = \DateTimeImmutable::createFromFormat('U', time());

        $id = 'id';
        $secret = 'secret';
        $watermark = 'watermark';
        $token = \Pepgen\Helper\Tokenizer::tokenize($id, $secret, $watermark);
        $this->assertEquals($token, md5(
            $id.
            $secret.
            $watermark.
            $date->format('d.m.Y')
        ));
    }
}
