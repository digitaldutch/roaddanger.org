/**
 * Input is key. Output is translated text.
 * First character is automatically capitalized if the first character of the key is capitalized.
 * @param key
 * @return {string|*}
 */

function translate(key){

  const languageTable = {
    created_by:     {en: 'created by', nl: 'aangemaakt door'},
    today:          {en: 'today', nl: 'vandaag'},
    week:           {en: 'week', nl: 'week'},
    weeks:          {en: 'weeks', nl: 'weken'},
    year:           {en: 'year', nl: 'jaar'},
    years:          {en: 'years', nl: 'jaren'},
    recent_crashes: {en: 'recent crashes', nl: 'recente ongelukken'},
  };

  const textItem = languageTable[key.toLowerCase()];

  // Return key name if language item has not been defined yet. This should not happen.
  if (! textItem) return '[undefined text: ' + key + ']';
  let langCode = document.documentElement.lang;

  let textTranslated = '';
  if (textItem[langCode])  textTranslated = textItem[langCode];
  else if (textItem['en']) {
    // Untranslated text is returned in english and gets an superscript 1 to point out that a translation is needed
    textTranslated = textItem['en'] + '¹';
  }
  else {
    // Unknown keys are returned error to point out that it does not yet exist.
    textTranslated = '[Undefined text: ' + key + ']';
  }

  function initialIsCapital(text){
    return text[0] !== text[0].toLowerCase();
  }

  function capitalizeFirstLetter(text) {
    return text.charAt(0).toUpperCase() + text.slice(1);
  }

  if (initialIsCapital(key)) textTranslated = capitalizeFirstLetter(textTranslated);

  return textTranslated;
}
