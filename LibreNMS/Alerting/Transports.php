<?php
/**
 * Transports.php
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

namespace LibreNMS\Alerting;


use LibreNMS\Alerting\Transport\Mail;
use LibreNMS\Config;
use LibreNMS\Interfaces\Alerting\Transport;

class Transports
{
    /**
     * @return Transport[]
     */
    public static function getAll()
    {
        return [new Mail()];

//        $dir = Config::get('install_dir') . '/LibreNMS/Alerting/Transport/';
//        $namespace = '\LibreNMS\Alerting\Transport\\';
//
//        $transports = ['mail' => null];
//
//        $class_files = glob($dir . '*.php');
//        foreach ($class_files as $class_file) {
//            $class_name = ucfirst(basename($class_file, '.php'));
//            $class = $namespace . $class_name;
//            $transport = new $class();
//
//            if ($transport instanceof Transport) {
//                $transports[$transport->getName()] = $transport;
//            }
//        }
//
//        return $transports;
    }
}
