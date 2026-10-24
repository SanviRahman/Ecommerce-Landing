# Meeting PDF Source, Provenance and Coverage

**Status:** Source-integrated specification, not a verified audio/video transcript. Last integration: 2026-10-24. This file is the evidence policy for all meeting-derived requirements.

## Source identification

- User-uploaded source: `প্রজেক্ট ফাইল অপেক্ষার বার্তা.pdf`, 32 pages. Verbatim bytes preserved inside this package as [`sources/client-meeting-provided.pdf`](../sources/client-meeting-provided.pdf).
- SHA-256: `d974c4f318c8bda0383d9756538a51c28243c11271180ed70706e163b6ba2611`.
- Pages 1-5: user prompt instructing an external assistant to analyze a meeting, plus preliminary dialogue.
- Pages 5-20: a **previous assistant's claimed transcript** of an approximately 00:00:01-00:14:07 call. It is printed in the PDF, but the original recording is NOT attached in this conversation and speaker identification/timing are not independently checked.
- Pages 20-23: previous assistant's screen-sharing analysis and observations.
- Pages 23-30: previous assistant's feature inventory **F-001 to F-011**. These are the 11 features actually included in the PDF.
- Pages 30-32: a feedback table **CF-01 to CF-06**; the PDF ends mid-table on page 32. The requested later phases (full issue/decision/task/SRS/QA reports) are not supplied as completed output. Do not invent the missing Part D-L text.
- The PDF mentions proyojonerbazar.com as the site demonstrated in the discussion. The examined user-uploaded ZIP is `website(1).zip`. It is **not independently verified** that the live proyojonerbazar.com deployment and this ZIP are the same revision/codebase.

## Evidence hierarchy

1. **U (direct user instruction):** user's explicit messages in this project/chat; e.g., drag-and-drop all homepage sections, independent section/button colors, pieces available in cart and product details, floating + exposing WhatsApp/Messenger/Email, preserve campaign features.
2. **C (inspected source):** `website(1).zip`, source files and migrations, `SOURCE-EVIDENCE.md`; establishes what is implemented in the uploaded archive, not what is live.
3. **M (meeting-PDF-reported):** the previous AI report/transcript inside the 32-page PDF. Usable as meeting-document requirements and timestamped leads, but not separately verified original audio.
4. **S (user screenshot):** direct visual evidence of controls and layouts, not proof of backend behavior.
5. **W (public website reference):** HT Bazar/Leatherket site and screenshots; reference can change since the meeting, not independently an approved requirement.
6. **P (proposed implementation):** engineering defaults/architecture, never described as a client decision without separate approval.

When U and M supplement each other, take the union when feasible (e.g., user specifies WhatsApp/Messenger/Email; M includes WhatsApp/Call) and flag any extra as **proposed pending approval**. When they conflict, create a decision gate rather than quietly choosing.

## Primary meeting timestamps and feature pages

| Meeting ID | PDF-reported time | PDF pages | Topic |
|---|---|---:|---|
| F-001 | 00:01:34-00:02:08 | 7-8, 21, 23-24 | All Categories nested subcategories |
| F-002 | 00:02:19-00:02:47; 00:03:58-00:04:21; 00:05:11-00:05:18 | 8-11, 21, 24 | Dynamic category sections and ordering |
| F-003 | 00:02:40-00:03:10; 00:03:46-00:03:57 | 8-10, 21-22, 25 | Four desktop/two mobile columns, larger cards |
| F-004 | 00:05:53-00:06:28 | 12-13, 22, 25-26 | 25% Add to Cart + 75% Buy/Order Now |
| F-005 | 00:05:20-00:06:28; 00:09:14-00:09:39; 00:12:35-00:13:45 | 11-13, 15, 18-19, 22-23, 26-27 | Two-part product details, borderless image, description below buttons |
| F-006 | 00:06:31-00:07:30 | 13-14, 23, 27 | Color, size, KG/piece variant pricing |
| F-007 | 00:09:08-00:09:13; 00:09:40-00:10:53 | 15-16, 23, 27-28 | Scroll-triggered sticky Order Now |
| F-008 | 00:07:31-00:08:20 | 14, 23, 28 | Desktop sticky summary; mobile order button immediately after form |
| F-009 | 00:08:18-00:08:36 | 14, 23, 28-29 | Mobile sticky header and bottom navigation |
| F-010 | 00:11:43-00:12:14 | 17-18, 23, 29 | Floating WhatsApp/Call and independent mini-cart drawer |
| F-011 | 00:11:03-00:11:42; 00:12:27-00:12:35 | 17-18, 23, 30 | Green default theme and dynamic admin color controls |
| CF-01 to CF-06 | various | 30-32 | **Only six** entries of incomplete feedback table |

**Important:** cited time ranges come from the previous AI-produced document; we do not certify that audio at that exact timestamp was independently checked. Avoid claims such as "we listened to the recording".

## What the PDF does not establish

- Exact logo asset, approved brand color hex code, or finalized live frontend identity.
- Whether category submenu opens on hover or click on each device (click is favored in the dialogue).
- Whether a variable product card may buy directly without an explicit variant choice.
- Whether two-kilogram price is an explicit total (e.g. 1800) or an automatically summed multiple; the transcript gives **both illustrative price and inconsistent follow-up wording**. Treat per-variant total price as the safe modeled option; require a business decision before implementing automatic weight pricing.
- Whether multi-piece packs consume underlying single pieces or independently counted sellable packs.
- Exact admin drag-and-drop behavior; user explicitly requested it outside the meeting PDF.
- Exact floating phone/call channel approval when the direct user requested WhatsApp/Messenger/Email.
- Customer registration, account/wishlist, shipping SLA, payment gateway, real inventory starting balances, employment authorization, or production approval.

## Coverage / ethical limitation

The original 14:07 recording was not supplied. The full transcript is **not independently verified**. The PDF's available text is preserved unmodified as an appendix; a line of transcript shall never be introduced as a fresh first-hand observation. Its ending after CF-06 is a document-level truncation. Extend the specification by combining supplied evidence and explicitly marked proposals, not by fabricating unprovided meeting phases.
