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

namespace local_pg;

/**
 * Class serve
 *
 * @package    local_pg
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preview extends serve {

    /**
     * prepare a preview page included in the specified layout.
     * @param array{shortname:string,header:string,content:string,contentformat:int,css:string,js:string,layout:string} $params
     */
    public function __construct($params) {
        foreach ($params as $name => $value) {
            if (property_exists($this, $name)) {
                $this->$name = $value;
            }
        }
    }

    /**
     * Get the page content.
     *
     * @return string
     */
    public function get_formatted_content() {
        global $CFG;
        if (isset($this->formattedcontent)) {
            return $this->formattedcontent;
        }

        $content = $this->content;
        self::render_content($content);

        $content = format_text($content, $this->contentformat, ['noclean' => true]);
        $this->formattedcontent = str_replace("\"$CFG->wwwroot/brokenfile.php#", "\"$CFG->wwwroot/draftfile.php", $content);
        return $this->formattedcontent;
    }

    /**
     * Render the content of the page in a html box
     * without tempering the $PAGE or sending headers...
     * @return string
     */
    public function out() {
        global $OUTPUT;

        $this->inject_js();
        $this->inject_css();

        $out = $this->get_formatted_content();

        return $OUTPUT->box($out, 'generalbox local-pg local-pg-' . $this->shortname);
    }
    /**
     * Should not be used in preview.
     * @return void
     */
    public function serve() {
        echo $this->out();
    }
}
