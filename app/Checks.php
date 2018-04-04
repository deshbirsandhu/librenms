<?php
/**
 * Checks.php
 *
 * Pre-flight checks at various stages of booting
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

namespace App;

use App\Models\Device;
use App\Models\Notification;
use Auth;
use Carbon\Carbon;
use Kamaln7\Toastr\Facades\Toastr;
use LibreNMS\Config;

class Checks
{
    public static function preBoot()
    {
        Config::load(); // load without database

        self::checkWriteAccess();
    }

    /**
     * Pre-boot dependency check
     */
    public static function postAutoload()
    {
        if (!class_exists(\Illuminate\Foundation\Application::class)) {
            self::printMessage(
                'Error: Missing dependencies! Run the following command to fix:',
                './scripts/composer_wrapper.php install --no-dev',
                true
            );
        }
    }

    /**
     * Mid-boot check for folder access
     */
    public static function checkWriteAccess()
    {
        // check file/folder permissions
        $check = [
            self::basePath('bootstrap/cache'),
            self::basePath('storage'),
            self::basePath('logs/librenms.log')
        ];
        foreach ($check as $path) {
            if (!is_writable($path)) {
                $user = Config::get('user', 'librenms');
                $group = Config::get('group', $user);
                $dirs = 'rrd/ logs/ storage/ bootstrap/cache/';
                self::printMessage(
                    "Error: $path is not writable! Run these commands to fix:",
                    [
                        "cd " . self::basePath(),
                        "chown -R $user:$group $dirs",
                        "setfacl -R -m g::rwx $dirs",
                        "setfacl -d -m g::rwx $dirs"
                    ],
                    true
                );
            }
        }
    }

    /**
     * Post boot Toast messages
     */
    public static function postAuth()
    {
        $notifications = Notification::isUnread(Auth::user())->where('severity', '>', 1)->get();
        foreach ($notifications as $notification) {
            Toastr::error("<a href='notifications/'>$notification->body</a>", $notification->title);
        }

        if (Device::isUp()->whereTime('last_polled', '<=', Carbon::now()->subMinutes(15))->count() > 0) {
            Toastr::warning('<a href="poll-log/filter=unpolled/">It appears as though you have some devices that haven\'t completed polling within the last 15 minutes, you may want to check that out :)</a>', 'Devices unpolled');
        }

        // Directory access checks
        $rrd_dir = Config::get('rrd_dir');
        if (!is_dir($rrd_dir)) {
            Toastr::error("RRD Directory is missing ($rrd_dir).  Graphing may fail.");
        }

        $temp_dir = Config::get('temp_dir');
        if (!is_dir($temp_dir)) {
            Toastr::error("Temp Directory is missing ($temp_dir).  Graphing may fail.");
        } elseif (!is_writable($temp_dir)) {
            Toastr::error("Temp Directory is not writable ($temp_dir).  Graphing may fail.");
        }

    }

    private static function printMessage($title, $content, $exit = false)
    {
        $content = (array)$content;

        if (PHP_SAPI == 'cli') {
            $format = "%s\n\n%s\n\n";
            $message = implode(PHP_EOL, $content);
        } else {
            $format = "<h3 style='color: firebrick;'>%s</h3><p>%s</p>";
            $message = implode('<br />', $content);
        }

        printf($format, $title, $message);

        if ($exit) {
            exit(1);
        }
    }

    private static function basePath($path = '')
    {
        if (function_exists('base_path')) {
            return base_path($path);
        }

        $base_dir = realpath(__DIR__ . '..');
        return "$base_dir/$path";
    }
}
