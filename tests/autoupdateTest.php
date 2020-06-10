<?php
/**
 * @category    Christopher Queen Consulting
 * @package     ChrisQueen_[INSERT Module Name]
 * @copyright   Copyright (c) 2020 Christopher Queen Consulting LLC (http://www.ChristopherQueen.com/)
 * @author      christopherqueen <chris@christopherqueenconsulting.com>
 */

//declare(strict_types=1);
use PHPUnit\Framework\TestCase;


final class autoupdateTest extends TestCase
{
    public function testMultiLineCompare()
    {
        $this->assertEquals(
            true,
            AutoUpdate::multiLineCompare("this\r\ntest\rshould\npass", "this
test
should
pass")
        );
        $this->assertEquals(
            false,
            AutoUpdate::multiLineCompare("this\r\nTest\rshould\nFail", "this\r\ntest\rshould\nfail")
        );
    }


}