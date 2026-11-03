-- The texts of the button Clean up with AI (key Clean_up_with_ai) and of step 2 of fetching an article (key
-- Cleaning_up_text) in all five languages.
-- For a website that already exists: run this once. Running it again changes nothing.
-- init_data.sql does not have this key yet: on a new website run this file after the installation.
-- Translations are stored in lower case in languages.translations.

UPDATE languages SET translations = JSON_SET(translations, '$.clean_up_with_ai', 'Mit KI bereinigen')    WHERE id = 'de';
UPDATE languages SET translations = JSON_SET(translations, '$.clean_up_with_ai', 'Clean up with AI')      WHERE id = 'en';
UPDATE languages SET translations = JSON_SET(translations, '$.clean_up_with_ai', 'Limpiar con IA')        WHERE id = 'es';
UPDATE languages SET translations = JSON_SET(translations, '$.clean_up_with_ai', 'Nettoyer avec l\'IA')   WHERE id = 'fr';
UPDATE languages SET translations = JSON_SET(translations, '$.clean_up_with_ai', 'Opschonen met AI')      WHERE id = 'nl';

UPDATE languages SET translations = JSON_SET(translations, '$.cleaning_up_text', 'Schritt 2: Titel und Text werden mit KI bereinigt…') WHERE id = 'de';
UPDATE languages SET translations = JSON_SET(translations, '$.cleaning_up_text', 'Step 2: Cleaning up the title and text with AI…') WHERE id = 'en';
UPDATE languages SET translations = JSON_SET(translations, '$.cleaning_up_text', 'Paso 2: limpiando el título y el texto con IA…') WHERE id = 'es';
UPDATE languages SET translations = JSON_SET(translations, '$.cleaning_up_text', 'Étape 2 : nettoyage du titre et du texte avec l\'IA…') WHERE id = 'fr';
UPDATE languages SET translations = JSON_SET(translations, '$.cleaning_up_text', 'Stap 2: titel en tekst opschonen met AI…') WHERE id = 'nl';
