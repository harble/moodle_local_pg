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

use stdClass;

/**
 * Class helper.
 *
 * @package    local_pg
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * If the page still draft.
     * @var int
     */
    public const UNPUBLISHED = 8;

    /**
     * If the page is public.
     * @var int
     */
    public const PUBLIC = 1;

    /**
     * If the page require login and allowed to guests.
     * @var int
     */
    public const ALLOW_GUEST = 2;

    /**
     * If the page require authenticated user.
     * @var int
     */
    public const REQUIRE_AUTH = 4;

    /**
     * Page content file area
     * @var string
     */
    public const CONTENT_FILEAREA = 'pagecontent';
    /**
     * Page content in custom lang file area.
     * @var string
     */
    public const CUSTOMLANG_FILEAREA = 'pagecontentlang';
    /**
     * FAQ Questions file area.
     * @var string
     */
    public const FAQ_Q_FILEAREA = 'questions';
    /**
     * FAQ Answers filearea.
     * @var string
     */
    public const FAQ_A_FILEAREA = 'answers';
    /**
     * Cached visibility strings.
     * @var string[]
     */
    protected static $visibilitystrings;

    /**
     * Cached layout options.
     * @var string[]
     */
    protected static $layoutstrings;

    /**
     * Get the visibility options.
     * @return string[]
     */
    public static function get_visibility_options() {
        if (isset(self::$visibilitystrings)) {
            return self::$visibilitystrings;
        }
        self::$visibilitystrings = [
            self::UNPUBLISHED   => get_string('unpublished', 'local_pg'),
            self::PUBLIC        => get_string('public', 'local_pg'),
            self::ALLOW_GUEST   => get_string('allowguest', 'local_pg'),
            self::REQUIRE_AUTH  => get_string('requireauth', 'local_pg'),
        ];

        return self::$visibilitystrings;
    }

    /**
     * Get the html editor options.
     * @return array{context: null, maxbytes: int, maxfiles: int, noclean: bool, subdirs: bool}
     */
    public static function get_editor_options() {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');

        return [
            'subdirs'   => false,
            'maxfiles'  => EDITOR_UNLIMITED_FILES,
            'maxbytes'  => get_max_upload_file_size(),
            'context'   => null,
            'noclean'   => true,
        ];
    }

    /**
     * Get pages layout options in moodle.
     * @return string[]
     */
    public static function get_layout_options() {
        if (isset(self::$layoutstrings)) {
            return self::$layoutstrings;
        }
        global $PAGE;
        $layouts             = $PAGE->theme->layouts;
        $strman              = get_string_manager();
        self::$layoutstrings = [];

        foreach ($layouts as $layout => $ignore) {
            $name = $layout;

            if ($strman->string_exists($layout, 'moodle')) {
                $name = get_string($layout);
            }
            self::$layoutstrings[$layout] = $name;
        }

        return self::$layoutstrings;
    }

    /**
     * Get pages as options to be parent page.
     * Now we just use first level pages that could be acted as parent.
     * @return string[]
     */
    public static function get_pages_options() {
        global $DB;
        $sort    = 'shortname ASC, path ASC, id ASC';
        $fields  = 'id, shortname, path, header';

        // We only use the 0 level pages as parents.
        // Todo: allow more nesting.
        $pages   = $DB->get_records('local_pg_pages', ['parent' => 0], $sort, $fields);
        $options = [
            0 => get_string('home'),
        ];

        foreach ($pages as $page) {
            if (empty($page->path)) {
                $page->path = '/';
            }
            $page->path .= $page->shortname;
            $options[$page->id] = '(' . $page->path . ') ' . $page->header;
        }

        return $options;
    }

    /**
     * Format the page path as \parent\page if it has a parent
     * or \page if no parent exist.
     *
     * @param  stdClass $data {shortname:string, parent:int}
     * @return string
     */
    public static function format_page_path(stdClass &$data) {
        global $DB;
        $path     = "\\{$data->shortname}";
        $parentid = $data->parent;

        while ($parentid && ($parent = $DB->get_record('local_pg_pages', ['id' => $data->parent], 'shortname, path, parent'))) {
            $path     = "\\{$parent->shortname}" . $path;
            $parentid = $parent->parent;
        }

        $data->path = $path;

        return $path;
    }

    /**
     * Fix pages paths.
     * This is used to fix the path of all pages in the system.
     * It will update the path of each page based on its parent and shortname.
     */
    public static function fix_pages_paths() {
        global $DB;
        $tr    = $DB->start_delegated_transaction();
        $pages = $DB->get_records('local_pg_pages', null, '', 'id, parent, shortname, path');

        foreach ($pages as $page) {
            $oldpath = (string)$page->path;
            $newpath = self::format_page_path($page);

            if ($oldpath != $newpath) {
                $DB->update_record('local_pg_pages', $page);
            }
        }
        $tr->allow_commit();
    }

    /**
     * Validate a css code.
     * @param  string $css
     * @return bool
     */
    public static function validate_css($css) {
        if (empty($css)) {
            return true;
        }

        $css = trim($css);

        $scss = new \core_scss();

        try {
            $scss->compile($css);
        } catch (\ScssPhp\ScssPhp\Exception\ParserException $e) {
            return false;
        } catch (\ScssPhp\ScssPhp\Exception\CompilerException $e) {
            // Silently ignore this - it could be a scss variable defined from somewhere
            // else which we are not examining here.
            return true;
        }

        return true;
    }

    /**
     * Validate a js code.
     * This is not sufficient for validation, so another frontend method used in the edit form.
     * @param  string $js
     * @return bool
     */
    public static function validate_js($js) {
        if (empty($js)) {
            return true;
        }

        $js  = trim($js);
        $min = new \core_minify();

        try {
            $out = $min->js($js);

            if (strlen($out) > strlen($js)) {
                return false;
            }
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }

    /**
     * Get the page record belongs to support page.
     * @return \stdClass
     */
    public static function get_support_page_record() {
        global $DB, $USER;

        $fields  = 'id, shortname, header, layout, visible, pnav, snav';
        $support = $DB->get_record('local_pg_pages', ['shortname' => 'support'], $fields);

        if (!$support) {
            $support                = new \stdClass();
            $support->shortname     = 'support';
            $support->header        = get_string('contactsitesupport', 'admin');
            $support->content       = '';
            $support->contentformat = FORMAT_HTML;
            $support->parent        = 0;
            $support->css           = '';
            $support->js            = '';
            $support->timecreated   = time();
            $support->timemodified  = time();
            $support->usermodified  = $USER->id;
            $support->layout        = 'standard';
            $support->visible       = self::PUBLIC;
            $support->pnav          = 0;
            $support->snav          = 0;
            $support->id            = $DB->insert_record('local_pg_pages', $support);
        }

        return $support;
    }
}
