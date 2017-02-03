<?php

namespace Pepgen\Tests\helper;

use PHPUnit\Framework\TestCase;

class TokenizeTest extends TestCase
{

    public function testTokenize()
    {
        $id = 'id';
        $secret = 'secret';
        $watermark = 'watermark';
        $token = \Pepgen\helper\Tokenizer::tokenize($id, $secret, $watermark);
        $this->assertEquals($token, md5(
            $id.
            $secret.
            $watermark.
            strftime("%d.%m.%Y")
        ));
    }
}
