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
 * Product attribution and free-edition notice.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_mulima_analytics\local;

defined('MOODLE_INTERNAL') || die();

/** Renders the attribution used by the free Learning Analytics edition. */
final class branding {
    /** @var string Author's public telephone number. */
    private const PHONE = '+258 84 200 8122';

    /** @var string Author's public email address. */
    private const EMAIL = 'joaquimmulima.ab@gmail.com';

    /** @var bool The distributed edition carries the attribution watermark. */
    private const BRANDED_EDITION = true;

    /**
     * Whether the current package is the branded/free edition.
     *
     * This package includes the attribution. There is no public Moodle setting
     * to switch it off, and no licence server or cryptographic build-signing
     * system is implemented by this class.
     *
     * @return bool
     */
    public static function is_branded(): bool {
        return self::BRANDED_EDITION;
    }

    /**
     * Render the product footer and its lightweight screen watermark.
     *
     * The method is deliberately self-contained so the footer also works on
     * the standalone configuration and detail pages, which do not include the
     * shared report stylesheet.
     *
     * @param string $variant Optional page variant for future licensed builds.
     */
    public static function render_footer(string $variant = 'report'): void {
        if (!self::is_branded()) {
            return;
        }

        $year = (int)date('Y');
        $footer = get_string('branding_footer', 'local_mulima_analytics', (object)['year' => $year]);
        $free = get_string('branding_free_edition', 'local_mulima_analytics');
        $contact = get_string('branding_contact', 'local_mulima_analytics');
        $watermark = get_string('branding_watermark', 'local_mulima_analytics');
        $email = self::EMAIL;
        $phone = self::PHONE;
        $mailto = 'mailto:' . $email . '?subject=' . rawurlencode(get_string('branding_email_subject', 'local_mulima_analytics'));

        echo '<style id="learning-analytics-branding-style">'
            . '.la-branding-footer{margin:28px 0 8px;padding:14px 16px;text-align:center;'
            . 'border-top:1px solid #e2e8f0;color:#64748b;font:11px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}'
            . '.la-branding-footer__copy{display:block;color:#475569;font-weight:600;}'
            . '.la-branding-footer__free{display:block;margin-top:2px;color:#94a3b8;font-size:10px;}'
            . '.la-branding-footer__contact{display:inline-flex;flex-wrap:wrap;justify-content:center;gap:4px 10px;margin-top:4px;}'
            . '.la-branding-footer a{color:#2563eb;text-decoration:none;}'
            . '.la-branding-footer a:hover{text-decoration:underline;}'
            . '.la-branding-watermark{position:fixed;right:14px;bottom:12px;z-index:40;pointer-events:none;'
            . 'padding:5px 9px;border:1px solid rgba(100,116,139,.18);border-radius:999px;'
            . 'background:rgba(248,250,252,.84);color:rgba(100,116,139,.55);font:700 9px/1.2 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;'
            . 'letter-spacing:.04em;text-transform:uppercase;box-shadow:0 1px 5px rgba(15,23,42,.05);}'
            . '@media(max-width:640px){.la-branding-footer{margin-top:20px;padding:12px 10px;}.la-branding-watermark{right:8px;bottom:8px;font-size:8px;}}'
            . '</style>';
        echo '<footer class="la-branding-footer" data-branding-variant="' . htmlspecialchars($variant, ENT_QUOTES, 'UTF-8') . '" role="contentinfo">';
        echo '<span class="la-branding-footer__copy">' . htmlspecialchars($footer, ENT_QUOTES, 'UTF-8') . '</span>';
        echo '<span class="la-branding-footer__free">' . htmlspecialchars($free, ENT_QUOTES, 'UTF-8') . '</span>';
        echo '<span class="la-branding-footer__contact">';
        echo '<span>' . htmlspecialchars($contact, ENT_QUOTES, 'UTF-8') . '</span>';
        echo '<a href="' . htmlspecialchars($mailto, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</a>';
        echo '<a href="https://wa.me/' . preg_replace('/\D/', '', $phone) . '" target="_blank" rel="noopener noreferrer">WhatsApp: ' . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . '</a>';
        echo '</span></footer>';
        echo '<div class="la-branding-watermark" aria-hidden="true">' . htmlspecialchars($watermark, ENT_QUOTES, 'UTF-8') . '</div>';
    }
}
