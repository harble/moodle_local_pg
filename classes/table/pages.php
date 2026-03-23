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

namespace local_pg\table;

use html_writer;
use local_pg\helper;
use moodle_url;
use pix_icon;
use table_sql;

/**
 * Class pages.
 *
 * @package    local_pg
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pages extends table_sql {
    /**
     * Possible forms of page url.
     * @var array
     */
    public const BASE_URLS = [
        '/local/pg/{shortname}',
        '/?/{shortname}',
        '/local/pg/index.php/{shortname}',
        '/local/pg/?/{shortname}',
        '/local/pg/p.php/{shortname}',
        '/local/pg/?page={id}',
        '/local/pg/index.php?page={id}',
        '/local/pg/p.php?page={id}',
    ];

    /**
     * Prepare the table data.
     */
    public function __construct() {
        parent::__construct('pages_reports_ids');
        $columns = [
            'id'           => get_string('pageid', 'local_pg'),
            'shortname'    => get_string('shortname'),
            'title'        => get_string('title', 'local_pg'),
            'urls'         => get_string('page_urls', 'local_pg'),
            'visible'      => get_string('visible'),
            'pnav'         => get_string('pnav', 'local_pg'),
            'snav'         => get_string('snav', 'local_pg'),
            'parent'       => get_string('parent', 'local_pg'),
            'layout'       => get_string('layout', 'local_pg'),
            'usermodified' => get_string('usermodified', 'local_pg'),
            'timemodified' => get_string('timemodified', 'data'),
            'timecreated'  => get_string('timecreated'),
            'actions'      => get_string('actions'),
        ];
        $this->define_columns(array_keys($columns));
        $this->define_headers(array_values($columns));
        $this->set_our_sql();
    }

    /**
     * Set sql query.
     * @return void
     */
    protected function set_our_sql() {
        $pagesfields = [
            'id',
            'shortname',
            'header as title',
            'layout',
            'visible',
            'pnav',
            'snav',
            'parent',
            'timecreated',
            'timemodified',
        ];
        $userfields = \core_user\fields::for_name();
        $ufields    = $userfields->get_sql('u', true, '', 'userid', false)->selects;
        $pfields    = implode(', ', array_map(function ($value) {
            return 'p.' . $value;
        }, $pagesfields));
        $selects = $pfields . ', ' . $ufields;

        $from = '{local_pg_pages} p
                 JOIN {user} u ON p.usermodified = u.id';

        $this->set_sql($selects, $from, '1=1');
    }

    /**
     * Out the parent page name.
     * @param  \stdClass $row
     * @return string
     */
    public function col_parent($row) {
        global $DB;

        if (empty($row->parent)) {
            return get_string('home');
        }

        if (isset($this->rawdata[$row->parent])) {
            return format_string($this->rawdata[$row->parent]->title);
        }

        $parent = $DB->get_field('local_pg_pages', 'title', ['id' => $row->parent]);

        if ($parent) {
            return format_string($parent);
        }

        return get_string('deleted', 'data');
    }

    /**
     * Out all possible links for this page.
     * @param  \stdClass $row
     * @return string
     */
    public function col_urls($row) {
        global $CFG;
        $links = html_writer::start_tag('ul');

        foreach (self::BASE_URLS as $base) {
            $url = str_replace('{shortname}', $row->shortname, $base);
            $url = str_replace('{id}', $row->id, $url);
            $url = $CFG->wwwroot . $url;
            $links .= html_writer::tag('li', \html_writer::link($url, $url));
        }
        $links .= html_writer::end_tag('ul');

        return $links;
    }

    /**
     * Actions column (edit or delete).
     * @param  \stdClass $row
     * @return string
     */
    public function col_actions($row) {
        global $OUTPUT;
        $actions = [];
        $context = \local_pg\context\page::instance($row->id);

        if (has_capability('local/pg:edit', $context)) {
            $editurl   = new moodle_url('/local/pg/edit.php', ['id' => $row->id]);
            $actions[] = $OUTPUT->action_icon($editurl, new pix_icon('t/edit', get_string('edit')));
        }

        if (has_capability('local/pg:delete', $context)) {
            $deleteurl = new moodle_url('/local/pg/delete.php', ['id' => $row->id]);
            $actions[] = $OUTPUT->action_icon($deleteurl, new pix_icon('t/delete', get_string('delete')));
        }

        return implode('', $actions);
    }

    /**
     * Format columns data.
     * @param  string      $column
     * @param  \stdClass   $row
     * @return string|null
     */
    public function other_cols($column, $row) {
        switch ($column) {
            case 'usermodified':
                return self::col_fullname($row);

            case 'timemodified':
            case 'timecreated':
                return userdate($row->$column);

            case 'layout':
                return helper::get_layout_options()[$row->layout];

            case 'visible':
                return helper::get_visibility_options()[$row->visible];

            case 'pnav':
            case 'snav':
                return (bool)$row->$column ? get_string('yes') : get_string('no');

            case 'title':
                return format_string($row->title);

            default:
                return parent::other_cols($column, $row);
        }
    }
}
