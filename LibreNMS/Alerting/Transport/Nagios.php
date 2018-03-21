<?php
/**
 * Nagios.php
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
 * @copyright  2018 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

amespace LibreNMS\Alerting\Transport;

use LibreNMS\Interfaces\Alerting\Transport;

class Nagios implements Transport
{
    public function deliverAlert($obj, $opts)
    {
        /*
        host_perfdata_file_template=
        [HOSTPERFDATA]\t
        $TIMET$\t
        $HOSTNAME$\t
        HOST\t
        $HOSTSTATE$\t
        $HOSTEXECUTIONTIME$\t
        $HOSTLATENCY$\t
        $HOSTOUTPUT$\t
        $HOSTPERFDATA$
        */

        $format = '';
        $format .= "[HOSTPERFDATA]\t";
        $format .= $obj['timestamp'] . "\t";
        $format .= $obj['hostname'] . "\t";
        $format .= md5($obj['rule']) . "\t"; //FIXME: Better entity
        $format .= ($obj['state'] ? $obj['severity'] : "ok") . "\t";
        $format .= "0\t";
        $format .= "0\t";
        $format .= str_replace("\n", "", nl2br($obj['msg'])) . "\t";
        $format .= "NULL"; //FIXME: What's the HOSTPERFDATA equivalent for LibreNMS? Oo
        $format .= "\n";
        return file_put_contents($opts, $format);
    }
}
