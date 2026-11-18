# harshwalia.com

Source for [harshwalia.com](https://harshwalia.com). Equity research notes and the valuation
tools used to produce them.

Coverage is deliberately narrow: **transport, travel and logistics**, where the same drivers
recur: capacity, load, price per unit, fuel and operating leverage.

## Contents

| Path | |
|---|---|
| `index.html` | Home, research index, projects, writing |
| `research/` | Published company notes. `data.js` lists every company and note; `company.html?id=` is one company's page, newest note on top |
| `markets.html` | Market monitor: live TradingView chart, policy rates, event archive |
| `projects/` | Valuation tools: DCF, DDM, residual income, securitisation, retirement |
| `assets/` | Charts and data for published notes |

## Research notes

- **easyJet plc (EZJ.L)**. Bottom-up valuation against Apollo's 715p cash offer. Capacity
  inherited from EUROCONTROL's published traffic scenarios; beta re-estimated by OLS on a clean
  pre-offer window rather than taken off a screen.
- **Airbnb, Inc. (ABNB)**. Pre-IPO DCF and comparables, December 2020.

To publish a new note, add it to that company's `notes` in `research/data.js`. The home page card
and the company page pick the newest note by date.

Each note states its method, its sources and their dates, and what would prove it wrong. Where a
figure is not verified at source, the note says so.

## Stack

Static HTML, Tailwind via CDN, Chart.js, and a small amount of PHP for the market-data proxies.
No build step: the repository is the site.

## Local development

```bash
python -m http.server 8000
```

Then open <http://localhost:8000>. The PHP proxies under `projects/` only run on a PHP host.

## Configuration

`projects/yahoo-proxy.php` needs a [Financial Modeling Prep](https://financialmodelingprep.com)
API key. It is **not** stored in this repository. Provide it either as an `FMP_API_KEY`
environment variable, or by copying `projects/config.example.php` to `projects/config.local.php`
and pasting the key there. `config.local.php` is git-ignored.

## Licence

Research and written content © Harsh Walia. The valuation tools are free to read and learn from.

*The notes here are for education and portfolio demonstration. They are not investment advice.*
