# Meeting Schedule PDF Generator

This service accepts a [Meeting Guide-formatted JSON feed](https://github.com/code4recovery/spec) and returns inside pages for a PDF meeting schedule. 

## Assemble a PDF

This service provides the inside pages of a meeting book. To create the outer pages and merge it all into a single PDF:

1. Decide on a paper size. The default is 4.25 x 11, so that it can be printed on standard US Letter and stapled down the middle.
1. Create a Google or Word doc at that paper size. [Here is an example "before" Google doc](https://docs.google.com/document/d/1bmDg2j8cyalcqnw5GV1JJll7g8Av7uW6O6o4kVADwEc/edit?usp=sharing) you can copy. (Note: Google Docs doesn't support custom paper sizes, but the [Page Sizer app](https://workspace.google.com/marketplace/app/page_sizer/595382898724) will enable that functionality).
1. Download it as a PDF, taking note of how many pages it is.
1. Now generate your inside pages at (pdf.code4recovery.org)[https://pdf.code4recovery.org]. Set the paper size and starting page number according to the results of the steps above.
1. Open the downloaded PDF document locally. I used Preview (on Mac) for this. Then you can drag your "Meeting Directory Before" PDF to the start of this document, in the thumbnails area on the left side. (Note: I found that it works better if I add my Google Doc *to* my generated PDF, and not vice-versa).
1. If you don't want to add content after the meetings, you're done! If you do then [here is an example "after" doc](https://docs.google.com/document/d/1whm-ZL1JbZFinSRnbt4uKvFM6Hhv8e246TYtadsnVZQ/edit?usp=sharing) you can copy.
1. Set the page numbers to start where they need to and save the PDF locally.
1. Now drag it to the bottom of your thumbnails in Preview and hit save. Now you have a complete meeting schedule PDF.

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
