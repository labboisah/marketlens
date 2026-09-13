# MarketLens client setup pack — CSV schema 1

Give this pack to the business owner before installation. Start with the empty
CSV templates at the ZIP root. The examples folder contains fictional records;
keep it separate from completed owner data. Each dataset has a column guide.

Read NEW-INSTALLATION.md for a new business or workstation, and
UPGRADE-CHECKLIST.md for an existing installation. The detailed CSV guide is
inside guides/CSV-DATA-TRANSFERS.md.

Download the current Business Server and Business App installers from the same
Platform website that supplied this pack, using its download menu. Those links
resolve the published stable Windows releases. This ZIP supplies templates and
instructions; the installer downloads supply the applications.

Import into Business Server > Admin > Data Setup & Transfers. This requires a
Server build that includes CSV setup and schema version 1; check for that screen
before preparing data. The manifest records the source build and template fields.
An old Server without this feature must be upgraded first. Operational records
are imported locally, not uploaded to the cloud Platform.

Suggested order:

1. Categories, brands, units and warehouses.
2. Products, conversions, barcodes and prices.
3. Customers and suppliers.
4. Approved opening stock, receivables and payables at an agreed cutover time.
5. Purchase header/line drafts, stock counts and optional service catalog.

Service templates require the service module. Staff, bank/cash configuration and
ownership imports are not supported by schema 1; use their existing setup screens.
For another workstation in the SAME business, pair it to the existing Server;
do not import opening data a second time. For a different business/branch, use
its own authorized Server and owner-approved data.

Batch limit: 30 files, 10,000 rows and 8 MiB combined. Use code references, text
identifiers, plain decimals and YYYY-MM-DD dates. Update the fictional sample
dates to your actual document/cutover dates. Validate, review errors and totals,
then approve. Keep completed owner files private; this downloadable pack contains
no business data, passwords or activation codes.
