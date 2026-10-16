<?php
/**
 * Rules for ArticleTextCleaner: lines that are not part of an article and are removed from the full text.
 *
 * - Every rule is a regular expression that is tested on one line of text. A line matches when the whole line
 *   is the unwanted text (use ^ and $ where needed). Case does not matter. Spaces are normalised before the test.
 * - 'general' rules are used for every website. 'sites' rules only for the website of the article. The key is the
 *   domain without www. A subdomain of that domain also matches.
 * - Be careful: a rule must never match a real sentence of an article. Subheadings of the article itself
 *   ("Traumahelikopter", "Weg afgesloten") are part of the article. Do not remove them.
 * - To find new lines: look for lines that occur in many articles of the same website.
 */

return [
  'general' => [
    // Request for tips and WhatsApp messages
    '/^[^\p{L}\p{N}]*whatsapp ons\b.*$/iu',
    '/^heb (je|jij|u)\b.{0,60}\b(tips?|nieuwstip|foutje gespot)\b.*\b(redactie|whatsapp|mail|stuur|bericht|foto)\b.*$/iu',
    '/^heb (je|jij|u) een (nieuws)?tip( of (opmerking|nieuwe informatie))?\??$/iu',
    '/^[^\p{L}\p{N}]*(whatsapp|app) (ons|de redactie)\b.{0,100}$/iu',
    '/^omrop fryslân is ook via whatsapp te volgen\b.*$/iu',

    // Newsletter and liveblog advertisements
    '/^(wil|wilt) (je|u)\b.{0,80}\bnieuwsbrief\b.*$/iu',
    '/^.{0,60}\bmeld je (dan )?aan voor onze nieuwsbrief\b.*$/iu',
    '/^meer 112-nieuws (brengen we|lees je|vind je)\b.*$/iu',

    // Links to other articles
    '/^(lees ook|lees meer|zie ook|ook interessant|gerelateerd)\b\s*:.*$/iu',

    // Bylines and photo credits. A real sentence does not look like this: short, no full stop at the end.
    '/^(?i:door|tekst)\s*:?\s+(\p{Lu}[\p{L}.\'’-]*(\s+|$)){1,5}$/u',
    '/^(foto|fotografie|beeld|bron|video|photo|image|credit)s?\s*:\s*[^.]{1,70}$/iu',
    '/^foto van auteur$/iu',
    '/^foto[\'’]?s?$/iu',
    '/^foto[\'’]s\s+(\p{Lu}[\p{L}.\'’-]*(\s+|$)){1,5}$/iu',

    // Date and time of publication, and numbers (comment counts) on a line of their own
    '/^\d{1,2}[ -]\p{L}+[ -]\d{2,4}(\s+(om|-|,)\s+\d{1,2}[:.]\d{2}( uur)?)?$/iu',
    '/^\d{1,4}$/u',
  ],

  // Lines that only say who wrote the article. The line itself and the name on the next line are removed.
  'labels' => [
    '/^(geschreven door|auteur|door|tekst|fotografie|verslaggever)$/iu',
  ],

  'sites' => [
    'destentor.nl' => [
      '/^maak ons je google-favoriet$/iu',
      '/^blijf op de hoogte$/iu',
      '/^krijg een melding bij belangrijk nieuws\b.*$/iu',
      '/^mis nooit meer het laatste nieuws$/iu',
      '/^(eten en drinken|aanbiedingen|van de lezer|algemeen verslaggever)$/iu',
    ],
    'weblogzwolle.nl' => [
      '/^algemeen redactie$/iu',
    ],
    'rtvfocuszwolle.nl' => [
      '/^plaats op kaart bij benadering;?$/iu',
    ],
  ],
];
