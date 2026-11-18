/*
 * Research data: one entry per company, each with every published note (newest is shown on top).
 * Used by index.html (company list) and research/company.html (one company's page).
 *
 * To publish a new note on a company: add an object to its `notes` array. The company page and the
 * home page pick the newest note by `date` automatically. Paths in `url` are relative to /research/.
 * Every figure here must match the note it comes from.
 */
window.RESEARCH_COMPANIES = [
    {
        id: 'easyjet',
        firm: 'easyJet plc',
        ticker: 'EZJ',
        exchange: 'LSE',
        sector: 'Airlines · Travel & Leisure',
        logoUrl: 'https://upload.wikimedia.org/wikipedia/commons/1/1b/EasyJet_logo.svg',
        about: 'UK-based low-cost airline flying about 104 million seats a year across Europe (FY25), plus the '
             + 'easyJet holidays package business. Financial year ends 30 September. Subject to Apollo\'s 715p '
             + 'cash offer, approved by shareholders on 11 November 2026.',
        status: 'Update in progress. A dated update will follow easyJet\'s FY26 results (expected around '
              + '1 December 2026). The figures below are from the 16 September 2026 note.',
        notes: [
            {
                id: 'easyjet-2026-09-16',
                date: '2026-09-16',
                dateLabel: '16 Sep 2026',
                type: 'Initiation',
                title: 'easyJet plc: Apollo\'s 715p Offer',
                url: 'easyjet.html',
                valuation: 'undervalued',
                verdict: 'Offer looks light',
                value: '752p',
                valueLabel: 'Base case value',
                price: '666p',
                priceLabel: 'Share price, 15 Sep 2026',
                reference: '715p',
                referenceLabel: 'Apollo cash offer',
                upside: '+12.9%',
                method: 'Bottom-up DCF · EUROCONTROL capacity scenarios',
                summary: 'Apollo\'s 715p cash offer looks light. Built bottom up from seats flown, load factor and '
                       + 'revenue per seat, with capacity inherited from EUROCONTROL\'s published scenarios. Base '
                       + 'case 752p. Verdict: do not accept.',
                thesis: [
                    'Margin recovery unfinished: FY25 profit per seat of £6.39 vs management\'s own £7–£10 target, reached only in FY28 on our base case',
                    '£602m net cash in a sector that rarely has any, so c.80p per share of the offer is paid with easyJet\'s own money',
                    'easyJet holidays compounds off an existing customer base: higher margin, asset-light, already-acquired passengers',
                    'Working capital is a structural tailwind: unearned revenue rose £209m in FY25, and the earlier model assumed zero'
                ],
                risks: [
                    'The unit wedge is only £3.99 a seat, so 50bp of annual cost drift takes profit per seat to £1.74 by FY31 with no recession required',
                    'Fuel modelled as a cost per seat at a fixed growth rate, not off the forward curve and disclosed hedge book',
                    'Terminal value is 63.9% of enterprise value',
                    'Not yet three-statement: the balance sheet is not rolled forward and cash ties are not proven'
                ]
            }
        ]
    },
    {
        id: 'airbnb',
        firm: 'Airbnb, Inc.',
        ticker: 'ABNB',
        exchange: 'NASDAQ',
        sector: 'Travel & Leisure',
        logoUrl: 'https://upload.wikimedia.org/wikipedia/commons/6/69/Airbnb_Logo_B%C3%A9lo.svg',
        about: 'Online marketplace for short-term stays and experiences. Listed on NASDAQ since its IPO in '
             + 'December 2020.',
        status: '',
        notes: [
            {
                id: 'airbnb-2020-12-10',
                date: '2020-12-10',
                dateLabel: '10 Dec 2020',
                type: 'Case study',
                title: 'Airbnb, Inc. IPO Valuation',
                url: 'airbnb-ipo.html',
                valuation: 'overvalued',
                verdict: 'Overvalued at the IPO price',
                value: '$40.28',
                valueLabel: 'Base case value',
                price: '$68.00',
                priceLabel: 'IPO price',
                reference: '',
                referenceLabel: '',
                upside: '-40.8%',
                method: 'DCF + Trading Comps',
                summary: 'At $68 the market was buying the narrative, not the business. A five year DCF anchored to '
                       + 'UNWTO recovery scenarios puts base case fair value at $40.28. Historical case study, '
                       + 'December 2020.',
                thesis: [
                    'Strong platform economics: ~75% gross margin, take rate rising from 11.4% to 14.1%',
                    'Domestic STR demand outpacing international travel recovery post-COVID',
                    'Brand moat maturing, with S&M spend declining from 43% to 22% of revenue',
                    'FCF-generative at scale: $505M FCF in FY2018'
                ],
                risks: [
                    'IPO priced at $68 implies ~$100B Day-1 EV, far above any DCF scenario',
                    'Adj. EBITDA negative in FY2019 and FY2020; profitability timeline uncertain',
                    'Revenue fell 32% YoY in 9M 2020, so COVID recovery speed is the key model assumption',
                    'Heavy post-IPO SBC and $2B emergency debt burden'
                ]
            }
        ]
    }
];

/* Newest note first. */
window.researchNotesNewestFirst = function (company) {
    return (company.notes || []).slice().sort(function (a, b) { return b.date.localeCompare(a.date); });
};
