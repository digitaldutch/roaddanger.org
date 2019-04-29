

function translate(key){
  let languageTable = {
    created_by:     {en: 'created by', nl: 'aangemaakt door'},
    today:          {en: 'Today', nl: 'Vandaag'},
    week:           {en: 'week', nl: 'week'},
    weeks:          {en: 'weeks', nl: 'weken'},
    year:           {en: 'year', nl: 'jaar'},
    years:          {en: 'years', nl: 'jaren'},
  };

  let textItem = languageTable[key];

  // Return key name if language item has not been defined yet. This should not happen.
  if (! textItem) return '[undefined text: ' + key + ']';
  let langCode = document.documentElement.lang;

  if (textItem[langCode])  return textItem[langCode];
  else if (textItem['en']) return textItem['en'] + '¹'; // Untranslated text gets an superscript 1 to make them stand out
  else                     return '[Undefined text: ' + key + ']';
}
