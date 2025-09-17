# Company Name Availability Search

A WordPress plugin that lets site visitors check whether a company name appears to be available. The plugin offers a shortcode-powered search form, a REST API endpoint, and a configurable data source.

## Features

- **Frontend search form** using the `[company_name_search]` shortcode.
- **AJAX-powered experience** that leverages the WordPress REST API.
- **Configurable data sources**: a bundled sample dataset or live results from the OpenCorporates API.
- **Caching support** to avoid repeating the same remote lookups.
- **Settings page** under *Settings → Company Name Search* for managing configuration.

## Installation

1. Upload the `company-name-search` folder to your site's `wp-content/plugins/` directory.
2. Activate the plugin through the WordPress admin dashboard.
3. Navigate to *Settings → Company Name Search* to adjust the data source and optional OpenCorporates options.
4. Add the `[company_name_search]` shortcode to any post, page, or block editor shortcode block.

## Shortcode Options

| Attribute | Description | Default |
|-----------|-------------|---------|
| `title`   | Heading displayed above the search form. | `Check company name availability` |

Example usage:

```
[company_name_search title="Find a company name"]
```

## Data Sources

### Bundled Sample Data

The plugin ships with a JSON file containing a handful of fictional company names. This mode is ideal for offline demos and development environments without internet access.

### OpenCorporates API

When the OpenCorporates data source is selected, the plugin sends a search request to `https://api.opencorporates.com/v0.4/companies/search` and displays the returned matches. Optionally provide an API token and a country code to refine the results.

> **Note:** Network access is required for OpenCorporates lookups. If the remote request fails, users will see an error message.

## Development

- JavaScript assets are located in `assets/js/` and styles in `assets/css/`.
- The REST endpoint is registered at `/wp-json/company-name-search/v1/check`.
- Local search results can be filtered with the `company_name_search_local_companies` hook.
- Final responses can be modified with the `company_name_search_result` filter.

## License

GPL-2.0-or-later
