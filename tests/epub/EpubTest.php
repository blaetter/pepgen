<?php
use PHPUnit\Framework\TestCase;

class EpubTest extends TestCase
{

    public function testEpubEmty()
    {
        $epub = new \Pepgen\epub\Epub('', '', '');
    }
}
