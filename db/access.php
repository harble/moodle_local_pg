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
 * Capability definitions for Pages
 *
 * Documentation: {@link https://moodledev.io/docs/apis/subsystems/access}
 *
 * @package    local_pg
 * @category   access
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/pg:edit' => [
        'riskbitmask' => RISK_MANAGETRUST | RISK_CONFIG | RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => local_pg\context\page::LEVEL,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
    'local/pg:delete' => [
        'riskbitmask' => RISK_MANAGETRUST | RISK_CONFIG | RISK_SPAM | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => local_pg\context\page::LEVEL,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
    'local/pg:add' => [
        'riskbitmask' => RISK_MANAGETRUST | RISK_CONFIG | RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
    'local/pg:viewhidden' => [
        'captype' => 'read',
        'contextlevel' => local_pg\context\page::LEVEL,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
