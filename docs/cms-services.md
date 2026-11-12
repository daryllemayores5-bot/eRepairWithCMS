# Native CMS repair services

Statamic Core now includes a **Repair Services** collection. Open the existing
administrator Control Panel, choose **Content > Collections > Repair Services**,
or use **Repair services** on the eRepair Operations page.

The native entry editor manages title, short description, plain-text details,
display order and publication status. Lower display-order numbers appear first.
Published entries are projected through the existing `/api/v1/content` endpoint
and displayed in the homepage repair-services section. Details expand in each
card, and the call to action opens the existing technician finder. Prices and
availability remain matters for the selected technician and repair estimate.

Three initial service guides are provided. Existing pages, FAQs, announcements,
homepage globals, matching catalogs, authentication and operational data remain
in place. Existing catalogs still control device/problem filters and matching;
service guides are editorial descriptions, not technician capability records.
No database migration, new dependency or paid Statamic feature is required.

For a defense demonstration:

1. Open `/cp/erepair` and choose **Repair services**.
2. Create an entry as unpublished, including all required fields.
3. Confirm it does not appear on the homepage.
4. Publish it, then reload the homepage to see its title, summary and details.
5. Choose **Find a technician** and demonstrate the existing search filters.
6. Unpublish the entry and reload the homepage to confirm its removal.

The shared content query has a one-minute stale time, so reload the page after
publication when presenting. Text is rendered without interpreting HTML. CMS
access uses the existing independent, single-editor administrator guard.

Relevant verification: `CmsServicesIntegrationTest` exercises native creation,
editing, publication, unpublication, validation, ordering and access boundaries.
`ServiceHighlights.test.ts` covers text escaping, finder links and empty states.
Physical browser/mobile presentation remains a manual acceptance check.
