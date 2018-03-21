<?php
/**
 * Transport.php
 *
 * An interface for the transport of alerts.
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
 * @copyright  2017 Robrecht Plaisier
 * @author     Robbrecht Plaisier <librenms@mcq8.be>
 */

namespace LibreNMS\Interfaces\Alerting;

interface Transport
{
    /**
     * Get the name of this transport
     * This will be used to prefix config settings and should be all lowercase and alpha-numeric
     *
     * @return string
     */
    public function getName();

    /**
     * Get the description of this transport
     * This will be displayed in the webui
     *
     * @return string
     */
    public function getDescription();

    /**
     * Gets called when an alert is sent
     *
     * @param $alert_data array An array created by DescribeAlert
     * @param $opts array|true The options from $config['alert']['transports'][$transport]
     * @return bool Returns if the call was successful
     */
    public function deliverAlert($alert_data, $opts);

    /**
     * Generate a config template to be used with generate_dynamic_config_panel()
     *
     * @return mixed
     */
    public function configTemplate();
}
