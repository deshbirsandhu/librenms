<?php
/**
 * ConfigTest.php
 *
 * -Description-
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    LibreNMS
 * @link       http://librenms.org
 * @copyright  2017 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Tests;

use LibreNMS\Config;

class ConfigTest extends \PHPUnit_Framework_TestCase
{
    public function testGetBasic()
    {
        $dir = realpath(__DIR__ . '/..');
        $this->assertEquals($dir, Config::get('install_dir'));
    }

    public function testSetBasic()
    {
        global $config;
        Config::set('basics', 'first');
        $this->assertEquals('first', $config['basics']);
    }

    public function testGet()
    {
        global $config;
        $config['one']['two']['three'] = 'easy';

        $this->assertEquals('easy', Config::get('one.two.three'));
    }

    public function testSet()
    {
        global $config;
        Config::set('you.and.me', "I'll be there");

        $this->assertEquals("I'll be there", $config['you']['and']['me']);
    }

    public function testHas()
    {
        Config::set('long.key.setting', 'no one cares');

        $this->assertTrue(Config::has('long'));
        $this->assertTrue(Config::has('long.key.setting'));
        $this->assertFalse(Config::has('long.key.setting.nothing'));

        $this->assertFalse(Config::has('off.the.wall'));
        $this->assertFalse(Config::has('off.the'));
    }

    public function testGetNonExistent()
    {
        $this->assertNull(Config::get('There.is.no.way.this.is.a.key'));
        $this->assertFalse(Config::has('There.is.no'));  // should not add kes when getting
    }

    public function testGetNonExistentNested()
    {
        $this->assertNull(Config::get('cheese.and.bologna'));
    }



    public function testGetSubtree()
    {
        Config::set('words.top', 'August');
        Config::set('words.mid', 'And Everything');
        Config::set('words.bot', 'After');
        $expected = array(
            'top' => 'August',
            'mid' => 'And Everything',
            'bot' => 'After'
        );

        $this->assertEquals($expected, Config::get('words'));
    }
}
