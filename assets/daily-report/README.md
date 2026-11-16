# Daily Report backgrounds

Backgrounds are derived from the user-provided reference `2026-11-11 [MSSD] Evaluasi PES Tahap 1 BU.pdf` with authorization to reuse its design.

- `cover.jpg`: reference page 1, variable text removed; BPS logo replaced with the project's `logo-bps-kaltim.png`.
- `appreciation.jpg`: reference page 2, text removed so the requested appreciation wording can be rendered.
- `chart.jpg`: decorative ribbons and logos from reference page 3, with chart/text removed.
- `discussion.jpg`: reference page 8.
- `closing.jpg`: reference page 9; the generated report adjusts the page number to 12.

Each image is 1920 x 1080. Charts and changing text are drawn as vectors in the PDF by `daily_report_pdf.php`. Reference page 7 is intentionally not included. Cover presenter text is replaced with Tim PES SE2026 / BPS Provinsi Kalimantan Timur. The appreciation panel overlays the national illustration with a vector progress map from the same local GeoJSON and date-based color thresholds as the dashboard; nonsample areas remain gray. Province scope shows 8 districts and 160 SubSLS. Map progress includes Submit + Reject + Pending + Approve, matching the dashboard; page 6 separately uses Submit + Approve as requested. Pie slices carry direct percentage labels.
