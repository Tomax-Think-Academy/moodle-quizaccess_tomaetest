<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * version.php - version information.
 *
 * @package    quizaccess_tomaetest
 * @subpackage quiz
 * @copyright  2021 Tomax ltd <roy@tomax.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir . '/adminlib.php');

class admin_setting_requiredconfigpasswordunmask extends admin_setting_configpasswordunmask {

    /**
     * Validate data before storage.
     *
     * @param string $data The string to be validated.
     * @return bool|string true for success or error string if invalid.
     */
    public function validate($data) {
        $cleaned = clean_param($data, PARAM_TEXT);
        if ($cleaned === '') {
            return get_string('required');
        }

        return parent::validate($data);
    }
}
