/*!
 * This file is part of Moodle - https://moodle.org/
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle. If not, see <https://www.gnu.org/licenses/>.
 *
 * @package local_mulima_analytics
 * @copyright 2026 Joaquim Pascoal Mulima Junior
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// This file is part of Moodle - http://moodle.org/

/** Shared browser utilities for the DIE reports. */
define([], function() {
    const colours = {ok: '#16a34a', err: '#dc2626', info: '#1e293b'};
    let strings = {};

    const toast = (message, type = 'info') => {
        const wrap = document.getElementById('die-toast-wrap');
        if (!wrap) {
            return;
        }
        const toastElement = document.createElement('div');
        const text = document.createElement('span');
        const close = document.createElement('button');

        toastElement.className = 'die-toast';
        toastElement.style.cssText = 'display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:12px;' +
            'color:#fff;font-size:12px;font-weight:500;box-shadow:0 8px 24px rgba(0,0,0,.25);' +
            'pointer-events:auto;max-width:340px;background:' + (colours[type] || colours.info);
        text.style.flex = '1';
        text.textContent = String(message || '');
        close.type = 'button';
        close.setAttribute('aria-label', strings.close);
        close.textContent = '×';
        close.style.cssText = 'background:none;border:0;color:#fff;opacity:.7;cursor:pointer;font-size:16px;padding:0';
        close.addEventListener('click', () => toastElement.remove());
        toastElement.append(text, close);
        wrap.appendChild(toastElement);
        window.setTimeout(() => toastElement.remove(), 4000);
    };

    const fetchJson = async(url, options = {}) => {
        const response = await fetch(url, Object.assign({credentials: 'same-origin'}, options));
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            throw new Error('HTTP ' + response.status + ': ' + strings.invalid_response);
        }
        const data = await response.json();
        if (!response.ok || data.ok === false) {
            throw new Error(data.error || ('HTTP ' + response.status));
        }
        return data;
    };

    return {
        init: function(localisedStrings) {
            strings = localisedStrings;
            window.dieToast = toast;
            window.dieFetchJSON = fetchJson;
        }
    };
});
