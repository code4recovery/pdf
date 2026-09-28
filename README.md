# Meeting Schedule PDF Generator

This service accepts a [Meeting Guide-formatted JSON feed](https://github.com/code4recovery/spec) or a Google Sheet and returns inside pages for a PDF meeting schedule.

This system is designed for weekly in-person recovery meetings. It does not display online-only, inactive, temporarily closed, or by-appointment meetings. Meetings without a specific street address (approximate locations) are also skipped.

## Generate your pages

Go to [pdf.code4recovery.org](https://pdf.code4recovery.org) and enter your Meeting Guide JSON feed URL or Google Sheets URL. If you're using a Google Sheet, its sharing settings must allow anyone with the link to view it, and its columns should follow the Meeting Guide format ([here is a template you can copy](https://docs.google.com/spreadsheets/d/12Ga8uwMG4WJ8pZ_SEU7vNETp_aQZ-2yNVsYDFqIwHyE/edit)).

Then you can adjust how your schedule looks:

-   **Width and height** set the paper size in inches. The default is 4.25 x 11.
-   **Print as booklet** arranges the finished file two pages per sheet in folding order, ready to print double-sided, fold, and staple (more on that below).
-   **Start #** sets the page number of the first page, which is useful when your schedule will follow cover pages (more on that below).
-   **Type** limits the schedule to one meeting type, for example Open or Women.
-   **Language** translates day names, times, and headings. English, Spanish, French, Japanese, Dutch, Portuguese, Slovak, Swedish, and Thai are supported.
-   **Font and font size** offer serif or sans serif type at sizes from 8 to 24.
-   **Group by** arranges meetings by Day → Region (the default), by Day only, or by Region → Day.
-   **Filter by Regions** lets you uncheck regions or sub-regions you don't want to include.
-   **Options** can add a meeting types legend, page breaks after each group, the full address on every meeting, and 24-hour time.
-   **Mode** either downloads the PDF or streams it in a new browser tab.
-   **Cover pages** let you attach a front PDF and a back PDF. The service merges them around the schedule and returns one file. Covers must be the same paper size you set above (within 1/8 inch), and at most 5 MB and 10 pages each.

## Assemble a PDF

This service provides the inside pages of a meeting book. To create the outer pages and merge it all into a single PDF:

1. Decide on a paper size. The default is 4.25 x 11, so that it can be printed on standard US Letter and stapled down the middle.
1. Create a Google or Word doc at that paper size. [Here is an example "before" Google doc](https://docs.google.com/document/d/1bmDg2j8cyalcqnw5GV1JJll7g8Av7uW6O6o4kVADwEc/edit?usp=sharing) you can copy. (Note: Google Docs doesn't support custom paper sizes, but the [Page Sizer app](https://workspace.google.com/marketplace/app/page_sizer/595382898724) will enable that functionality).
1. Download it as a PDF, taking note of how many pages it is.
1. Now generate your inside pages at [pdf.code4recovery.org](https://pdf.code4recovery.org). Set the paper size and starting page number according to the results of the steps above, attach your front PDF (and back PDF, if you have one) under **Cover pages**, and generate. The download is a single merged PDF. If you want content after the meetings, [here is an example "after" doc](https://docs.google.com/document/d/1whm-ZL1JbZFinSRnbt4uKvFM6Hhv8e246TYtadsnVZQ/edit?usp=sharing) you can copy.
1. If you would rather assemble by hand, open the downloaded inside pages in a PDF editor such as Preview on Mac and drag your cover PDFs before and after them in the thumbnail sidebar.

## Booklet printing

One nice way to use this is to print a meeting booklet for a central office. You will need a duplex printer.

1. Set the page size of one booklet page (the default 4.25 x 11 folds from US Letter), attach any cover PDFs, and check **Print as booklet**. The form shows the sheet size to print on (twice the page width by the same height) and which duplex setting to use.
1. Generate. The file comes back with two pages on each sheet, in the order that makes them read correctly once folded. Blank pages are added where needed to reach a multiple of four, placed just before your back PDF so it stays on the outside back.
1. Print the file double-sided at 100% (actual size), choosing "flip on long edge" or "flip on short edge" as the form says.
1. Fold the stack in half and staple along the fold. After a download the page tells you how many sheets the booklet is; check that your stapler can handle that thickness.

If a print shop is producing your booklet, send them the normal (not booklet) file instead: they arrange pages on their own equipment.

To arrange pages by hand instead, assemble the normal file as described above and print it from a program such as [Adobe Reader](https://get.adobe.com/reader/) (free). Open the file in Reader, hit Print, and:

-   Select "Booklet"
-   Booklet subset should be "Both Sides"
-   Binding should be "Left (Tall)"
-   Then eliminate page margins by going to Page Setup… > Paper Size > Custom > 8.5 x 11 and set the margins to 0

## Usage analytics

This service records anonymous usage data so we can see how the tool is being used.

**What is recorded:** the type of request (form opened or PDF generated) and its outcome, a fingerprint and host for the feed used (not the feed URL itself), the source type (JSON, Google Sheet, or TSML), the host of the site that linked here (if any), the number of meetings and regions in the schedule, how long the PDF took to render and how much memory it used, and the form settings you chose (paper size, language, grouping, etc.).

**What is not recorded:** feed URLs or Google Sheet IDs, IP addresses, the meeting data itself, or any cookies or identifying information about who is visiting.

## Next steps

-   [ ] printing screencast video
-   [ ] mode to show which meetings are skipped
