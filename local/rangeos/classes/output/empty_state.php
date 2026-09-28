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
 * Contexts for the shared empty-state component.
 *
 * @package    local_rangeos
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_rangeos\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the context for local_rangeos/empty_state.
 *
 * Every empty area on these pages comes from here, so the wording follows one pattern -
 * a headline naming what is missing, a line saying what to do - and the obvious next
 * step is offered in the same place each time.
 */
class empty_state {
    /**
     * Build an empty-state context.
     *
     * @param string $key Language string identifier for the headline; its message is the
     *                    same identifier suffixed with _desc.
     * @param array $action Optional action: ['label' => string identifier] plus either
     *                      'url' => \moodle_url for a link, or 'data' => string for a
     *                      button carrying that data-action value.
     * @return array Template context.
     */
    public static function build(string $key, array $action = []): array {
        $context = [
            'title' => get_string($key, 'local_rangeos'),
            'message' => get_string($key . '_desc', 'local_rangeos'),
        ];

        if (!empty($action['label'])) {
            $context['actionlabel'] = get_string($action['label'], 'local_rangeos');
            if (!empty($action['url'])) {
                $context['actionurl'] = $action['url'] instanceof \moodle_url
                    ? $action['url']->out(false)
                    : (string) $action['url'];
            } else if (!empty($action['data'])) {
                $context['actiondata'] = $action['data'];
            }
        }

        return $context;
    }

    /**
     * The empty state for a site with no environments configured.
     *
     * Every page here needs an environment before it can show anything, so they all show
     * this one, and it points at the page that fixes it. The link is offered only to
     * someone who could actually follow it.
     *
     * @return array Template context.
     */
    public static function no_environments(): array {
        $canmanage = has_capability(
            'local/rangeos:manageenvironments',
            \context_system::instance()
        );

        return self::build('noenvironments', $canmanage ? [
            'label' => 'addenvironment',
            'url' => new \moodle_url('/local/rangeos/environment_profiles.php', ['action' => 'add']),
        ] : []);
    }
}
