<?php
use PHPUnit\Framework\TestCase;

class EpubTest extends TestCase
{
    /**
     * @expectedException ErrorException
     */
    public function testEpubEmty()
    {
        $epub = new \Pepgen\epub\Epub('', '', '');
        $epub->run();
    }
}
