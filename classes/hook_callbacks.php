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

use local_pg\output\footer;
use navigation_node;

/**
 * Class hook_callbacks.
 *
 * @package    local_pg
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Check slashed argument in home page to redirect to the required page.
     * @param  \core\hook\after_config $hook
     * @return void
     */
    public static function after_config(\core\hook\after_config $hook) {
        global $FULLME;

        if (AJAX_SCRIPT || CLI_SCRIPT || WS_SERVER || during_initial_install()) {
            return;
        }

        $current = new \moodle_url($FULLME);
        $home    = new \moodle_url('/');

        if ($current->compare($home, URL_MATCH_BASE) || $home->compare($current, URL_MATCH_BASE)) {
            $serve = serve::make();

            if ($serve->page_exists()) {
                $serve->serve();
            }
        }
    }

    /**
     * Extend primary navigation to add additional pages.
     * @param  \core\hook\navigation\primary_extend $hook
     * @return void
     */
    public static function primary_extend(\core\hook\navigation\primary_extend $hook) {
        self::add_to_navigation($hook->get_primaryview());
    }

    /**
     * Extend secondary navigation to add additional pages.
     * @param  \core\hook\navigation\secondary_extend $hook
     * @return void
     */
    public static function secondary_extend(\core\hook\navigation\secondary_extend $hook) {
        global $PAGE;
        $view = $hook->get_secondaryview();
        self::add_to_navigation($view);

        if ($PAGE->user_is_editing() && has_any_capability(['moodle/site:config', 'local/pg:add'], \context_system::instance())) {
            $view->add(
                get_string('addpage', 'local_pg'),
                new \moodle_url('/local/pg/edit.php'),
                navigation_node::NODETYPE_LEAF,
                null,
                'addpage'
            );
        }
    }

    /**
     * Add pages to navigation.
     * @param  \navigation_node $view
     * @param  mixed            $all
     * @return void
     */
    public static function add_to_navigation(navigation_node $view, $all = false) {
        global $DB;

        $visible = helper::PUBLIC;
        if (isloggedin()) {
            $visible = helper::ALLOW_GUEST;
            if (!isguestuser()) {
                $visible = helper::REQUIRE_AUTH;
            }
        }
        $params = ['visibility' => $visible];
        $sql    = 'SELECT id, parent, shortname, header, path, visible
                FROM {local_pg_pages}
                WHERE visible <= :visibility';

        if (!$all) {
            if ($view instanceof \core\navigation\views\primary) {
                $sql .= ' AND pnav = 1';
            } else if ($view instanceof \core\navigation\views\secondary) {
                $sql .= ' AND snav = 1';
            }
        }

        $sql .= ' ORDER BY parent ASC, path ASC';
        $pages = $DB->get_records_sql($sql, $params);

        $currentspotted = false;

        /** // phpcs:ignore moodle.Commenting.InlineComment.DocBlock
         * @var array[navigation_node]
         */
        $nodes = [];

        foreach ($pages as $k => $page) {
            $serve = new serve($page, false);

            if (!$serve->is_visible()) {
                unset($pages[$k]);
                continue;
            }

            if (empty($page->parent)) {
                $nodes[$page->id] = $view->add(
                    $serve->get_title(),
                    $serve->get_page_url(),
                    navigation_node::TYPE_CUSTOM,
                    null,
                    'page-' . $serve->get_page_shortname()
                );

                if (!$currentspotted && self::is_current($page->shortname)) {
                    $currentspotted = true;
                    $nodes[$page->id]->make_active();
                }
                unset($pages[$k]);
            }
        }

        foreach ($pages as $k => $page) {
            if (isset($nodes[$page->parent])) {
                $serve = serve::make($page->id, false);
                $node = $nodes[$page->parent]->add(
                    $serve->get_title(),
                    $serve->get_page_url(),
                    navigation_node::TYPE_CUSTOM,
                    null,
                    'page-' . $page->shortname
                );

                if (!$currentspotted && self::is_current($page->shortname)) {
                    $currentspotted = true;
                    $node->make_active();
                }
                unset($pages[$k]);
            }
        }
    }

    /**
     * Add pages links to footer.
     * @param \core\hook\output\before_standard_footer_html_generation $hook
     * @return void
     */
    public function add_to_footer(\core\hook\output\before_standard_footer_html_generation $hook) {
        global $PAGE;
        $widget = new footer();
        $renderer = $PAGE->get_renderer('local_pg');
        $hook->add_html($renderer->render($widget));
    }

    /**
     * Check if the shortname of the page passed is already the current page.
     * @param  string $shortname
     * @return bool
     */
    public static function is_current($shortname) {
        global $FULLME, $DB;
        $current = new \moodle_url($FULLME);
        $path    = $current->get_path();

        if ($pos = strpos('.php/', $path)) {
            $arg = substr($path, $pos + 5);

            if ($arg == $shortname) {
                return true;
            }
        }

        $id = $DB->get_field('local_pg_pages', 'id', ['shortname' => $shortname]);

        if (!$id) {
            return false;
        }

        if (strpos('local/pg/index.php', $path) !== false || strpos('local/pg/p.php', $path) !== false) {
            if ($id == $current->get_param('page')) {
                return true;
            }

            if ($id == $current->get_param('id')) {
                return true;
            }
        }

        return false;
    }
}
