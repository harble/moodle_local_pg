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

namespace local_pg\context;
use stdClass;
use coding_exception;
use context;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

if (!defined('LOCAL_PG_CONTEXT_LEVEL')) {
    page::create_custom_level();
}

/**
 * Class page
 *
 * @package    local_pg
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page extends context {
    /**
     * The context level.
     * @var int
     */
    public const LEVEL = LOCAL_PG_CONTEXT_LEVEL;

    /**
     * Page record from local_pg_pages table.
     * @var stdClass
     */
    public stdClass $instance;

    /**
     * Please use \local_pg\context\page::instance($pageid) if you need the instance of context.
     * Alternatively if you know only the context id use \core\context::instance_by_id($contextid)
     *
     * @param stdClass $record
     */
    protected function __construct(stdClass $record) {
        self::insert_custom_level();
        parent::__construct($record);
        if ($record->contextlevel != self::LEVEL) {
            throw new coding_exception('Invalid $record->contextlevel in local_pg\context\page constructor.');
        }
    }

    /**
     * Context short name.
     * @return string
     */
    public static function get_short_name(): string {
        return 'page';
    }

    /**
     * Returns human readable context level name.
     *
     * @return string the human readable context level name.
     */
    public static function get_level_name() {
        return get_string('page');
    }

    /**
     * Returns human readable context identifier.
     *
     * @param boolean $withprefix whether to prefix the name of the context with Course
     * @param boolean $short whether to use the short name of the thing.
     * @param bool $escape Whether the returned category name is to be HTML escaped or not.
     * @return string the human readable context name.
     */
    public function get_context_name($withprefix = true, $short = false, $escape = true) {
        global $DB;

        $name = '';
        if (!empty($this->instance) && !empty($this->instance->header)) {
            $name = format_string($this->instance->header);
        } else {
            $header = $DB->get_field('local_pg_pages', 'header', ['id' => $this->instanceid]);
            if ($header) {
                $name = format_string($header);
            }
        }
        return $name;
    }

    /**
     * Returns the URL to view the context.
     *
     * @return moodle_url the URL to view the context.
     */
    public function get_url() {
        global $DB;
        if (!isset($this->instance)) {
            $this->instance = $DB->get_record('local_pg_pages', ['id' => $this->instanceid]);
        }

        return new moodle_url('/local/pg/index.php/' . $this->instance->shortname);
    }

    /**
     * Returns context instance database name.
     *
     * @return string|null table name for all levels except system.
     */
    protected static function get_instance_table(): ?string {
        return 'local_pg_pages';
    }

    /**
     * Returns list of all role archetypes that are compatible
     * with role assignments in context level.
     * @since Moodle 4.2
     *
     * @return int[]
     */
    protected static function get_compatible_role_archetypes(): array {
        return ['manager'];
    }

    /**
     * Returns list of all possible parent context levels.
     * @since Moodle 4.2
     *
     * @return int[]
     */
    public static function get_possible_parent_levels(): array {
        return [\core\context\system::LEVEL, self::LEVEL];
    }

    /**
     * Returns array of relevant context capability records.
     *
     * @param string $sort
     * @return array
     */
    public function get_capabilities(string $sort = self::DEFAULT_CAPABILITY_SORT) {
        global $DB;

        $levels = [
            \core\context\system::LEVEL,
            self::LEVEL,
        ];

        return $DB->get_records_list('capabilities', 'contextlevel', $levels, $sort);
    }

    /**
     * Returns page context instance.
     *
     * @param int $pageid id from {local_pg_pages} table
     * @param int $strictness
     * @return page|false context instance
     */
    public static function instance($pageid, $strictness = MUST_EXIST) {
        global $DB;

        if ($context = context::cache_get(self::LEVEL, $pageid)) {
            return $context;
        }

        if (!$record = $DB->get_record('context', ['contextlevel' => self::LEVEL, 'instanceid' => $pageid])) {
            if ($page = $DB->get_record('local_pg_pages', ['id' => $pageid], 'id, parent', $strictness)) {
                if ($page->parent) {
                    $parentcontext = self::instance($page->parent);
                    $record = context::insert_context_record(self::LEVEL, $page->id, $parentcontext->path);
                } else {
                    $record = context::insert_context_record(self::LEVEL, $page->id, '/'.SYSCONTEXTID);
                }
            }
        }

        if ($record) {
            $context = new page($record);
            context::cache_add($context);
            return $context;
        }

        return false;
    }

    /**
     * Create missing context instances at course context level
     */
    protected static function create_level_instances() {
        global $DB;

        $sql = "SELECT ".self::LEVEL.", p.id
                  FROM {local_pg_pages} p
                 WHERE NOT EXISTS (SELECT 'x'
                                     FROM {context} cx
                                    WHERE p.id = cx.instanceid AND cx.contextlevel=".self::LEVEL.")";
        $contextdata = $DB->get_recordset_sql($sql);
        foreach ($contextdata as $context) {
            context::insert_context_record(self::LEVEL, $context->id, null);
        }
        $contextdata->close();
    }

    /**
     * Returns sql necessary for purging of stale context instances.
     *
     * @return string cleanup SQL
     */
    protected static function get_cleanup_sql() {
        $sql = "SELECT c.*
                    FROM {context} c
         LEFT OUTER JOIN {local_pg_pages} p ON c.instanceid = p.id
                   WHERE p.id IS NULL AND c.contextlevel = ".self::LEVEL;

        return $sql;
    }
    /**
     * Rebuild context paths and depths at course context level.
     *
     * @param bool $force
     */
    protected static function build_paths($force) {
        global $DB;

        if ($force || $DB->record_exists_select('context', "contextlevel = ".self::LEVEL." AND (depth = 0 OR path IS NULL)")) {
            if ($force) {
                $ctxemptyclause = $emptyclause = '';
            } else {
                $ctxemptyclause = "AND (ctx.path IS NULL OR ctx.depth = 0)";
                $emptyclause = "AND ({context}.path IS NULL OR {context}.depth = 0)";
            }

            $base = '/'.SYSCONTEXTID;

            // Standard frontpage.
            $sql = "UPDATE {context}
                       SET depth = 2,
                           path = ".$DB->sql_concat("'$base/'", 'id')."
                     WHERE contextlevel = ".self::LEVEL."
                           AND EXISTS (SELECT 'x'
                                         FROM {local_pg_pages} p
                                        WHERE p.id = {context}.instanceid AND p.parent = 0)
                           $emptyclause";
            $DB->execute($sql);

            // Nested pages.
            $sql = "INSERT INTO {context_temp} (id, path, depth, locked)
                    SELECT ctx.id, ".$DB->sql_concat('pctx.path', "'/'", 'ctx.id').", pctx.depth+1, ctx.locked
                      FROM {context} ctx
                      JOIN {local_pg_pages} p ON (p.id = ctx.instanceid AND ctx.contextlevel = ".self::LEVEL." AND p.parent <> 0)
                      JOIN {context} pctx ON (pctx.instanceid = c.parent AND pctx.contextlevel = ".self::LEVEL.")
                     WHERE pctx.path IS NOT NULL AND pctx.depth > 0
                           $ctxemptyclause";
            $trans = $DB->start_delegated_transaction();
            $DB->delete_records('context_temp');
            $DB->execute($sql);
            context::merge_context_temp_table();
            $DB->delete_records('context_temp');
            $trans->allow_commit();
        }
    }

    /**
     * Insert this custom context class and level in $CFG->custom_context_classes
     * @return void
     */
    protected static function insert_custom_level() {
        global $CFG;
        if (empty($CFG->custom_context_classes)) {
            $levels = [
                self::LEVEL => self::class,
            ];
            set_config('custom_context_classes', serialize($levels));
            $CFG->custom_context_classes = $levels;
            return;
        }

        $alllevels = \context_helper::get_all_levels();
        if (isset($alllevels[self::LEVEL])) {
            return;
        }

        if (!is_array($CFG->custom_context_classes)) {
            $levels = @unserialize($CFG->custom_context_classes);

            if (empty($levels) || !is_array($levels)) {
                $levels = [];
            }
        } else {
            $levels = $CFG->custom_context_classes;
        }

        \context_helper::reset_levels();
        if (isset($levels[self::LEVEL])) {
            // Already exists.
            return;
        }

        $levels[self::LEVEL] = self::class;

        set_config('custom_context_classes', serialize($levels));
        $CFG->custom_context_classes = $levels;
    }

    /**
     * Create a custom context level constant which not interfere
     * with any other plugin.
     * @return int
     */
    public static function create_custom_level(): int {
        // If already defined no need to redefine.
        if (defined('LOCAL_PG_CONTEXT_LEVEL')) {
            return LOCAL_PG_CONTEXT_LEVEL;
        }

        // Check the config store.
        if ($level = get_config('local_pg', 'contextlevel')) {
            define('LOCAL_PG_CONTEXT_LEVEL', (int)$level);
            return (int)$level;
        }

        $levels = \context_helper::get_all_levels();
        // Check if already exists in the custom levels.
        foreach ($levels as $level => $class) {
            if ($class === self::class) {
                set_config('contextlevel', $level, 'local_pg');
                define('LOCAL_PG_CONTEXT_LEVEL', (int)$level);
                return (int)$level;
            }
        }

        // Redefine a new context level and make sure it never been used by another system.
        $level = 13;
        while (array_key_exists($level, $levels) && $levels[$level] !== self::class) {
            $level++;
        }

        define('LOCAL_PG_CONTEXT_LEVEL', $level);
        set_config('contextlevel', $level, 'local_pg');

        \context_helper::reset_levels();
        return $level;
    }
}
