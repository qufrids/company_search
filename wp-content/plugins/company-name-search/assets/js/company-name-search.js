(function () {
    'use strict';

    const data = window.CompanyNameSearch || null;

    if (!data) {
        return;
    }

    const escapeHTML = (value) => {
        if (value === null || value === undefined) {
            return '';
        }

        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    const createMatchList = (matches, strings) => {
        if (!Array.isArray(matches) || matches.length === 0) {
            return '<p class="company-name-search__no-results">' + escapeHTML(strings.noMatches) + '</p>';
        }

        let list = '<h4 class="company-name-search__matches-title">' + escapeHTML(strings.similarTitle) + '</h4>';
        list += '<ul class="company-name-search__matches">';

        matches.forEach((match) => {
            if (!match || !match.name) {
                return;
            }

            const name = escapeHTML(match.name);
            let details = '';

            if (match.company_number) {
                details += '<span class="company-name-search__match-number">' + escapeHTML(match.company_number) + '</span>';
            }

            if (match.status) {
                if (details) {
                    details += ' · ';
                }

                details += '<span class="company-name-search__match-status">' + escapeHTML(match.status) + '</span>';
            }

            if (match.jurisdiction_code) {
                if (details) {
                    details += ' · ';
                }

                details += '<span class="company-name-search__match-jurisdiction">' + escapeHTML(String(match.jurisdiction_code).toUpperCase()) + '</span>';
            }

            if (match.registry_url) {
                const registryUrl = escapeHTML(match.registry_url);
                if (registryUrl) {
                    details += ' · <a class="company-name-search__match-link" target="_blank" rel="noopener" href="' + registryUrl + '">' + escapeHTML(strings.registryLink || 'Registry') + '</a>';
                }
            }

            let item = '<li class="company-name-search__match"><span class="company-name-search__match-name">' + name + '</span>';

            if (details) {
                item += '<span class="company-name-search__match-details">' + details + '</span>';
            }

            if (match.source) {
                item += '<span class="company-name-search__match-source">' + escapeHTML(match.source) + '</span>';
            }

            item += '</li>';
            list += item;
        });

        list += '</ul>';

        return list;
    };

    const setLoadingState = (container, isLoading, strings) => {
        const spinner = container.querySelector('.company-name-search__spinner');
        const results = container.querySelector('.company-name-search__results');

        if (isLoading) {
            if (spinner) {
                spinner.removeAttribute('hidden');
                spinner.setAttribute('aria-hidden', 'false');
            }
            if (results) {
                results.innerHTML = '<p class="company-name-search__status">' + escapeHTML(strings.loading) + '</p>';
            }
        } else if (spinner) {
            spinner.setAttribute('hidden', 'hidden');
            spinner.setAttribute('aria-hidden', 'true');
        }
    };

    const renderResult = (container, response, strings) => {
        const results = container.querySelector('.company-name-search__results');

        if (!results) {
            return;
        }

        if (!response || typeof response.available === 'undefined') {
            results.innerHTML = '<p class="company-name-search__status company-name-search__status--error">' + escapeHTML(strings.error) + '</p>';
            return;
        }

        const available = Boolean(response.available);
        const statusClass = available ? 'company-name-search__status--available' : 'company-name-search__status--unavailable';
        const message = available ? strings.available : strings.unavailable;

        let html = '<p class="company-name-search__status ' + statusClass + '">' + escapeHTML(message) + '</p>';

        if (response.matches && response.matches.length) {
            html += createMatchList(response.matches, strings);
        } else if (!available) {
            html += '<p class="company-name-search__no-results">' + escapeHTML(strings.noMatches) + '</p>';
        }

        if (response.message) {
            html += '<p class="company-name-search__message">' + escapeHTML(response.message) + '</p>';
        }

        results.innerHTML = html;
    };

    const renderError = (container, error, strings) => {
        const results = container.querySelector('.company-name-search__results');

        if (results) {
            const message = (error && error.message) ? error.message : strings.error;
            results.innerHTML = '<p class="company-name-search__status company-name-search__status--error">' + escapeHTML(message) + '</p>';
        }
    };

    const handleSubmit = (event) => {
        event.preventDefault();

        const form = event.currentTarget;
        const container = form.closest('[data-company-name-search]');

        if (!container) {
            return;
        }

        const input = container.querySelector('.company-name-search__input');
        const strings = data.strings;

        if (!input) {
            return;
        }

        const value = input.value.trim();

        if (!value) {
            renderError(container, { message: strings.emptyQuery }, strings);
            return;
        }

        setLoadingState(container, true, strings);

        const url = data.restUrl + (data.restUrl.includes('?') ? '&' : '?') + 'name=' + encodeURIComponent(value);

        fetch(url, {
            method: 'GET',
            headers: {
                'X-WP-Nonce': data.nonce,
                'Accept': 'application/json'
            }
        })
            .then((response) => {
                if (!response.ok) {
                    return response.json().catch(() => ({})).then((payload) => {
                        const message = payload && payload.message ? payload.message : strings.error;
                        throw new Error(message);
                    });
                }
                return response.json();
            })
            .then((payload) => {
                setLoadingState(container, false, strings);
                renderResult(container, payload, strings);
            })
            .catch((error) => {
                setLoadingState(container, false, strings);
                renderError(container, error, strings);
            });
    };

    const init = () => {
        const containers = document.querySelectorAll('[data-company-name-search]');

        containers.forEach((container) => {
            const form = container.querySelector('.company-name-search__form');
            if (form) {
                form.addEventListener('submit', handleSubmit);
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
