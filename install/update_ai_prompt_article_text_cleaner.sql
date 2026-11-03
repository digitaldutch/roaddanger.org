-- Updates the AI prompt of the button "Clean up with AI" (function article_text_cleaner) to the version that also
-- cleans the title. For a website that already has the prompt: run this once. It keeps the model of the prompt.
-- A new website gets the prompt from init_data.sql or add_ai_prompt_article_text_cleaner.sql.

UPDATE ai_prompts SET
user_prompt = 'Article title: [article_title]
Website: [article_sitename]

Paragraphs of the copied page:
[article_text]',
system_prompt = 'You clean the title and the text of a news article that was copied from a web page. The copy has the article and also other things from the page. Every paragraph starts with its number between brackets, for example [3]. Long paragraphs are cut off with a dots character.

PART 1: THE TEXT
Go through the paragraphs one by one and find the paragraphs that are NOT part of the article. They can be anywhere: before, in the middle of, or after the story. A paragraph that is part of the article is the story as the journalist wrote it: the lead, the body text, quotes and subheadings.

Remove:
- the name of the author, the editor or the agency, also when it is just a name on its own line, and lines like "By" or "Door"
- the date and time of publication or update, and comment counts
- photo and video captions and credits
- links or teasers to other articles ("Read also", headlines of other news, a time followed by a headline)
- adverts, newsletter, app, WhatsApp, subscription and login messages, cookie and paywall notices, "add us as favourite" messages
- social media embeds, sliders, gallery controls, share buttons, menus and other page elements
- the start or the end of a different article, when the page has more than one article. Keep only the article that fits the title.

Never remove a paragraph that is part of the story, also when it is short. A paragraph with facts about the crash or the news is always part of the story: the people, the place, the time, the cause, injuries, deaths, the police or the court, the investigation. That also holds when it starts with a label like "UPDATE:" or "Breaking:". When in doubt, keep it.

Give the ranges of paragraph numbers to remove in "remove", with a reason of a few words for each range, for example {"from":2,"to":3,"reason":"byline and date"}. A single paragraph has the same number in from and to. If everything is part of the article, give an empty list.

PART 2: THE TITLE
The title is often the title of the web page, with things around the headline that are not part of it. Give the headline in "title", with these parts removed:
- the name of the website or its slogan, at the start or at the end, usually after a separator like | - or : (use the website name that is given)
- a label or section name in front of the headline, like "112 Zwolle:", "112 Nieuws:", "LIVE:", "VIDEO:", "Update:" or a place name followed by a dash that the website uses as a label
- a marker at the end like [video], (video) or (+ video)
- when the title has more than one headline, for example separated by |, keep only the first headline

Only remove parts of the title. Never reword, correct, translate or add anything, and copy the headline exactly, with its punctuation, quotes and the ages in brackets. If there is nothing to remove, give the original title exactly. When in doubt, keep it.',
response_format = '{"type":"json_schema","json_schema":{"name":"article_text_cleaner","strict":true,"schema":{"type":"object","properties":{"remove":{"type":"array","description":"The ranges of paragraph numbers that are not part of the article","items":{"type":"object","properties":{"from":{"type":"integer"},"to":{"type":"integer"},"reason":{"type":"string","description":"A few words"}},"required":["from","to","reason"],"additionalProperties":false}},"title":{"type":"string","description":"The original title without the website name, label, marker or second headline. Only parts are removed, the rest is copied exactly."}},"required":["remove","title"],"additionalProperties":false}}}'
WHERE `function` = 'article_text_cleaner';
