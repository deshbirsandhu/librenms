<?php
/**
 * SnmpEngineTest.php
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
 * @copyright  2016 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Tests;

use LibreNMS\SNMP;
use LibreNMS\SNMP\DataSet;
use LibreNMS\SNMP\Engines\Mock;
use LibreNMS\SNMP\OIDData;

abstract class SnmpEngineTest extends \PHPUnit_Framework_TestCase
{
    private function checkSnmpsim()
    {
        if (!getenv('SNMPSIM') && !SNMP::getInstance() instanceof Mock) {
            $this->markTestSkipped('SNMPSIM not present');
        }
    }

    public function testUnreachable()
    {
        $unreachable = Mock::genDevice('unreachable', 1);
        $unreachable['timeout'] = 0.001;
        $result = SNMP::get($unreachable, 'sysDescr.0');
//        $this->assertNotNull($result);
        $this->assertTrue($result->hasError());
        $this->assertEquals(SNMP::ERROR_UNREACHABLE, $result->getError());
    }

    public function testNonExistantOidGet()
    {
        $this->checkSnmpsim();
        $result = SNMP::get(Mock::genDevice('unit_tests'), '.1.3.4.5.6.7.236.7');
        $this->assertTrue($result->hasError(), 'Error is not set');
        $this->assertEquals(
            SNMP::ERROR_NO_SUCH_OID,
            $result->getError(),
            'Error does not match expected: ' . $result->getErrorMessage()
        );
        $this->assertNull($result['value'], 'The value attribute must be null');
    }

    public function testNonExistantOidWalk()
    {
        $this->checkSnmpsim();
        $result = SNMP::walk(Mock::genDevice('unit_tests'), '.1.3.4.5.6.7.236.8')->first();
        $this->assertTrue($result->hasError(), 'Error is not set');
        $this->assertEquals(
            SNMP::ERROR_NO_SUCH_OID,
            $result->getError(),
            'Error does not match expected: ' . $result->getErrorMessage()
        );
        $this->assertNull($result['value'], 'The value attribute must be null');
    }

    public function testSnmpTranslate()
    {
        $this->assertEquals('', SNMP::translate(Mock::genDevice(), ''));
        $this->assertEquals(array(), SNMP::translate(Mock::genDevice(), array()));
        $this->assertEquals('UCD-SNMP-MIB::prTable', SNMP::translate(Mock::genDevice(), '1.3.6.1.4.1.2021.2'));

        $oids = array('system', 'ifTable');
        $expected = array('system' => 'SNMPv2-MIB::system', 'ifTable' => 'IF-MIB::ifTable');
        $this->assertEquals($expected, SNMP::translate(Mock::genDevice(), $oids, '-IR'));

        $oids = array('system', 'ifTable', 'SNMPv2-MIB:sysName.0');
        $expected = array(
            'system' => '.1.3.6.1.2.1.1',
            'ifTable' => '.1.3.6.1.2.1.2.2',
            'SNMPv2-MIB:sysName.0' => '.1.3.6.1.2.1.1.5.0'
        );
        $this->assertEquals($expected, SNMP::translate(Mock::genDevice(), $oids, '-IR -On'));
    }

    public function testSnmpTranslateFailure()
    {
        $this->assertEquals('', SNMP::translate(Mock::genDevice(), ''));
        $this->assertEquals(array(), SNMP::translate(Mock::genDevice(), array()));
        $this->assertEquals('.1.3.6.1.2.1.1.5.0', SNMP::translate(Mock::genDevice(), 'SNMPv2-MIB::sysName.0', '-On'));

        $expected = array('sysName.0' => '.1.3.6.1.2.1.1.5.0');
        $this->assertEquals($expected, SNMP::translate(Mock::genDevice(), array('sysName.0'), '-On -IR'));

        $oids = array('SNMPv2-MIB::system', '.1.3.6.1.2.1.1', 'fldsmdfr', '.1.3.6.1.2.1.1.5.0');
        $expected = array(
            'SNMPv2-MIB::system' => 'SNMPv2-MIB::system',
            '.1.3.6.1.2.1.1' => 'SNMPv2-MIB::system',
            'fldsmdfr' => null,
            '.1.3.6.1.2.1.1.5.0' => null
        );
        $this->assertEquals($expected, SNMP::translate(Mock::genDevice(), $oids));

        $expected['.1.3.6.1.2.1.1'] = null;
        $this->assertEquals($expected, SNMP::translate(Mock::genDevice(), $oids, '-IR'));
    }

    public function testSnmpTranslateNumeric()
    {
        $this->assertEquals('', SNMP::translateNumeric(Mock::genDevice(), ''));
        $this->assertEquals(array(), SNMP::translateNumeric(Mock::genDevice(), array()));
        $this->assertEquals('.1.3.6.1.2.1.1.5.0', SNMP::translateNumeric(Mock::genDevice(), 'sysName.0'));

        $oids = array('SNMPv2-MIB::system', 'UCD-SNMP-MIB::ssCpuUser.0');
        $expected = array(
            'SNMPv2-MIB::system' => '.1.3.6.1.2.1.1',
            'UCD-SNMP-MIB::ssCpuUser.0' => '.1.3.6.1.4.1.2021.11.9.0'
        );
        $this->assertEquals($expected, SNMP::translateNumeric(Mock::genDevice(), $oids));
    }

    public function testSnmpGet()
    {
        $this->checkSnmpsim();
        $device = Mock::genDevice('unit_tests');
        $expected = OIDData::make(array(
            'oid' => 'SNMPv2-MIB::sysDescr.0',
            'base_oid' => 'SNMPv2-MIB::sysDescr',
            'index' => 0,
            'extra_oid' => array(),
            'type' => 'string',
            'value' => 'Unit Tests sysDescr',
            'mib' => 'SNMPv2-MIB',
            'name' => 'sysDescr',
        ));

        $results = SNMP::get($device, 'SNMPv2-MIB::sysDescr.0');
        $this->assertEquals($expected, $results);
    }

    public function testQuotes()
    {
        $this->checkSnmpsim();
        $expected = OIDData::make(array(
            'oid' => 'SNMPv2-MIB::sysContact.0',
            'base_oid' => 'SNMPv2-MIB::sysContact',
            'index' => 0,
            'extra_oid' => array(),
            'type' => 'string',
            'value' => 'null@yarl.com',
            'mib' => 'SNMPv2-MIB',
            'name' => 'sysContact',
        ));

        $results = SNMP::get(Mock::genDevice('unit_tests'), 'SNMPv2-MIB::sysContact.0');
        $this->assertEquals($expected, $results);
    }

    public function testEmbeddedString()
    {
        $this->checkSnmpsim();
        $device = Mock::genDevice('unit_tests');
        $expected = DataSet::make(array(
            OIDData::make(array(
                'oid' => 'IP-MIB::ipNetToPhysicalPhysAddress.1.ipv6."fd:80:00:00:00:00:00:00:86:d6:d0:ff:fe:ed:0f:cc"',
                'base_oid' => 'IP-MIB::ipNetToPhysicalPhysAddress',
                'index' => 1,
                'extra_oid' => array(
                    0 => 'ipv6',
                    1 => 'fd:80:00:00:00:00:00:00:86:d6:d0:ff:fe:ed:0f:cc'
                ),
                'type' => 'string',
                'value' => '84:d6:d0:ed:1f:96',
                'mib' => 'IP-MIB',
                'name' => 'ipNetToPhysicalPhysAddress',
            )),
            OIDData::make(array(
                'oid' => 'IP-MIB::ipNetToPhysicalPhysAddress.97.ipv6."fd:80:00:00:00:00:00:00:26:e9:b3:ff:fe:bb:50:c3"',
                'base_oid' => 'IP-MIB::ipNetToPhysicalPhysAddress',
                'index' => 97,
                'extra_oid' => array(
                    0 => 'ipv6',
                    1 => 'fd:80:00:00:00:00:00:00:26:e9:b3:ff:fe:bb:50:c3'
                ),
                'type' => 'string',
                'value' => '24:e9:b3:bb:60:ad',
                'mib' => 'IP-MIB',
                'name' => 'ipNetToPhysicalPhysAddress',
            ))
        ));


        $results = SNMP::walk($device, 'ipNetToPhysicalPhysAddress');
        $this->assertEquals($expected, $results);
    }
}
