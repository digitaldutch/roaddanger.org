-- The texts of the bookmark page (/bookmarklet/) and the link to it in the add crash window, in all five languages:
-- Bookmarklet_in_add_window (the link), Bookmarklet_install_title and Bookmarklet_use_title (the titles),
-- Bookmarklet_show_bar (new), and Bookmarklet_install and Bookmarklet_use (now without the "Install once:" and "Use:"
-- at the start, as those are the titles).
-- For a website that already exists: run this once. Running it again changes nothing.
-- init_data.sql does not have these keys yet: on a new website run this file after the installation.
-- Translations are stored in lower case in languages.translations.

UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_in_add_window', 'Um schnell einen Artikel aus deinem Browser hinzuzufügen, nutze die Lesezeichen-Funktion') WHERE id = 'de';
UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_in_add_window', 'To quickly add an article from your browser use the bookmark feature') WHERE id = 'en';
UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_in_add_window', 'Para añadir rápidamente un artículo desde tu navegador, usa la función de marcador') WHERE id = 'es';
UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_in_add_window', 'Pour ajouter rapidement un article depuis votre navigateur, utilisez la fonction favori') WHERE id = 'fr';
UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_in_add_window', 'Om snel een artikel uit je browser toe te voegen: gebruik de bladwijzer') WHERE id = 'nl';

UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_install_title', 'Einmal installieren') WHERE id = 'de';
UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_install_title', 'Install once') WHERE id = 'en';
UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_install_title', 'Instalar una vez') WHERE id = 'es';
UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_install_title', 'À installer une seule fois') WHERE id = 'fr';
UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_install_title', 'Eenmalig installeren') WHERE id = 'nl';

UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_install', 'Ziehe diese Schaltfläche in die Lesezeichenleiste deines Browsers.') WHERE id = 'de';
UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_install', 'Drag this button to your browser’s bookmarks bar.') WHERE id = 'en';
UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_install', 'Arrastra este botón a la barra de marcadores de tu navegador.') WHERE id = 'es';
UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_install', 'Faites glisser ce bouton vers la barre de favoris de votre navigateur.') WHERE id = 'fr';
UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_install', 'Sleep deze knop naar de bladwijzerbalk van je browser.') WHERE id = 'nl';

UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_show_bar', 'Du siehst die Lesezeichenleiste nicht? Drücke Strg+Umschalt+B, um sie anzuzeigen (Cmd+Umschalt+B auf dem Mac).') WHERE id = 'de';
UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_show_bar', 'Can’t see the bookmarks bar? Press Ctrl+Shift+B to show it (Cmd+Shift+B on a Mac).') WHERE id = 'en';
UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_show_bar', '¿No ves la barra de marcadores? Pulsa Ctrl+Mayús+B para mostrarla (Cmd+Mayús+B en Mac).') WHERE id = 'es';
UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_show_bar', 'Vous ne voyez pas la barre de favoris ? Appuyez sur Ctrl+Maj+B pour l’afficher (Cmd+Maj+B sur Mac).') WHERE id = 'fr';
UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_show_bar', 'Zie je de bladwijzerbalk niet? Druk op Ctrl+Shift+B om hem te tonen (Cmd+Shift+B op een Mac).') WHERE id = 'nl';

UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_use_title', 'Verwendung') WHERE id = 'de';
UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_use_title', 'Use') WHERE id = 'en';
UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_use_title', 'Uso') WHERE id = 'es';
UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_use_title', 'Utilisation') WHERE id = 'fr';
UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_use_title', 'Gebruik') WHERE id = 'nl';

UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_use', 'Öffne den Artikel in deinem Browser und klicke auf das Lesezeichen, um ihn zu roaddanger hinzuzufügen.') WHERE id = 'de';
UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_use', 'Open the article in your browser and click the bookmark to add it to roaddanger.') WHERE id = 'en';
UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_use', 'Abre el artículo en tu navegador y haz clic en el marcador para añadirlo a roaddanger.') WHERE id = 'es';
UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_use', 'Ouvrez l’article dans votre navigateur et cliquez sur le favori pour l’ajouter à roaddanger.') WHERE id = 'fr';
UPDATE languages SET translations = JSON_SET(translations, '$.bookmarklet_use', 'Open het artikel in je browser en klik op de bladwijzer om het aan roaddanger toe te voegen.') WHERE id = 'nl';
