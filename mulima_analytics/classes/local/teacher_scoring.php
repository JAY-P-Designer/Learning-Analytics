<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Learning Analytics teacher scoring.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_mulima_analytics\local;

defined('MOODLE_INTERNAL') || die();

/** One scoring policy for settings, the teacher report and its export. */
final class teacher_scoring {
    public static function defaults(): array {
        return ['weight_activities'=>3, 'weight_resources'=>2, 'weight_grading'=>4,
            'weight_forums'=>1, 'maximum'=>99, 'moderate'=>20, 'high'=>60];
    }

    public static function criteria(): array {
        return ['activities'=>'weight_activities', 'resources'=>'weight_resources',
            'graded'=>'weight_grading', 'forum_posts'=>'weight_forums'];
    }

    /** Return translated error keys, preserving user input until it is valid. */
    public static function errors(array $input): array {
        $errors = [];
        foreach (self::defaults() as $key=>$unused) {
            $value = $input[$key] ?? null;
            $valid = (is_int($value) || is_string($value)) && preg_match('/^\d{1,6}$/D', (string)$value);
            $weight = strpos($key, 'weight_') === 0;
            $min = $weight ? 0 : ($key === 'maximum' ? 2 : 1);
            $max = $weight ? 100 : 100000;
            if (!$valid || (int)$value < $min || (int)$value > $max) {
                $errors[$key] = $weight ? 'score_error_weight' : ($key === 'maximum' ? 'score_error_maximum' : 'score_error_threshold');
            }
        }
        if (!isset($errors['moderate']) && !isset($errors['high']) && !isset($errors['maximum'])) {
            if ((int)$input['moderate'] >= (int)$input['high'] || (int)$input['high'] > (int)$input['maximum']) {
                $errors['high'] = 'score_error_order';
            }
        }
        return $errors;
    }

    public static function settings(): array {
        $stored = get_config('local_mulima_analytics', 'teacher_scoring');
        $values = $stored ? json_decode($stored, true) : null;
        return is_array($values) && !self::errors($values)
            ? array_map('intval', array_intersect_key($values, self::defaults())) : self::defaults();
    }

    /** Save one validated setting, avoiding a partially saved set of weights. */
    public static function save(array $values): void {
        if (self::errors($values)) { throw new \invalid_parameter_exception('Invalid teacher scoring settings'); }
        $values = array_map('intval', array_intersect_key($values, self::defaults()));
        set_config('teacher_scoring', json_encode($values), 'local_mulima_analytics');
    }

    public static function calculate(array $counts, array $rules): array {
        $raw = 0;
        $actions = 0;
        $components = [];
        foreach (self::criteria() as $criterion=>$setting) {
            $count = max(0, (int)($counts[$criterion] ?? 0));
            $weight = (int)$rules[$setting];
            $points = $count * $weight;
            $components[$criterion] = ['count'=>$count, 'weight'=>$weight, 'points'=>$points];
            $raw += $points;
            $actions += $count;
        }
        $score = min($raw, $rules['maximum']);
        $level = !$score ? 'none' : ($score >= $rules['high'] ? 'high' : ($score >= $rules['moderate'] ? 'moderate' : 'low'));
        return ['score'=>$score, 'score_raw'=>$raw, 'score_max'=>$rules['maximum'],
            'score_level'=>$level, 'score_components'=>$components, 'has_activity'=>$actions > 0];
    }
}
