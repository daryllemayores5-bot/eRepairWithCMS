# Technician finder

> Current scope: PWA installation was removed at the user's request. eRepair is a regular online web application. Older PWA results below are historical evidence, not current features or deployment requirements. See [web delivery](web-delivery.md).


This is the primary product feature. It includes the Laravel query service, paginated public resources, Vue directory filters, comparison page, detailed profiles and Leaflet/OpenStreetMap view. The frontend uses real APIs; there are no runtime technician fixtures. SQL filtering/distance ordering pass PostgreSQL tests. The live Vue DOM/API journey verified directory filtering, profile selection and a repair request through completion/review. Compiler, component tests and production build pass. Physical browser layout, comparison interaction and map-tile rendering still require browser acceptance.

First restrict the query to approved technicians with active accounts. Apply selected device compatibility and specialization/problem filters. A location entered manually must work without browser geolocation. Match normalized city/municipality and province; model multiple service areas so a technician can cover neighboring municipalities.

`repair_category` accepts an active problem-category ID and resolves its device/specialization requirements before running the query. Conflicting explicit selections return a field-specific 422. The homepage and directory provide this selection; the repair wizard sends the actual chosen category and paginates matching professionals. City and province must match together on the primary location or within the same additional service-area record, preventing unrelated locations from being combined. Directory and profile matching inputs use the same Laravel Form Request. These backend behaviors pass PostgreSQL regression tests, and the connected React filtering/profile/request journey passes the real DOM/API test. The compiler and production build pass; physical browser and map acceptance remain pending.

The transparent score after eligibility filters is device fit 35, specialization fit 30, selected service area 20, availability 10, and average rating up to 5. A supplied criterion contributes only when it matches. Missing inputs contribute zero and are not described as matches. Resources include component scores/reasons. Because selected device, specialty and location are eligibility filters, their scores are constant within those results; recommended ordering prioritizes availability and rating, then stable profile ID. This is rule-based matching, not AI.

Search name, city, province and indexed category/specialization relationships. Support rating/availability/device filters, pagination and explicit sorting. Compare up to three public profiles using their real API resources. Profile availability is information, not an appointment guarantee; confirm actual booking conflicts separately.

The active Vue comparison page also lists supported devices and deduplicated
service locations for each technician. Each failed profile has its own retry
action, allowing recovery without reloading successful comparison profiles.
Opening comparison carries the directory filters and page in the URL. Returning
to search restores those filters and the selected technician IDs. Selection
metadata is excluded from directory API requests; selections are limited to
three unique positive safe-integer IDs.

Leaflet/OpenStreetMap shows rounded public service-location references. The old
illustrative 2 km circles have been removed because the database does not define
service radii. These markers are not verified shop entrances, residential addresses,
municipal boundaries, guaranteed coverage or road/travel distances. Their geographic
accuracy depends on the administrator checking the reference against the profile's
city/province. The demo uses approximate sample locations, not real businesses.

Approving a technician now automatically fills a missing public reference from
their city and province. The bundled catalog covers 313 Philippine locations
from [SimpleMaps](https://simplemaps.com/data/ph-cities) (MIT), with adapted
province labels for highly urbanized cities, the existing Cavinti reference and
a [GeoNames-based Pola reference](https://mapcarta.com/15731994) (CC BY 4.0).
Unlisted towns use a bounded, cached [Open-Meteo / GeoNames lookup](https://open-meteo.com/en/docs/geocoding-api).
Only city names are sent; country and province must match and ambiguous results
are refused. Results are rounded to two decimals. The free Open-Meteo endpoint
is for noncommercial use; configure `GEOCODING_URL` and `GEOCODING_API_KEY` for
a suitable commercial/self-hosted endpoint, or `GEOCODING_ENABLED=false` to use
only the local catalog and administrator reference points.

Existing valid manual points are preserved. Approval fails with a clear validation
message when no reference can be resolved, allowing the administrator to correct
the city/province or save a manual point before retrying. Approved technicians
changing city/province receive new coordinates atomically; unresolved edits return
422 and preserve the previous profile/location. Pending profile changes clear old
points and remain hidden. Map view refreshes the current filtered results every
30 seconds while visible; List view does not poll.

`php artisan erepair:technician-locations` fills missing points on existing approved,
active profiles using the local catalog only. It preserves existing points,
ignores unapproved profiles, logs updates and can safely be repeated. `--online`
also enables the configured lookup for unlisted towns. Unresolved locations are
counted for administrator review; the command never invents coordinates.

The native Statamic operations page also provides a **Public map location** form.
Only the CMS administrator can save a complete latitude/longitude pair within
geographic bounds. Inputs are rounded to two decimals before storage, changes are
activity-logged, and saving a point does not approve an unverified technician.
Use a town centre or public landmark, never a residential pin. Unknown locations
are left unplotted rather than guessed.

Markers at identical coordinates share one popup listing every corresponding
technician and profile link. Partial, nonfinite and out-of-range coordinates are
excluded; zero coordinates remain valid. Fit-all restores the viewport; automatic
bounds follow changed coordinates without resetting identical-data renders. Map
counts refer to the current paginated results, not the entire search. Missing
locations remain available in List view and receive an explicit count. Tile failures
show a connection notice and a retry action that redraws tiles while preserving
markers and the map view. A recreated map clears the previous tile error. If a
background result refresh fails, the last successful results stay visible with
an explicit stale-results warning and retry action. An initial search failure
uses the regular error state and does not claim to be searching.

List/map controls preserve search filters. The optional geolocation button rounds
coordinates to two decimals; permission failure leaves manual search usable.
Pending requests display progress and prevent duplicate clicks. Resetting or
changing the search, or leaving the finder, invalidates older callbacks so they
cannot overwrite newer filters. Removing approximate location retains search
criteria/comparison selections, resets pagination and replaces distance sorting
with recommended sorting; other sort choices remain unchanged.
PostgreSQL nearest sorting uses the same rounded valid points as the public map,
with unmapped profiles last and stable ID ordering for ties. It does not calculate
driving routes or infer a service radius.

The tile URL and visible attribution follow the official [OpenStreetMap tile
policy](https://operations.osmfoundation.org/policies/tiles/). Tiles are not prefetched
or cached for offline use. The viewport uses Leaflet through the Vue
`useTechnicianMap` composable. Automated map/coordinate,
authorization, sorting and navigation tests cover these behaviors; physical browser
tile rendering, accessibility and geographic checks still require live QA.
