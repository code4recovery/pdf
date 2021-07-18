# Meeting Schedule PDF Generator

This service accepts a [Meeting Guide-formatted JSON feed](https://github.com/code4recovery/spec) and returns inside pages for a PDF meeting schedule. It can be used via the form at [pdf.code4recovery.org](https://pdf.code4recovery.org), or directly by URL access.

## Parameters

Example URL: `https://pdf.code4recovery.org/?json=https%3A%2F%2Faasanjose.org%2Fwp-admin%2Fadmin-ajax.php%3Faction%3Dmeetings&width=4.25&height=11&numbering=1&font=sans-serif`

* `json` (required) encoded URL of a valid, publicly-accessible JSON feed
* `width` page width, in inches. default is `4.25`
* `height` page height, in inches. default is `11`
* `start` number to start page numbering at, default is `1`
* `font` is `serif` or `sans-serif`, default is `serif`

## Assemble a PDF

This is intended to provide the inside pages of a meeting book. To create the outer pages and merge it all into a single PDF:

1. Decide on a paper size. The default is 4.25 x 11, so that it can be printed on standard US Letter and stapled down the middle.
1. Create a Google or Word doc at that paper size. [Here is an example Google doc](https://docs.google.com/document/d/1bmDg2j8cyalcqnw5GV1JJll7g8Av7uW6O6o4kVADwEc/edit?usp=sharing) at that size that you can copy. (Note that Google Docs doesn't support custom paper sizes, but you can try the [Page Sizer app](https://workspace.google.com/marketplace/app/page_sizer/595382898724) for Google Docs).
1. Take note of how many pages your prepended document is. Now save it as a PDF (pro tip: just hit Command-P and it will save a PDF).
1. Now generate your PDF. This will be easy once it's published to the web. Alternately for now you could clone this repo and run it locally. Set the paper size, of course, and the starting page number should be the number of pages you noted earlier, plus one.
1. Open the downloaded PDF document locally. I used Preview (on Mac) for this. Then you can drag your "Meeting Directory Before" PDF to the start of this document, in the thumbnails area on the left side. (Note: I found that it works better if I add my Google Doc *to* my generated PDF, and not vice-versa).
1. If you don't want to add content after the meetings, you're done! If you do then [here is an example doc](https://docs.google.com/document/d/1whm-ZL1JbZFinSRnbt4uKvFM6Hhv8e246TYtadsnVZQ/edit?usp=sharing) you can copy.
1. Set the page numbers to start where they need to and save the PDF locally.
1. Now drag it to the bottom of your thumbnails in Preview and hit save.

## Booklet printing

Once nice way to use this is to print a meeting booklet for a central office. To get booklet printing to work properly, the first step is to assemble the booklet following the instructions above.

You will need a duplex printer and a program such as [Adobe Reader](https://get.adobe.com/reader/) (free) to print bookletized. Open the file in Reader, hit Print, and:

* Select "Booklet"
* Booklet subset should be "Both Sides"
* Binding should be "Left (Tall)"
* Then eliminate page margins by going to to Page Setup… -> Paper Size > Custom > 8.5 x 11 and set the margins to 0

This should print a stack of pages that you can fold and staple down the middle. Voila!

## Not all meetings are shown

This script skips any meetings that are:

* marked "Location Temporarily Closed"
* don't have a valid day and time
* don't have a street address

## Next steps

* [x] group by region / sub-region
* [x] home page form
* [x] publish
* [x] invalid URL / JSON error handling
* [x] stream mode
* [ ] google sheet support
* [ ] printing screencast video
* [ ] mode to show which meetings are skipped
* [ ] remember last form values in cookie