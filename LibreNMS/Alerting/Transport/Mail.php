<?php
/* Copyright (C) 2014 Daniel Preussker <f0o@devilcode.org>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>. */

/**
 * Mail Transport
 * @author f0o <f0o@devilcode.org>
 * @copyright 2014 f0o, LibreNMS
 * @license GPL
 * @package LibreNMS
 * @subpackage Alerts
 */

namespace LibreNMS\Alerting\Transport;

use LibreNMS\Config;
use LibreNMS\Interfaces\Alerting\Transport;

class Mail implements Transport
{
    public function getName()
    {
        return "mail";
    }

    public function getDescription()
    {
        return "Mail";
    }

    public function deliverAlert($obj, $opts)
    {
        global $config;
        return send_mail($obj['contacts'], $obj['title'], $obj['msg'], ($config['email_html'] == 'true') ? true : false);
    }

    public function configTemplate()
    {
        return [
            [
                'name'  => 'alert.transports.mail',
                'descr' => 'Enable email alerting',
                'type'  => 'checkbox',
            ],
            [
                'name'    => 'email_backend',
                'descr'   => 'How to deliver mail',
                'type'    => 'select',
                'options' => Config::get('email_backend_options', ['mail', 'sendmail', 'smtp']),
            ],
            [
                'name'  => 'email_user',
                'descr' => 'From name',
                'type'  => 'text',
            ],
            [
                'name'    => 'email_from',
                'descr'   => 'From email address',
                'type'    => 'text',
                'pattern' => '[a-zA-Z0-9_\-\.\+]+@[a-zA-Z0-9_\-\.]+\.[a-zA-Z]{2,18}',
            ],
            [
                'name'  => 'email_html',
                'descr' => 'Use HTML emails',
                'type'  => 'checkbox',
            ],
            [
                'name'  => 'email_sendmail_path',
                'descr' => 'Sendmail path',
                'type'  => 'text',
            ],
            [
                'name'    => 'email_smtp_host',
                'descr'   => 'SMTP Host',
                'type'    => 'text',
                'pattern' => '[a-zA-Z0-9_\-\.]+',
            ],
            [
                'name'     => 'email_smtp_port',
                'descr'    => 'SMTP Port',
                'type'     => 'numeric',
                'required' => true,
            ],
            [
                'name'     => 'email_smtp_timeout',
                'descr'    => 'SMTP Timeout',
                'type'     => 'numeric',
                'required' => true,
            ],
            [
                'name'    => 'email_smtp_secure',
                'descr'   => 'SMTP Secure',
                'type'    => 'select',
                'options' => Config::get('email_smtp_secure_options', ['', 'tls', 'ssl']),
            ],
            [
                'name'    => 'email_auto_tls',
                'descr'   => 'SMTP Auto TLS Support',
                'type'    => 'select',
                'options' => ['true', 'false'],
            ],
            [
                'name'  => 'email_smtp_auth',
                'descr' => 'SMTP Authentication',
                'type'  => 'checkbox',
            ],
            [
                'name'  => 'email_smtp_username',
                'descr' => 'SMTP Authentication Username',
                'type'  => 'text',
            ],
            [
                'name'  => 'email_smtp_password',
                'descr' => 'SMTP Authentication Password',
                'type'  => 'password',
            ],
        ];
    }
}
