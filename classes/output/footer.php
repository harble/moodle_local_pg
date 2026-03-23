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

namespace local_pg\output;

use local_pg\helper;
use renderable;
use templatable;
use local_pg\serve;

/**
 * Class footer
 *
 * @package    local_pg
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class footer implements renderable, templatable {
    /**
     * Summary of pages
     * @var serve[]
     */
    protected array $pages;
    /**
     * Prepare pages to be displayed in footer
     */
    public function __construct() {
        global $DB;
        $visible = helper::PUBLIC;
        if (isloggedin()) {
            $visible = helper::ALLOW_GUEST;
            if (!isguestuser()) {
                $visible = helper::REQUIRE_AUTH;
            }
        }

        $pages = $DB->get_records_select('local_pg_pages', "visible <= :vis", ['vis' => $visible], 'parent ASC', 'id');
        $this->pages = [];
        foreach ($pages as $page) {
            $serve = serve::make($page->id, false);
            if ($serve->is_visible()) {
                $this->pages[$page->id] = $serve;
            }
        }
    }
    /**
     * Summary of export_for_template
     * @param \core\output\renderer_base $output
     * @return array{pages:array{url:\moodle_url,name:string,haschildren:bool,children:array{url:moodle_url,name:string}}}
     */
    public function export_for_template(\core\output\renderer_base $output) {
        $context = ['pages' => []];
        foreach ($this->pages as $page) {
            if (!$page->parent) {
                $context['pages'][$page->id] = [
                    'url'         => $page->get_page_url(),
                    'name'        => $page->get_title(),
                    'children'    => [],
                    'haschildren' => false,
                ];
            } else {
                $context['pages'][$page->parent]['children'][] = [
                    'url'  => $page->get_page_url(),
                    'name' => $page->get_title(),
                ];
                $context['pages'][$page->parent]['haschildren'] = true;
            }
        }

        $context['pages'] = array_values($context['pages']);
        return $context;
    }
}
