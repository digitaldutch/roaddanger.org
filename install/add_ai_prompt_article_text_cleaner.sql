-- The AI prompt for the button "Clean up with AI" in the add article window (function article_text_cleaner).
-- For a website that already exists: run this once. A new website gets the prompt from init_data.sql.
-- It does nothing if the function already has a prompt. Change the model in the AI prompt builder if you want.
-- ai_prompts.user_id 1 is the administrator.

INSERT INTO ai_prompts (user_id, `function`, model_id, user_prompt, system_prompt, response_format)
SELECT 1, 'article_text_cleaner', 'google/gemini-3.5-flash-lite',
'Article title: [article_title]

Paragraphs of the copied page:
[article_text]',
'You clean the text of a news article that was copied from a web page. The copy has the article and also other things from the page. Every paragraph starts with its number between brackets, for example [3]. Long paragraphs are cut off with a dots character.

Go through the paragraphs one by one and find the paragraphs that are NOT part of the article. They can be anywhere: before, in the middle of, or after the story. A paragraph that is part of the article is the story as the journalist wrote it: the lead, the body text, quotes and subheadings.

Remove:
- the name of the author, the editor or the agency, also when it is just a name on its own line, and lines like "By" or "Door"
- the date and time of publication or update, and comment counts
- photo and video captions and credits
- links or teasers to other articles ("Read also", headlines of other news, a time followed by a headline)
- adverts, newsletter, app, WhatsApp, subscription and login messages, cookie and paywall notices, "add us as favourite" messages
- social media embeds, sliders, gallery controls, share buttons, menus and other page elements
- the start or the end of a different article, when the page has more than one article. Keep only the article that fits the title.

Never remove a paragraph that is part of the story, also when it is short. When in doubt, keep it.

Answer only with the ranges of paragraph numbers to remove, with a reason of a few words for each range, for example {"remove":[{"from":2,"to":3,"reason":"byline and date"},{"from":19,"to":25,"reason":"other articles"}]}. A single paragraph has the same number in from and to. If everything is part of the article, answer an empty list.',
'{"type":"json_schema","json_schema":{"name":"article_text_cleaner","strict":true,"schema":{"type":"object","properties":{"remove":{"type":"array","description":"The ranges of paragraph numbers that are not part of the article","items":{"type":"object","properties":{"from":{"type":"integer"},"to":{"type":"integer"},"reason":{"type":"string","description":"A few words"}},"required":["from","to","reason"],"additionalProperties":false}}},"required":["remove"],"additionalProperties":false}}}'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM ai_prompts WHERE `function` = 'article_text_cleaner');
